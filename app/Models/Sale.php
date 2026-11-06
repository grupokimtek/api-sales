<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'total'
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }
}
