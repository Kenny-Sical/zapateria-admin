<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WpTerm extends Model
{
    protected $table = 'wp_terms';
    protected $primaryKey = 'term_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'term_group',
    ];

    /**
     * Accesor para id (compatibilidad con term_id).
     */
    public function getIdAttribute()
    {
        return $this->attributes['term_id'] ?? null;
    }

    /**
     * Accesor para size (compatibilidad con vistas de tallas).
     */
    public function getSizeAttribute()
    {
        return $this->attributes['name'] ?? null;
    }

    /**
     * Accesor para created_at.
     */
    public function getCreatedAtAttribute()
    {
        return null;
    }

    /**
     * Relación con la tabla wp_term_taxonomy.
     */
    public function taxonomy(): HasOne
    {
        return $this->hasOne(WpTermTaxonomy::class, 'term_id', 'term_id');
    }

    /**
     * Scope para obtener categorías de productos.
     */
    public function scopeCategories($query)
    {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('taxonomy', 'product_cat');
        });
    }

    /**
     * Scope para obtener términos de color (pa_color).
     */
    public function scopeColors($query)
    {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('taxonomy', 'pa_color');
        });
    }

    /**
     * Scope para obtener términos de talla (pa_talla).
     */
    public function scopeSizes($query)
    {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('taxonomy', 'pa_talla');
        });
    }
}
