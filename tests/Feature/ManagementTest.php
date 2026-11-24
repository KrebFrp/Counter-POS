<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create(['username' => 'manager']));
    }

    public function test_all_management_screens_render(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();
        $staff = User::factory()->create();
        foreach (['products' => $product, 'customers' => $customer, 'staff' => $staff] as $resource => $record) {
            foreach (['/'.$resource, '/'.$resource.'/create', '/'.$resource.'/'.$record->id, '/'.$resource.'/'.$record->id.'/edit'] as $url) {
                $this->get($url)->assertOk();
            }
        }
        $this->get('/sales/create')->assertOk();
        $this->get('/sales')->assertOk();
    }

    public function test_product_crud_and_escaped_name(): void
    {
        $this->post('/products', ['name' => '  <script>alert(1)</script>  ', 'price' => '125.50', 'stock_quantity' => 10])->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::sole();
        $this->assertSame('<script>alert(1)</script>', $product->name);
        $this->get('/products/'.$product->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->patch('/products/'.$product->id, ['name' => 'Updated', 'price' => '130.00', 'stock_quantity' => 15, 'original_stock' => 10, 'stock_reason' => 'Stock count correction'])->assertSessionHasNoErrors();
        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->delete('/products/'.$product->id)->assertRedirect(route('products.index'));
        $this->assertSoftDeleted($product);
        $this->get('/products/'.$product->id)->assertNotFound();
        $this->get('/sales/create')->assertDontSee('Updated');
    }

    public function test_customer_crud_allows_duplicate_email(): void
    {
        $data = ['full_name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '09171234567'];
        $this->post('/customers', $data)->assertSessionHasNoErrors()->assertRedirect();
        $customer = Customer::sole();
        $this->post('/customers', $data)->assertSessionHasNoErrors();
        $this->patch('/customers/'.$customer->id, array_replace($data, ['full_name' => 'Updated customer', 'phone' => '']))->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->phone);
        $this->delete('/customers/'.$customer->id)->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted($customer);
    }

    public function test_staff_crud_hashes_passwords_and_reserves_removed_usernames(): void
    {
        $data = ['username' => '  CASHIER  ', 'full_name' => 'Cashier', 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->post('/staff', $data)->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('username', 'cashier')->sole();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $oldHash = $user->password;
        $this->patch('/staff/'.$user->id, ['username' => 'cashier', 'full_name' => 'New name', 'password' => '', 'password_confirmation' => ''])->assertSessionHasNoErrors();
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->patch('/staff/'.$user->id, ['username' => 'cashier', 'full_name' => 'New name', 'password' => 'replacement', 'password_confirmation' => 'replacement'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('replacement', $user->fresh()->password));
        $this->delete('/staff/'.$user->id)->assertRedirect(route('staff.index'));
        $this->assertSoftDeleted($user);
        $this->post('/staff', $data)->assertSessionHasErrors('username');
    }

    public function test_self_removal_is_rejected_and_self_password_change_keeps_current_session(): void
    {
        $user = auth()->user();
        $this->delete('/staff/'.$user->id)->assertSessionHasErrors('account');
        $this->assertNotSoftDeleted($user);
        $this->get('/products')->assertOk();
        $this->patch('/staff/'.$user->id, ['username' => $user->username, 'full_name' => $user->full_name, 'password' => 'replacement', 'password_confirmation' => 'replacement'])->assertSessionHasNoErrors();
        $this->get('/products')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_values_do_not_write_records(): void
    {
        $this->post('/products', ['name' => str_repeat('x', 101), 'price' => '12.345', 'stock_quantity' => -1])->assertSessionHasErrors(['name', 'price', 'stock_quantity']);
        foreach (['1e2', '999999999.99', '-1.00', '0.001'] as $price) {
            $this->post('/products', ['name' => 'Product', 'price' => $price, 'stock_quantity' => 1])->assertSessionHasErrors('price');
        }
        $this->post('/customers', ['full_name' => '', 'email' => 'bad', 'phone' => str_repeat('1', 21)])->assertSessionHasErrors(['full_name', 'email', 'phone']);
        $this->post('/staff', ['username' => 'bad username', 'full_name' => 'Staff', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['username', 'password']);
        $longPassword = str_repeat('é', 37);
        $this->post('/staff', ['username' => 'newstaff', 'full_name' => 'Staff', 'password' => $longPassword, 'password_confirmation' => $longPassword])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_stale_inventory_adjustment_is_rejected_but_ordinary_edit_preserves_current_stock(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 7]);
        $this->patch('/products/'.$product->id, ['name' => 'Changed', 'price' => '130.00', 'original_stock' => 10, 'stock_quantity' => 20, 'stock_reason' => 'Stock count correction'])->assertSessionHasErrors('stock_quantity');
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame('125.50', $product->fresh()->price);
        $this->patch('/products/'.$product->id, ['name' => 'Changed', 'price' => '130.00', 'original_stock' => 10, 'stock_quantity' => 10])->assertSessionHasNoErrors();
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertSame('130.00', $product->fresh()->price);
    }

    public function test_initial_staff_seeding_hashes_secret_and_does_not_reset_existing_account(): void
    {
        config(['pos.initial_username' => 'initial', 'pos.initial_name' => 'Initial Staff', 'pos.initial_password' => 'initial-password']);
        $this->seed(InitialStaffSeeder::class);
        $user = User::where('username', 'initial')->sole();
        $this->assertTrue($user->isAdmin());
        $hash = $user->password;
        $this->assertTrue(Hash::check('initial-password', $hash));
        config(['pos.initial_password' => 'changed-secret']);
        $this->seed(InitialStaffSeeder::class);
        $this->assertSame($hash, $user->fresh()->password);
    }
}
