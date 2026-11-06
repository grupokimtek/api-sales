<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_returns_only_the_required_fields(): void
    {
        Product::create([
            'name' => 'Monitor',
            'price' => 100.50,
            'stock' => 5,
            'image' => 'monitor.jpg',
        ]);

        $response = $this->getJson('/api/products')->assertOk();

        $this->assertSame(['id', 'name', 'price', 'stock'], array_keys($response->json()[0]));
        $response->assertJsonPath('0.name', 'Monitor');
    }

    public function test_store_uses_database_price_and_decrements_stock(): void
    {
        $product = Product::create([
            'name' => 'Teclado',
            'price' => 20.25,
            'stock' => 8,
        ]);

        $response = $this->postJson('/api/sales', [
            'user_id' => 99,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'price' => 0.01,
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('total', 40.5);

        $sale = Sale::query()->firstOrFail();
        $detail = SaleDetail::query()->firstOrFail();

        $this->assertSame(1, $sale->user_id);
        $this->assertEquals(20.25, (float) $detail->price);
        $this->assertEquals(40.50, (float) $detail->subtotal);
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_the_entire_sale(): void
    {
        $availableProduct = Product::create([
            'name' => 'Mouse',
            'price' => 10,
            'stock' => 5,
        ]);
        $limitedProduct = Product::create([
            'name' => 'Webcam',
            'price' => 50,
            'stock' => 1,
        ]);

        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $availableProduct->id, 'quantity' => 2],
                ['product_id' => $limitedProduct->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, SaleDetail::query()->count());
        $this->assertSame(5, $availableProduct->fresh()->stock);
        $this->assertSame(1, $limitedProduct->fresh()->stock);
    }

    public function test_sales_endpoint_includes_product_details(): void
    {
        $product = Product::create([
            'name' => 'Audífonos',
            'price' => 75,
            'stock' => 4,
        ]);
        $sale = Sale::create(['user_id' => 1, 'total' => 150]);
        $sale->details()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 75,
            'subtotal' => 150,
        ]);

        $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonPath('0.id', $sale->id)
            ->assertJsonPath('0.total', 150)
            ->assertJsonPath('0.details.0.product', 'Audífonos')
            ->assertJsonPath('0.details.0.quantity', 2)
            ->assertJsonPath('0.details.0.subtotal', 150);
    }

    public function test_destroy_soft_deletes_a_sale_and_returns_404_when_missing(): void
    {
        $sale = Sale::create(['user_id' => 1, 'total' => 0]);

        $this->deleteJson('/api/sales/' . $sale->id)
            ->assertOk()
            ->assertJsonPath('message', 'Venta eliminada correctamente');

        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        $this->deleteJson('/api/sales/999999')->assertNotFound();
    }
}