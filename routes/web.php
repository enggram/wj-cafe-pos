<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\SalesReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Menu Management Routes
Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::post('/menu', [MenuController::class, 'store'])->name('menu.store');
Route::put('/menu/{id}', [MenuController::class, 'update'])->name('menu.update');
Route::patch('/menu/{id}/deactivate', [MenuController::class, 'deactivate'])->name('menu.deactivate');
Route::post('/menu/{menuItem}/sub-varieties', [MenuController::class, 'storeSubVariety'])->name('menu.sub-varieties.store');

// Category Management Routes
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::patch('/categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
Route::patch('/categories/{category}/activate', [CategoryController::class, 'activate'])->name('categories.activate');

// Order Management Routes
Route::get('/orders/tables', [OrderController::class, 'tableOverview'])->name('orders.tables');
Route::get('/orders/create/{table}', [OrderController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::post('/orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.addItems');

// Billing Routes
Route::post('/billing/generate/{table}', [BillingController::class, 'generate'])->name('billing.generate');
Route::get('/billing/{table}', [BillingController::class, 'show'])->name('billing.show');
Route::post('/billing/{bill}/settle', [BillingController::class, 'settle'])->name('billing.settle');
Route::get('/billing/{table}/settled', [BillingController::class, 'settled'])->name('billing.settled');

// Sales Reporting Routes
Route::get('/reports/sales', [SalesReportController::class, 'index'])->name('reports.sales');

// Inventory Purchase Tracking Routes
Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
Route::get('/inventory/daily', [InventoryController::class, 'dailyView'])->name('inventory.daily');
Route::get('/inventory/monthly', [InventoryController::class, 'monthlyView'])->name('inventory.monthly');

// Profit & Loss Reporting Routes
Route::get('/reports/profit-loss', [ProfitLossController::class, 'index'])->name('reports.profit-loss');
