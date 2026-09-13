<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WooCommerceApiService
{
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
     * Ejecuta una petición HTTP a la REST API de WooCommerce.
     */
    public function request(string $method, string $endpoint, array $params = [], array $data = [])
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

        return $response->json();
    }

    /* -------------------------------------------------------------------------- */
    /*                         CATEGORÍAS DE PRODUCTOS                            */
    /* -------------------------------------------------------------------------- */

    public function getCategories(array $params = ['per_page' => 100]): array
    {
        return $this->request('GET', 'products/categories', $params) ?? [];
    }

    public function getCategory(int $id): array
    {
        return $this->request('GET', "products/categories/{$id}") ?? [];
    }

    public function createCategory(array $data): array
    {
        return $this->request('POST', 'products/categories', [], $data) ?? [];
    }

    public function updateCategory(int $id, array $data): array
    {
        return $this->request('PUT', "products/categories/{$id}", [], $data) ?? [];
    }

    public function deleteCategory(int $id, bool $force = true): array
    {
        return $this->request('DELETE', "products/categories/{$id}", ['force' => $force ? 'true' : 'false']) ?? [];
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
     * Asegura que los atributos globales 'Color' y 'Talla' y sus términos existan en WooCommerce.
     * Retorna la lista formateada para los selectores de las vistas.
     */
    public function ensureAttributesAndTermsExist(): array
    {
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

        // 3. Términos de Color
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

        // 4. Términos de Talla
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

        // Formatear para compatibilidad con vistas Blade
        $colors = array_map(function ($item) {
            return (object)[
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'slug' => $item['slug'],
            ];
        }, $existingColors);

        $sizes = array_map(function ($item) {
            return (object)[
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'size' => $item['name'], // Compatibilidad con $size->size en Blade
                'slug' => $item['slug'],
            ];
        }, $existingSizes);

        return [
            'colors' => $colors,
            'sizes' => $sizes,
            'colorAttributeId' => $colorAttrId,
            'sizeAttributeId' => $sizeAttrId,
        ];
    }

    /* -------------------------------------------------------------------------- */
    /*                         PRODUCTOS Y VARIACIONES                            */
    /* -------------------------------------------------------------------------- */

    public function getProducts(array $params = ['per_page' => 50]): array
    {
        return $this->request('GET', 'products', $params) ?? [];
    }

    public function getProduct(int $id): array
    {
        return $this->request('GET', "products/{$id}") ?? [];
    }

    public function getProductVariations(int $productId, array $params = ['per_page' => 100]): array
    {
        return $this->request('GET', "products/{$productId}/variations", $params) ?? [];
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
     * @param array $data ['sku' => string, 'category_id' => int, 'image_url' => ?string, 'inventory' => array, 'is_active' => ?bool]
     * @param int|null $productId ID del producto si es actualización
     * @return array Producto creado o actualizado
     */
    public function saveFullVariableProduct(array $data, ?int $productId = null): array
    {
        $meta = $this->ensureAttributesAndTermsExist();
        $colorAttrId = $meta['colorAttributeId'];
        $sizeAttrId = $meta['sizeAttributeId'];

        $colorMap = [];
        foreach ($meta['colors'] as $c) {
            $colorMap[$c->id] = $c;
        }

        $sizeMap = [];
        foreach ($meta['sizes'] as $s) {
            $sizeMap[$s->id] = $s;
        }

        $sku = trim($data['sku']);
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
        $parentPayload = [
            'name' => $sku,
            'type' => 'variable',
            'sku' => $sku,
            'status' => (isset($data['is_active']) && !$data['is_active']) ? 'draft' : 'publish',
            'categories' => !empty($data['category_id']) ? [['id' => (int)$data['category_id']]] : [],
            'attributes' => [
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
            ],
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
                        'regular_price' => '0',
                    ];
                } else {
                    if ($amount > 0 || $productId) {
                        $batchCreate[] = [
                            'sku' => $variationSku,
                            'manage_stock' => true,
                            'stock_quantity' => $amount,
                            'stock_status' => $stockStatus,
                            'regular_price' => '0',
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
}
