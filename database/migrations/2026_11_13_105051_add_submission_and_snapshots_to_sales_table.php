<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('submission_id')->nullable()->unique();
            $table->string('product_name', 100)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('staff_name', 100)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('currency', 20)->nullable();
            $table->boolean('details_captured_at_sale')->default(false);
        });

        DB::table('sales')
            ->join('products', 'products.id', '=', 'sales.product_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->join('users', 'users.id', '=', 'sales.sold_by')
            ->select('sales.id', 'sales.quantity', 'sales.total_price', 'products.name as product_name', 'customers.full_name as customer_name', 'users.full_name as staff_name')
            ->chunkById(200, function (Collection $sales): void {
                foreach ($sales as $sale) {
                    $totalCents = Money::cents($sale->total_price);
                    $unitPrice = $sale->quantity > 0 && $totalCents % $sale->quantity === 0
                        ? Money::decimal(intdiv($totalCents, $sale->quantity)) : null;
                    DB::table('sales')->where('id', $sale->id)->update([
                        'product_name' => $sale->product_name,
                        'customer_name' => $sale->customer_name,
                        'staff_name' => $sale->staff_name,
                        'unit_price' => $unitPrice,
                        'currency' => config('pos.currency'),
                    ]);
                }
            }, 'sales.id', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['submission_id']);
            $table->dropColumn(['submission_id', 'product_name', 'customer_name', 'staff_name', 'unit_price', 'currency', 'details_captured_at_sale']);
        });
    }
};
