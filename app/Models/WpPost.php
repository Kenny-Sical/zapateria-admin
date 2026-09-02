<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WpPost extends Model
{
    protected $table = 'wp_posts';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'post_author',
        'post_date',
        'post_date_gmt',
        'post_content',
        'post_title',
        'post_excerpt',
        'post_status',
        'comment_status',
        'ping_status',
        'post_password',
        'post_name',
        'to_ping',
        'pinged',
        'post_modified',
        'post_modified_gmt',
        'post_content_filtered',
        'post_parent',
        'guid',
        'menu_order',
        'post_type',
        'post_mime_type',
        'comment_count',
    ];

    /**
     * Relación con los metadatos de WordPress.
     */
    public function meta(): HasMany
    {
        return $this->hasMany(WpPostMeta::class, 'post_id', 'ID');
    }

    /**
     * Relación con las variaciones hijas (para productos variables).
     */
    public function variations(): HasMany
    {
        return $this->hasMany(WpPost::class, 'post_parent', 'ID')
            ->where('post_type', 'product_variation');
    }

    /**
     * Relación con el producto padre si es una variación.
     */
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(WpPost::class, 'post_parent', 'ID');
    }

    /**
     * Relación con taxonomías (categorías, tipos de producto, etc.).
     */
    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(
            WpTermTaxonomy::class,
            'wp_term_relationships',
            'object_id',
            'term_taxonomy_id'
        );
    }

    /**
     * Relación con la tabla de lookup de WooCommerce.
     */
    public function lookup(): HasOne
    {
        return $this->hasOne(WpProductMetaLookup::class, 'product_id', 'ID');
    }

    /**
     * Obtiene el valor de un metadato por su clave.
     */
    public function getMeta(string $key, $default = null)
    {
        if ($this->relationLoaded('meta')) {
            $record = $this->meta->firstWhere('meta_key', $key);
            return $record ? $record->meta_value : $default;
        }

        $record = $this->meta()->where('meta_key', $key)->first();
        return $record ? $record->meta_value : $default;
    }

    /**
     * Asigna o actualiza un metadato.
     */
    public function setMeta(string $key, $value)
    {
        return WpPostMeta::updateOrCreate(
            ['post_id' => $this->ID, 'meta_key' => $key],
            ['meta_value' => $value]
        );
    }

    /**
     * Accesor para el SKU.
     */
    public function getSkuAttribute(): ?string
    {
        return $this->getMeta('_sku', '');
    }

    /**
     * Accesor para la imagen destacada.
     */
    public function getImageAttribute(): ?string
    {
        return $this->getMeta('_thumbnail_path', $this->getMeta('_product_image_url', ''));
    }
}
