<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\RecordSale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(): View
    {
        $sales = Sale::latest('created_at')->latest('id')->paginate(15);

        return view('sales.index', [
            'sales' => $sales,
            'saleItems' => DB::table('sale_items')->whereIn('sale_id', $sales->getCollection()->modelKeys())->orderBy('id')->get()->groupBy('sale_id'),
        ]);
    }

    public function create(): View
    {
        return view('sales.create', $this->formData());
    }

    public function checkout(): View
    {
        $oldItems = old('items', [['product_id' => old('product_id', ''), 'quantity' => old('quantity', 1)]]);
        $cartItems = array_map(function (mixed $item): array {
            return [
                'product_id' => is_array($item) && is_scalar($item['product_id'] ?? null) ? $item['product_id'] : '',
                'quantity' => is_array($item) && is_scalar($item['quantity'] ?? null) ? $item['quantity'] : 1,
            ];
        }, is_array($oldItems) && $oldItems !== [] ? array_slice(array_values($oldItems), 0, 100) : [[]]);

        return view('sales.checkout', $this->formData() + ['cartItems' => $cartItems]);
    }

    public function store(StoreSaleRequest $request, RecordSale $recordSale): RedirectResponse
    {
        $sale = $recordSale->handle($request->validated(), $request->user());

        $message = $sale->wasRecentlyCreated ? ' recorded successfully. Inventory updated.' : ' was already recorded. Stock was not deducted again.';

        return redirect()->route($request->has('items') ? 'sales.show' : 'sales.index', $request->has('items') ? $sale : [])->with('success', 'Sale #'.str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT).$message);
    }

    public function show(Sale $sale): View
    {
        $items = DB::table('sale_items')->where('sale_id', $sale->id)->orderBy('id')->get();
        if ($items->isEmpty()) {
            $items->push((object) ['product_name' => $sale->product_name, 'quantity' => $sale->quantity, 'unit_price' => $sale->unit_price, 'total_price' => $sale->total_price]);
        }

        return view('sales.show', compact('sale', 'items'));
    }

    /**
     * @return array{products: Collection<int, Product>, customers: Collection<int, Customer>, submissionId: string}
     */
    private function formData(): array
    {
        return [
            'products' => Product::orderBy('name')->get(),
            'customers' => Customer::orderBy('full_name')->get(),
            'submissionId' => (string) Str::uuid(),
        ];
    }
}
