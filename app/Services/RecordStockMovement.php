<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordStockMovement
{
    public function handle(Product $product, int $stockBefore, string $type, string $reason, ?User $staff = null, ?Sale $sale = null, ?string $submissionId = null): void
    {
        DB::table('stock_movements')->insert([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sale_id' => $sale?->id,
            'performed_by' => $staff?->id,
            'staff_name' => $staff?->full_name,
            'type' => $type,
            'quantity_change' => $product->stock_quantity - $stockBefore,
            'stock_before' => $stockBefore,
            'stock_after' => $product->stock_quantity,
            'reason' => $reason,
            'submission_id' => $submissionId,
            'created_at' => now(),
        ]);
    }
}
