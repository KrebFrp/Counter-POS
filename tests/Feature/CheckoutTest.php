<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_checkout_saves_multiple_items_exact_totals_change_and_stock_movements(): void
    {
        $cashier = User::factory()->create();
        $customer = Customer::factory()->create();
        $first = Product::factory()->create(['name' => 'Small item', 'price' => '0.10']);
        $second = Product::factory()->create(['name' => 'Larger item', 'price' => '0.20']);
        $data = $this->checkout($first, $second) + ['customer_id' => $customer->id, 'total_price' => '0.01', 'change_given' => '999.00', 'sold_by' => 999];
        $this->actingAs($cashier)->post('/sales', $data)->assertSessionHasNoErrors()->assertRedirect(route('sales.show', Sale::sole()));
        $sale = Sale::sole();
        $this->assertSame('0.70', $sale->total_price);
        $this->assertSame('1.00', $sale->amount_tendered);
        $this->assertSame('0.30', $sale->change_given);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame(5, $sale->quantity);
        $this->assertSame($cashier->id, $sale->sold_by);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $first->id, 'quantity' => 3, 'unit_price' => '0.10', 'total_price' => '0.30']);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'product_id' => $second->id, 'quantity' => 2, 'total_price' => '0.40']);
        $this->assertSame(7, $first->fresh()->stock_quantity);
        $this->assertSame(8, $second->fresh()->stock_quantity);
        $this->assertSame(1, $first->sales()->count());
        $this->assertSame(1, $second->sales()->count());
        $this->get(route('sales.show', $sale))->assertOk()->assertSee('Small item')->assertSee('Larger item')->assertSee('PHP 0.70')->assertSee('PHP 0.30')->assertSee('Print receipt');
        $this->get('/sales')->assertOk()->assertSee('Small item')->assertSee('Larger item')->assertSee(route('sales.show', $sale));
    }

    public function test_an_unavailable_or_understocked_item_rejects_the_whole_cart(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20', 'stock_quantity' => 1]);
        $this->actingAs(User::factory()->create())->post('/sales', $this->checkout($first, $second))->assertSessionHasErrors('items');
        $second->delete();
        $this->post('/sales', $this->checkout($first, $second))->assertSessionHasErrors('items');
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(1, $second->fresh()->stock_quantity);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_insufficient_or_invalid_cash_and_missing_payment_methods_do_not_change_stock(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20']);
        $this->actingAs(User::factory()->create());
        foreach ([null, '', '-1.00', '0.001', '1e2', '999999999.99', '0.69'] as $cash) {
            $this->post('/sales', array_replace($this->checkout($first, $second), ['amount_tendered' => $cash]))->assertSessionHasErrors('amount_tendered');
        }
        foreach (['', 'crypto'] as $method) {
            $this->post('/sales', array_replace($this->checkout($first, $second), ['payment_method' => $method, 'amount_tendered' => null]))->assertSessionHasErrors('payment_method');
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $first->fresh()->stock_quantity);
    }

    public function test_card_and_ewallet_payments_record_the_method_without_inventing_cash_or_change(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20']);
        $this->actingAs(User::factory()->create());
        foreach (['card', 'ewallet'] as $method) {
            $data = array_replace($this->checkout($first, $second), ['payment_method' => $method, 'amount_tendered' => null]);
            $this->post('/sales', $data)->assertSessionHasNoErrors();
            $sale = Sale::latest('id')->firstOrFail();
            $this->assertSame($method, $sale->payment_method);
            $this->assertNull($sale->amount_tendered);
            $this->assertNull($sale->change_given);
            $this->get(route('sales.show', $sale))->assertOk()->assertDontSee('Cash received')->assertDontSee('Change given');
            $this->post('/sales', array_replace($data, ['submission_id' => (string) Str::uuid(), 'amount_tendered' => '1.00']))->assertSessionHasErrors('amount_tendered');
        }
        $this->assertDatabaseCount('sales', 2);
    }

    public function test_cart_retries_are_order_independent_and_reject_changed_items_payment_or_staff(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20']);
        $data = $this->checkout($first, $second);
        $this->actingAs(User::factory()->create())->post('/sales', $data)->assertSessionHasNoErrors();
        $this->post('/sales', array_replace($data, ['items' => array_reverse($data['items']), 'amount_tendered' => '1']))->assertSessionHasNoErrors();
        foreach ([['amount_tendered' => '2.00'], ['payment_method' => 'card', 'amount_tendered' => null], ['items' => [['product_id' => $first->id, 'quantity' => 1]]]] as $change) {
            $this->post('/sales', array_replace($data, $change))->assertSessionHasErrors('submission_id');
        }
        $this->actingAs(User::factory()->create())->post('/sales', $data)->assertSessionHasErrors('submission_id');
        $first->delete();
        $second->delete();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(7, $first->fresh()->stock_quantity);
    }

    public function test_a_failure_on_the_second_product_rolls_back_the_entire_checkout_and_can_be_retried(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20']);
        $data = $this->checkout($first, $second);
        $this->actingAs(User::factory()->create());
        Product::updating(function (Product $product) use ($second): void {
            if ($product->id === $second->id) {
                throw new \RuntimeException('Second item failed.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/sales', $data);
            $this->fail('Expected the second item to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Second item failed.', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(10, $second->fresh()->stock_quantity);
        $this->post('/sales', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_receipts_keep_all_line_snapshots_currency_and_payment_after_later_edits(): void
    {
        $first = Product::factory()->create(['name' => '<Original first>', 'price' => '0.10']);
        $second = Product::factory()->create(['name' => 'Original second', 'price' => '0.20']);
        $seller = User::factory()->create(['full_name' => 'Original cashier']);
        $customer = Customer::factory()->create(['full_name' => 'Original customer']);
        $data = $this->checkout($first, $second) + ['customer_id' => $customer->id];
        $this->actingAs($seller)->post('/sales', $data)->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $first->update(['name' => 'Changed first', 'price' => '10.00']);
        $second->update(['name' => 'Changed second']);
        $seller->update(['full_name' => 'Changed cashier']);
        $customer->update(['full_name' => 'Changed customer']);
        $first->delete();
        $second->delete();
        $seller->delete();
        $customer->delete();
        config(['pos.currency' => 'USD']);
        $this->actingAs(User::factory()->create())->get(route('sales.show', $sale))->assertOk()->assertSee('<Original first>')->assertSee('Original second')->assertSee('Original cashier')->assertSee('Original customer')->assertSee('PHP 0.70')->assertSee('PHP 0.30')->assertDontSee('Changed first')->assertDontSee('Changed second')->assertDontSee('USD 0.70');
    }

    public function test_duplicate_products_empty_carts_invalid_quantities_and_malformed_input_are_rejected(): void
    {
        $first = Product::factory()->create(['price' => '0.10']);
        $second = Product::factory()->create(['price' => '0.20']);
        $this->actingAs(User::factory()->create());
        foreach ([[], 'invalid', [['product_id' => $first->id, 'quantity' => 0]], [['product_id' => $first->id, 'quantity' => '1.5']], [['product_id' => $first->id, 'quantity' => 1], ['product_id' => $first->id, 'quantity' => 2]], array_fill(0, 101, ['product_id' => $first->id, 'quantity' => 1])] as $items) {
            $this->from('/checkout')->post('/sales', array_replace($this->checkout($first, $second), ['items' => $items]))->assertSessionHasErrors();
            $this->get('/checkout')->assertOk();
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $first->fresh()->stock_quantity);
    }

    public function test_combined_total_overflow_is_rejected_and_free_items_allow_zero_cash(): void
    {
        $first = Product::factory()->create(['price' => '60000000.00']);
        $second = Product::factory()->create(['price' => '60000000.00']);
        $this->actingAs(User::factory()->create());
        $data = $this->checkout($first, $second);
        $data['items'][0]['quantity'] = 1;
        $data['items'][1]['quantity'] = 1;
        $data['amount_tendered'] = '99999999.99';
        $this->post('/sales', $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $first->update(['price' => '0.00']);
        $second->update(['price' => '0.00']);
        $data['amount_tendered'] = '0.00';
        $this->post('/sales', $data)->assertSessionHasNoErrors();
        $this->assertSame('0.00', Sale::sole()->total_price);
        $this->assertSame('0.00', Sale::sole()->change_given);
    }

    /**
     * @return array{submission_id: string, items: array<int, array{product_id: int, quantity: int}>, payment_method: string, amount_tendered: string}
     */
    private function checkout(Product $first, Product $second): array
    {
        return ['submission_id' => (string) Str::uuid(), 'items' => [['product_id' => $first->id, 'quantity' => 3], ['product_id' => $second->id, 'quantity' => 2]], 'payment_method' => 'cash', 'amount_tendered' => '1.00'];
    }
}
