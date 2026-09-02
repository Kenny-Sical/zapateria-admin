<?php

namespace App\Http\Controllers\Categories;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\WpTerm;
use App\Models\WpTermTaxonomy;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = WpTerm::categories()->with('taxonomy');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->orderBy('term_id', 'desc')->get();
        return view('categories.read', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
        ]);

        $slug = Str::slug($request->name);
        $baseSlug = $slug;
        $i = 1;
        while (WpTerm::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $term = WpTerm::create([
            'name' => $request->name,
            'slug' => $slug,
            'term_group' => 0,
        ]);

        WpTermTaxonomy::create([
            'term_id' => $term->term_id,
            'taxonomy' => 'product_cat',
            'description' => '',
            'parent' => 0,
            'count' => 0,
        ]);

        return redirect()->route('categories.index')->with('success', 'Categoría creada exitosamente.');
    }

    public function edit($id)
    {
        $category = WpTerm::categories()->where('term_id', $id)->first();
        if (!$category) {
            return redirect()->route('categories.index')->with('error', 'Categoría no encontrada.');
        }
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $category = WpTerm::categories()->where('term_id', $id)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:200',
        ]);

        $slug = Str::slug($request->name);
        $category->update([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return redirect()->route('categories.index')->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy($id)
    {
        $category = WpTerm::categories()->with('taxonomy')->where('term_id', $id)->firstOrFail();
        
        $hasProducts = false;
        if ($category->taxonomy) {
            $hasProducts = DB::table('wp_term_relationships')
                ->where('term_taxonomy_id', $category->taxonomy->term_taxonomy_id)
                ->exists();
        }

        if ($hasProducts) {
            return redirect()->route('categories.index')->with('error', 'No se puede eliminar la categoría porque tiene productos asociados en la tienda.');
        }

        if ($category->taxonomy) {
            $category->taxonomy->delete();
        }
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Categoría eliminada exitosamente.');
    }
}
