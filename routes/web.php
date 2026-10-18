<?php

use App\Http\Controllers\Admin\CashCutController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'index'])->name('menu');
Route::get('/caja', [PosController::class, 'index'])->name('pos');

Route::prefix('api')->name('api.')->group(function () {
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
    Route::get('orders/{order:uuid}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('admin/session', [SessionController::class, 'show'])->name('admin.session.show');
    Route::post('admin/session', [SessionController::class, 'store'])->middleware('throttle:8,1')->name('admin.session.store');
    Route::delete('admin/session', [SessionController::class, 'destroy'])->name('admin.session.destroy');

    Route::middleware('pos.admin')->group(function () {
        Route::get('catalog', [PosController::class, 'catalog'])->name('catalog');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    });

    Route::middleware('pos.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('sales/export', [DashboardController::class, 'export'])->name('sales.export');

        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');

        Route::post('cuts/close-day', [CashCutController::class, 'closeDay'])->name('cuts.close-day');
        Route::get('cuts/{cashCut}', [CashCutController::class, 'show'])->name('cuts.show');

        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
