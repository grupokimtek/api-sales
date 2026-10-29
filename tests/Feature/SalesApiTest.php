<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1]);
        Product::create(['name' => 'Laptop', 'price' => 7500, 'stock' => 10]);
        Product::create(['name' => 'Mouse', 'price' => 150.5, 'stock' => 5]);
    }

    public function test_lists_products(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Laptop', 'price' => 7500, 'stock' => 10]);
    }

    public function test_creates_sale_calculates_totals_and_discounts_stock(): void
    {
        $this->postJson('/api/sales', [
            'user_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ])->assertCreated()->assertJson([
            'message' => 'Venta creada correctamente',
            'sale_id' => 1,
            'total' => 15150.5,
        ]);

        $this->assertSame(8, Product::find(1)->stock);
        $this->assertSame(4, Product::find(2)->stock);
    }

    public function test_applies_discount(): void
    {
        $this->postJson('/api/sales', [
            'items' => [['product_id' => 1, 'quantity' => 2, 'discount_percent' => 10]],
        ])->assertCreated()->assertJsonFragment(['total' => 13500]);

        $this->getJson('/api/sales')->assertOk()->assertJsonFragment([
            'total_discount' => 1500,
            'discount_amount' => 1500,
            'subtotal_with_discount' => 13500,
        ]);
    }

    public function test_insufficient_stock_does_not_register_anything(): void
    {
        $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => 2, 'quantity' => 1],
                ['product_id' => 1, 'quantity' => 11],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertSame(5, Product::find(2)->stock);
    }

    public function test_validations(): void
    {
        $this->postJson('/api/sales', ['items' => []])->assertStatus(422);
        $this->postJson('/api/sales', ['items' => [['product_id' => 99, 'quantity' => 1]]])->assertStatus(422);
        $this->postJson('/api/sales', ['items' => [['product_id' => 1, 'quantity' => 0]]])->assertStatus(422);
        $this->postJson('/api/sales', ['items' => [['product_id' => 1, 'quantity' => 1, 'discount_percent' => 11]]])->assertStatus(422);
    }

    public function test_deletes_sale_and_restores_stock(): void
    {
        $this->postJson('/api/sales', ['items' => [['product_id' => 1, 'quantity' => 3]]])->assertCreated();

        $this->deleteJson('/api/sales/1')->assertOk()->assertJson(['message' => 'Venta eliminada correctamente']);
        $this->assertSame(10, Product::find(1)->stock);
        $this->getJson('/api/sales')->assertOk()->assertJsonCount(0);
    }

    public function test_delete_missing_sale_returns_404(): void
    {
        $this->deleteJson('/api/sales/999')->assertNotFound();
    }
}
