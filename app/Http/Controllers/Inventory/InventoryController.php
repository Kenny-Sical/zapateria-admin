<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\WpPost;
use App\Models\WpTerm;
use App\Models\WpProductMetaLookup;
use App\Services\WooCommerceService;

class InventoryController extends Controller
{
    /**
     * Muestra la vista principal del inventario de WooCommerce.
     */
    public function index(Request $request)
    {
        $categories = WpTerm::categories()->get();
        $colors = WpTerm::colors()->get();
        $sizes = WpTerm::sizes()->get();

        $productsQuery = WpPost::where('post_type', 'product')
            ->whereIn('post_status', ['publish', 'draft'])
            ->with(['meta', 'variations.meta', 'taxonomies.term']);

        if ($request->filled('search')) {
            $search = $request->search;
            $productsQuery->where(function ($q) use ($search) {
                $q->where('post_title', 'like', '%' . $search . '%')
                  ->orWhereHas('meta', function ($m) use ($search) {
                      $m->where('meta_key', '_sku')->where('meta_value', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('categories')) {
            $categoryTermIds = (array)$request->categories;
            $productsQuery->whereHas('taxonomies', function ($t) use ($categoryTermIds) {
                $t->where('taxonomy', 'product_cat')->whereIn('term_id', $categoryTermIds);
            });
        }

        if ($request->filled('colors')) {
            $selectedColorIds = (array)$request->colors;
            $colorSlugs = WpTerm::whereIn('term_id', $selectedColorIds)->pluck('slug')->toArray();
            $productsQuery->whereHas('variations.meta', function ($m) use ($colorSlugs) {
                $m->where('meta_key', 'attribute_pa_color')->whereIn('meta_value', $colorSlugs);
            });
        }

        if ($request->filled('sizes')) {
            $selectedSizeIds = (array)$request->sizes;
            $sizeSlugs = WpTerm::whereIn('term_id', $selectedSizeIds)->pluck('slug')->toArray();
            $productsQuery->whereHas('variations.meta', function ($m) use ($sizeSlugs) {
                $m->where('meta_key', 'attribute_pa_talla')->whereIn('meta_value', $sizeSlugs);
            });
        }

        $rawProducts = $productsQuery->orderBy('ID', 'desc')->get();

        $allColors = $colors->keyBy('slug');
        $allSizes = $sizes->keyBy('slug');

        $filteredProducts = [];
        foreach ($rawProducts as $p) {
            $product = new \stdClass();
            $product->id = $p->ID;
            $product->sku = $p->getMeta('_sku', $p->post_title);
            $product->image = $p->image;
            $product->is_active = ($p->post_status === 'publish');

            $catTax = $p->taxonomies->firstWhere('taxonomy', 'product_cat');
            $product->category_name = ($catTax && $catTax->term) ? $catTax->term->name : 'Sin categoría';

            $inventory = [];
            $matrixSizes = [];
            $matrixData = [];
            $totalStock = 0;

            foreach ($p->variations as $var) {
                $cSlug = $var->getMeta('attribute_pa_color');
                $sSlug = $var->getMeta('attribute_pa_talla');
                $amount = (int)$var->getMeta('_stock', 0);

                $cTerm = $allColors->get($cSlug);
                $sTerm = $allSizes->get($sSlug);

                $cId = $cTerm ? $cTerm->term_id : $cSlug;
                $cName = $cTerm ? $cTerm->name : $cSlug;
                $sId = $sTerm ? $sTerm->term_id : $sSlug;
                $sName = $sTerm ? $sTerm->name : $sSlug;

                $totalStock += $amount;

                $inventory[] = (object)[
                    'id' => $var->ID,
                    'product_id' => $p->ID,
                    'color_id' => $cId,
                    'color_name' => $cName,
                    'size_id' => $sId,
                    'size_name' => $sName,
                    'amount' => $amount,
                ];

                $matrixSizes[$sId] = $sName;
                $matrixData[$cName][$sId] = $amount;
            }

            ksort($matrixSizes);
            $product->inventory = $inventory;
            $product->total_stock = $totalStock;
            $product->matrix_sizes = $matrixSizes;
            $product->matrix_data = $matrixData;

            $filteredProducts[] = $product;
        }

        $products = $filteredProducts;
        return view('inventory.read', compact('categories', 'colors', 'sizes', 'products'));
    }

    /**
     * Muestra el formulario de creación (Wizard).
     */
    public function create()
    {
        $categories = WpTerm::categories()->get();
        $colors = WpTerm::colors()->get();
        $sizes = WpTerm::sizes()->get();

        return view('inventory.create', compact('categories', 'colors', 'sizes'));
    }

    /**
     * Guarda el nuevo producto y su inventario en WooCommerce.
     */
    public function store(Request $request, WooCommerceService $wcService)
    {
        $request->validate([
            'sku' => 'required|string',
            'category_id' => 'required|exists:wp_terms,term_id',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        // Validar SKU único en WooCommerce
        $skuExists = DB::table('wp_postmeta')
            ->join('wp_posts', 'wp_postmeta.post_id', '=', 'wp_posts.ID')
            ->where('wp_posts.post_type', 'product')
            ->where('wp_postmeta.meta_key', '_sku')
            ->where('wp_postmeta.meta_value', $request->sku)
            ->exists();

        if ($skuExists) {
            return back()->withErrors(['sku' => 'El SKU ya está en uso por otro producto.'])->withInput();
        }

        $imageName = null;
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
                $imageName = 'storage/products/' . $imageNameStr;
            }
        }

        $wcService->saveVariableProduct([
            'sku' => $request->sku,
            'category_id' => (int)$request->category_id,
            'image' => $imageName,
            'inventory' => $request->inventory,
            'is_active' => true,
        ]);

        return redirect()->route('inventory.index')->with('success', 'El producto y su inventario se guardaron correctamente en WooCommerce.');
    }

    /**
     * Muestra la vista de edición.
     */
    public function edit($id)
    {
        $productPost = WpPost::where('ID', $id)
            ->where('post_type', 'product')
            ->with(['meta', 'variations.meta', 'taxonomies.term'])
            ->firstOrFail();

        $categories = WpTerm::categories()->get();
        $colors = WpTerm::colors()->get();
        $sizes = WpTerm::sizes()->get();

        $allColors = $colors->keyBy('slug');
        $allSizes = $sizes->keyBy('slug');

        $existingInventory = [];
        $selectedColors = [];
        $selectedSizes = [];

        foreach ($productPost->variations as $var) {
            $cSlug = $var->getMeta('attribute_pa_color');
            $sSlug = $var->getMeta('attribute_pa_talla');
            $amount = (int)$var->getMeta('_stock', 0);

            $cTerm = $allColors->get($cSlug);
            $sTerm = $allSizes->get($sSlug);

            if ($cTerm && $sTerm) {
                $cId = $cTerm->term_id;
                $sId = $sTerm->term_id;

                $existingInventory[$cId][$sId] = $amount;
                if (!in_array($cId, $selectedColors)) $selectedColors[] = $cId;
                if (!in_array($sId, $selectedSizes)) $selectedSizes[] = $sId;
            }
        }

        // Obtener ID de la categoría asociada
        $catTax = $productPost->taxonomies->firstWhere('taxonomy', 'product_cat');
        $categoryId = $catTax ? $catTax->term_id : null;

        $product = (object)[
            'id' => $productPost->ID,
            'sku' => $productPost->getMeta('_sku', $productPost->post_title),
            'category_id' => $categoryId,
            'image' => $productPost->image,
            'is_active' => ($productPost->post_status === 'publish'),
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
    }

    /**
     * Actualiza el producto y sus variaciones en WooCommerce.
     */
    public function update(Request $request, $id, WooCommerceService $wcService)
    {
        $productPost = WpPost::where('ID', $id)
            ->where('post_type', 'product')
            ->firstOrFail();

        $request->validate([
            'sku' => 'required|string',
            'category_id' => 'required|exists:wp_terms,term_id',
            'image' => 'nullable|image',
            'inventory' => 'required|array',
        ]);

        // Validar SKU único en otros productos
        $skuExists = DB::table('wp_postmeta')
            ->join('wp_posts', 'wp_postmeta.post_id', '=', 'wp_posts.ID')
            ->where('wp_posts.post_type', 'product')
            ->where('wp_posts.ID', '!=', $id)
            ->where('wp_postmeta.meta_key', '_sku')
            ->where('wp_postmeta.meta_value', $request->sku)
            ->exists();

        if ($skuExists) {
            return back()->withErrors(['sku' => 'El SKU ya está en uso por otro producto.'])->withInput();
        }

        $imageName = $productPost->image;
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
                $imageName = 'storage/products/' . $imageNameStr;
            }
        }

        $wcService->saveVariableProduct([
            'sku' => $request->sku,
            'category_id' => (int)$request->category_id,
            'image' => $imageName,
            'inventory' => $request->inventory,
            'is_active' => ($productPost->post_status === 'publish'),
        ], (int)$id);

        return redirect()->route('inventory.index')->with('success', 'Producto e inventario actualizados con éxito en WooCommerce.');
    }

    /**
     * Alterna el estado activo (publish) / inactivo (draft) del producto.
     */
    public function toggleStatus($id)
    {
        $product = WpPost::where('ID', $id)
            ->where('post_type', 'product')
            ->firstOrFail();

        $newStatus = ($product->post_status === 'publish') ? 'draft' : 'publish';

        $product->update([
            'post_status' => $newStatus,
            'post_modified' => now(),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
        ]);

        $message = ($newStatus === 'publish') ? 'Producto habilitado con éxito en la tienda.' : 'Producto deshabilitado con éxito en la tienda.';
        return redirect()->route('inventory.index')->with('success', $message);
    }

    /**
     * Elimina permanentemente el producto y sus variaciones asociadas en WooCommerce.
     */
    public function destroy($id)
    {
        $product = WpPost::where('ID', $id)
            ->where('post_type', 'product')
            ->firstOrFail();

        if ($product->post_status === 'publish') {
            return redirect()->route('inventory.index')->with('error', 'No puedes eliminar un producto que está activo en la tienda. Deshabilítalo primero.');
        }

        if ($product->image && file_exists(public_path($product->image))) {
            @unlink(public_path($product->image));
        }

        // Obtener variaciones hijas
        $variationIds = WpPost::where('post_parent', $id)
            ->where('post_type', 'product_variation')
            ->pluck('ID')
            ->toArray();

        $allPostIds = array_merge([$id], $variationIds);

        // Eliminar metadatos, relaciones, lookup tables y posts
        DB::table('wp_postmeta')->whereIn('post_id', $allPostIds)->delete();
        DB::table('wp_term_relationships')->whereIn('object_id', $allPostIds)->delete();
        DB::table('wp_wc_product_meta_lookup')->whereIn('product_id', $allPostIds)->delete();
        WpPost::whereIn('ID', $allPostIds)->delete();

        return redirect()->route('inventory.index')->with('success', 'Producto y variaciones eliminados permanentemente de WooCommerce.');
    }
}
