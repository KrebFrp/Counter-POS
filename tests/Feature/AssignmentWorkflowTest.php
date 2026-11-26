<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_sale_form_and_optional_checkout_are_separate_and_linked(): void
    {
        Product::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->get(route('sales.create'))->assertOk()
            ->assertSee('id="single-sale-form"', false)
            ->assertSee('name="product_id"', false)
            ->assertSee('name="customer_id"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee(route('sales.checkout'))
            ->assertDontSee('name="items[0][product_id]"', false)
            ->assertDontSee('name="payment_method"', false);
        $this->get(route('sales.checkout'))->assertOk()
            ->assertSee('id="sale-form"', false)
            ->assertSee('name="items[0][product_id]"', false)
            ->assertSee('name="payment_method"', false)
            ->assertSee('Add product')
            ->assertSee(route('sales.create'))
            ->assertDontSee('id="single-sale-form"', false);
    }
}
