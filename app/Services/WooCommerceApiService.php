<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WooCommerceApiService
{
    private const SHOE_AUDIENCE_META_KEY = '_shoe_audience';

    protected string $storeUrl;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected string $version;
    protected bool $verifySsl;
    protected int $timeout;

    public function __construct()
    {
        $this->storeUrl = config('woocommerce.store_url', env('WOOCOMMERCE_STORE_URL', 'http://localhost:8080'));
        $this->consumerKey = config('woocommerce.consumer_key', env('WOOCOMMERCE_CONSUMER_KEY', ''));
        $this->consumerSecret = config('woocommerce.consumer_secret', env('WOOCOMMERCE_CONSUMER_SECRET', ''));
        $this->version = config('woocommerce.version', env('WOOCOMMERCE_VERSION', 'wc/v3'));
        $this->verifySsl = (bool) config('woocommerce.verify_ssl', env('WOOCOMMERCE_VERIFY_SSL', false));
        $this->timeout = (int) config('woocommerce.timeout', env('WOOCOMMERCE_TIMEOUT', 30));
    }

    /**
     * Construye la URL completa del endpoint de WooCommerce.
     */
    protected function buildUrl(string $endpoint): string
    {
        $base = rtrim($this->storeUrl, '/');
        $ver = trim($this->version, '/');
        $end = ltrim($endpoint, '/');

        return "{$base}/wp-json/{$ver}/{$end}";
    }

    /**
     * Ejecuta una petición HTTP a la REST API de WooCommerce y devuelve la Response del HTTP Client.
     */
    public function rawRequest(string $method, string $endpoint, array $params = [], array $data = []): Response
    {
        $url = $this->buildUrl($endpoint);

        $client = Http::timeout($this->timeout)
            ->withOptions(['verify' => $this->verifySsl]);

        // Autenticación por query params (compatible con HTTP local sin SSL)
        $authParams = array_merge($params, [
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
        ]);

        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $authParams),
            'POST' => $client->withQueryParameters($authParams)->post($url, $data),
            'PUT' => $client->withQueryParameters($authParams)->put($url, $data),
            'DELETE' => $client->withQueryParameters($authParams)->delete($url, $data),
            default => throw new \InvalidArgumentException("Método HTTP no soportado: {$method}")
        };

        if ($response->failed()) {
            $errorMsg = $response->json('message') ?? $response->body() ?? 'Error en la petición a WooCommerce REST API';
            Log::error("WooCommerce API Error [{$method} {$endpoint}]: {$errorMsg}");
            throw new \Exception($errorMsg, $response->status());
        }

        return $response;
    }

    /**
     * Ejecuta una petición HTTP a la REST API de WooCommerce.
     */
    public function request(string $method, string $endpoint, array $params = [], array $data = [])
    {
        return $this->rawRequest($method, $endpoint, $params, $data)->json();
    }

    /* -------------------------------------------------------------------------- */
    /*                         CATEGORÍAS DE PRODUCTOS                            */
    /* -------------------------------------------------------------------------- */

    public function getCategories(array $params = ['per_page' => 100]): array
    {
        $isDefault = empty($params) || ($params == ['per_page' => 100]);

        if ($isDefault) {
            return Cache::remember('wc_categories_list', 3600, function () use ($params) {
                return $this->request('GET', 'products/categories', $params) ?? [];
            });
        }

        return $this->request('GET', 'products/categories', $params) ?? [];
    }

    public function clearCategoriesCache(): void
    {
        Cache::forget('wc_categories_list');
    }

    public function getCategory(int $id): array
    {
        return $this->request('GET', "products/categories/{$id}") ?? [];
    }

    public function createCategory(array $data): array
    {
        $result = $this->request('POST', 'products/categories', [], $data) ?? [];
        $this->clearCategoriesCache();
        return $result;
    }

    public function updateCategory(int $id, array $data): array
    {
        $result = $this->request('PUT', "products/categories/{$id}", [], $data) ?? [];
        $this->clearCategoriesCache();
        return $result;
    }

    public function deleteCategory(int $id, bool $force = true): array
    {
        $result = $this->request('DELETE', "products/categories/{$id}", ['force' => $force ? 'true' : 'false']) ?? [];
        $this->clearCategoriesCache();
        return $result;
    }

    /* -------------------------------------------------------------------------- */
    /*                   ATRIBUTOS Y TÉRMINOS (COLORES Y TALLAS)                  */
    /* -------------------------------------------------------------------------- */

    public function getAttributes(): array
    {
        return $this->request('GET', 'products/attributes') ?? [];
    }

    public function createAttribute(array $data): array
    {
        return $this->request('POST', 'products/attributes', [], $data) ?? [];
    }

    public function getAttributeTerms(int $attributeId, array $params = ['per_page' => 100]): array
    {
        return $this->request('GET', "products/attributes/{$attributeId}/terms", $params) ?? [];
    }

    public function createAttributeTerm(int $attributeId, array $data): array
    {
        return $this->request('POST', "products/attributes/{$attributeId}/terms", [], $data) ?? [];
    }

    /**
     * Asegura que los atributos globales 'Color', 'Talla' y 'Público' y sus términos existan en WooCommerce.
     * Retorna la lista formateada para los selectores de las vistas.
     */
    public function ensureAttributesAndTermsExist(): array
    {
        $cached = Cache::remember('wc_attributes_and_terms', 86400, function () {
            $existingAttributes = $this->getAttributes();
            $attrByName = [];
            foreach ($existingAttributes as $attr) {
                $attrByName[strtolower($attr['name'])] = $attr;
                $attrByName[strtolower($attr['slug'])] = $attr;
            }

            // 1. Atributo Color
            $colorAttr = $attrByName['color'] ?? $attrByName['pa_color'] ?? null;
            if (!$colorAttr) {
                $colorAttr = $this->createAttribute([
                    'name' => 'Color',
                    'slug' => 'pa_color',
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => true,
                ]);
            }
            $colorAttrId = (int)$colorAttr['id'];

            // 2. Atributo Talla
            $sizeAttr = $attrByName['talla'] ?? $attrByName['pa_talla'] ?? null;
            if (!$sizeAttr) {
                $sizeAttr = $this->createAttribute([
                    'name' => 'Talla',
                    'slug' => 'pa_talla',
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => true,
                ]);
            }
            $sizeAttrId = (int)$sizeAttr['id'];

            // 3. Atributo Público
            $audienceAttr = $attrByName['público']
                ?? $attrByName['publico']
                ?? $attrByName['pa_publico']
                ?? $attrByName['pa_genero']
                ?? $attrByName['genero']
                ?? $attrByName['género']
                ?? null;

            if (!$audienceAttr) {
                $audienceAttr = $this->createAttribute([
                    'name' => 'Público',
                    'slug' => 'pa_publico',
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => true,
                ]);
            }
            $audienceAttrId = (int)$audienceAttr['id'];

            // 4. Términos de Color
            $existingColors = $this->getAttributeTerms($colorAttrId);
            if (empty($existingColors)) {
                $defaultColors = ['Negro', 'Blanco', 'Café', 'Azul', 'Rojo', 'Beige', 'Gris', 'Rosa', 'Vino'];
                foreach ($defaultColors as $cName) {
                    $this->createAttributeTerm($colorAttrId, [
                        'name' => $cName,
                        'slug' => Str::slug($cName),
                    ]);
                }
                $existingColors = $this->getAttributeTerms($colorAttrId);
            }

            // 5. Términos de Talla
            $existingSizes = $this->getAttributeTerms($sizeAttrId);
            if (empty($existingSizes)) {
                $defaultSizes = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
                foreach ($defaultSizes as $sName) {
                    $this->createAttributeTerm($sizeAttrId, [
                        'name' => $sName,
                        'slug' => Str::slug($sName),
                    ]);
                }
                $existingSizes = $this->getAttributeTerms($sizeAttrId);
            }

            // 6. Términos de Público
            $existingAudiences = $this->getAttributeTerms($audienceAttrId);
            $existingAudienceSlugs = array_map(function ($item) {
                return strtolower(((array)$item)['slug'] ?? '');
            }, $existingAudiences);

            $requiredAudiences = [
                ['name' => 'Hombre', 'slug' => 'hombre'],
                ['name' => 'Mujer', 'slug' => 'mujer'],
                ['name' => 'Niño', 'slug' => 'nino'],
            ];

            $createdAnyAudience = false;
            foreach ($requiredAudiences as $reqAud) {
                if (!in_array($reqAud['slug'], $existingAudienceSlugs, true)) {
                    $this->createAttributeTerm($audienceAttrId, [
                        'name' => $reqAud['name'],
                        'slug' => $reqAud['slug'],
                    ]);
                    $createdAnyAudience = true;
                }
            }

            if ($createdAnyAudience) {
                $existingAudiences = $this->getAttributeTerms($audienceAttrId);
            }

            return [
                'colors' => array_map(function ($item) {
                    $item = (array)$item;
                    return [
                        'id' => (int)$item['id'],
                        'name' => (string)$item['name'],
                        'slug' => (string)$item['slug'],
                    ];
                }, $existingColors),
                'sizes' => array_map(function ($item) {
                    $item = (array)$item;
                    return [
                        'id' => (int)$item['id'],
                        'name' => (string)$item['name'],
                        'size' => (string)($item['size'] ?? $item['name']),
                        'slug' => (string)$item['slug'],
                    ];
                }, $existingSizes),
                'audiences' => array_map(function ($item) {
                    $item = (array)$item;
                    return [
                        'id' => (int)$item['id'],
                        'name' => (string)$item['name'],
                        'slug' => (string)$item['slug'],
                    ];
                }, $existingAudiences),
                'colorAttributeId' => $colorAttrId,
                'sizeAttributeId' => $sizeAttrId,
                'audienceAttributeId' => $audienceAttrId,
            ];
        });

        // Formatear como objetos para compatibilidad total con vistas Blade y controladores
        $colors = array_map(function ($item) {
            $item = (array)$item;
            return (object)[
                'id' => (int)$item['id'],
                'name' => (string)$item['name'],
                'slug' => (string)$item['slug'],
            ];
        }, $cached['colors'] ?? []);

        $sizes = array_map(function ($item) {
            $item = (array)$item;
            return (object)[
                'id' => (int)$item['id'],
                'name' => (string)$item['name'],
                'size' => (string)($item['size'] ?? $item['name']),
                'slug' => (string)$item['slug'],
            ];
        }, $cached['sizes'] ?? []);

        $audiences = array_map(function ($item) {
            $item = (array)$item;
            return (object)[
                'id' => (int)$item['id'],
                'name' => (string)$item['name'],
                'slug' => (string)$item['slug'],
            ];
        }, $cached['audiences'] ?? []);

        return [
            'colors' => $colors,
            'sizes' => $sizes,
            'audiences' => $audiences,
            'colorAttributeId' => (int)$cached['colorAttributeId'],
            'sizeAttributeId' => (int)$cached['sizeAttributeId'],
            'audienceAttributeId' => (int)($cached['audienceAttributeId'] ?? 0),
        ];
    }

    public function clearAttributesCache(): void
    {
        Cache::forget('wc_attributes_and_terms');
    }

    /* -------------------------------------------------------------------------- */
    /*                         PRODUCTOS Y VARIACIONES                            */
    /* -------------------------------------------------------------------------- */

    public function getProducts(array $params = ['per_page' => 50]): array
    {
        if (!isset($params['_fields'])) {
            $params['_fields'] = 'id,name,sku,price,regular_price,categories,images,status,attributes';
        }

        return $this->request('GET', 'products', $params) ?? [];
    }

    /**
     * Obtiene productos con información de paginación desde los headers X-WP-Total y X-WP-TotalPages.
     *
     * @param array $params
     * @return array ['data' => array, 'total' => int, 'totalPages' => int]
     */
    public function getProductsPaginated(array $params = []): array
    {
        if (!isset($params['_fields'])) {
            $params['_fields'] = 'id,name,sku,price,regular_price,categories,images,status,attributes';
        }

        $response = $this->rawRequest('GET', 'products', $params);
        $data = $response->json() ?? [];
        $totalHeader = $response->header('X-WP-Total');
        $totalPagesHeader = $response->header('X-WP-TotalPages');

        $total = ($totalHeader !== null && $totalHeader !== '') ? (int)$totalHeader : count($data);
        $totalPages = ($totalPagesHeader !== null && $totalPagesHeader !== '') ? (int)$totalPagesHeader : 1;

        return [
            'data' => $data,
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }

    public function getProduct(int $id): array
    {
        return $this->request('GET', "products/{$id}") ?? [];
    }

    public function getProductAudience(array $product): ?string
    {
        if (!empty($product['attributes']) && is_array($product['attributes'])) {
            foreach ($product['attributes'] as $attr) {
                $name = strtolower($attr['name'] ?? '');
                $slug = strtolower($attr['slug'] ?? '');
                $targets = ['público', 'publico', 'pa_publico', 'género', 'genero', 'pa_genero'];
                if (in_array($name, $targets, true) || in_array($slug, $targets, true)) {
                    $options = $attr['options'] ?? [];
                    if (!empty($options) && is_array($options)) {
                        $val = Str::slug(strtolower(trim((string)$options[0])));
                        if (in_array($val, ['hombre', 'mujer', 'nino'], true)) {
                            return $val;
                        }
                        if (str_starts_with($val, 'hombr')) {
                            return 'hombre';
                        }
                        if (str_starts_with($val, 'muj')) {
                            return 'mujer';
                        }
                        if (str_starts_with($val, 'nin')) {
                            return 'nino';
                        }
                    }
                }
            }
        }

        foreach ($product['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) === self::SHOE_AUDIENCE_META_KEY) {
                $val = $meta['value'] ?? null;
                if ($val !== null && $val !== '') {
                    $slug = Str::slug(strtolower(trim((string)$val)));
                    if (in_array($slug, ['hombre', 'mujer', 'nino'], true)) {
                        return $slug;
                    }
                    if (str_starts_with($slug, 'hombr')) {
                        return 'hombre';
                    }
                    if (str_starts_with($slug, 'muj')) {
                        return 'mujer';
                    }
                    if (str_starts_with($slug, 'nin')) {
                        return 'nino';
                    }
                    return $val;
                }
            }
        }

        return null;
    }

    public function getProductVariations(int $productId, array $params = ['per_page' => 100]): array
    {
        if (!isset($params['_fields'])) {
            $params['_fields'] = 'id,regular_price,attributes,stock_quantity';
        }

        return $this->request('GET', "products/{$productId}/variations", $params) ?? [];
    }

    /**
     * Obtiene las variaciones de múltiples productos concurrentemente en paralelo usando Http::pool().
     *
     * @param array $productIds
     * @return array Mapa [productId => variationsArray]
     */
    public function getMultipleProductVariations(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($productIds) {
            $requests = [];
            foreach ($productIds as $id) {
                $url = $this->buildUrl("products/{$id}/variations");
                $authParams = [
                    'consumer_key' => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                    'per_page' => 100,
                    '_fields' => 'id,regular_price,attributes,stock_quantity',
                ];

                $requests[(string)$id] = $pool->as((string)$id)
                    ->timeout($this->timeout)
                    ->withOptions(['verify' => $this->verifySsl])
                    ->get($url, $authParams);
            }
            return $requests;
        });

        $result = [];
        foreach ($productIds as $id) {
            $resp = $responses[(string)$id] ?? null;
            if ($resp instanceof Response && $resp->successful()) {
                $result[$id] = $resp->json() ?? [];
            } else {
                $result[$id] = [];
            }
        }

        return $result;
    }

    public function createProduct(array $data): array
    {
        return $this->request('POST', 'products', [], $data) ?? [];
    }

    public function updateProduct(int $id, array $data): array
    {
        return $this->request('PUT', "products/{$id}", [], $data) ?? [];
    }

    public function deleteProduct(int $id, bool $force = true): array
    {
        return $this->request('DELETE', "products/{$id}", ['force' => $force ? 'true' : 'false']) ?? [];
    }

    public function batchVariations(int $productId, array $batchData): array
    {
        return $this->request('POST', "products/{$productId}/variations/batch", [], $batchData) ?? [];
    }

    /**
     * Guarda o actualiza un producto variable y todas sus variaciones (Matriz Color x Talla) vía REST API.
     *
    * @param array $data ['sku' => string, 'category_id' => int, 'audience' => string, 'image_url' => ?string, 'inventory' => array, 'is_active' => ?bool]
     * @param int|null $productId ID del producto si es actualización
     * @return array Producto creado o actualizado
     */
    public function saveFullVariableProduct(array $data, ?int $productId = null): array
    {
        $meta = $this->ensureAttributesAndTermsExist();
        $colorAttrId = $meta['colorAttributeId'];
        $sizeAttrId = $meta['sizeAttributeId'];
        $audienceAttrId = $meta['audienceAttributeId'] ?? 0;

        $colorMap = [];
        foreach ($meta['colors'] as $c) {
            $colorMap[$c->id] = $c;
        }

        $sizeMap = [];
        foreach ($meta['sizes'] as $s) {
            $sizeMap[$s->id] = $s;
        }

        $sku = trim($data['sku']);
        $name = !empty($data['name']) ? trim($data['name']) : $sku;
        $price = isset($data['price']) ? (string)$data['price'] : (isset($data['regular_price']) ? (string)$data['regular_price'] : '0');
        $inventory = $data['inventory'] ?? [];

        // Identificar colores y tallas presentes en la matriz enviada
        $usedColorNames = [];
        $usedSizeNames = [];

        foreach ($inventory as $colorId => $sizes) {
            if (isset($colorMap[$colorId])) {
                $usedColorNames[] = $colorMap[$colorId]->name;
            }
            foreach ($sizes as $sizeId => $amount) {
                if (isset($sizeMap[$sizeId])) {
                    $usedSizeNames[] = $sizeMap[$sizeId]->name;
                }
            }
        }

        $usedColorNames = array_values(array_unique($usedColorNames));
        $usedSizeNames = array_values(array_unique($usedSizeNames));

        // 1. Preparar payload del Producto Padre (Variable)
        $parentAttributes = [
            [
                'id' => $colorAttrId,
                'name' => 'Color',
                'position' => 0,
                'visible' => true,
                'variation' => true,
                'options' => $usedColorNames,
            ],
            [
                'id' => $sizeAttrId,
                'name' => 'Talla',
                'position' => 1,
                'visible' => true,
                'variation' => true,
                'options' => $usedSizeNames,
            ],
        ];

        $parentMetaData = [];

        if (!empty($data['audience'])) {
            $rawAudience = strtolower(trim((string)$data['audience']));
            $audienceSlug = Str::slug($rawAudience);
            $audienceMap = [
                'hombre' => 'Hombre',
                'mujer' => 'Mujer',
                'nino' => 'Niño',
            ];
            $audienceName = $audienceMap[$audienceSlug] ?? ucfirst($rawAudience);

            $parentAttributes[] = [
                'id' => $audienceAttrId,
                'name' => 'Público',
                'position' => 2,
                'visible' => true,
                'variation' => false,
                'options' => [$audienceName],
            ];

            $parentMetaData[] = [
                'key' => self::SHOE_AUDIENCE_META_KEY,
                'value' => $audienceSlug,
            ];
        }

        $parentPayload = [
            'name' => $name,
            'type' => 'variable',
            'sku' => $sku,
            'regular_price' => $price,
            'status' => (isset($data['is_active']) && !$data['is_active']) ? 'draft' : 'publish',
            'categories' => !empty($data['category_id']) ? [['id' => (int)$data['category_id']]] : [],
            'meta_data' => $parentMetaData,
            'attributes' => $parentAttributes,
        ];

        if (!empty($data['image_url'])) {
            $parentPayload['images'] = [['src' => $data['image_url']]];
        }

        if ($productId) {
            $product = $this->updateProduct($productId, $parentPayload);
        } else {
            $product = $this->createProduct($parentPayload);
        }

        $actualProductId = (int)$product['id'];

        // 2. Obtener variaciones existentes para batch
        $existingVariations = $this->getProductVariations($actualProductId);

        $batchCreate = [];
        $batchUpdate = [];
        $processedVarIds = [];

        foreach ($inventory as $colorId => $sizes) {
            $colorObj = $colorMap[$colorId] ?? null;
            if (!$colorObj) continue;

            foreach ($sizes as $sizeId => $amount) {
                $sizeObj = $sizeMap[$sizeId] ?? null;
                if (!$sizeObj) continue;

                $amount = max(0, (int)$amount);
                $colorSlug = $colorObj->slug;
                $sizeSlug = $sizeObj->slug;
                $variationSku = "{$sku}-" . strtoupper($colorSlug) . "-{$sizeSlug}";
                $stockStatus = ($amount > 0) ? 'instock' : 'outofstock';

                // Buscar si ya existe la variación para esta combinación
                $existing = null;
                foreach ($existingVariations as $ev) {
                    $evColor = '';
                    $evSize = '';
                    foreach ($ev['attributes'] as $attr) {
                        if (in_array(strtolower($attr['name']), ['color', 'pa_color'])) {
                            $evColor = strtolower($attr['option']);
                        }
                        if (in_array(strtolower($attr['name']), ['talla', 'pa_talla'])) {
                            $evSize = strtolower($attr['option']);
                        }
                    }

                    if (strtolower($colorObj->name) === $evColor && strtolower($sizeObj->name) === $evSize) {
                        $existing = $ev;
                        break;
                    }
                }

                if ($existing) {
                    $processedVarIds[] = (int)$existing['id'];
                    $batchUpdate[] = [
                        'id' => (int)$existing['id'],
                        'manage_stock' => true,
                        'stock_quantity' => $amount,
                        'stock_status' => $stockStatus,
                        'regular_price' => $price,
                    ];
                } else {
                    if ($amount > 0 || $productId) {
                        $batchCreate[] = [
                            'sku' => $variationSku,
                            'manage_stock' => true,
                            'stock_quantity' => $amount,
                            'stock_status' => $stockStatus,
                            'regular_price' => $price,
                            'attributes' => [
                                [
                                    'id' => $colorAttrId,
                                    'name' => 'Color',
                                    'option' => $colorObj->name,
                                ],
                                [
                                    'id' => $sizeAttrId,
                                    'name' => 'Talla',
                                    'option' => $sizeObj->name,
                                ],
                            ],
                        ];
                    }
                }
            }
        }

        // Poner en stock 0 las variaciones antiguas que ya no fueron incluidas
        foreach ($existingVariations as $oldVar) {
            if (!in_array((int)$oldVar['id'], $processedVarIds)) {
                $batchUpdate[] = [
                    'id' => (int)$oldVar['id'],
                    'manage_stock' => true,
                    'stock_quantity' => 0,
                    'stock_status' => 'outofstock',
                ];
            }
        }

        // Ejecutar Batch de Variaciones
        $batchPayload = [];
        if (!empty($batchCreate)) $batchPayload['create'] = $batchCreate;
        if (!empty($batchUpdate)) $batchPayload['update'] = $batchUpdate;

        if (!empty($batchPayload)) {
            $this->batchVariations($actualProductId, $batchPayload);
        }

        return $product;
    }

    /**
     * Alterna el estado activo (publish) / inactivo (draft) de un producto.
     */
    public function toggleProductStatus(int $id): array
    {
        $product = $this->getProduct($id);
        $newStatus = ($product['status'] === 'publish') ? 'draft' : 'publish';

        return $this->updateProduct($id, ['status' => $newStatus]);
    }

    /* -------------------------------------------------------------------------- */
    /*                                   PEDIDOS                                  */
    /* -------------------------------------------------------------------------- */

    /**
     * Obtiene pedidos desde WooCommerce REST API.
     */
    public function getOrders(array $params = []): array
    {
        if (!isset($params['_fields'])) {
            $params['_fields'] = 'id,number,status,currency,currency_symbol,date_created,total,total_tax,shipping_total,discount_total,payment_method_title,billing,shipping,line_items';
        }

        return $this->request('GET', 'orders', $params) ?? [];
    }

    /**
     * Obtiene pedidos con información de paginación desde los headers X-WP-Total y X-WP-TotalPages.
     *
     * @param array $params
     * @return array ['data' => array, 'total' => int, 'totalPages' => int]
     */
    public function getOrdersPaginated(array $params = []): array
    {
        if (!isset($params['_fields'])) {
            $params['_fields'] = 'id,number,status,currency,currency_symbol,date_created,total,total_tax,shipping_total,discount_total,payment_method_title,billing,shipping,line_items';
        }

        $response = $this->rawRequest('GET', 'orders', $params);
        $orders = $response->json() ?? [];
        $totalHeader = $response->header('X-WP-Total');
        $totalPagesHeader = $response->header('X-WP-TotalPages');

        $total = ($totalHeader !== null && $totalHeader !== '') ? (int)$totalHeader : count($orders);
        $totalPages = ($totalPagesHeader !== null && $totalPagesHeader !== '') ? (int)$totalPagesHeader : 1;

        return [
            'data' => $orders,
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Obtiene un pedido específico por ID.
     */
    public function getOrder(int $id): array
    {
        return $this->request('GET', "orders/{$id}") ?? [];
    }

    /**
     * Actualiza el estado de un pedido en WooCommerce.
     */
    public function updateOrderStatus(int $id, string $status): array
    {
        return $this->request('PUT', "orders/{$id}", [], ['status' => $status]) ?? [];
    }
}
