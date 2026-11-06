<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
        'discount_percent',
        'discount_amount',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
