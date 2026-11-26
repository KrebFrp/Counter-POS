<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Middleware\EnsureActiveStaff;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', EnsureActiveStaff::class, 'auth.session'])->group(function () {
    Route::get('/', fn () => redirect()->route('products.index'))->name('home');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::middleware('can:manage-store')->group(function () {
        Route::get('products/{product}/stock', [StockAdjustmentController::class, 'index'])->withTrashed()->name('products.stock.index');
        Route::post('products/{product}/stock', [StockAdjustmentController::class, 'store'])->name('products.stock.store');
        Route::resource('products', ProductController::class)->except('index', 'show');
        Route::resource('staff', StaffController::class)->parameters(['staff' => 'user']);
    });
    Route::resource('products', ProductController::class)->only('index', 'show');
    Route::resource('customers', CustomerController::class);
    Route::get('checkout', [SaleController::class, 'checkout'])->name('sales.checkout');
    Route::resource('sales', SaleController::class)->only('index', 'create', 'store', 'show');
});
