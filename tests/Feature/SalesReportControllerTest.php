<?php

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Models\User;
use App\Enums\UserRole;
use Carbon\Carbon;

beforeEach(function () {
    // Reports are admin-only — authenticate as an admin
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $this->category = Category::create(['name' => 'Food', 'is_active' => true]);
    $this->menuItem = MenuItem::create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'price' => 10.00,
        'is_active' => true,
    ]);
    $this->table = Table::create(['table_number' => 1, 'status' => 'vacant']);
});

function createCompletedOrderWithBillForController(Table $table, MenuItem $menuItem, int $quantity, Carbon $billedAt): Bill
{
    $order = Order::create([
        'table_id' => $table->id,
        'status' => OrderStatus::Completed,
        'ordered_at' => $billedAt->copy()->subHour(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => $quantity,
        'unit_price' => $menuItem->price,
    ]);

    return Bill::create([
        'order_id' => $order->id,
        'table_id' => $table->id,
        'grand_total' => $menuItem->price * $quantity,
        'status' => 'paid',
        'billed_at' => $billedAt,
    ]);
}

it('renders daily sales report for today by default', function () {
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 2, now());

    $response = $this->get('/reports/sales');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->has('report')
        ->where('report.totalRevenue', 20)
        ->where('report.totalOrders', 1)
        ->has('report.itemSales')
        ->has('report.topItems')
        ->has('report.periodLabel')
        ->has('filters')
        ->where('filters.period', 'daily')
    );
});

it('renders daily report for a specific date', function () {
    $date = Carbon::parse('2024-06-15');
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 3, $date);

    $response = $this->get('/reports/sales?period=daily&date=2024-06-15');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->where('report.totalRevenue', 30)
        ->where('report.totalOrders', 1)
        ->where('filters.period', 'daily')
        ->where('filters.date', '2024-06-15')
    );
});

it('renders weekly report', function () {
    $startDate = Carbon::parse('2024-06-10');
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 1, $startDate);
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 2, $startDate->copy()->addDays(3));

    $response = $this->get('/reports/sales?period=weekly&start_date=2024-06-10');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->where('report.totalRevenue', 30)
        ->where('report.totalOrders', 2)
        ->where('filters.period', 'weekly')
        ->where('filters.start_date', '2024-06-10')
    );
});

it('renders monthly report', function () {
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 5, Carbon::parse('2024-03-15'));

    $response = $this->get('/reports/sales?period=monthly&year=2024&month=3');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->where('report.totalRevenue', 50)
        ->where('report.totalOrders', 1)
        ->where('filters.period', 'monthly')
        ->where('filters.year', 2024)
        ->where('filters.month', 3)
    );
});

it('renders yearly report', function () {
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 4, Carbon::parse('2024-07-01'));
    createCompletedOrderWithBillForController($this->table, $this->menuItem, 6, Carbon::parse('2024-11-20'));

    $response = $this->get('/reports/sales?period=yearly&year=2024');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->where('report.totalRevenue', 100)
        ->where('report.totalOrders', 2)
        ->where('filters.period', 'yearly')
        ->where('filters.year', 2024)
    );
});

it('returns zero values when no sales data exists', function () {
    $response = $this->get('/reports/sales?period=daily&date=2020-01-01');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->where('report.totalRevenue', 0)
        ->where('report.totalOrders', 0)
        ->where('report.itemSales', [])
        ->where('report.topItems', [])
    );
});

it('includes top items in report', function () {
    $items = [];
    for ($i = 1; $i <= 7; $i++) {
        $items[] = MenuItem::create([
            'category_id' => $this->category->id,
            'name' => "Item $i",
            'price' => 5.00,
            'is_active' => true,
        ]);
    }

    $date = Carbon::parse('2024-06-15');
    foreach ($items as $index => $item) {
        createCompletedOrderWithBillForController($this->table, $item, ($index + 1) * 2, $date);
    }

    $response = $this->get('/reports/sales?period=daily&date=2024-06-15');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Reports/Sales')
        ->has('report.topItems', 5)
    );
});
