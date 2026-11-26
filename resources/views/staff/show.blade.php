@extends('layouts.app')
@section('title', $user->full_name)
@section('section', 'Staff')
@section('content')
<a class="back-link" href="{{ route('staff.index') }}"><x-icon name="back" />Back to staff</a><x-page-header :title="$user->full_name" :description="'Staff #'.$user->id" eyebrow="YOUR PEOPLE"><a class="button secondary" href="{{ route('staff.edit', $user) }}"><x-icon name="edit" />Edit staff member</a>@if($user->id !== auth()->id())<x-confirm :action="route('staff.destroy', $user)" :name="$user->full_name" />@endif</x-page-header><section class="panel detail-panel"><x-image :path="$user->avatar" :name="$user->full_name" avatar large /><dl class="detail-grid"><div><dt>Username</dt><dd>{{ $user->username }}</dd></div><div><dt>Access</dt><dd><span class="status-pill"><span></span>{{ ucfirst($user->role) }}</span></dd></div><div><dt>Joined</dt><dd>{{ $user->created_at->timezone(config('pos.display_timezone'))->format('M j, Y') }}</dd></div><div><dt>Sales recorded</dt><dd>{{ $user->sales()->count() }}</dd></div></dl></section>
@endsection
