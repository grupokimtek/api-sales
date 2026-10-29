<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Sale */
class SaleResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'total_discount' => $this->total_discount,
            'created_at' => $this->created_at->toDateString(),
            'details' => SaleDetailResource::collection($this->details),
        ];
    }
}
