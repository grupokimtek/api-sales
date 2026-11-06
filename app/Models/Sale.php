<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'user_id',
        'total',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }
}