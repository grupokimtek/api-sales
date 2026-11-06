<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'total'
    ];

    // Una venta pertenece a un usuario
    public function usuario(): BelongsTo{
        return $this->belongsTo(User::class, 'user_id');
    }

    // Una venta tiene muchos detalles
    public function detalles(): HasMany{
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }
}
