<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'stock',
        'image'
    ];

    // Un producto puede tener muchos detalles de venta
    public function detallesVenta(): HasMany{
        return $this->hasMany(SaleDetail::class);
    }
}
