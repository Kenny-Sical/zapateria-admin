<?php

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\WooCommerceApiService;

class CategoryController extends Controller
{
    public function index(Request $request, WooCommerceApiService $wcApi)
    {
        try {
            $rawCategories = $wcApi->getCategories(['per_page' => 100]);
            $categories = array_map(function ($cat) {
                return (object)[
                    'id' => (int)$cat['id'],
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'count' => $cat['count'] ?? 0,
                    'created_at' => null,
                ];
            }, $rawCategories);

            if ($request->filled('search')) {
                $search = strtolower($request->search);
                $categories = array_values(array_filter($categories, function ($cat) use ($search) {
                    return str_contains(strtolower($cat->name), $search);
                }));
            }

            return view('categories.read', compact('categories'));
        } catch (\Exception $e) {
            return view('categories.read', ['categories' => []])
                ->with('error', 'Error de conexión con WooCommerce API: ' . $e->getMessage());
        }
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request, WooCommerceApiService $wcApi)
    {
        $request->validate(['name' => 'required|string|max:200']);

        try {
            $wcApi->createCategory([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
            ]);

            return redirect()->route('categories.index')->with('success', 'Categoría creada exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear la categoría: ' . $e->getMessage())->withInput();
        }
    }

    public function edit($id, WooCommerceApiService $wcApi)
    {
        try {
            $rawCategory = $wcApi->getCategory((int)$id);
            $category = (object)[
                'id' => (int)$rawCategory['id'],
                'name' => $rawCategory['name'],
                'slug' => $rawCategory['slug'],
            ];

            return view('categories.edit', compact('category'));
        } catch (\Exception $e) {
            return redirect()->route('categories.index')->with('error', 'No se pudo cargar la categoría: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id, WooCommerceApiService $wcApi)
    {
        $request->validate(['name' => 'required|string|max:200']);

        try {
            $wcApi->updateCategory((int)$id, [
                'name' => $request->name,
                'slug' => Str::slug($request->name),
            ]);

            return redirect()->route('categories.index')->with('success', 'Categoría actualizada exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar la categoría: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id, WooCommerceApiService $wcApi)
    {
        try {
            $wcApi->deleteCategory((int)$id, true);
            return redirect()->route('categories.index')->with('success', 'Categoría eliminada exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return redirect()->route('categories.index')->with('error', 'No se pudo eliminar la categoría: ' . $e->getMessage());
        }
    }
}
