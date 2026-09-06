<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfitLossController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Guest (login) ────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

// ── Authenticated (any logged-in user: admin or staff) ───────
Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Home → role-based landing (admin: menu, staff: orders)
    Route::get('/', function () {
        return redirect(auth()->user()->isAdmin()
            ? route('menu.index')
            : route('orders.tables'));
    });

    // Orders & Billing — staff + admin
    Route::get('/orders/tables', [OrderController::class, 'tableOverview'])->name('orders.tables');
    Route::get('/orders/create/{table}', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/orders/{order}/items', [OrderController::class, 'addItems'])->name('orders.addItems');

    Route::post('/billing/generate/{table}', [BillingController::class, 'generate'])->name('billing.generate');
    Route::get('/billing/{table}', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/{bill}/settle', [BillingController::class, 'settle'])->name('billing.settle');
    Route::get('/billing/{table}/settled', [BillingController::class, 'settled'])->name('billing.settled');

    // Menu — staff can VIEW (read-only list used by order screen already);
    // the menu management page is admin-only below.

    // ── Admin only ───────────────────────────────────────────
    Route::middleware('admin')->group(function () {

        // Menu management
        Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
        Route::post('/menu', [MenuController::class, 'store'])->name('menu.store');
        Route::put('/menu/{id}', [MenuController::class, 'update'])->name('menu.update');
        Route::patch('/menu/{id}/deactivate', [MenuController::class, 'deactivate'])->name('menu.deactivate');
        Route::post('/menu/{menuItem}/sub-varieties', [MenuController::class, 'storeSubVariety'])->name('menu.sub-varieties.store');

        // Category management
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
        Route::patch('/categories/{category}/activate', [CategoryController::class, 'activate'])->name('categories.activate');

        // Reports
        Route::get('/reports/sales', [SalesReportController::class, 'index'])->name('reports.sales');
        Route::get('/reports/profit-loss', [ProfitLossController::class, 'index'])->name('reports.profit-loss');

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('/inventory/daily', [InventoryController::class, 'dailyView'])->name('inventory.daily');
        Route::get('/inventory/monthly', [InventoryController::class, 'monthlyView'])->name('inventory.monthly');

        // Expense Categories
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::patch('/expense-categories/{expenseCategory}/deactivate', [ExpenseCategoryController::class, 'deactivate'])->name('expense-categories.deactivate');
        Route::patch('/expense-categories/{expenseCategory}/activate', [ExpenseCategoryController::class, 'activate'])->name('expense-categories.activate');

        // Expenses
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/expenses/daily', [ExpenseController::class, 'dailyView'])->name('expenses.daily');
        Route::get('/expenses/monthly', [ExpenseController::class, 'monthlyView'])->name('expenses.monthly');

        // User management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Database Backup & Restore
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/download/sqlite', [BackupController::class, 'downloadSqlite'])->name('backup.download.sqlite');
        Route::get('/backup/download/sql', [BackupController::class, 'downloadSql'])->name('backup.download.sql');
        Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup.restore');
    });
});
