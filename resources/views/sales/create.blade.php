@extends('layouts.app')
@section('title', 'Record sale')
@section('section', 'Record sale')
@section('content')
<x-page-header title="Record a sale" description="Choose one product, an optional customer, and the quantity sold." eyebrow="AT THE COUNTER"><a class="button secondary" href="{{ route('sales.checkout') }}">Multi-product checkout</a><a class="button secondary" href="{{ route('sales.index') }}"><x-icon name="history" />Sales history</a></x-page-header>
@if($products->isEmpty())
<section class="panel"><div class="empty-state"><h3>Add a product to get started.</h3><p>An administrator needs to add a product before you can record a sale.</p>@can('manage-store')<a class="button primary" href="{{ route('products.create') }}">Add product</a>@endcan</div></section>
@else
<div class="form-layout sale-layout"><section class="panel form-panel"><div class="panel-heading"><h2>Sale details</h2></div>
<form action="{{ route('sales.store') }}" method="post" class="record-form" id="single-sale-form" data-currency="{{ config('pos.currency') }}">@csrf
<input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::isUuid(old('submission_id')) ? old('submission_id') : $submissionId }}"><x-error name="submission_id" />
<div class="field"><label for="product_id">Product</label><select name="product_id" id="product_id" required aria-describedby="product_id-error"><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock_quantity }}" @selected(is_scalar(old('product_id')) && (string) old('product_id') === (string) $product->id)>{{ $product->name }} · {{ App\Support\Money::display($product->price) }} · {{ $product->stock_quantity }} available</option>@endforeach</select><x-error name="product_id" /><p class="field-hint" id="stock-hint">Stock and price are checked again when the sale is recorded.</p></div>
<div class="field"><label for="customer_id">Customer (optional)</label><select name="customer_id" id="customer_id" aria-describedby="customer_id-error"><option value="">Walk in customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(is_scalar(old('customer_id')) && (string) old('customer_id') === (string) $customer->id)>{{ $customer->full_name }}</option>@endforeach</select><x-error name="customer_id" /></div>
<div class="field"><label for="quantity">Quantity</label><input type="number" name="quantity" id="quantity" value="{{ is_scalar(old('quantity', 1)) ? old('quantity', 1) : 1 }}" min="1" max="2147483647" step="1" required aria-describedby="quantity-error"><x-error name="quantity" /></div>
<div class="inline-note"><x-icon name="shield" /><p>This sale is recorded under <strong>{{ auth()->user()->full_name }}</strong>. Its total uses the product's current price.</p></div>
<div class="form-actions"><a class="button secondary" href="{{ route('products.index') }}">Cancel</a><button class="button primary"><x-icon name="check" />Record sale</button></div>
</form></section>
<aside class="sale-summary panel"><div class="panel-heading"><h2>Sale summary</h2></div><div class="summary-body"><span class="summary-icon"><x-icon name="store" /></span><p id="summary-product">Choose a product</p><dl><div><dt>Unit price</dt><dd id="summary-price">—</dd></div><div><dt>Quantity</dt><dd id="summary-quantity">—</dd></div></dl><div class="summary-total"><span>Estimated total</span><strong id="summary-total" aria-live="polite">{{ config('pos.currency') }} 0.00</strong></div><p class="field-hint">The server calculates the saved total and rejects quantities above available stock.</p></div><div class="summary-footer"><x-icon name="history" />Saved to sales history</div></aside></div>
@endif
@endsection
