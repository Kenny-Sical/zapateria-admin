<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

            $params = ['per_page' => 50];
            if ($request->filled('search')) {
                $params['search'] = $request->search;
            }

            $rawProducts = $wcApi->getProducts($params);

            $products = [];
            foreach ($rawProducts as $p) {
                $productId = (int)$p['id'];
                $sku = !empty($p['sku']) ? $p['sku'] : $p['name'];
                $categoryName = !empty($p['categories']) ? $p['categories'][0]['name'] : 'Sin categoría';
                $categoryId = !empty($p['categories']) ? (int)$p['categories'][0]['id'] : null;
                $image = !empty($p['images']) ? $p['images'][0]['src'] : null;
                $isActive = ($p['status'] === 'publish');

                $variations = $wcApi->getProductVariations($productId);

                $inventory = [];
                $matrixSizes = [];
                $matrixData = [];
                $totalStock = 0;

                foreach ($variations as $var) {
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
                $productObj->sku = $sku;
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

            return view('inventory.read', compact('categories', 'colors', 'sizes', 'products'));
        } catch (\Exception $e) {
            return view('inventory.read', [
                'categories' => [],
                'colors' => [],
                'sizes' => [],
                'products' => [],
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
            'sku' => 'required|string',
            'category_id' => 'required',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageNameStr = 'sku_' . preg_replace('/[^A-Za-z0-9\-]/', '', $request->sku) . '_' . time() . '.webp';
            $destinationPath = public_path('storage/products');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $sourceImage = null;
            $mime = $image->getMimeType();
            switch ($mime) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($image->getPathname());
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($image->getPathname());
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($image->getPathname());
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($image->getPathname());
                    break;
            }

            if ($sourceImage) {
                imagewebp($sourceImage, $destinationPath . '/' . $imageNameStr, 85);
                imagedestroy($sourceImage);
                $imageUrl = asset('storage/products/' . $imageNameStr);
            }
        }

        try {
            $wcApi->saveFullVariableProduct([
                'sku' => $request->sku,
                'category_id' => $request->category_id,
                'image_url' => $imageUrl,
                'inventory' => $request->inventory,
                'is_active' => true,
            ]);

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

            $existingInventory = [];
            $selectedColors = [];
            $selectedSizes = [];

            foreach ($variations as $var) {
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
                'sku' => !empty($p['sku']) ? $p['sku'] : $p['name'],
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
            'sku' => 'required|string',
            'category_id' => 'required',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageNameStr = 'sku_' . preg_replace('/[^A-Za-z0-9\-]/', '', $request->sku) . '_' . time() . '.webp';
            $destinationPath = public_path('storage/products');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $sourceImage = null;
            $mime = $image->getMimeType();
            switch ($mime) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($image->getPathname());
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($image->getPathname());
                    imagepalettetotruecolor($sourceImage);
                    imagealphablending($sourceImage, true);
                    imagesavealpha($sourceImage, true);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($image->getPathname());
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($image->getPathname());
                    break;
            }

            if ($sourceImage) {
                imagewebp($sourceImage, $destinationPath . '/' . $imageNameStr, 85);
                imagedestroy($sourceImage);
                $imageUrl = asset('storage/products/' . $imageNameStr);
            }
        }

        try {
            $wcApi->saveFullVariableProduct([
                'sku' => $request->sku,
                'category_id' => $request->category_id,
                'image_url' => $imageUrl,
                'inventory' => $request->inventory,
            ], (int)$id);

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
            return redirect()->route('inventory.index')->with('success', 'Producto eliminado exitosamente de WooCommerce.');
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Error al eliminar producto: ' . $e->getMessage());
        }
    }
}
