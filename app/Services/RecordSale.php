<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSale
{
    public function __construct(private RecordStockMovement $movements) {}

    /**
     * @param  array{submission_id: string, items?: array<int, array{product_id: int|string, quantity: int|string}>, product_id?: int|string, quantity?: int|string, customer_id?: int|string|null, payment_method?: string, amount_tendered?: string|null}  $data
     */
    public function handle(array $data, User $staff): Sale
    {
        $legacy = ! array_key_exists('items', $data);
        $items = $legacy ? [['product_id' => $data['product_id'], 'quantity' => $data['quantity']]] : array_values($data['items']);
        $items = array_map(fn (array $item): array => ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']], $items);
        usort($items, fn (array $first, array $second): int => $first['product_id'] <=> $second['product_id']);
        $customerId = empty($data['customer_id']) ? null : (int) $data['customer_id'];
        $method = $data['payment_method'] ?? ($legacy ? 'unrecorded' : '');
        if (! in_array($method, $legacy ? ['cash', 'card', 'ewallet', 'unrecorded'] : ['cash', 'card', 'ewallet'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Choose a payment method.']);
        }
        $tenderedCents = $method === 'cash' && isset($data['amount_tendered']) ? Money::cents((string) $data['amount_tendered']) : null;
        $fingerprint = hash('sha256', json_encode([$items, $customerId, $method, $tenderedCents], JSON_THROW_ON_ERROR));
        $submissionId = strtolower($data['submission_id']);
        try {
            return DB::transaction(function () use ($items, $customerId, $method, $tenderedCents, $fingerprint, $submissionId, $legacy, $staff): Sale {
                $seller = User::query()->lockForUpdate()->find($staff->id);
                if (! $seller) {
                    throw ValidationException::withMessages(['product_id' => 'Your staff account is no longer active.']);
                }
                $existing = Sale::where('submission_id', $submissionId)->first();
                if ($existing) {
                    return $this->replay($existing, $fingerprint, $items, $customerId, $method, $seller);
                }
                if (count($items) < 1 || count($items) > 100 || count(array_unique(array_column($items, 'product_id'))) !== count($items)) {
                    throw ValidationException::withMessages(['items' => 'Choose between 1 and 100 different products.']);
                }
                $products = Product::query()->whereIn('id', array_column($items, 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $customer = $customerId ? Customer::query()->lockForUpdate()->find($customerId) : null;
                if ($customerId && ! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'This customer is no longer available.']);
                }
                $totalCents = 0;
                $totalQuantity = 0;
                $lines = [];
                foreach ($items as $item) {
                    $product = $products->get($item['product_id']);
                    if (! $product) {
                        throw ValidationException::withMessages([$legacy ? 'product_id' : 'items' => 'A selected product is no longer available.']);
                    }
                    $quantity = $item['quantity'];
                    if ($quantity < 1 || $quantity > $product->stock_quantity) {
                        throw ValidationException::withMessages([$legacy ? 'quantity' : 'items' => $legacy ? 'Available stock is '.$product->stock_quantity.' units.' : $product->name.' has '.$product->stock_quantity.' units available.']);
                    }
                    $unitCents = Money::cents($product->price);
                    if (($unitCents > 0 && $quantity > intdiv(Money::MAX_CENTS - $totalCents, $unitCents)) || $quantity > 2147483647 - $totalQuantity) {
                        throw ValidationException::withMessages([$legacy ? 'quantity' : 'items' => 'The checkout total or quantity exceeds the supported limit. Reduce the quantity.']);
                    }
                    $lineTotal = $unitCents * $quantity;
                    $totalCents += $lineTotal;
                    $totalQuantity += $quantity;
                    $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $quantity, 'unit_price' => $product->price, 'total_price' => Money::decimal($lineTotal)];
                }
                if ($method === 'cash' && ($tenderedCents === null || $tenderedCents < $totalCents)) {
                    throw ValidationException::withMessages(['amount_tendered' => 'Cash received must cover the full checkout total.']);
                }
                $first = $lines[0];
                $sale = Sale::create([
                    'submission_id' => $submissionId,
                    'request_fingerprint' => $fingerprint,
                    'product_id' => $first['product_id'],
                    'product_name' => $first['product_name'],
                    'unit_price' => $first['unit_price'],
                    'quantity' => $totalQuantity,
                    'total_price' => Money::decimal($totalCents),
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->full_name,
                    'sold_by' => $seller->id,
                    'staff_name' => $seller->full_name,
                    'currency' => config('pos.currency'),
                    'details_captured_at_sale' => true,
                    'payment_method' => $method === 'unrecorded' ? null : $method,
                    'amount_tendered' => $method === 'cash' ? Money::decimal($tenderedCents) : null,
                    'change_given' => $method === 'cash' ? Money::decimal($tenderedCents - $totalCents) : null,
                ]);
                foreach ($lines as $line) {
                    DB::table('sale_items')->insert(['sale_id' => $sale->id] + $line);
                    $product = $products->get($line['product_id']);
                    $stockBefore = $product->stock_quantity;
                    $product->stock_quantity -= $line['quantity'];
                    $product->save();
                    $this->movements->handle($product, $stockBefore, 'sale', 'Sale #'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT), $seller, $sale);
                }

                return $sale;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Sale::where('submission_id', $submissionId)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $fingerprint, $items, $customerId, $method, $staff);
        }
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    private function replay(Sale $sale, string $fingerprint, array $items, ?int $customerId, string $method, User $staff): Sale
    {
        $matches = $sale->request_fingerprint !== null
            ? hash_equals($sale->request_fingerprint, $fingerprint)
            : count($items) === 1 && $sale->product_id === $items[0]['product_id'] && $sale->quantity === $items[0]['quantity'] && $sale->customer_id === $customerId && $method === 'unrecorded';
        if ($sale->sold_by !== $staff->id || ! $matches) {
            throw ValidationException::withMessages(['submission_id' => 'This checkout was already used for another sale. Open a new sale form to continue.']);
        }

        return $sale;
    }
}
