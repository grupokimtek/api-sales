<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarVentaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
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
            'items.required' => 'Debe enviar al menos un producto.',
            'items.array' => 'Los productos deben enviarse en formato de lista.',
            'items.min' => 'La venta debe contener al menos un producto.',

            'items.*.product_id.required' => 'El producto es obligatorio.',
            'items.*.product_id.integer' => 'El identificador del producto debe ser numérico.',
            'items.*.product_id.distinct' => 'No puede repetir productos en la misma venta.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe.',

            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad debe ser como mínimo 1.',
        ];
    }
}
