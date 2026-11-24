@extends('layouts.app')
@section('title', 'Checkout')
@section('section', 'Checkout')
@section('content')
<x-page-header title="Checkout" description="Build a cart, record payment, and print a receipt." eyebrow="AT THE COUNTER"><a class="button secondary" href="{{ route('sales.create') }}">Record one product</a><a class="button secondary" href="{{ route('sales.index') }}"><x-icon name="history" />Sales history</a></x-page-header>
@if($products->isEmpty())
<section class="panel"><div class="empty-state"><h3>Add a product to get started.</h3><p>An administrator needs to add a product before you can record a sale.</p>@can('manage-store')<a class="button primary" href="{{ route('products.create') }}">Add product</a>@endcan</div></section>
@else
<form action="{{ route('sales.store') }}" method="post" id="sale-form" data-currency="{{ config('pos.currency') }}">
@csrf<input type="hidden" name="submission_id" value="{{ Illuminate\Support\Str::isUuid(old('submission_id')) ? old('submission_id') : $submissionId }}"><x-error name="submission_id" />
<div class="form-layout sale-layout"><section class="panel form-panel"><div class="panel-heading"><div><h2>Your cart</h2><p>Choose each product once. Prices and stock are checked when you save.</p></div><button class="button secondary" type="button" id="add-cart-item">Add product</button></div>
<div class="record-form"><x-error name="items" /><div id="cart-items">
@foreach($cartItems as $index => $item)
<div class="cart-row" data-cart-row>
<div class="field"><label for="product-{{ $index }}">Product</label><select name="items[{{ $index }}][product_id]" id="product-{{ $index }}" data-product required><option value="">Select a product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->price }}" data-stock="{{ $product->stock_quantity }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->name }} · {{ App\Support\Money::display($product->price) }} · {{ $product->stock_quantity }} available</option>@endforeach</select><x-error :name="'items.'.$index.'.product_id'" /></div>
<div class="field"><label for="quantity-{{ $index }}">Quantity</label><input type="number" name="items[{{ $index }}][quantity]" id="quantity-{{ $index }}" data-quantity value="{{ $item['quantity'] ?? 1 }}" min="1" max="2147483647" step="1" required><x-error :name="'items.'.$index.'.quantity'" /></div>
<div class="cart-line-total"><span>Line total</span><strong data-line-total>—</strong><button type="button" class="button secondary" data-remove-item aria-label="Remove this product">Remove</button></div>
</div>
@endforeach
</div>
<noscript><p class="field-hint">Enable JavaScript to add more products and preview totals. You can submit the product shown here without it.</p></noscript>
<div class="field"><label for="customer_id">Customer (optional)</label><select name="customer_id" id="customer_id"><option value="">Walk in customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->full_name }}</option>@endforeach</select><x-error name="customer_id" /></div>
<div class="field"><label for="payment_method">Payment method</label><select name="payment_method" id="payment_method" required><option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option><option value="card" @selected(old('payment_method') === 'card')>Card</option><option value="ewallet" @selected(old('payment_method') === 'ewallet')>E-wallet</option></select><x-error name="payment_method" /><p class="field-hint">Card and e-wallet payments must be completed through your payment provider before recording the sale.</p></div>
<x-field name="amount_tendered" :label="'Cash received ('.config('pos.currency').')'" type="number" min="0" max="99999999.99" step="0.01" hint="For cash payments only. Enter the amount handed over by the customer." />
<div class="inline-note"><x-icon name="shield" /><p>Recorded under <strong>{{ auth()->user()->full_name }}</strong>. Every product is saved together with its stock movement.</p></div>
<div class="form-actions"><a class="button secondary" href="{{ route('products.index') }}">Cancel</a><button class="button primary"><x-icon name="check" />Complete sale</button></div>
</div></section>
<aside class="sale-summary panel"><div class="panel-heading"><h2>Checkout summary</h2></div><div class="summary-body"><span class="summary-icon"><x-icon name="store" /></span><p id="summary-product">Your cart</p><dl><div><dt>Products</dt><dd id="summary-lines">0</dd></div><div><dt>Total units</dt><dd id="summary-quantity">0</dd></div><div><dt>Change due</dt><dd id="summary-change" aria-live="polite">—</dd></div></dl><div class="summary-total"><span>Estimated total</span><strong id="summary-total" aria-live="polite">{{ config('pos.currency') }} 0.00</strong></div><p class="field-hint">Final prices are checked when you complete the sale.</p></div><div class="summary-footer"><x-icon name="history" />A printable receipt follows checkout</div></aside></div>
</form>
@endif
@endsection
