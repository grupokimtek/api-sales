<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'in:1',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'El usuario es obligatorio.',
            'user_id.in' => 'El usuario debe ser el número 1.',

            'items.required' => 'Debe agregar productos a la venta.',
            'items.min' => 'La venta debe contener al menos un producto.',

            'items.*.product_id.required' => 'El producto es obligatorio.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',

            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}