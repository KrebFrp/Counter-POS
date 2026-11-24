<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockHistoryUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_starts_from_existing_balances_without_changing_stock_or_inventing_earlier_movements(): void
    {
        $migration = require database_path('migrations/2026_11_13_105704_create_stock_movements_table.php');
        $checkoutMigration = require database_path('migrations/2026_11_13_110803_add_cart_and_payment_details_to_sales.php');
        $checkoutMigration->down();
        $migration->down();
        $product = Product::factory()->create(['stock_quantity' => 7]);
        $removed = Product::factory()->create(['stock_quantity' => 0]);
        $removed->delete();
        $migration->up();
        $checkoutMigration->up();
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'opening', 'quantity_change' => 7, 'stock_before' => 0, 'stock_after' => 7, 'performed_by' => null, 'sale_id' => null]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $removed->id, 'type' => 'opening', 'quantity_change' => 0, 'stock_after' => 0]);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame(0, $removed->fresh()->stock_quantity);
        $this->actingAs(User::factory()->create())->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity_change' => -2, 'stock_before' => 7, 'stock_after' => 5]);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(5, (int) DB::table('stock_movements')->where('product_id', $product->id)->sum('quantity_change'));
    }
}
