<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\WpPost;
use App\Models\WpPostMeta;
use App\Models\WpTerm;
use App\Models\WpTermTaxonomy;
use App\Models\WpProductMetaLookup;

class WooCommerceService
{
    /**
     * Asegura que las definiciones de atributos globales 'color' y 'talla' existan en WooCommerce.
     */
    public function ensureAttributesExist(): void
    {
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
    }

    /**
     * Guarda o actualiza un producto variable de calzado y sus variaciones (Matriz Color x Talla).
     *
     * @param array $data ['sku' => string, 'category_id' => int, 'image' => ?string, 'inventory' => array, 'is_active' => ?bool]
     * @param int|null $productId ID del producto padre si es actualización
     * @return WpPost
     */
    public function saveVariableProduct(array $data, ?int $productId = null): WpPost
    {
        $this->ensureAttributesExist();

        $now = now();
        $gmtNow = gmdate('Y-m-d H:i:s');
        $sku = trim($data['sku']);
        $title = $sku;
        $slug = Str::slug($sku);
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        $status = $isActive ? 'publish' : 'draft';

        if ($productId) {
            $product = WpPost::findOrFail($productId);
            $product->update([
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => $status,
                'post_modified' => $now,
                'post_modified_gmt' => $gmtNow,
            ]);
        } else {
            $product = WpPost::create([
                'post_author' => auth()->id() ?? 1,
                'post_date' => $now,
                'post_date_gmt' => $gmtNow,
                'post_content' => '',
                'post_title' => $title,
                'post_excerpt' => '',
                'post_status' => $status,
                'comment_status' => 'open',
                'ping_status' => 'closed',
                'post_password' => '',
                'post_name' => $slug,
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => $now,
                'post_modified_gmt' => $gmtNow,
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => url('/?post_type=product&#038;p='),
                'menu_order' => 0,
                'post_type' => 'product',
                'post_mime_type' => '',
                'comment_count' => 0,
            ]);

            // Actualizar guid con el ID real
            $product->guid = url('/?post_type=product&#038;p=' . $product->ID);
            $product->save();
        }

        // 1. Guardar metadatos base del producto padre
        $product->setMeta('_sku', $sku);
        $product->setMeta('_manage_stock', 'no'); // El stock se maneja en las variaciones
        $product->setMeta('_tax_status', 'taxable');
        $product->setMeta('_tax_class', '');
        $product->setMeta('_visibility', 'visible');

        if (!empty($data['image'])) {
            $product->setMeta('_product_image_url', $data['image']);
            $product->setMeta('_thumbnail_path', $data['image']);
        }

        // Configurar atributos globales serializados para WooCommerce
        $productAttributes = [
            'pa_color' => [
                'name' => 'pa_color',
                'value' => '',
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            ],
            'pa_talla' => [
                'name' => 'pa_talla',
                'value' => '',
                'position' => 1,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            ],
        ];
        $product->setMeta('_product_attributes', serialize($productAttributes));

        // 2. Asociar Categoría y Tipo de Producto ('variable') en wp_term_relationships
        $termTaxonomyIdsToSync = [];

        // Tipo de producto: variable (en taxonomy product_type)
        $variableTypeTaxonomy = DB::table('wp_term_taxonomy')
            ->join('wp_terms', 'wp_term_taxonomy.term_id', '=', 'wp_terms.term_id')
            ->where('wp_term_taxonomy.taxonomy', 'product_type')
            ->where('wp_terms.slug', 'variable')
            ->value('wp_term_taxonomy.term_taxonomy_id');

        if ($variableTypeTaxonomy) {
            $termTaxonomyIdsToSync[] = $variableTypeTaxonomy;
        }

        // Categoría seleccionada
        if (!empty($data['category_id'])) {
            $catTaxonomyId = DB::table('wp_term_taxonomy')
                ->where('term_id', $data['category_id'])
                ->where('taxonomy', 'product_cat')
                ->value('term_taxonomy_id');

            if ($catTaxonomyId) {
                $termTaxonomyIdsToSync[] = $catTaxonomyId;
            }
        }

        // 3. Procesar Matriz de Variaciones (Color x Talla)
        $inventory = $data['inventory'] ?? [];
        $totalStock = 0;
        $activeVariationIds = [];

        // Obtener términos de colores y tallas para resolver slugs y taxonomías
        $colorTerms = WpTerm::colors()->with('taxonomy')->get()->keyBy('term_id');
        $sizeTerms = WpTerm::sizes()->with('taxonomy')->get()->keyBy('term_id');

        // Cargar variaciones existentes del producto padre
        $existingVariations = WpPost::where('post_parent', $product->ID)
            ->where('post_type', 'product_variation')
            ->with('meta')
            ->get();

        foreach ($inventory as $colorId => $sizes) {
            $colorTerm = $colorTerms->get($colorId);
            if (!$colorTerm) continue;

            if ($colorTerm->taxonomy && !in_array($colorTerm->taxonomy->term_taxonomy_id, $termTaxonomyIdsToSync)) {
                $termTaxonomyIdsToSync[] = $colorTerm->taxonomy->term_taxonomy_id;
            }

            foreach ($sizes as $sizeId => $amount) {
                $sizeTerm = $sizeTerms->get($sizeId);
                if (!$sizeTerm) continue;

                if ($sizeTerm->taxonomy && !in_array($sizeTerm->taxonomy->term_taxonomy_id, $termTaxonomyIdsToSync)) {
                    $termTaxonomyIdsToSync[] = $sizeTerm->taxonomy->term_taxonomy_id;
                }

                $amount = max(0, (int)$amount);
                $totalStock += $amount;
                $colorSlug = $colorTerm->slug;
                $sizeSlug = $sizeTerm->slug;

                // Buscar si ya existe una variación para este color y talla
                $variation = $existingVariations->first(function ($v) use ($colorSlug, $sizeSlug) {
                    $vColor = $v->getMeta('attribute_pa_color');
                    $vSize = $v->getMeta('attribute_pa_talla');
                    return $vColor === $colorSlug && $vSize === $sizeSlug;
                });

                $variationSku = $sku . '-' . strtoupper($colorSlug) . '-' . $sizeSlug;
                $stockStatus = ($amount > 0) ? 'instock' : 'outofstock';

                if (!$variation) {
                    if ($amount > 0 || $productId) { // Crear variación si tiene stock o si estamos editando
                        $variation = WpPost::create([
                            'post_author' => auth()->id() ?? 1,
                            'post_date' => $now,
                            'post_date_gmt' => $gmtNow,
                            'post_content' => '',
                            'post_title' => "Variación #{$product->ID} - Color: {$colorTerm->name}, Talla: {$sizeTerm->name}",
                            'post_excerpt' => '',
                            'post_status' => 'publish',
                            'comment_status' => 'closed',
                            'ping_status' => 'closed',
                            'post_password' => '',
                            'post_name' => "product-{$product->ID}-variation-" . Str::slug("{$colorSlug}-{$sizeSlug}"),
                            'to_ping' => '',
                            'pinged' => '',
                            'post_modified' => $now,
                            'post_modified_gmt' => $gmtNow,
                            'post_content_filtered' => '',
                            'post_parent' => $product->ID,
                            'guid' => url('/?post_type=product_variation&#038;p='),
                            'menu_order' => 0,
                            'post_type' => 'product_variation',
                            'post_mime_type' => '',
                            'comment_count' => 0,
                        ]);
                        $variation->guid = url('/?post_type=product_variation&#038;p=' . $variation->ID);
                        $variation->save();
                    }
                } else {
                    $variation->update([
                        'post_modified' => $now,
                        'post_modified_gmt' => $gmtNow,
                    ]);
                }

                if ($variation) {
                    $activeVariationIds[] = $variation->ID;

                    // Metas de la variación
                    $variation->setMeta('attribute_pa_color', $colorSlug);
                    $variation->setMeta('attribute_pa_talla', $sizeSlug);
                    $variation->setMeta('_sku', $variationSku);
                    $variation->setMeta('_manage_stock', 'yes');
                    $variation->setMeta('_stock', $amount);
                    $variation->setMeta('_stock_status', $stockStatus);
                    $variation->setMeta('_price', 0);
                    $variation->setMeta('_regular_price', 0);

                    // Sincronizar lookup table para la variación
                    WpProductMetaLookup::updateOrCreate(
                        ['product_id' => $variation->ID],
                        [
                            'sku' => $variationSku,
                            'virtual' => 0,
                            'downloadable' => 0,
                            'min_price' => 0,
                            'max_price' => 0,
                            'onsale' => 0,
                            'stock_quantity' => $amount,
                            'stock_status' => $stockStatus,
                            'tax_status' => 'taxable',
                        ]
                    );
                }
            }
        }

        // Poner en stock 0 aquellas variaciones anteriores que ya no se enviaron
        foreach ($existingVariations as $oldVar) {
            if (!in_array($oldVar->ID, $activeVariationIds)) {
                $oldVar->setMeta('_stock', 0);
                $oldVar->setMeta('_stock_status', 'outofstock');
                WpProductMetaLookup::where('product_id', $oldVar->ID)->update([
                    'stock_quantity' => 0,
                    'stock_status' => 'outofstock'
                ]);
            }
        }

        // 4. Sincronizar stock y estado en el producto padre
        $parentStockStatus = ($totalStock > 0) ? 'instock' : 'outofstock';
        $product->setMeta('_stock_status', $parentStockStatus);

        // Sincronizar lookup table para el padre
        WpProductMetaLookup::updateOrCreate(
            ['product_id' => $product->ID],
            [
                'sku' => $sku,
                'virtual' => 0,
                'downloadable' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'onsale' => 0,
                'stock_quantity' => $totalStock,
                'stock_status' => $parentStockStatus,
                'tax_status' => 'taxable',
            ]
        );

        // 5. Sincronizar relaciones de términos en wp_term_relationships
        DB::table('wp_term_relationships')->where('object_id', $product->ID)->delete();
        $relationInserts = [];
        foreach (array_unique($termTaxonomyIdsToSync) as $taxId) {
            $relationInserts[] = [
                'object_id' => $product->ID,
                'term_taxonomy_id' => $taxId,
                'term_order' => 0,
            ];
        }
        if (!empty($relationInserts)) {
            DB::table('wp_term_relationships')->insert($relationInserts);
        }

        return $product;
    }
}
