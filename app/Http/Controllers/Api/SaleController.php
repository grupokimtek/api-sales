<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Listar todas las ventas.
     */
    public function index(): JsonResponse
    {
        $sales = Sale::with('details.product:id,name')
            ->orderByDesc('created_at')
            ->get();

        $result = $sales->map(function ($sale) {

            return [
                'id' => $sale->id,
                'total' => (float) $sale->total,
                'created_at' => $sale->created_at->format('Y-m-d'),

                'details' => $sale->details->map(function ($detail) {

                    return [
                        'product' => $detail->product->name,
                        'quantity' => $detail->quantity,
                        'subtotal' => (float) $detail->subtotal,
                    ];

                })->values(),
            ];

        });

        return response()->json($result, 200);
    }

    /**
     * Registrar una venta.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($validated) {

            // Crear la venta
            $sale = Sale::create([
                'user_id' => $validated['user_id'],
                'total' => 0,
            ]);

            $total = 0;

            // Recorrer los productos solicitados
            foreach ($validated['items'] as $index => $item) {

                // Bloquear el producto durante la transacción
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => [
                            'El producto ya no existe.',
                        ],
                    ]);
                }

                $quantity = (int) $item['quantity'];

                // Verificar stock disponible
                if ($product->stock < $quantity) {

                    throw ValidationException::withMessages([
                        "items.$index.quantity" => [
                            "Stock insuficiente para {$product->name}. Disponible: {$product->stock}.",
                        ],
                    ]);

                }

                // Obtener el precio desde la base de datos
                $price = (float) $product->price;

                // Calcular subtotal
                $subtotal = round($price * $quantity, 2);

                // Registrar detalle de venta
                $sale->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                // Descontar stock
                $product->decrement('stock', $quantity);

                // Acumular total
                $total = round($total + $subtotal, 2);
            }

            // Actualizar total de la venta
            $sale->update([
                'total' => $total,
            ]);

            return $sale;
        });

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => (float) $sale->total,
        ], 201);
    }

    /**
     * Eliminar una venta y restaurar el stock.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::transaction(function () use ($id) {

            $sale = Sale::with('details')
                ->lockForUpdate()
                ->find($id);

            if (!$sale) {
                return false;
            }

            // Restaurar el stock de cada producto
            foreach ($sale->details->sortBy('product_id') as $detail) {

                $product = Product::where('id', $detail->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment(
                        'stock',
                        $detail->quantity
                    );
                }
            }

            // Eliminar venta y sus detalles
            $sale->delete();

            return true;
        });

        if (!$deleted) {
            return response()->json([
                'message' => 'Venta no encontrada',
            ], 404);
        }

        return response()->json([
            'message' => 'Venta eliminada correctamente',
        ], 200);
    }
}