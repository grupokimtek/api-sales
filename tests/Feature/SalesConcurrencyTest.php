<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SalesConcurrencyTest extends TestCase
{
    public function test_concurrent_purchases_and_cancellations_change_stock_once(): void
    {
        $database = getenv('SALES_TEST_MYSQL_DATABASE');
        if ($database === false || $database === '') {
            $this->markTestSkipped('Define SALES_TEST_MYSQL_DATABASE con una base MySQL de pruebas aislada.');
        }

        $this->assertMatchesRegularExpression('/^sales_test_[a-z0-9_]+$/', $database);
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.url' => null,
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        if (!User::whereKey(1)->exists()) {
            User::factory()->create(['id' => 1]);
        }

        $product = Product::create(['name' => 'Última unidad', 'price' => '12.34', 'stock' => 1]);

        try {
            $this->assertSame([201, 409], $this->race(
                'POST', '/api/sales', 'products', $product->id,
                ['user_id' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]
            ));
            $this->assertSame(0, $product->fresh()->stock);
            $details = SaleDetail::where('product_id', $product->id)->get();
            $this->assertCount(1, $details);
            $saleId = $details->first()->sale_id;

            $this->assertSame([200, 404], $this->race('DELETE', "/api/sales/$saleId", 'sales', $saleId));
            $this->assertSame(1, $product->fresh()->stock);
            $this->assertTrue(Sale::withTrashed()->findOrFail($saleId)->trashed());
        } finally {
            $saleIds = SaleDetail::where('product_id', $product->id)->pluck('sale_id');
            SaleDetail::where('product_id', $product->id)->delete();
            Sale::withTrashed()->whereKey($saleIds->all())->forceDelete();
            $product->delete();
        }
    }

    private function race(string $method, string $uri, string $table, int $id, array $body = []): array
    {
        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $kernel->bootstrap();
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => getenv('SALES_TEST_MYSQL_DATABASE'),
                'database.connections.mysql.url' => null,
            ]);
            Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($query) use ($argv) {
                static $ready = false;
                if (!$ready && str_contains($query, '`' . $argv[3] . '`')) {
                    $ready = true;
                    echo "ready\n";
                }
            });
            $request = Illuminate\Http\Request::create($argv[2], $argv[1], [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ], $argv[4]);
            $response = $kernel->handle($request);
            echo 'status=', $response->getStatusCode(), "\n";
            PHP;

        $processes = [];
        DB::beginTransaction();
        try {
            DB::table($table)->where('id', $id)->lockForUpdate()->first();
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([PHP_BINARY, '-r', $worker, $method, $uri, $table, json_encode($body)], base_path(), timeout: 15);
                $processes[] = $process;
                $process->start();
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());
            }
            DB::commit();

            $statuses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $this->assertMatchesRegularExpression('/status=\d{3}/', $process->getOutput());
                preg_match('/status=(\d{3})/', $process->getOutput(), $matches);
                $statuses[] = (int) $matches[1];
            }
            sort($statuses);

            return $statuses;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
        }
    }
}
