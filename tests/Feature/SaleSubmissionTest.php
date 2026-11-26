<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\RecordSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_return_the_original_sale_even_after_records_are_changed_or_removed(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Original product', 'stock_quantity' => 2]);
        $customer = Customer::factory()->create(['full_name' => 'Original customer']);
        $data = ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 2];
        $this->actingAs($seller)->post('/sales', $data)->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $product->update(['name' => 'Changed product', 'price' => '999.99']);
        $product->delete();
        $customer->delete();
        $this->post('/sales', $data)->assertSessionHasNoErrors()->assertRedirect(route('sales.index'))->assertSessionHas('success', 'Sale #'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT).' was already recorded. Stock was not deducted again.');
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame('251.00', $sale->fresh()->total_price);
    }

    public function test_a_used_reference_cannot_be_reused_with_changed_details_or_by_another_staff_member(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $customer = Customer::factory()->create();
        $data = ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 1];
        $this->actingAs($seller)->post('/sales', $data)->assertSessionHasNoErrors();
        foreach ([['quantity' => 2], ['product_id' => $otherProduct->id], ['customer_id' => $customer->id]] as $changes) {
            $this->post('/sales', array_replace($data, $changes))->assertSessionHasErrors('submission_id');
        }
        $this->actingAs(User::factory()->create())->post('/sales', $data)->assertSessionHasErrors('submission_id');
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(10, $otherProduct->fresh()->stock_quantity);
    }

    public function test_failed_checkouts_can_be_corrected_and_distinct_references_allow_identical_sales(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $data = ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 3];
        $this->actingAs(User::factory()->create())->from('/sales/create')->post('/sales', $data)->assertSessionHasErrors('quantity')->assertSessionHasInput('submission_id', $data['submission_id']);
        $this->get('/sales/create')->assertSee($data['submission_id']);
        $data['quantity'] = 1;
        $this->post('/sales', $data)->assertSessionHasNoErrors();
        $this->post('/sales', array_replace($data, ['submission_id' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 2);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_checkout_requires_a_valid_reference_and_new_forms_get_different_references(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        $first = $this->get('/sales/create')->assertOk()->viewData('submissionId');
        $second = $this->get('/sales/create')->assertOk()->viewData('submissionId');
        $this->assertTrue(Str::isUuid($first));
        $this->assertNotSame($first, $second);
        foreach ([null, 'invalid'] as $reference) {
            $this->post('/sales', ['submission_id' => $reference, 'product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('submission_id');
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_a_rolled_back_sale_can_be_retried_with_the_same_reference(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->create();
        $data = ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'quantity' => 2];
        Product::updated(fn () => throw new \RuntimeException('Stock write failed.'));
        try {
            app(RecordSale::class)->handle($data, $seller);
            $this->fail('Expected the stock update to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Stock write failed.', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->actingAs($seller)->post('/sales', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_sale_details_and_currency_survive_renames_price_changes_and_removal(): void
    {
        config(['pos.currency' => 'PHP']);
        $seller = User::factory()->create(['full_name' => 'Original seller']);
        $product = Product::factory()->create(['name' => '<Original product>']);
        $customer = Customer::factory()->create(['full_name' => 'Original customer']);
        $this->actingAs($seller)->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 2, 'product_name' => 'Forged', 'unit_price' => '0.01', 'currency' => 'USD', 'staff_name' => 'Forged'])->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $this->assertSame('<Original product>', $sale->product_name);
        $this->assertSame('Original customer', $sale->customer_name);
        $this->assertSame('Original seller', $sale->staff_name);
        $this->assertSame('125.50', $sale->unit_price);
        $this->assertSame('PHP', $sale->currency);
        $this->assertTrue($sale->details_captured_at_sale);
        $product->update(['name' => 'Changed product', 'price' => '999.99']);
        $customer->update(['full_name' => 'Changed customer']);
        $seller->update(['full_name' => 'Changed seller']);
        $product->delete();
        $customer->delete();
        $seller->delete();
        config(['pos.currency' => 'USD']);
        $this->actingAs(User::factory()->create())->get('/sales')->assertOk()->assertSee('<Original product>')->assertSee('Original customer')->assertSee('Original seller')->assertSee('PHP 125.50')->assertSee('PHP 251.00')->assertDontSee('Changed product')->assertDontSee('Changed customer')->assertDontSee('Changed seller')->assertDontSee('USD 251.00');
    }
}
