<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\RecordStockMovement;
use App\Services\SaveImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::latest('id')->paginate(12),
            'productCount' => Product::count(),
            'lowStockCount' => Product::where('stock_quantity', '<=', 5)->count(),
            'unitCount' => Product::sum('stock_quantity'),
        ]);
    }

    public function create(): View
    {
        return view('products.form', ['product' => new Product]);
    }

    public function store(StoreProductRequest $request, SaveImage $images, RecordStockMovement $movements): RedirectResponse
    {
        $product = $images->handle($request->file('image'), 'products', false, function ($path) use ($request, $movements) {
            $product = Product::create(
                $request->safe()->only('name', 'price', 'stock_quantity') + ['image' => $path]
            );
            $movements->handle($product, 0, 'opening', 'Initial stock when product was created.', $request->user());

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('products.form', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product, SaveImage $images, RecordStockMovement $movements): RedirectResponse
    {
        $images->handle($request->file('image'), 'products', false, function ($path) use ($request, $product, $images, $movements) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $data = $request->validated();
            $adjustingStock = (int) $data['stock_quantity'] !== (int) $data['original_stock'];
            if ($adjustingStock && $locked->stock_quantity !== (int) $data['original_stock']) {
                throw ValidationException::withMessages(['stock_quantity' => 'Stock changed since you opened this form. Reload the page before adjusting stock. Current stock is '.$locked->stock_quantity.' units.']);
            }

            $locked->fill($request->safe()->only('name', 'price'));
            $stockBefore = $locked->stock_quantity;
            if ($adjustingStock) {
                $locked->stock_quantity = (int) $data['stock_quantity'];
            }
            if ($path) {
                $images->removeAfterCommit($locked->image);
                $locked->image = $path;
            }
            $locked->save();
            if ($adjustingStock) {
                $movements->handle($locked, $stockBefore, 'correction', $data['stock_reason'], $request->user());
            }

            return $locked;
        });

        return redirect()->route('products.show', $product)->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        DB::transaction(fn () => Product::query()->lockForUpdate()->findOrFail($product->id)->delete(), 3);

        return redirect()->route('products.index')->with('success', 'Product removed. Previous sales are preserved.');
    }
}
