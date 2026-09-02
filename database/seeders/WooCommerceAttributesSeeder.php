<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\WpTerm;
use App\Models\WpTermTaxonomy;

class WooCommerceAttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Atributos globales en wp_woocommerce_attribute_taxonomies
        $attributes = [
            ['attribute_name' => 'color', 'attribute_label' => 'Color', 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => 1],
            ['attribute_name' => 'talla', 'attribute_label' => 'Talla', 'attribute_type' => 'select', 'attribute_orderby' => 'menu_order', 'attribute_public' => 1],
        ];

        foreach ($attributes as $attr) {
            $exists = DB::table('wp_woocommerce_attribute_taxonomies')
                ->where('attribute_name', $attr['attribute_name'])
                ->exists();

            if (!$exists) {
                DB::table('wp_woocommerce_attribute_taxonomies')->insert($attr);
            }
        }

        // 2. Colores por defecto
        $colors = ['Negro', 'Blanco', 'Café', 'Azul', 'Rojo', 'Beige', 'Gris', 'Rosa', 'Vino'];
        foreach ($colors as $colorName) {
            $slug = Str::slug($colorName);
            $term = WpTerm::where('slug', $slug)->first();
            if (!$term) {
                $term = WpTerm::create([
                    'name' => $colorName,
                    'slug' => $slug,
                    'term_group' => 0,
                ]);
            }

            $taxExists = WpTermTaxonomy::where('term_id', $term->term_id)
                ->where('taxonomy', 'pa_color')
                ->exists();

            if (!$taxExists) {
                WpTermTaxonomy::create([
                    'term_id' => $term->term_id,
                    'taxonomy' => 'pa_color',
                    'description' => '',
                    'parent' => 0,
                    'count' => 0,
                ]);
            }
        }

        // 3. Tallas por defecto
        $sizes = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
        foreach ($sizes as $sizeName) {
            $slug = Str::slug($sizeName);
            $term = WpTerm::where('slug', $slug)->first();
            if (!$term) {
                $term = WpTerm::create([
                    'name' => $sizeName,
                    'slug' => $slug,
                    'term_group' => 0,
                ]);
            }

            $taxExists = WpTermTaxonomy::where('term_id', $term->term_id)
                ->where('taxonomy', 'pa_talla')
                ->exists();

            if (!$taxExists) {
                WpTermTaxonomy::create([
                    'term_id' => $term->term_id,
                    'taxonomy' => 'pa_talla',
                    'description' => '',
                    'parent' => 0,
                    'count' => 0,
                ]);
            }
        }

        // 4. Categorías por defecto si no existen
        $categories = ['Tacones', 'Botas', 'Sandalias', 'Tenis', 'Plataformas'];
        foreach ($categories as $catName) {
            $slug = Str::slug($catName);
            $term = WpTerm::where('slug', $slug)->first();
            if (!$term) {
                $term = WpTerm::create([
                    'name' => $catName,
                    'slug' => $slug,
                    'term_group' => 0,
                ]);
            }

            $taxExists = WpTermTaxonomy::where('term_id', $term->term_id)
                ->where('taxonomy', 'product_cat')
                ->exists();

            if (!$taxExists) {
                WpTermTaxonomy::create([
                    'term_id' => $term->term_id,
                    'taxonomy' => 'product_cat',
                    'description' => '',
                    'parent' => 0,
                    'count' => 0,
                ]);
            }
        }
    }
}
