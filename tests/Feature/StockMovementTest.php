<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\RecordStockMovement;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_restock_removal_and_count_correction_record_balances_reasons_and_staff(): void
    {
        $admin = User::factory()->admin()->create(['full_name' => 'Original administrator']);
        $this->actingAs($admin)->post('/products', ['name' => 'Tracked product', 'price' => '10.00', 'stock_quantity' => 10])->assertSessionHasNoErrors();
        $product = Product::sole();
        $url = route('products.stock.store', $product);
        $this->post($url, $this->adjustment('restock', 5, 'Supplier delivery'))->assertSessionHasNoErrors()->assertRedirect(route('products.stock.index', $product));
        $this->post($url, $this->adjustment('removal', 3, '<Damaged goods>'))->assertSessionHasNoErrors();
        $this->patch(route('products.update', $product), ['name' => 'Tracked product', 'price' => '10.00', 'stock_quantity' => 11, 'original_stock' => 12, 'stock_reason' => 'Physical count'])->assertSessionHasNoErrors();
        $movements = DB::table('stock_movements')->orderBy('id')->get();
        $this->assertSame(['opening', 'restock', 'removal', 'correction'], $movements->pluck('type')->all());
        $this->assertSame([10, 5, -3, -1], $movements->pluck('quantity_change')->all());
        $this->assertSame([0, 10, 15, 12], $movements->pluck('stock_before')->all());
        $this->assertSame([10, 15, 12, 11], $movements->pluck('stock_after')->all());
        $this->assertSame(11, $product->fresh()->stock_quantity);
        $this->assertSame($admin->id, $movements->last()->performed_by);
        $this->assertSame('Physical count', $movements->last()->reason);
        $this->assertNotNull($movements->last()->created_at);
        $admin->update(['full_name' => 'Renamed administrator']);
        $this->get(route('products.stock.index', $product))->assertOk()->assertSee('Original administrator')->assertSee('<Damaged goods>')->assertSee('Count correction');
    }

    public function test_cashiers_cannot_access_stock_management_and_removed_products_cannot_be_adjusted(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create())->get(route('products.stock.index', $product))->assertForbidden();
        $this->post(route('products.stock.store', $product), $this->adjustment())->assertForbidden();
        $this->get(route('products.show', $product))->assertDontSee(route('products.stock.index', $product));
        $this->actingAs(User::factory()->admin()->create());
        $product->delete();
        $this->get(route('products.stock.index', $product))->assertOk()->assertSee('Its stock history is preserved.')->assertDontSee('Save stock change');
        $this->post(route('products.stock.store', $product), $this->adjustment())->assertNotFound();
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_invalid_adjustments_and_missing_correction_reasons_do_not_write_anything(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([['quantity' => 0], ['quantity' => -1], ['quantity' => '1.5'], ['quantity' => 2147483648], ['type' => 'sale'], ['reason' => '   '], ['reason' => str_repeat('x', 501)], ['submission_id' => 'invalid']] as $invalid) {
            $field = array_key_first($invalid);
            $this->post(route('products.stock.store', $product), array_replace($this->adjustment(), $invalid))->assertSessionHasErrors($field);
        }
        $this->post(route('products.stock.store', $product), $this->adjustment('removal', 11))->assertSessionHasErrors('quantity');
        $this->patch(route('products.update', $product), ['name' => 'Changed', 'price' => '1.00', 'stock_quantity' => 11, 'original_stock' => 10])->assertSessionHasErrors('stock_reason');
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('125.50', $product->fresh()->price);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_overflow_is_rejected_and_stock_can_be_removed_down_to_zero(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2147483647]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('products.stock.store', $product), $this->adjustment())->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_movements', 0);
        $this->post(route('products.stock.store', $product), $this->adjustment('removal', 2147483647))->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['quantity_change' => -2147483647, 'stock_before' => 2147483647, 'stock_after' => 0]);
    }

    public function test_repeated_adjustments_are_applied_once_and_conflicting_reuse_is_rejected(): void
    {
        $product = Product::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $data = $this->adjustment('restock', 5);
        $this->post(route('products.stock.store', $product), $data)->assertSessionHasNoErrors();
        $this->post(route('products.stock.store', $product), $data)->assertSessionHasNoErrors();
        foreach ([['quantity' => 6], ['type' => 'removal'], ['reason' => 'Different reason']] as $change) {
            $this->post(route('products.stock.store', $product), array_replace($data, $change))->assertSessionHasErrors('submission_id');
        }
        $this->actingAs(User::factory()->admin()->create())->post(route('products.stock.store', $product), $data)->assertSessionHasErrors('submission_id');
        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_sales_record_one_movement_even_when_checkout_is_retried(): void
    {
        $product = Product::factory()->create();
        $cashier = User::factory()->create();
        $data = ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3];
        $this->actingAs($cashier)->post('/sales', $data)->assertSessionHasNoErrors();
        $this->post('/sales', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', ['sale_id' => DB::table('sales')->sole()->id, 'performed_by' => $cashier->id, 'type' => 'sale', 'quantity_change' => -3, 'stock_before' => 10, 'stock_after' => 7]);
    }

    public function test_a_failed_history_write_rolls_back_the_stock_change_and_sale(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->mock(RecordStockMovement::class)->shouldReceive('handle')->times(3)->andThrow(new \RuntimeException('History write failed.'));
        $this->withoutExceptionHandling();
        $requests = [
            fn () => $this->post(route('products.stock.store', $product), $this->adjustment()),
            fn () => $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2]),
            fn () => $this->patch(route('products.update', $product), ['name' => 'Updated', 'price' => '1.00', 'stock_quantity' => 20, 'original_stock' => 10, 'stock_reason' => 'Correction']),
        ];
        foreach ($requests as $request) {
            try {
                $request();
                $this->fail('Expected the history write to fail.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('History write failed.', $exception->getMessage());
            }
            $this->assertSame(10, $product->fresh()->stock_quantity);
            $this->assertSame('125.50', $product->fresh()->price);
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_ordinary_product_edits_preserve_live_stock_without_an_adjustment_record(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 7]);
        $this->actingAs(User::factory()->admin()->create())->patch(route('products.update', $product), ['name' => 'Renamed', 'price' => '2.00', 'stock_quantity' => 10, 'original_stock' => 10])->assertSessionHasNoErrors();
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_demo_products_start_with_opening_balances_and_reseeding_does_not_duplicate_them(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('stock_movements', 6);
        foreach (Product::all() as $product) {
            $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'opening', 'stock_before' => 0, 'stock_after' => $product->stock_quantity, 'quantity_change' => $product->stock_quantity]);
        }
    }

    /**
     * @return array{submission_id: string, type: string, quantity: int, reason: string}
     */
    private function adjustment(string $type = 'restock', int $quantity = 1, string $reason = 'Supplier delivery'): array
    {
        return ['submission_id' => (string) Str::uuid(), 'type' => $type, 'quantity' => $quantity, 'reason' => $reason];
    }
}
