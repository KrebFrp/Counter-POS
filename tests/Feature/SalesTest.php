<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\RecordSale;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_sale_uses_exact_database_price_and_authenticated_seller(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $otherStaff = User::factory()->create();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 3, 'total_price' => '0.01', 'sold_by' => $otherStaff->id])->assertRedirect(route('sales.index'))->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $this->assertSame('376.50', $sale->total_price);
        $this->assertSame(auth()->id(), $sale->sold_by);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->get('/sales')->assertOk()->assertSee('376.50')->assertSee($customer->full_name)->assertSee($product->name)->assertSee(auth()->user()->full_name);
    }

    public function test_walk_in_sale_can_exhaust_stock_but_cannot_oversell(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->assertNull(Sale::sole()->customer_id);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors(['quantity' => 'Available stock is 0 units.']);
        $this->assertDatabaseCount('sales', 1);
        $this->get('/sales')->assertSee('Walk in customer');
    }

    public function test_insufficient_stock_keeps_stock_and_sales_unchanged(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $this->from('/sales/create')->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3])
            ->assertRedirect('/sales/create')->assertSessionHasErrors(['quantity' => 'Available stock is 2 units.'])->assertSessionHasInput('quantity', 3);
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_removed_entities_and_invalid_quantities_are_rejected(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $customer->delete();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 1])->assertSessionHasErrors('customer_id');
        foreach ([0, -1, '1.5', 2147483648] as $quantity) {
            $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => $quantity])->assertSessionHasErrors('quantity');
        }
        $product->delete();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_service_rechecks_entities_removed_after_request_validation(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $customer->delete();
        try {
            app(RecordSale::class)->handle(['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 1], auth()->user());
            $this->fail('A removed customer must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }
        $product->delete();
        try {
            app(RecordSale::class)->handle(['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1], auth()->user());
            $this->fail('A removed product must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
        }
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_failure_after_stock_update_rolls_back_sale_and_stock(): void
    {
        $product = Product::factory()->create();
        Product::updated(fn () => throw new \RuntimeException('Forced stock failure after SQL update.'));
        try {
            app(RecordSale::class)->handle(['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3], auth()->user());
            $this->fail('Expected the forced exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Forced stock failure after SQL update.', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_failed_sale_insert_does_not_change_inventory(): void
    {
        $product = Product::factory()->create();
        Sale::creating(fn () => throw new \RuntimeException('Forced insert failure.'));
        try {
            app(RecordSale::class)->handle(['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3], auth()->user());
            $this->fail('Expected the forced exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Forced insert failure.', $exception->getMessage());
        } finally {
            Sale::flushEventListeners();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_history_survives_price_change_and_parent_removal(): void
    {
        $product = Product::factory()->create(['name' => 'Original product']);
        $customer = Customer::factory()->create(['full_name' => 'Original customer']);
        $seller = auth()->user();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 3])->assertSessionHasNoErrors();
        $product->update(['price' => '999.99']);
        $product->delete();
        $customer->delete();
        $seller->delete();
        $this->actingAs(User::factory()->create())->get('/sales')->assertOk()->assertSee('Original product')->assertSee('Original customer')->assertSee($seller->full_name)->assertSee('376.50');
        $this->assertSame('376.50', Sale::sole()->total_price);
    }

    public function test_excessive_total_is_rejected_and_zero_price_is_supported(): void
    {
        $product = Product::factory()->create(['price' => '99999999.99']);
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $product->update(['price' => '0.00']);
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->assertSame('0.00', Sale::sole()->total_price);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_foreign_keys_prevent_physical_parent_deletion(): void
    {
        $product = Product::factory()->create();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1]);
        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $product->id)->delete();
    }
}
