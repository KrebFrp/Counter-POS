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
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->string('product_name', 100);
            $table->unsignedInteger('sale_id')->nullable()->unique();
            $table->unsignedInteger('performed_by')->nullable();
            $table->string('staff_name', 100)->nullable();
            $table->string('type', 20);
            $table->integer('quantity_change');
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->string('reason', 500);
            $table->uuid('submission_id')->nullable()->unique();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['product_id', 'id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->restrictOnDelete();
        });
        DB::table('products')->orderBy('id')->chunkById(200, function (Collection $products): void {
            foreach ($products as $product) {
                DB::table('stock_movements')->insert([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'type' => 'opening',
                    'quantity_change' => $product->stock_quantity,
                    'stock_before' => 0,
                    'stock_after' => $product->stock_quantity,
                    'reason' => 'Opening balance when stock history started.',
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
