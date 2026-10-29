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
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,10'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'La venta debe contener al menos un producto.',
            'items.min' => 'La venta debe contener al menos un producto.',
            'items.*.product_id.required' => 'Cada ítem debe indicar un producto.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.quantity.required' => 'Cada ítem debe indicar una cantidad.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor que cero.',
            'items.*.discount_percent.between' => 'El descuento debe estar entre 0 y 10.',
        ];
    }
}
