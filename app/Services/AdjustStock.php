<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class AdjustStock
{
    public function __construct(private RecordStockMovement $movements) {}

    /**
     * @param  array{submission_id: string, type: string, quantity: int|string, reason: string}  $data
     */
    public function handle(Product $product, array $data, User $staff): void
    {
        $data['submission_id'] = strtolower($data['submission_id']);
        try {
            DB::transaction(function () use ($product, $data, $staff): void {
                $administrator = User::query()->lockForUpdate()->find($staff->id);
                abort_unless($administrator?->isAdmin(), 403);
                $existing = DB::table('stock_movements')->where('submission_id', $data['submission_id'])->first();
                if ($existing) {
                    $this->checkReplay($existing, $product, $data, $staff);

                    return;
                }
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                $quantity = (int) $data['quantity'];
                if ($quantity < 1 || $quantity > 2147483647 || ! in_array($data['type'], ['restock', 'removal'], true)) {
                    throw ValidationException::withMessages(['quantity' => 'Enter a valid quantity and adjustment type.']);
                }
                $stockBefore = $locked->stock_quantity;
                $stockAfter = $stockBefore + ($data['type'] === 'restock' ? $quantity : -$quantity);
                if ($stockAfter < 0 || $stockAfter > 2147483647) {
                    throw ValidationException::withMessages(['quantity' => $stockAfter < 0 ? 'You cannot remove more than the available '.$stockBefore.' units.' : 'The resulting stock exceeds the supported limit.']);
                }
                $locked->stock_quantity = $stockAfter;
                $locked->save();
                $this->movements->handle($locked, $stockBefore, $data['type'], $data['reason'], $administrator, submissionId: $data['submission_id']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = DB::table('stock_movements')->where('submission_id', $data['submission_id'])->first();
            if (! $existing) {
                throw $exception;
            }
            $this->checkReplay($existing, $product, $data, $staff);
        }
    }

    /**
     * @param  array{submission_id: string, type: string, quantity: int|string, reason: string}  $data
     */
    private function checkReplay(stdClass $movement, Product $product, array $data, User $staff): void
    {
        $change = $data['type'] === 'restock' ? (int) $data['quantity'] : -(int) $data['quantity'];
        if ($movement->performed_by !== $staff->id || $movement->product_id !== $product->id || $movement->type !== $data['type'] || $movement->quantity_change !== $change || $movement->reason !== $data['reason']) {
            throw ValidationException::withMessages(['submission_id' => 'This form was already used for another stock change. Open stock history again to start a new adjustment.']);
        }
    }
}
