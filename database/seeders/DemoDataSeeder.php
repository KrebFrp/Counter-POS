<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Services\RecordStockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo data may only be seeded in local or testing environments.');
        }

        foreach ([
            ['name' => 'House Blend Coffee', 'price' => '125.50', 'stock_quantity' => 24],
            ['name' => 'Ceramic Everyday Mug', 'price' => '245.00', 'stock_quantity' => 18],
            ['name' => 'Canvas Market Tote', 'price' => '189.00', 'stock_quantity' => 5],
            ['name' => 'Pocket Notebook', 'price' => '79.90', 'stock_quantity' => 36],
            ['name' => 'Stainless Water Bottle', 'price' => '449.00', 'stock_quantity' => 12],
            ['name' => 'Bamboo Desk Organizer', 'price' => '329.50', 'stock_quantity' => 0],
        ] as $product) {
            DB::transaction(function () use ($product): void {
                $createdProduct = Product::firstOrCreate(['name' => $product['name']], $product);
                if ($createdProduct->wasRecentlyCreated) {
                    app(RecordStockMovement::class)->handle($createdProduct, 0, 'opening', 'Initial stock from demo data.');
                }
            });
        }

        Customer::firstOrCreate(['email' => 'alex@example.com'], ['full_name' => 'Alex Reyes', 'phone' => '09171234567']);
        Customer::firstOrCreate(['email' => 'sam@example.com'], ['full_name' => 'Sam Cruz', 'phone' => null]);
    }
}
