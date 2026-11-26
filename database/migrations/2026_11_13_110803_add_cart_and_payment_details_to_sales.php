<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('payment_method', 20)->nullable();
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->decimal('change_given', 10, 2)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('sale_id');
            $table->unsignedInteger('product_id');
            $table->string('product_name', 100)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2);
            $table->unique(['sale_id', 'product_id']);
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
        DB::table('sales')->orderBy('id')->chunkById(200, function (Collection $sales): void {
            foreach ($sales as $sale) {
                DB::table('sale_items')->insert([
                    'sale_id' => $sale->id,
                    'product_id' => $sale->product_id,
                    'product_name' => $sale->product_name,
                    'quantity' => $sale->quantity,
                    'unit_price' => $sale->unit_price,
                    'total_price' => $sale->total_price,
                ]);
            }
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique(['sale_id', 'product_id']);
            $table->dropUnique(['sale_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('sale_items')->select('sale_id')->groupBy('sale_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot roll back checkout support while multi-product sales exist.');
        }
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique('sale_id');
            $table->dropUnique(['sale_id', 'product_id']);
        });
        Schema::dropIfExists('sale_items');
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'amount_tendered', 'change_given', 'request_fingerprint']);
        });
    }
};
