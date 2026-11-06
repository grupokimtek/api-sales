<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::query()
            ->with(['details.product:id,name'])
            ->latest('created_at')
            ->get(['id', 'total', 'created_at'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'total' => (float) $sale->total,
                'created_at' => $sale->created_at,
                'details' => $sale->details->map(fn ($detail) => [
                    'product' => $detail->product?->name,
                    'quantity' => $detail->quantity,
                    'subtotal' => (float) $detail->subtotal,
                ])->values(),
            ])
            ->values();

        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sale = DB::transaction(function () use ($validated) {
            $items = collect($validated['items']);
            $productIds = $items->pluck('product_id')->unique()->sort()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $quantities = $items->groupBy('product_id')->map(
                fn ($productItems) => $productItems->sum('quantity')
            );

            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => ['Uno o más productos ya no están disponibles.'],
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para el producto {$product->name}."],
                    ]);
                }
            }

            $totalCents = 0;
            $details = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $priceCents = $this->toCents($product->price);
                $subtotalCents = $priceCents * $item['quantity'];
                $totalCents += $subtotalCents;

                $details[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $this->fromCents($priceCents),
                    'subtotal' => $this->fromCents($subtotalCents),
                ];
            }

            $sale = Sale::create([
                'user_id' => 1,
                'total' => $this->fromCents($totalCents),
            ]);

            $sale->details()->createMany($details);

            foreach ($quantities as $productId => $quantity) {
                $products->get($productId)->decrement('stock', $quantity);
            }

            return $sale;
        });

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => (float) $sale->total,
        ], 201);
    }

    public function destroy($id)
    {
        $sale = Sale::query()->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        $sale->delete();

        return response()->json(['message' => 'Venta eliminada correctamente']);
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', number_format((float) $amount, 2, '.', ''), 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
