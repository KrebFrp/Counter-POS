<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashiers_cannot_manage_staff_or_change_products_even_with_direct_requests(): void
    {
        $cashier = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $this->actingAs($cashier);

        foreach (['/staff', '/staff/create', '/staff/'.$admin->id, '/staff/'.$admin->id.'/edit', '/products/create', '/products/'.$product->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post('/staff', ['username' => 'intruder', 'full_name' => 'Intruder', 'role' => 'admin', 'password' => 'replacement', 'password_confirmation' => 'replacement'])->assertForbidden();
        $this->patch('/staff/'.$admin->id, ['role' => 'cashier', 'password' => 'replacement', 'password_confirmation' => 'replacement'])->assertForbidden();
        $this->patch('/staff/'.$cashier->id, ['role' => 'admin'])->assertForbidden();
        $this->delete('/staff/'.$admin->id)->assertForbidden();
        $this->post('/products', ['name' => 'Unauthorized', 'price' => '1.00', 'stock_quantity' => 1])->assertForbidden();
        $this->patch('/products/'.$product->id, ['name' => 'Unauthorized', 'price' => '0.00', 'stock_quantity' => 999, 'original_stock' => 10])->assertForbidden();
        $this->delete('/products/'.$product->id)->assertForbidden();

        $this->assertSame('cashier', $cashier->fresh()->role);
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
        $this->assertNotSoftDeleted($admin);
        $this->assertNotSoftDeleted($product);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame('125.50', $product->fresh()->price);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_cashiers_can_use_checkout_and_customers_without_admin_controls(): void
    {
        $cashier = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($cashier);
        $this->get('/products')->assertOk()->assertDontSee(route('staff.index'))->assertDontSee(route('products.create'))->assertDontSee(route('products.edit', $product));
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee(route('products.edit', $product))->assertDontSee('name="_method"', false);
        $this->get('/sales/create')->assertOk();
        $this->post('/customers', ['full_name' => 'Customer', 'email' => 'customer@example.com'])->assertSessionHasNoErrors()->assertRedirect();
        $customer = Customer::sole();
        $this->get('/customers')->assertOk();
        $this->patch('/customers/'.$customer->id, ['full_name' => 'Updated customer', 'email' => 'customer@example.com'])->assertSessionHasNoErrors();
        $this->post('/sales', ['submission_id' => (string) Str::uuid(), 'product_id' => $product->id, 'customer_id' => $customer->id, 'quantity' => 2, 'role' => 'admin'])->assertSessionHasNoErrors()->assertRedirect(route('sales.index'));
        $this->get('/sales')->assertOk();
        $this->assertDatabaseHas('sales', ['sold_by' => $cashier->id, 'quantity' => 2]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame('cashier', $cashier->fresh()->role);
    }

    public function test_admins_can_assign_roles_and_cannot_downgrade_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $data = ['username' => 'newstaff', 'full_name' => 'New Staff', 'password' => 'replacement', 'password_confirmation' => 'replacement'];
        $this->post('/staff', $data)->assertSessionHasNoErrors();
        $staff = User::where('username', 'newstaff')->sole();
        $this->assertSame('cashier', $staff->role);
        $update = ['username' => 'newstaff', 'full_name' => 'New Staff'];
        $this->patch('/staff/'.$staff->id, $update + ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('admin', $staff->fresh()->role);
        $this->patch('/staff/'.$staff->id, $update + ['role' => 'cashier'])->assertSessionHasNoErrors();
        $this->assertSame('cashier', $staff->fresh()->role);
        $this->patch('/staff/'.$staff->id, $update + ['role' => 'owner'])->assertSessionHasErrors('role');
        $this->assertSame('cashier', $staff->fresh()->role);
        $this->patch('/staff/'.$admin->id, ['username' => $admin->username, 'full_name' => $admin->full_name, 'role' => 'cashier'])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
        $this->get('/staff')->assertOk()->assertSee('Cashier')->assertSee('Admin');
    }

    public function test_role_changes_apply_to_an_existing_session_on_its_next_request(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/staff')->assertOk();
        User::whereKey($admin->id)->update(['role' => 'cashier']);
        $this->get('/staff')->assertForbidden();
        $this->get('/products')->assertOk()->assertDontSee(route('staff.index'));
    }
}
