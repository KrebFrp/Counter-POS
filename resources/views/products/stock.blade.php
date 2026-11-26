@extends('layouts.app')
@section('title', 'Stock history · '.$product->name)
@section('section', 'Stock history')
@section('content')
<a class="back-link" href="{{ $product->trashed() ? route('products.index') : route('products.show', $product) }}"><x-icon name="back" />{{ $product->trashed() ? 'Back to products' : 'Back to product' }}</a>
<x-page-header :title="$product->name" description="Deliveries, sales, and stock corrections in one place." eyebrow="STOCK HISTORY" />
@if($product->trashed())
<div class="inline-note"><x-icon name="history" /><p>This product has been removed. Its stock history is preserved.</p></div>
@else
<div class="form-layout"><section class="panel form-panel"><div class="panel-heading"><div><h2>Adjust stock</h2><p>{{ number_format($product->stock_quantity) }} units currently available.</p></div></div>
<form action="{{ route('products.stock.store', $product) }}" method="post" class="record-form">@csrf
<input type="hidden" name="submission_id" value="{{ old('submission_id', $submissionId) }}"><x-error name="submission_id" />
<div class="form-grid"><div class="field"><label for="type">Action</label><select name="type" id="type" required aria-describedby="type-error"><option value="restock" @selected(old('type', 'restock') === 'restock')>Add stock / delivery</option><option value="removal" @selected(old('type') === 'removal')>Remove stock / damage or loss</option></select><x-error name="type" /></div>
<x-field name="quantity" label="Units to add or remove" type="number" min="1" max="2147483647" step="1" required /></div>
<x-field name="reason" label="Reason" maxlength="500" required placeholder="e.g. Supplier delivery, invoice 1042" />
<div class="form-actions"><button class="button primary"><x-icon name="check" />Save stock change</button></div></form></section>
<aside class="form-aside"><span class="aside-icon"><x-icon name="box" /></span><h3>Keep every unit accounted for.</h3><p>Enter the number of units being added or removed. Your name, the reason, and the before and after balances are saved automatically.</p><hr><h4>Correcting a stock count?</h4><p>Use <a href="{{ route('products.edit', $product) }}">Edit product</a> to set a counted total and explain the correction.</p></aside></div>
@endif
<section class="panel"><div class="panel-heading"><div><h2>Stock movements <span class="count-badge">{{ $movements->total() }}</span></h2><p>Opening balances mark the start of tracking. Earlier movements are not reconstructed.</p></div><span class="subtle-label">Newest first</span></div>
<div class="table-scroll"><table><thead><tr><th>Date / action</th><th>Change</th><th>Before</th><th>After</th><th>Staff</th><th>Reason</th></tr></thead><tbody>
@forelse($movements as $movement)
<tr><td><span class="stacked-cell"><strong>{{ ['opening' => 'Opening balance', 'restock' => 'Restock', 'removal' => 'Stock removal', 'correction' => 'Count correction', 'sale' => 'Sale'][$movement->type] ?? ucfirst($movement->type) }}</strong><small>{{ Illuminate\Support\Carbon::parse($movement->created_at)->timezone(config('pos.display_timezone'))->format('M j, Y · g:i A') }}</small></span></td><td><strong>{{ $movement->quantity_change > 0 ? '+' : '' }}{{ number_format($movement->quantity_change) }}</strong></td><td>{{ number_format($movement->stock_before) }}</td><td>{{ number_format($movement->stock_after) }}</td><td>{{ $movement->staff_name ?? 'System' }}</td><td>{{ $movement->reason }}</td></tr>
@empty
<tr><td colspan="6"><div class="empty-state"><h3>No stock movements yet.</h3><p>New sales and adjustments will appear here.</p></div></td></tr>
@endforelse
</tbody></table></div><x-pagination :records="$movements" /></section>
@endsection
