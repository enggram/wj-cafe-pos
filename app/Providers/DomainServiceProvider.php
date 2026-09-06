<?php

namespace App\Providers;

use App\Contracts\BillingServiceInterface;
use App\Contracts\ExpenseServiceInterface;
use App\Contracts\InventoryServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\OrderServiceInterface;
use App\Contracts\ProfitLossServiceInterface;
use App\Contracts\SalesReportServiceInterface;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register domain service bindings.
     */
    public function register(): void
    {
        // Menu Management
        $this->app->bind(MenuServiceInterface::class, \App\Services\MenuService::class);

        // Order Management
        $this->app->bind(OrderServiceInterface::class, \App\Services\OrderService::class);

        // Billing
        $this->app->bind(BillingServiceInterface::class, \App\Services\BillingService::class);

        // Sales Reporting
        $this->app->bind(SalesReportServiceInterface::class, \App\Services\SalesReportService::class);

        // Inventory Tracking
        $this->app->bind(InventoryServiceInterface::class, \App\Services\InventoryService::class);

        // Profit/Loss Reporting
        $this->app->bind(ProfitLossServiceInterface::class, \App\Services\ProfitLossService::class);

        // Expense Tracking
        $this->app->bind(ExpenseServiceInterface::class, \App\Services\ExpenseService::class);
    }

    /**
     * Bootstrap domain services.
     */
    public function boot(): void
    {
        //
    }
}
