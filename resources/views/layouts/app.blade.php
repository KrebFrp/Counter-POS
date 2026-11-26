<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142b35">
    <title>@yield('title', 'Point of Sale') · Counter</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <a href="#main" class="skip-link">Skip to content</a>
    <aside class="sidebar">
        <a href="{{ route('products.index') }}" class="brand">
            <span class="brand-mark"><x-icon name="grid" /></span>
            counter<span class="brand-dot">.</span>
        </a>
        <div class="workspace">
            <span class="store-mark"><x-icon name="store" /></span>
            <div>
                <strong>My store</strong>
                <small>Point of sale workspace</small>
            </div>
            <span class="live-dot" title="Store workspace"></span>
        </div>
        <p class="nav-label">WORKSPACE</p>
        <nav aria-label="Main navigation">
            @foreach (['products' => ['box', 'Products'], 'customers' => ['users', 'Customers'], 'staff' => ['badge', 'Staff']] as $section => [$icon, $label])
                @if($section === 'staff' && ! auth()->user()->isAdmin()) @continue @endif
                <a href="{{ route($section.'.index') }}" @class(['nav-link', 'active' => request()->routeIs($section.'.*')]) @if(request()->routeIs($section.'.*')) aria-current="page" @endif><x-icon :name="$icon" />{{ $label }}</a>
            @endforeach
            <p class="nav-label sales-label">SALES</p>
            <a href="{{ route('sales.create') }}" @class(['nav-link', 'active' => request()->routeIs('sales.create')]) @if(request()->routeIs('sales.create')) aria-current="page" @endif><x-icon name="plus-circle" />Record sale</a>
            <a href="{{ route('sales.checkout') }}" @class(['nav-link', 'active' => request()->routeIs('sales.checkout')]) @if(request()->routeIs('sales.checkout')) aria-current="page" @endif><x-icon name="store" />Checkout</a>
            <a href="{{ route('sales.index') }}" @class(['nav-link', 'active' => request()->routeIs('sales.index', 'sales.show')]) @if(request()->routeIs('sales.index', 'sales.show')) aria-current="page" @endif><x-icon name="history" />Sales history</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-note">
                <x-icon name="shield" />
                <span>Every sale, accounted for.<small>Simple tools. Clear records.</small></span>
            </div>
            <div class="profile">
                <x-image :path="auth()->user()->avatar" :name="auth()->user()->full_name" avatar />
                <div class="profile-name">
                    <strong>{{ auth()->user()->full_name }}</strong>
                    <small>{{ ucfirst(auth()->user()->role) }}</small>
                </div>
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="icon-button" aria-label="Log out" title="Log out">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <div class="main-shell">
        <header class="topbar">
            <span>
                Workspace <span class="breadcrumb-divider">/</span>
                <strong>@yield('section', 'Products')</strong>
            </span>
            <div class="topbar-meta">
                <span class="live-dot"></span>
                <span class="workspace-status">Staff workspace</span>
                <span class="currency-tag">{{ config('pos.currency') }}</span>
                <form class="mobile-logout" action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="icon-button" aria-label="Log out" title="Log out">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </header>
        <main id="main" class="content">
            <x-flash />
            @yield('content')
            <footer class="page-footer">
                <span>Counter · Point of sale</span>
                <span>Made for the everyday business.</span>
            </footer>
        </main>
    </div>
</body>
</html>
