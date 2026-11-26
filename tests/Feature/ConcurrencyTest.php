<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_authenticated_requests_cannot_oversell_locked_stock(): void
    {
        $this->runConcurrentSales(false);
    }

    public function test_simultaneous_retries_create_one_sale_and_deduct_stock_once(): void
    {
        $this->runConcurrentSales(true);
    }

    public function test_a_reference_submitted_by_two_staff_members_only_creates_one_sale(): void
    {
        $this->runConcurrentSales(true, true);
    }

    public function test_a_sale_and_restock_do_not_overwrite_each_others_stock_changes(): void
    {
        $this->runConcurrentSales(false, false, true);
    }

    public function test_two_carts_lock_products_in_a_consistent_order_and_cannot_oversell(): void
    {
        $this->runConcurrentSales(false, false, false, true);
    }

    public function test_simultaneous_cart_retries_only_save_one_checkout_and_its_stock_movements(): void
    {
        $this->runConcurrentSales(true, false, false, true);
    }

    private function runConcurrentSales(bool $duplicate, bool $differentStaff = false, bool $restock = false, bool $cart = false): void
    {
        $product = Product::factory()->create(['stock_quantity' => $differentStaff ? 9 : 5]);
        $extraProduct = $cart ? Product::factory()->create() : null;
        $staff = User::factory()->count(2)->create($restock ? ['role' => 'admin'] : []);
        $submissionId = (string) Str::uuid();
        if ($duplicate && ! $differentStaff) {
            $staff[1] = $staff[0];
        }
        $barriers = [tempnam(sys_get_temp_dir(), 'pos-a-'), tempnam(sys_get_temp_dir(), 'pos-b-')];
        $connection = config('database.connections.mysql');
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => $connection['database'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $processes = [];

        DB::beginTransaction();
        Product::query()->lockForUpdate()->findOrFail($product->id);
        try {
            foreach ($staff as $index => $user) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/sale-worker.php'), (string) $product->id, (string) $user->id, $barriers[$index], $duplicate ? $submissionId : (string) Str::uuid(), $cart ? 'cart' : ($restock && $index === 1 ? 'restock' : 'sale'), (string) ($extraProduct?->id ?? 0), (string) $index], base_path(), $environment);
                $process->setTimeout(20)->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 12;
            $bothWaiting = false;
            do {
                $ids = array_map(fn ($file) => (int) file_get_contents($file), $barriers);
                if (! in_array(0, $ids, true)) {
                    $waiting = DB::select('SELECT ID, INFO FROM information_schema.PROCESSLIST WHERE ID IN (?, ?)', $ids);
                    $bothWaiting = count(array_filter($waiting, fn ($row) => str_contains(strtolower($row->INFO ?? ''), 'for update') && (str_contains($row->INFO ?? '', 'products') || ($duplicate && str_contains($row->INFO ?? '', 'users'))))) === 2;
                }
                if (! $bothWaiting) {
                    usleep(25000);
                }
            } while (! $bothWaiting && microtime(true) < $deadline);

            $this->assertTrue($bothWaiting, 'Both independent requests must overlap while waiting for InnoDB row locks. '.implode(' ', array_map(fn ($process) => $process->getErrorOutput().$process->getOutput(), $processes)));
            DB::commit();

            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $result = json_decode($process->getOutput(), true);
                $this->assertIsArray($result, 'Worker output must be JSON: '.$process->getOutput().' '.$process->getErrorOutput());
                $this->assertSame(302, $result['status']);
                $results[] = $result['result'];
            }
            sort($results);
            $this->assertSame(($duplicate && ! $differentStaff) || $restock ? ['sold', 'sold'] : ['rejected', 'sold'], $results);
            $this->assertSame($differentStaff || $restock ? 5 : 1, $product->fresh()->stock_quantity);
            $this->assertSame(1, Sale::count());
            $this->assertSame($cart ? 5 : 4, Sale::sole()->quantity);
            $movements = DB::table('stock_movements')->orderBy('id')->get();
            $this->assertCount($restock || $cart ? 2 : 1, $movements);
            if ($cart) {
                $this->assertSame(9, $extraProduct->fresh()->stock_quantity);
                $this->assertSame(2, DB::table('sale_items')->count());
            }
            if ($restock) {
                $this->assertSame(5, $movements->first()->stock_before);
                $this->assertSame($movements->first()->stock_after, $movements->last()->stock_before);
                $this->assertSame(5, $movements->last()->stock_after);
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ($barriers as $file) {
                unlink($file);
            }
            if ($cart) {
                $saleIds = DB::table('sales')->pluck('id');
                DB::table('stock_movements')->whereIn('sale_id', $saleIds)->delete();
                DB::table('sale_items')->whereIn('sale_id', $saleIds)->delete();
                DB::table('sales')->whereIn('id', $saleIds)->delete();
            }
        }
    }
}
