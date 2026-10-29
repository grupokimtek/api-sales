<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'total',
        'total_discount'
    ];

    protected $casts = [
        'total' => 'float',
        'total_discount' => 'float',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }
}
