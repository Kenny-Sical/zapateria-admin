<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\WooCommerceApiService;

class InventoryController extends Controller
{
    /**
     * Muestra la vista principal del inventario desde WooCommerce REST API.
     */
    public function index(Request $request, WooCommerceApiService $wcApi)
    {
        try {
            $meta = $wcApi->ensureAttributesAndTermsExist();
            $colors = $meta['colors'];
            $sizes = $meta['sizes'];

            $rawCategories = $wcApi->getCategories(['per_page' => 100]);
            $categories = array_map(function ($c) {
                return (object)[
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                ];
            }, $rawCategories);

            $perPage = (int) $request->input('per_page', 15);
            $currentPage = (int) $request->input('page', 1);

            $params = [
                'per_page' => $perPage,
                'page' => $currentPage,
            ];

            if ($request->filled('search')) {
                $params['search'] = $request->search;
            }

            if ($request->filled('categories')) {
                $categoryFilter = is_array($request->categories) ? implode(',', $request->categories) : $request->categories;
                $params['category'] = $categoryFilter;
            }

            $productsResult = $wcApi->getProductsPaginated($params);
            $rawProducts = $productsResult['data'];
            $totalProducts = (int)$productsResult['total'];

            $productIds = array_column($rawProducts, 'id');
            $allVariations = $wcApi->getMultipleProductVariations($productIds);

            $products = [];
            foreach ($rawProducts as $p) {
                $productId = (int)$p['id'];
                $name = $p['name'] ?? '';
                $sku = !empty($p['sku']) ? $p['sku'] : $name;
                $price = $p['price'] ?? $p['regular_price'] ?? '';
                $categoryName = !empty($p['categories']) ? $p['categories'][0]['name'] : 'Sin categoría';
                $categoryId = !empty($p['categories']) ? (int)$p['categories'][0]['id'] : null;
                $image = !empty($p['images']) ? $p['images'][0]['src'] : null;
                $isActive = ($p['status'] === 'publish');

                $variations = $allVariations[$productId] ?? [];

                $inventory = [];
                $matrixSizes = [];
                $matrixData = [];
                $totalStock = 0;

                foreach ($variations as $var) {
                    if ($price === '' && !empty($var['regular_price'])) {
                        $price = $var['regular_price'];
                    }

                    $varColor = '';
                    $varSize = '';
                    foreach ($var['attributes'] as $attr) {
                        if (in_array(strtolower($attr['name']), ['color', 'pa_color'])) {
                            $varColor = $attr['option'];
                        }
                        if (in_array(strtolower($attr['name']), ['talla', 'pa_talla'])) {
                            $varSize = $attr['option'];
                        }
                    }

                    $amount = (int)($var['stock_quantity'] ?? 0);
                    $totalStock += $amount;

                    $colorId = null;
                    foreach ($colors as $c) {
                        if (strtolower($c->name) === strtolower($varColor)) {
                            $colorId = $c->id;
                            break;
                        }
                    }
                    $sizeId = null;
                    foreach ($sizes as $s) {
                        if (strtolower($s->name) === strtolower($varSize)) {
                            $sizeId = $s->id;
                            break;
                        }
                    }

                    if (!$colorId) $colorId = $varColor;
                    if (!$sizeId) $sizeId = $varSize;

                    $inventory[] = (object)[
                        'id' => (int)$var['id'],
                        'product_id' => $productId,
                        'color_id' => $colorId,
                        'color_name' => $varColor,
                        'size_id' => $sizeId,
                        'size_name' => $varSize,
                        'amount' => $amount,
                    ];

                    $matrixSizes[$sizeId] = $varSize;
                    $matrixData[$varColor][$sizeId] = $amount;
                }

                ksort($matrixSizes);

                // Aplicar filtros locales de categoría, color o talla
                if ($request->filled('categories') && !in_array($categoryId, (array)$request->categories)) {
                    continue;
                }

                if ($request->filled('colors')) {
                    $hasColor = false;
                    foreach ($inventory as $inv) {
                        if (in_array($inv->color_id, (array)$request->colors)) {
                            $hasColor = true;
                            break;
                        }
                    }
                    if (!$hasColor) continue;
                }

                if ($request->filled('sizes')) {
                    $hasSize = false;
                    foreach ($inventory as $inv) {
                        if (in_array($inv->size_id, (array)$request->sizes)) {
                            $hasSize = true;
                            break;
                        }
                    }
                    if (!$hasSize) continue;
                }

                $productObj = new \stdClass();
                $productObj->id = $productId;
                $productObj->name = $name;
                $productObj->sku = $sku;
                $productObj->price = $price;
                $productObj->category_name = $categoryName;
                $productObj->category_id = $categoryId;
                $productObj->image = $image;
                $productObj->is_active = $isActive;
                $productObj->inventory = $inventory;
                $productObj->total_stock = $totalStock;
                $productObj->matrix_sizes = $matrixSizes;
                $productObj->matrix_data = $matrixData;

                $products[] = $productObj;
            }

            $paginatedProducts = new LengthAwarePaginator(
                $products,
                $totalProducts,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $products = $paginatedProducts;

            return view('inventory.read', compact('categories', 'colors', 'sizes', 'products'));
        } catch (\Exception $e) {
            $emptyPaginator = new LengthAwarePaginator([], 0, 15, 1);
            return view('inventory.read', [
                'categories' => [],
                'colors' => [],
                'sizes' => [],
                'products' => $emptyPaginator,
            ])->with('error', 'Error al consultar WooCommerce API: ' . $e->getMessage());
        }
    }

    /**
     * Muestra el formulario de creación (Wizard).
     */
    public function create(WooCommerceApiService $wcApi)
    {
        try {
            $meta = $wcApi->ensureAttributesAndTermsExist();
            $colors = $meta['colors'];
            $sizes = $meta['sizes'];

            $rawCategories = $wcApi->getCategories(['per_page' => 100]);
            $categories = array_map(function ($c) {
                return (object)[
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                ];
            }, $rawCategories);

            return view('inventory.create', compact('categories', 'colors', 'sizes'));
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Error al cargar formulario: ' . $e->getMessage());
        }
    }

    /**
     * Guarda el nuevo producto y su inventario en WooCommerce vía REST API.
     */
    public function store(Request $request, WooCommerceApiService $wcApi)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeProductImage($request->file('image'), $request->sku);
        }

