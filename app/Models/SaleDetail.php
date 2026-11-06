<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'subtotal'
    ];

    // Un detalle pertenece a una venta
    public function venta(): BelongsTo{
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    // Un detalle pertenece a un producto
    public function producto(): BelongsTo{
        return $this->belongsTo(Product::class, 'product_id');
    }
}
