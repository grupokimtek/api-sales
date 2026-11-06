<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = DB::connection();
        $this->assertSame('sqlite', $connection->getDriverName());
        $this->assertSame(':memory:', $connection->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['id' => 1]);
    }

    public function test_products_endpoint_includes_catalog_fields(): void
    {
        $product = $this->product();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'price', 'stock', 'image', 'created_at', 'updated_at']])
            ->assertJsonPath('0.id', $product->id)
            ->assertJsonPath('0.stock', 5);
    }

    public function test_it_uses_database_prices_rounds_discounts_and_lists_details(): void
    {
        $first = $this->product('12.35');
        $second = $this->product('150.53');

        $response = $this->postJson('/api/sales', ['user_id' => 1, 'items' => [
            ['product_id' => $first->id, 'quantity' => 1, 'discount_percent' => 10, 'price' => 1],
            ['product_id' => $second->id, 'quantity' => 2, 'discount_percent' => 5.25],
        ]])->assertCreated()->assertJsonPath('total', 296.36)->assertJsonPath('total_discount', 17.05);

        $id = $response->json('sale_id');
        $this->assertDatabaseHas('sales', ['id' => $id, 'user_id' => 1, 'total' => 296.36]);
        $this->assertDatabaseHas('sale_details', ['sale_id' => $id, 'product_id' => $first->id, 'price' => 12.35, 'discount_amount' => 1.24]);
        $this->assertSame(4, $first->fresh()->stock);
        $this->assertSame(3, $second->fresh()->stock);

        $this->getJson('/api/sales')->assertOk()
            ->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.created_at', Sale::findOrFail($id)->created_at->toDateString())
            ->assertJsonPath('0.total_discount', 17.05)
            ->assertJsonFragment([
                'product' => $first->name,
                'quantity' => 1,
                'subtotal' => 12.35,
                'discount_percent' => 10,
                'discount_amount' => 1.24,
                'subtotal_with_discount' => 11.11,
            ]);
    }

    public function test_it_defaults_to_zero_discount_and_supports_older_sales(): void
    {
        $product = $this->product();
        $older = Sale::create(['user_id' => 1, 'total' => '12.34']);
        $older->details()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => '12.34', 'subtotal' => '12.34']);

        $newer = $this->postJson('/api/sales', ['user_id' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertCreated()->assertJsonPath('total_discount', 0)->json('sale_id');

        $this->getJson('/api/sales')->assertOk()
            ->assertJsonPath('0.id', $newer)
            ->assertJsonPath('1.id', $older->id)
            ->assertJsonPath('1.total_discount', 0)
            ->assertJsonPath('1.details.0.subtotal_with_discount', 12.34);
    }

    #[DataProvider('invalidDiscounts')]
    public function test_it_rejects_invalid_discounts(float $discount): void
    {
        $product = $this->product();

        $this->postJson('/api/sales', ['user_id' => 1, 'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'discount_percent' => $discount],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_percent');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public static function invalidDiscounts(): array
    {
        return [[-0.01], [10.01], [0.001]];
    }

    public function test_it_rejects_duplicate_missing_and_invalid_items(): void
    {
        $product = $this->product();
        $item = ['product_id' => $product->id, 'quantity' => 1];

        $this->postJson('/api/sales', ['user_id' => 1, 'items' => [$item, $item]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
        $this->postJson('/api/sales', ['user_id' => 1, 'items' => [['product_id' => $product->id + 1, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
        $this->postJson('/api/sales', ['user_id' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 0]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_insufficient_stock_does_not_leave_partial_changes(): void
    {
        $available = $this->product();
        $unavailable = $this->product(stock: 0);

        $this->postJson('/api/sales', ['user_id' => 1, 'items' => [
            ['product_id' => $available->id, 'quantity' => 2],
            ['product_id' => $unavailable->id, 'quantity' => 1],
        ]])->assertConflict()->assertJsonPath('product_id', $unavailable->id)->assertJsonPath('available_stock', 0);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertSame(5, $available->fresh()->stock);
    }

    public function test_canceling_a_discounted_sale_restores_stock_only_once(): void
    {
        $product = $this->product();
        $id = $this->postJson('/api/sales', ['user_id' => 1, 'items' => [
            ['product_id' => $product->id, 'quantity' => 2, 'discount_percent' => 10],
        ]])->assertCreated()->json('sale_id');

        $this->deleteJson("/api/sales/$id")->assertOk();
        $this->assertSoftDeleted('sales', ['id' => $id]);
        $this->assertDatabaseHas('sale_details', ['sale_id' => $id, 'discount_percent' => 10]);
        $this->getJson('/api/sales')->assertOk()->assertExactJson([]);
        $this->deleteJson("/api/sales/$id")->assertNotFound();
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_failed_stock_replenishment_leaves_the_sale_active(): void
    {
        $product = $this->product();
        $sale = Sale::create(['user_id' => 1, 'total' => '24.68']);
        $sale->details()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => '12.34', 'subtotal' => '12.34']);
        $sale->details()->create(['product_id' => $product->id + 1, 'quantity' => 1, 'price' => '12.34', 'subtotal' => '12.34']);

        $this->deleteJson("/api/sales/{$sale->id}")->assertConflict();
        $this->assertNotNull($sale->fresh());
        $this->assertSame(5, $product->fresh()->stock);
    }

    private function product(string $price = '12.34', int $stock = 5): Product
    {
        return Product::create(['name' => 'Producto ' . $price, 'price' => $price, 'stock' => $stock]);
    }
}
