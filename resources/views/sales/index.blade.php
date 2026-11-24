@extends('layouts.app')
@section('title', 'Sales history')
@section('section', 'Sales history')
@section('content')
<x-page-header title="Sales history" description="Every checkout, with its saved details and receipt." eyebrow="YOUR BUSINESS, ON RECORD"><a class="button primary" href="{{ route('sales.create') }}"><x-icon name="plus" />Record sale</a></x-page-header>
<section class="panel"><div class="panel-heading"><div><h2>Transactions <span class="count-badge">{{ $sales->total() }}</span></h2><p>Each row is one checkout. Names, prices, and currency stay as recorded.</p></div><span class="subtle-label">{{ config('pos.display_timezone') }} · Newest first</span></div>
<div class="table-scroll"><table class="sales-table"><thead><tr><th>Sale / date</th><th>Products / unit prices</th><th>Customer</th><th>Staff</th><th>Units</th><th>Payment</th><th class="align-right">Total / receipt</th></tr></thead><tbody>
@forelse($sales as $sale)
<tr><td><span class="stacked-cell"><strong>#{{ str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) }}</strong><small>{{ $sale->created_at->timezone(config('pos.display_timezone'))->format('M j, Y · g:i A') }}</small>@if(! $sale->details_captured_at_sale)<small title="Names and currency were captured when transaction history was upgraded.">Earlier record</small>@endif</span></td>
<td>@foreach($saleItems->get($sale->id, collect([(object) ['product_name' => $sale->product_name, 'quantity' => $sale->quantity, 'unit_price' => $sale->unit_price]])) as $item)<span class="stacked-cell"><strong>{{ $item->product_name ?? 'Unavailable product' }}</strong><small>{{ number_format($item->quantity) }} × {{ $item->unit_price !== null ? App\Support\Money::display($item->unit_price, $sale->currency) : 'Price unavailable' }}</small></span>@endforeach</td>
<td>{{ $sale->customer_name ?? 'Walk in customer' }}</td><td>{{ $sale->staff_name ?? 'Unavailable staff' }}</td><td>{{ number_format($sale->quantity) }}</td><td>{{ ['cash' => 'Cash', 'card' => 'Card', 'ewallet' => 'E-wallet'][$sale->payment_method] ?? 'Not recorded' }}</td><td class="align-right money"><strong>{{ App\Support\Money::display($sale->total_price, $sale->currency) }}</strong><br><a href="{{ route('sales.show', $sale) }}">View receipt</a></td></tr>
@empty<tr><td colspan="7"><div class="empty-state"><h3>Your next sale starts the story.</h3><p>Transactions will appear here as soon as you record a sale.</p><a href="{{ route('sales.create') }}" class="button primary">Record sale</a></div></td></tr>@endforelse
</tbody></table></div><x-pagination :records="$sales" /></section>
@endsection
