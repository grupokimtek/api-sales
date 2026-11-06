<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(): JsonResponse
    {
        $sales = Sale::with('details.product:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($sales->map(fn (Sale $sale) => [
            'id' => $sale->id,
            'total' => (float) $sale->total,
            'total_discount' => (float) $sale->total_discount,
            'created_at' => $sale->created_at?->toDateString(),
            'details' => $sale->details->map(fn (SaleDetail $detail) => [
                'product' => $detail->product?->name,
                'quantity' => $detail->quantity,
                'subtotal' => (float) $detail->subtotal,
                'discount_percent' => (float) $detail->discount_percent,
                'discount_amount' => (float) $detail->discount_amount,
                'subtotal_with_discount' => (float) bcsub($detail->subtotal, $detail->discount_amount, 2),
            ]),
        ]));
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = DB::transaction(function () use ($id): bool {
            if (Sale::whereKey($id)->delete() === 0) {
                return false;
            }

            $quantities = SaleDetail::where('sale_id', $id)
                ->selectRaw('product_id, SUM(quantity) AS units')
                ->groupBy('product_id')
                ->pluck('units', 'product_id');

            $products = Product::whereKey($quantities->keys()->all())->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== $quantities->count()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'No se puede cancelar la venta porque uno de sus productos ya no existe.',
                ], 409));
            }

            foreach ($products as $product) {
                $quantity = (int) $quantities[$product->id];
                if ($product->stock > 2147483647 - $quantity) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'La reposición supera el límite de stock del producto.',
                    ], 409));
                }
                $product->increment('stock', $quantity);
            }

            return true;
        }, 3);

        if (!$deleted) {
            return response()->json(['message' => 'Venta no encontrada.'], 404);
        }

        return response()->json(['message' => 'Venta eliminada correctamente']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'in:1', 'exists:users,id'],
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'max:4294967295', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.discount_percent' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'between:0,10'],
        ]);

        $sale = DB::transaction(function () use ($data): Sale {
            $products = Product::whereKey(array_map('intval', array_column($data['items'], 'product_id')))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $details = [];
            $total = '0.00';
            $totalDiscount = '0.00';

            foreach ($data['items'] as $index => $item) {
                $product = $products->get((int) $item['product_id']);
                $quantity = (int) $item['quantity'];

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'El producto no existe.',
                    ]);
                }

                if ($product->stock < $quantity) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Stock insuficiente.',
                        'product_id' => $product->id,
                        'available_stock' => $product->stock,
                    ], 409));
                }

                $subtotal = bcmul((string) $product->price, (string) $quantity, 2);
                if (bccomp(ltrim($subtotal, '-'), '99999999.99', 2) > 0) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => 'El subtotal supera el importe máximo permitido.',
                    ]);
                }
                $discountPercent = (string) ($item['discount_percent'] ?? '0');
                $discount = bcdiv(bcmul($subtotal, $discountPercent, 4), '100', 6);
                $discount = bcadd($discount, str_starts_with($discount, '-') ? '-0.005' : '0.005', 2);
                $totalDiscount = bcadd($totalDiscount, $discount, 2);
                $total = bcadd($total, bcsub($subtotal, $discount, 2), 2);
                $details[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discount,
                ];
            }

            if (bccomp(ltrim($total, '-'), '99999999.99', 2) > 0 || bccomp(ltrim($totalDiscount, '-'), '99999999.99', 2) > 0) {
                throw ValidationException::withMessages([
                    'items' => 'El total supera el importe máximo permitido.',
                ]);
            }

            $sale = Sale::create(['user_id' => 1, 'total' => $total, 'total_discount' => $totalDiscount]);
            $sale->details()->createMany($details);

            foreach ($details as $detail) {
                $products->get($detail['product_id'])->decrement('stock', $detail['quantity']);
            }

            return $sale;
        }, 3);

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => (float) $sale->total,
            'total_discount' => (float) $sale->total_discount,
        ], 201);
    }
}
