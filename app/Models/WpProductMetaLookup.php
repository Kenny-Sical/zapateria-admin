<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpProductMetaLookup extends Model
{
    protected $table = 'wp_wc_product_meta_lookup';
    protected $primaryKey = 'product_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'sku',
        'virtual',
        'downloadable',
        'min_price',
        'max_price',
        'onsale',
        'stock_quantity',
        'stock_status',
        'rating_count',
        'average_rating',
        'total_sales',
        'tax_status',
        'tax_class',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WpPost::class, 'product_id', 'ID');
    }
}
