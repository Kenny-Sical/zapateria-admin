<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Muestra la vista principal del inventario.
     */
    public function index(Request $request)
    {
        $categories = \Illuminate\Support\Facades\DB::table('categories')->get();
        $colors = \Illuminate\Support\Facades\DB::table('colors')->get();
        $sizes = \Illuminate\Support\Facades\DB::table('sizes')->get();

        $query = \Illuminate\Support\Facades\DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('products.*', 'categories.name as category_name');

        $inventoryQuery = \Illuminate\Support\Facades\DB::table('inventories')
            ->join('colors', 'inventories.color_id', '=', 'colors.id')
            ->join('sizes', 'inventories.size_id', '=', 'sizes.id')
            ->select('inventories.*', 'colors.name as color_name', 'sizes.size as size_name');

        if ($request->filled('search')) {
            $query->where('products.sku', 'like', '%' . $request->search . '%');
        }
        
        if ($request->filled('categories')) {
            $query->whereIn('products.category_id', $request->categories);
        }

        if ($request->filled('colors')) {
            $inventoryQuery->whereIn('inventories.color_id', $request->colors);
            $query->whereIn('products.id', function($q) use ($request) {
                $q->select('product_id')->from('inventories')->whereIn('color_id', $request->colors);
            });
        }
        
        if ($request->filled('sizes')) {
            $inventoryQuery->whereIn('inventories.size_id', $request->sizes);
            $query->whereIn('products.id', function($q) use ($request) {
                $q->select('product_id')->from('inventories')->whereIn('size_id', $request->sizes);
            });
        }

        $products = $query->orderBy('products.id', 'desc')->get();
        $inventories = $inventoryQuery->get();

        $groupedInventories = [];
        foreach ($inventories as $inv) {
            $groupedInventories[$inv->product_id][] = $inv;
        }

        $filteredProducts = [];
        foreach ($products as $product) {
            $product->inventory = $groupedInventories[$product->id] ?? [];
            $product->total_stock = array_sum(array_column($product->inventory, 'amount'));
            
            if (!empty($product->inventory) || (!$request->filled('colors') && !$request->filled('sizes'))) {
                // Preparar matriz cruzada (Color x Talla)
                $matrixSizes = [];
                $matrixData = [];
                
                foreach ($product->inventory as $inv) {
                    $matrixSizes[$inv->size_id] = $inv->size_name;
                    $matrixData[$inv->color_name][$inv->size_id] = $inv->amount;
                }
                
                asort($matrixSizes); // Ordenar tallas
                
                $product->matrix_sizes = $matrixSizes;
                $product->matrix_data = $matrixData;

                $filteredProducts[] = $product;
            }
        }
        $products = $filteredProducts;

        return view('inventory.read', compact('categories', 'colors', 'sizes', 'products'));
    }

    /**
     * Muestra el formulario de creación (Wizard).
     */
    public function create()
    {
        $categories = \Illuminate\Support\Facades\DB::table('categories')->get();
        $colors = \Illuminate\Support\Facades\DB::table('colors')->get();
        $sizes = \Illuminate\Support\Facades\DB::table('sizes')->get();

        return view('inventory.create', compact('categories', 'colors', 'sizes'));
    }

    /**
     * Guarda el nuevo producto y su inventario asociado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image',
            'inventory' => 'required|array'
        ]);

        $imageName = null;
        if ($request->hasFile('image')) {
            // Procesamiento de Imagen a WebP
            $image = $request->file('image');
            // Limpiamos el SKU y le concatenamos el tiempo para evitar duplicados
            $imageNameStr = 'sku_' . preg_replace('/[^A-Za-z0-9\-]/', '', $request->sku) . '_' . time() . '.webp';
            $destinationPath = public_path('storage/products');
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $sourceImage = null;
            $mime = $image->getMimeType();
            switch($mime) {
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
            }
            
            $imageName = 'storage/products/' . $imageNameStr;
        }

        // 1. Guardar Producto
        $productId = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
            'sku' => $request->sku,
            'category_id' => $request->category_id,
            'image' => $imageName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Guardar Inventario (Matriz Excel)
        $inventoryData = [];
        foreach ($request->inventory as $colorId => $sizes) {
            foreach ($sizes as $sizeId => $amount) {
                if ($amount > 0) { // Solo guardar si hay más de 0
                    $inventoryData[] = [
                        'product_id' => $productId,
                        'color_id' => $colorId,
                        'size_id' => $sizeId,
                        'amount' => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($inventoryData)) {
            \Illuminate\Support\Facades\DB::table('inventories')->insert($inventoryData);
        }

        return redirect()->route('inventory.index')->with('success', 'El producto y su inventario se guardaron correctamente.');
    }

    public function edit($id)
    {
        $product = \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->first();
        if (!$product) abort(404);

        $categories = \Illuminate\Support\Facades\DB::table('categories')->get();
        $colors = \Illuminate\Support\Facades\DB::table('colors')->get();
        $sizes = \Illuminate\Support\Facades\DB::table('sizes')->get();

        $inventoryRecords = \Illuminate\Support\Facades\DB::table('inventories')->where('product_id', $id)->get();
        
        $existingInventory = [];
        $selectedColors = [];
        $selectedSizes = [];
        
        foreach ($inventoryRecords as $record) {
            $existingInventory[$record->color_id][$record->size_id] = $record->amount;
            if (!in_array($record->color_id, $selectedColors)) $selectedColors[] = $record->color_id;
            if (!in_array($record->size_id, $selectedSizes)) $selectedSizes[] = $record->size_id;
        }

        return view('inventory.edit', compact('product', 'categories', 'colors', 'sizes', 'existingInventory', 'selectedColors', 'selectedSizes'));
    }

    public function update(Request $request, $id)
    {
        $product = \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->first();
        if (!$product) abort(404);

        $request->validate([
            'sku' => 'required|string|unique:products,sku,' . $id,
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image',
            'inventory' => 'required|array'
        ]);

        $imageName = $product->image;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageNameStr = 'sku_' . preg_replace('/[^A-Za-z0-9\-]/', '', $request->sku) . '_' . time() . '.webp';
            $destinationPath = public_path('storage/products');
            
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $sourceImage = null;
            $mime = $image->getMimeType();
            switch($mime) {
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

        \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->update([
            'sku' => $request->sku,
            'category_id' => $request->category_id,
            'image' => $imageName,
            'updated_at' => now(),
        ]);

        $processedCombinations = [];
        foreach ($request->inventory as $color_id => $sizes_data) {
            foreach ($sizes_data as $size_id => $amount) {
                // Insertar o actualizar manteniendo el ID intacto
                \Illuminate\Support\Facades\DB::table('inventories')->updateOrInsert(
                    ['product_id' => $id, 'color_id' => $color_id, 'size_id' => $size_id],
                    ['amount' => $amount, 'updated_at' => now()]
                );
                
                $processedCombinations[] = [
                    'color_id' => $color_id, 
                    'size_id' => $size_id
                ];
            }
        }

        // Poner en 0 aquellas combinaciones viejas que fueron omitidas ahora
        $allInventories = \Illuminate\Support\Facades\DB::table('inventories')->where('product_id', $id)->get();
        foreach ($allInventories as $inv) {
            $found = false;
            foreach ($processedCombinations as $pc) {
                if ($pc['color_id'] == $inv->color_id && $pc['size_id'] == $inv->size_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found && $inv->amount > 0) {
                \Illuminate\Support\Facades\DB::table('inventories')
                    ->where('id', $inv->id)
                    ->update(['amount' => 0, 'updated_at' => now()]);
            }
        }

        return redirect()->route('inventory.index')->with('success', 'Producto e inventario actualizados con éxito.');
    }

    public function toggleStatus($id)
    {
        $product = \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->first();
        if (!$product) abort(404);

        $newStatus = !$product->is_active;

        \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->update([
            'is_active' => $newStatus,
            'updated_at' => now(),
        ]);

        $message = $newStatus ? 'Producto habilitado con éxito.' : 'Producto deshabilitado con éxito.';
        return redirect()->route('inventory.index')->with('success', $message);
    }

    public function destroy($id)
    {
        $product = \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->first();
        if (!$product) abort(404);

        if ($product->is_active) {
            return redirect()->route('inventory.index')->with('error', 'No puedes eliminar un producto que está activo. Deshabilítalo primero.');
        }

        // Si tiene imagen, la borramos del storage
        if ($product->image && file_exists(public_path($product->image))) {
            unlink(public_path($product->image));
        }

        // Eliminar de base de datos
        \Illuminate\Support\Facades\DB::table('products')->where('id', $id)->delete();

        return redirect()->route('inventory.index')->with('success', 'Producto eliminado permanentemente.');
    }
}
