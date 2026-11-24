@extends('layouts.app')
@section('title', 'Receipt #'.$sale->id)
@section('section', 'Sales history')
@section('content')
<div class="receipt-actions"><a class="button secondary" href="{{ route('sales.index') }}">Sales history</a><a class="button secondary" href="{{ route('sales.checkout') }}">New checkout</a><button class="button primary" type="button" data-print-receipt>Print receipt</button></div>
<section class="panel receipt" aria-label="Sale receipt">
<div class="receipt-heading"><h1>Counter</h1><p>Sales receipt</p><strong>#{{ str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) }}</strong><p>{{ $sale->created_at->timezone(config('pos.display_timezone'))->format('M j, Y · g:i A') }} · {{ config('pos.display_timezone') }}</p></div>
<dl class="receipt-meta"><div><dt>Customer</dt><dd>{{ $sale->customer_name ?? 'Walk in customer' }}</dd></div><div><dt>Served by</dt><dd>{{ $sale->staff_name ?? 'Unavailable staff' }}</dd></div></dl>
@if(! $sale->details_captured_at_sale)<p class="field-hint">Earlier record: names and currency reflect the details available when history was upgraded.</p>@endif
<div class="table-scroll"><table><thead><tr><th>Product</th><th>Qty</th><th class="align-right">Unit price</th><th class="align-right">Total</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->product_name ?? 'Unavailable product' }}</td><td>{{ number_format($item->quantity) }}</td><td class="align-right">{{ $item->unit_price !== null ? App\Support\Money::display($item->unit_price, $sale->currency) : '—' }}</td><td class="align-right">{{ App\Support\Money::display($item->total_price, $sale->currency) }}</td></tr>@endforeach</tbody></table></div>
<dl class="receipt-totals"><div><dt>Total</dt><dd>{{ App\Support\Money::display($sale->total_price, $sale->currency) }}</dd></div><div><dt>Payment</dt><dd>{{ ['cash' => 'Cash', 'card' => 'Card', 'ewallet' => 'E-wallet'][$sale->payment_method] ?? 'Not recorded' }}</dd></div>@if($sale->payment_method === 'cash')<div><dt>Cash received</dt><dd>{{ App\Support\Money::display($sale->amount_tendered, $sale->currency) }}</dd></div><div><dt>Change given</dt><dd>{{ App\Support\Money::display($sale->change_given, $sale->currency) }}</dd></div>@endif</dl>
<p class="receipt-thanks">Thank you for shopping with us.</p>
</section>
@endsection