        try {
            $wcApi->saveFullVariableProduct([
                'name' => $request->name,
                'sku' => $request->sku,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'image_url' => $imageUrl,
                'inventory' => $request->inventory,
                'is_active' => true,
            ]);

            $wcApi->clearCategoriesCache();
            $wcApi->clearAttributesCache();

            return redirect()->route('inventory.index')->with('success', 'El producto y su inventario se guardaron correctamente en WooCommerce vía REST API.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al guardar el producto en WooCommerce: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Muestra la vista de edición.
     */
    public function edit($id, WooCommerceApiService $wcApi)
    {
        try {
            $meta = $wcApi->ensureAttributesAndTermsExist();
            $colors = $meta['colors'];
            $sizes = $meta['sizes'];

            $rawCategories = $wcApi->getCategories(['per_page' => 100]);
            $categories = array_map(function ($c) {
                return (object)[
                    'id' => (int)$c['id'],
                    'name' => $c['name'],
                ];
            }, $rawCategories);

            $p = $wcApi->getProduct((int)$id);
            $variations = $wcApi->getProductVariations((int)$id);

            $name = $p['name'] ?? '';
            $price = $p['price'] ?? $p['regular_price'] ?? '';

            $existingInventory = [];
            $selectedColors = [];
            $selectedSizes = [];

            foreach ($variations as $var) {
                if ($price === '' && !empty($var['regular_price'])) {
                    $price = $var['regular_price'];
                }

                $varColor = '';
                $varSize = '';
                foreach ($var['attributes'] as $attr) {
                    if (in_array(strtolower($attr['name']), ['color', 'pa_color'])) {
                        $varColor = $attr['option'];
                    }
                    if (in_array(strtolower($attr['name']), ['talla', 'pa_talla'])) {
                        $varSize = $attr['option'];
                    }
                }

                $amount = (int)($var['stock_quantity'] ?? 0);

                $colorId = null;
                foreach ($colors as $c) {
                    if (strtolower($c->name) === strtolower($varColor)) {
                        $colorId = $c->id;
                        break;
                    }
                }
                $sizeId = null;
                foreach ($sizes as $s) {
                    if (strtolower($s->name) === strtolower($varSize)) {
                        $sizeId = $s->id;
                        break;
                    }
                }

                if ($colorId && $sizeId) {
                    $existingInventory[$colorId][$sizeId] = $amount;
                    if (!in_array($colorId, $selectedColors)) $selectedColors[] = $colorId;
                    if (!in_array($sizeId, $selectedSizes)) $selectedSizes[] = $sizeId;
                }
            }

            $product = (object)[
                'id' => (int)$p['id'],
                'name' => $name,
                'sku' => !empty($p['sku']) ? $p['sku'] : $name,
                'price' => $price,
                'category_id' => !empty($p['categories']) ? (int)$p['categories'][0]['id'] : null,
                'image' => !empty($p['images']) ? $p['images'][0]['src'] : null,
                'is_active' => ($p['status'] === 'publish'),
            ];

            return view('inventory.edit', compact(
                'product',
                'categories',
                'colors',
                'sizes',
                'existingInventory',
                'selectedColors',
                'selectedSizes'
            ));
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Error al cargar el producto para edición: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el producto y sus variaciones en WooCommerce.
     */
    public function update(Request $request, $id, WooCommerceApiService $wcApi)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $imageUrl = $this->storeProductImage($request->file('image'), $request->sku);
        }

        try {
            $wcApi->saveFullVariableProduct([
                'name' => $request->name,
                'sku' => $request->sku,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'image_url' => $imageUrl,
                'inventory' => $request->inventory,
            ], (int)$id);

            $wcApi->clearCategoriesCache();
            $wcApi->clearAttributesCache();

            return redirect()->route('inventory.index')->with('success', 'Producto e inventario actualizados con éxito en WooCommerce.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar producto en WooCommerce: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Alterna el estado activo/inactivo del producto en WooCommerce.
     */
    public function toggleStatus($id, WooCommerceApiService $wcApi)
    {
        try {
            $wcApi->toggleProductStatus((int)$id);
            return redirect()->route('inventory.index')->with('success', 'Estado del producto actualizado en WooCommerce.');
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Error al cambiar estado: ' . $e->getMessage());
        }
    }

    /**
     * Elimina el producto en WooCommerce.
     */
    public function destroy($id, WooCommerceApiService $wcApi)
    {
        try {
            $wcApi->deleteProduct((int)$id, true);

            $wcApi->clearCategoriesCache();
            $wcApi->clearAttributesCache();

            return redirect()->route('inventory.index')->with('success', 'Producto eliminado exitosamente de WooCommerce.');
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Error al eliminar producto: ' . $e->getMessage());
        }
    }

    /**
     * Procesa y almacena la imagen en el directorio wp-content/uploads de WordPress organizado por año y mes (YYYY/MM).
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @param  string  $sku
     * @return string  URL pública accesible de la imagen en WordPress
     */
    protected function storeProductImage($image, string $sku): ?string
    {
        $year = date('Y');
        $month = date('m');

        // Directorio base de uploads de WordPress
        $uploadsBase = config('woocommerce.wp_uploads_path', base_path('../zapateria-wordpress/wp-content/uploads'));
        $targetDir = rtrim($uploadsBase, '/') . '/' . $year . '/' . $month;

        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $cleanSku = preg_replace('/[^A-Za-z0-9\-]/', '', $sku);
        if (empty($cleanSku)) {
            $cleanSku = 'prod';
        }
        $fileName = 'sku_' . $cleanSku . '_' . time() . '.webp';
        $destinationFile = $targetDir . '/' . $fileName;

        $sourceImage = null;
        $mime = $image->getMimeType();
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($image->getPathname());
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($image->getPathname());
                if ($sourceImage) {
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                }
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($image->getPathname());
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($image->getPathname());
                break;
        }

        if ($sourceImage) {
            imagewebp($sourceImage, $destinationFile, 85);
            imagedestroy($sourceImage);
        } else {
            $extension = $image->getClientOriginalExtension() ?: 'jpg';
            $fileName = 'sku_' . $cleanSku . '_' . time() . '.' . $extension;
            $destinationFile = $targetDir . '/' . $fileName;
            $image->move($targetDir, $fileName);
        }

        // Construir la URL completa apuntando al virtualhost de WordPress
        $storeUrl = rtrim(config('woocommerce.store_url', 'http://zapateria-wordpress'), '/');
        return "{$storeUrl}/wp-content/uploads/{$year}/{$month}/{$fileName}";
    }
}
