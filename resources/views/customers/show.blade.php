@extends('layouts.app')
@section('title', $customer->full_name)
@section('section', 'Customers')
@section('content')
<a class="back-link" href="{{ route('customers.index') }}"><x-icon name="back" />Back to customers</a><x-page-header :title="$customer->full_name" :description="'Customer #'.$customer->id" eyebrow="YOUR PEOPLE"><a class="button secondary" href="{{ route('customers.edit', $customer) }}"><x-icon name="edit" />Edit customer</a><x-confirm :action="route('customers.destroy', $customer)" :name="$customer->full_name" /></x-page-header><section class="panel detail-panel"><x-image :name="$customer->full_name" avatar large /><dl class="detail-grid"><div><dt>Email address</dt><dd>{{ $customer->email }}</dd></div><div><dt>Phone number</dt><dd>{{ $customer->phone ?: 'Not provided' }}</dd></div><div><dt>Customer since</dt><dd>{{ $customer->created_at->timezone(config('pos.display_timezone'))->format('M j, Y') }}</dd></div><div><dt>Sales recorded</dt><dd>{{ $customer->sales()->count() }}</dd></div></dl></section>
@endsection
