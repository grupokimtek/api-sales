<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /** Usuario estático definido por la prueba. */
    private const STATIC_USER_ID = 1;

    public function index(): JsonResponse
    {
        $sales = Sale::with('details.product:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'total' => $sale->total,
                'total_discount' => $sale->total_discount,
                'created_at' => $sale->created_at->toDateString(),
                'details' => $sale->details->map(fn (SaleDetail $d) => [
                    'product' => $d->product?->name,
                    'quantity' => $d->quantity,
                    'subtotal' => $d->subtotal,
                    'discount_percent' => $d->discount_percent,
                    'discount_amount' => $d->discount_amount,
                    'subtotal_with_discount' => $d->subtotal_with_discount,
                ])->values(),
            ]);

        return response()->json($sales, 200);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $items = $this->groupItems($request->validated('items'));

        $sale = DB::transaction(function () use ($items) {
            // Bloquea los productos para evitar ventas concurrentes sobre el mismo stock.
            $products = Product::whereIn('id', array_keys($items))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($items as $productId => $item) {
                $product = $products[$productId];
                if ($item['quantity'] > $product->stock) {
                    $errors["items.stock.$productId"] = "Stock insuficiente para \"{$product->name}\": disponible {$product->stock}, solicitado {$item['quantity']}.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $sale = Sale::create([
                'user_id' => self::STATIC_USER_ID,
                'total' => 0,
                'total_discount' => 0,
            ]);

            $total = 0;
            $totalDiscount = 0;

            foreach ($items as $productId => $item) {
                $product = $products[$productId];

                $subtotal = round($product->price * $item['quantity'], 2);
                $discountAmount = round($subtotal * ($item['discount_percent'] / 100), 2);
                $subtotalWithDiscount = round($subtotal - $discountAmount, 2);

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                    'discount_percent' => $item['discount_percent'],
                    'discount_amount' => $discountAmount,
                    'subtotal_with_discount' => $subtotalWithDiscount,
                ]);

                $product->decrement('stock', $item['quantity']);

                $total += $subtotalWithDiscount;
                $totalDiscount += $discountAmount;
            }

            $sale->update([
                'total' => round($total, 2),
                'total_discount' => round($totalDiscount, 2),
            ]);

            return $sale;
        });

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => $sale->total,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        DB::transaction(function () use ($sale) {
            // Al eliminar la venta se devuelve el stock vendido.
            foreach ($sale->details as $detail) {
                Product::where('id', $detail->product_id)->increment('stock', $detail->quantity);
            }

            $sale->delete();
        });

        return response()->json(['message' => 'Venta eliminada correctamente'], 200);
    }

    /**
     * Une los ítems repetidos del mismo producto para validar el stock correctamente.
     * Si hay descuentos distintos, se conserva el menor.
     */
    private function groupItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $discount = (float) ($item['discount_percent'] ?? 0);

            if (isset($grouped[$id])) {
                $grouped[$id]['quantity'] += (int) $item['quantity'];
                $grouped[$id]['discount_percent'] = min($grouped[$id]['discount_percent'], $discount);
            } else {
                $grouped[$id] = ['quantity' => (int) $item['quantity'], 'discount_percent' => $discount];
            }
        }

        return $grouped;
    }
}
