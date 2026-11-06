<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\RegistrarVentaRequest;

class SaleController extends Controller
{
    // Para crear una venta
    public function crearVenta(RegistrarVentaRequest $request){
        $datos = $request->validated();

        // Usuario estatico
        $usuarioId = 1;

        $venta = DB::transaction(function () use ($datos, $usuarioId) {

            // Verificar que exista el usuario
            if (!User::where('id', $usuarioId)->exists()) {
                throw ValidationException::withMessages([
                    'usuario' => 'El usuario no existe.',
                ]);
            }

            $items = collect($datos['items']);

            // Obtener los identificadores de productos
            $productosIds = $items->pluck('product_id')->sort()->values();

            // Consultar productos y bloquear sus registros
            $productos = Product::whereIn('id', $productosIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $detalles = [];
            $total = 0;

            // Validar stock y calcular subtotales
            foreach ($items as $item) {

                $producto = $productos->get($item['product_id']);

                if (!$producto) {
                    throw ValidationException::withMessages([
                        'producto' => 'Uno de los productos no existe.',
                    ]);
                }

                if ($producto->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insuficiente para el producto {$producto->name}.",
                    ]);
                }

                $precio = (float) $producto->price;
                $subtotal = round($precio * $item['quantity'], 2);
                $total += $subtotal;

                $detalles[] = [
                    'product_id' => $producto->id,
                    'quantity' => $item['quantity'],
                    'price' => $precio,
                    'subtotal' => $subtotal,
                ];
            }

            // Registrar la venta
            $venta = Sale::create([
                'user_id' => $usuarioId,
                'total' => round($total, 2),
            ]);

            // Registrar los detalles de la venta
            $venta->detalles()->createMany($detalles);

            // Descontar el stock de los productos
            foreach ($items as $item) {
                $productos->get($item['product_id'])->decrement('stock', $item['quantity']);
            }

            return $venta;
        });

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $venta->id,
            'total' => (float) $venta->total,
        ], 201);
    }

    // Para listar las ventas
    public function listar(){
        $ventas = Sale::with('detalles.producto')
            ->orderByDesc('id')
            ->get()
            ->map(function (Sale $venta) {
                return [
                    'id' => $venta->id,
                    'total' => (float) $venta->total,
                    'created_at' => $venta->created_at->toDateString(),
                    'details' => $venta->detalles->map(function (SaleDetail $detalle) {
                        return [
                            'product' => $detalle->producto->name,
                            'quantity' => $detalle->quantity,
                            'subtotal' => (float) $detalle->subtotal,
                        ];
                    }),
                ];
            });

        return response()->json($ventas, 200);
    }

    // Para eliminar una venta
    public function eliminar(int $id){
        $venta = Sale::find($id);

        if (!$venta) {
            return response()->json([
                'message' => 'La venta no existe.',
            ], 404);
        }

        $venta->delete();

        return response()->json([
            'message' => 'Venta eliminada correctamente',
        ], 200);
    }
}
