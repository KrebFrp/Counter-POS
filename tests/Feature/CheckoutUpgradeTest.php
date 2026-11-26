<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_preserves_existing_sales_receipts_and_does_not_invent_payment_details(): void
    {
        $migration = require database_path('migrations/2026_11_13_110803_add_cart_and_payment_details_to_sales.php');
        $migration->down();
        $product = Product::factory()->create(['name' => 'Renamed product', 'price' => '999.99', 'stock_quantity' => 7]);
        $seller = User::factory()->create();
        $saleId = DB::table('sales')->insertGetId(['product_id' => $product->id, 'sold_by' => $seller->id, 'quantity' => 3, 'total_price' => '376.50', 'unit_price' => '125.50', 'product_name' => 'Original product', 'staff_name' => 'Original seller', 'currency' => 'PHP', 'details_captured_at_sale' => true]);
        $migration->up();
        $sale = Sale::findOrFail($saleId);
        $this->assertSame('376.50', $sale->total_price);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertNull($sale->payment_method);
        $this->assertNull($sale->amount_tendered);
        $this->assertNull($sale->change_given);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $saleId, 'product_id' => $product->id, 'product_name' => 'Original product', 'quantity' => 3, 'unit_price' => '125.50', 'total_price' => '376.50']);
        $this->actingAs($seller)->get(route('sales.show', $sale))->assertOk()->assertSee('Original product')->assertSee('Original seller')->assertSee('PHP 376.50')->assertSee('Not recorded')->assertDontSee('Renamed product')->assertDontSee('Cash received');
    }
}
