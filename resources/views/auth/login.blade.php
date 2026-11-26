<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#142b35">
    <title>Sign in · Counter</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="login-body">
    <section class="login-story"><a class="brand" href="{{ route('login') }}"><span class="brand-mark"><x-icon name="grid" /></span>counter<span class="brand-dot">.</span></a><div class="story-copy"><span class="story-pill"><span class="live-dot"></span> YOUR EVERYDAY POINT OF SALE</span><h1>A little less admin.<br>A lot more business.</h1><p>Products, people, and every sale in between.<br>Keep your store moving, all from one place.</p><div class="login-preview" aria-hidden="true"><div class="preview-top"><span class="preview-icon"><x-icon name="store" /></span><span>Everything in its place<small>Your store, at a glance</small></span><x-icon name="check" /></div><div class="preview-row"><span class="preview-icon"><x-icon name="box" /></span><span>Inventory<small>Know what’s on your shelves</small></span><span class="preview-check"><x-icon name="check" /></span></div><div class="preview-row"><span class="preview-icon"><x-icon name="history" /></span><span>Sales history<small>A clear record of every transaction</small></span><span class="preview-check"><x-icon name="check" /></span></div></div></div><p class="story-footer">Simple tools. Clear records. Better days at the counter.</p></section>
    <main class="login-main"><div class="login-card"><p class="eyebrow">WELCOME TO COUNTER</p><h2>Good to see you.</h2><p class="page-description">Sign in to your staff workspace to get started.</p><x-flash /><form method="post" action="{{ route('login.store') }}" class="login-form">@csrf<x-field name="username" label="Username" required maxlength="50" autocomplete="username" autofocus placeholder="Enter your username" /><x-field name="password" label="Password" type="password" required autocomplete="current-password" placeholder="Enter your password" /><button class="button primary full-width">Sign in to workspace <x-icon name="arrow" /></button></form><p class="login-help"><x-icon name="shield" />Staff access only. Contact your store administrator<br>if you need an account or a password change.</p></div><small class="login-copyright">Counter · Point of sale</small></main>
</body></html>
