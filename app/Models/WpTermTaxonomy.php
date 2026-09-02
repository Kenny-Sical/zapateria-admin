<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WpTermTaxonomy extends Model
{
    protected $table = 'wp_term_taxonomy';
    protected $primaryKey = 'term_taxonomy_id';
    public $timestamps = false;

    protected $fillable = [
        'term_id',
        'taxonomy',
        'description',
        'parent',
        'count',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(WpTerm::class, 'term_id', 'term_id');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            WpPost::class,
            'wp_term_relationships',
            'term_taxonomy_id',
            'object_id'
        );
    }
}
