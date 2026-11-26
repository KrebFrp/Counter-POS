<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Product;
use App\Services\AdjustStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(Product $product): View
    {
        return view('products.stock', [
            'product' => $product,
            'movements' => DB::table('stock_movements')->where('product_id', $product->id)->latest('id')->paginate(15),
            'submissionId' => (string) Str::uuid(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, Product $product, AdjustStock $adjustStock): RedirectResponse
    {
        $adjustStock->handle($product, $request->validated(), $request->user());

        return redirect()->route('products.stock.index', $product)->with('success', 'Stock change saved. Repeated submissions are counted only once.');
    }
}
