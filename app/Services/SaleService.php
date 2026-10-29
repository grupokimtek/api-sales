<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /** Usuario estático definido por la prueba. */
    private const STATIC_USER_ID = 1;

    /**
     * Registra una venta con sus detalles y descuenta el stock en una sola transacción.
     *
     * @param  array<int, array{product_id: int, quantity: int, discount_percent?: float|int|null}>  $items
     *
     * @throws ValidationException si algún producto no tiene stock suficiente
     */
    public function create(array $items): Sale
    {
        $items = $this->groupItems($items);

        return DB::transaction(function () use ($items) {
            // Bloquea los productos para evitar ventas concurrentes sobre el mismo stock.
            $products = Product::whereIn('id', array_keys($items))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->assertStock($items, $products);

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
    }

    /** Elimina la venta y devuelve el stock vendido a los productos. */
    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            foreach ($sale->details as $detail) {
                Product::where('id', $detail->product_id)->increment('stock', $detail->quantity);
            }

            $sale->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    private function assertStock(array $items, $products): void
    {
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
