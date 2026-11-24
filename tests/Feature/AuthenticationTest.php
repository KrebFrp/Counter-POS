<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_management_route_requires_authentication(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array('auth', $route->middleware(), true)) {
                continue;
            }
            $url = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $this->call($method, $url)->assertRedirect(route('login'));
            }
        }
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_normalizes_username_and_logout_ends_access(): void
    {
        $user = User::factory()->create(['username' => 'cashier']);
        $this->get('/login')->assertOk()->assertSee('Good to see you.');
        $this->post('/login', ['username' => '  CASHIER  ', 'password' => 'password'])->assertRedirect(route('products.index'));
        $this->assertAuthenticatedAs($user);
        $this->get('/products')->assertOk();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/sales')->assertRedirect(route('login'));
    }

    public function test_failed_logins_are_generic_and_rate_limited_without_flashing_password(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['username' => 'unknown', 'password' => 'secret-value'])
                ->assertSessionHasErrors(['username' => 'The supplied credentials are incorrect.'])
                ->assertSessionMissing('_old_input.password');
        }
        $this->post('/login', ['username' => 'unknown', 'password' => 'secret-value'])
            ->assertSessionHasErrors('username');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));
    }

    public function test_removed_user_cannot_login_or_continue_a_session(): void
    {
        $user = User::factory()->create(['username' => 'removed']);
        $this->actingAs($user)->get('/products')->assertOk();
        $user->delete();
        $this->get('/products')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['username' => 'removed', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_changed_password_invalidates_an_existing_session(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->update(['password' => Hash::make('changed-password')]);
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldHash])
            ->get('/products')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_csrf_rejection_with_middleware_explicitly_enabled(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
        $this->actingAs(User::factory()->create())->post('/products', ['name' => 'Blocked', 'price' => '10.00', 'stock_quantity' => 1])->assertStatus(419);
        $this->assertDatabaseCount('products', 0);
    }
}
