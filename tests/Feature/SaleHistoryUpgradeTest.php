<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleHistoryUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_upgrade_preserves_old_totals_and_freezes_available_details_without_claiming_original_names(): void
    {
        $migration = require database_path('migrations/2026_11_13_105051_add_submission_and_snapshots_to_sales_table.php');
        $migration->down();
        config(['pos.currency' => 'PHP']);
        $seller = User::factory()->create(['full_name' => 'Earlier seller']);
        $product = Product::factory()->create(['name' => 'Earlier product', 'price' => '999.99']);
        $customer = Customer::factory()->create(['full_name' => 'Earlier customer']);
        $product->delete();
        $customer->delete();
        $seller->delete();
        $saleId = DB::table('sales')->insertGetId(['product_id' => $product->id, 'customer_id' => $customer->id, 'sold_by' => $seller->id, 'quantity' => 3, 'total_price' => '376.50']);
        $walkInId = DB::table('sales')->insertGetId(['product_id' => $product->id, 'sold_by' => $seller->id, 'quantity' => 3, 'total_price' => '1.00']);
        $migration->up();
        $sale = Sale::findOrFail($saleId);
        $this->assertSame('376.50', $sale->total_price);
        $this->assertSame('125.50', $sale->unit_price);
        $this->assertSame('Earlier product', $sale->product_name);
        $this->assertSame('Earlier customer', $sale->customer_name);
        $this->assertSame('Earlier seller', $sale->staff_name);
        $this->assertSame('PHP', $sale->currency);
        $this->assertFalse($sale->details_captured_at_sale);
        $this->assertNull($sale->submission_id);
        $this->assertNull(Sale::findOrFail($walkInId)->unit_price);
        $this->assertNull(Sale::findOrFail($walkInId)->customer_name);
        DB::table('products')->where('id', $product->id)->update(['name' => 'Renamed product']);
        DB::table('customers')->where('id', $customer->id)->update(['full_name' => 'Renamed customer']);
        DB::table('users')->where('id', $seller->id)->update(['full_name' => 'Renamed seller']);
        config(['pos.currency' => 'USD']);
        $this->actingAs(User::factory()->create())->get('/sales')->assertOk()->assertSee('Earlier record')->assertSee('Earlier product')->assertSee('Earlier customer')->assertSee('Earlier seller')->assertSee('PHP 376.50')->assertSee('PHP 125.50')->assertSee('Walk in customer')->assertDontSee('Renamed product')->assertDontSee('Renamed customer')->assertDontSee('Renamed seller');
    }
}
