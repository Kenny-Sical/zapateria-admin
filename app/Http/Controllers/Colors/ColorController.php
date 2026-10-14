<?php

namespace App\Http\Controllers\Colors;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ColorController extends Controller
{
    /**
     * Display a listing of color attribute terms.
     */
    public function index(Request $request, WooCommerceApiService $wcApi)
    {
        try {
            $rawColors = $wcApi->getColors();
            $colors = array_map(function ($color) {
                $color = (array) $color;

                return (object) [
                    'id' => (int) $color['id'],
                    'name' => (string) $color['name'],
                    'slug' => (string) $color['slug'],
                    'count' => (int) ($color['count'] ?? 0),
                ];
            }, $rawColors);

            if ($request->filled('search')) {
                $search = mb_strtolower(trim((string) $request->search));
                $colors = array_values(array_filter($colors, function ($color) use ($search) {
                    return str_contains(mb_strtolower($color->name), $search);
                }));
            }

            return view('colors.read', compact('colors'));
        } catch (\Exception $e) {
            return view('colors.read', ['colors' => []])
                ->with('error', 'Error de conexión con WooCommerce API: '.$e->getMessage());
        }
    }

    /**
     * Show the form for creating a new color.
     */
    public function create()
    {
        return view('colors.create');
    }

    /**
     * Store a newly created color in WooCommerce.
     */
    public function store(Request $request, WooCommerceApiService $wcApi)
    {
        $trimmedName = trim((string) $request->input('name', ''));
        $request->merge(['name' => $trimmedName]);
        $request->validate(['name' => 'required|string|max:100']);

        try {
            $existingColors = $wcApi->getColors();
            $lowerName = mb_strtolower($trimmedName);
            $slug = Str::slug($trimmedName);

            foreach ($existingColors as $color) {
                $c = (array) $color;
                $cName = mb_strtolower((string) ($c['name'] ?? ''));
                $cSlug = (string) ($c['slug'] ?? '');
                if ($cName === $lowerName || $cSlug === $slug) {
                    return back()->withErrors(['name' => 'Ya existe un color con ese nombre.'])->withInput();
                }
            }

            $wcApi->createColor($trimmedName);

            return redirect()->route('colors.index')->with('success', 'Color creado exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear el color: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for editing the specified color.
     */
    public function edit($id, WooCommerceApiService $wcApi)
    {
        try {
            $rawColor = $wcApi->getColor((int) $id);
            $color = (object) [
                'id' => (int) $rawColor['id'],
                'name' => (string) $rawColor['name'],
                'slug' => (string) $rawColor['slug'],
            ];

            return view('colors.edit', compact('color'));
        } catch (\Exception $e) {
            return redirect()->route('colors.index')->with('error', 'No se pudo cargar el color: '.$e->getMessage());
        }
    }

    /**
     * Update the specified color in WooCommerce (name only, never slug).
     */
    public function update(Request $request, $id, WooCommerceApiService $wcApi)
    {
        $id = (int) $id;
        $trimmedName = trim((string) $request->input('name', ''));
        $request->merge(['name' => $trimmedName]);
        $request->validate(['name' => 'required|string|max:100']);

        try {
            $existingColors = $wcApi->getColors();
            $lowerName = mb_strtolower($trimmedName);
            $slug = Str::slug($trimmedName);

            foreach ($existingColors as $color) {
                $c = (array) $color;
                if ((int) ($c['id'] ?? 0) === $id) {
                    continue;
                }
                $cName = mb_strtolower((string) ($c['name'] ?? ''));
                $cSlug = (string) ($c['slug'] ?? '');
                if ($cName === $lowerName || $cSlug === $slug) {
                    return back()->withErrors(['name' => 'Ya existe un color con ese nombre.'])->withInput();
                }
            }

            $wcApi->updateColor($id, $trimmedName);

            return redirect()->route('colors.index')->with('success', 'Color actualizado exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar el color: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified color from WooCommerce.
     */
    public function destroy($id, WooCommerceApiService $wcApi)
    {
        $id = (int) $id;

        try {
            $colors = $wcApi->getColors();
            if (count($colors) <= 1) {
                return redirect()->route('colors.index')->with('error', 'No se puede eliminar el último color.');
            }

            if ($wcApi->isColorInUse($id)) {
                return redirect()->route('colors.index')->with('error', 'No se puede eliminar: hay productos que usan este color.');
            }

            $wcApi->deleteColor($id, true);

            return redirect()->route('colors.index')->with('success', 'Color eliminado exitosamente en WooCommerce.');
        } catch (\Exception $e) {
            return redirect()->route('colors.index')->with('error', 'No se pudo eliminar el color: '.$e->getMessage());
        }
    }
}
