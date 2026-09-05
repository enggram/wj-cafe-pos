<?php

use App\Contracts\SalesReportServiceInterface;
use App\DTOs\SalesReportDTO;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = app(SalesReportServiceInterface::class);
});

/**
 * Helper to create a completed order with a bill and order items.
 */
function createCompletedOrderWithBill(Carbon $billedAt, array $items): Bill
{
    $table = Table::factory()->create();
    $order = Order::factory()->completed()->create(['table_id' => $table->id]);

    $grandTotal = 0;
    foreach ($items as $item) {
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item['menu_item_id'],
            'unit_price' => $item['unit_price'],
            'quantity' => $item['quantity'],
        ]);
        $grandTotal += round($item['unit_price'] * $item['quantity'], 2);
    }

    return Bill::factory()->create([
        'order_id' => $order->id,
        'table_id' => $table->id,
        'grand_total' => round($grandTotal, 2),
        'status' => BillStatus::Paid,
        'billed_at' => $billedAt,
    ]);
}

describe('dailyReport', function () {
    it('returns correct revenue and order count for a given date', function () {
        $date = Carbon::create(2024, 1, 15);
        $menuItem = MenuItem::factory()->create(['name' => 'Green Tea', 'price' => '5.00']);

        createCompletedOrderWithBill($date->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '5.00', 'quantity' => 2],
        ]);
        createCompletedOrderWithBill($date->copy()->setTime(14, 30), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '5.00', 'quantity' => 3],
        ]);

        $report = $this->service->dailyReport($date);

        expect($report)->toBeInstanceOf(SalesReportDTO::class)
            ->and($report->totalRevenue)->toBe(25.0)
            ->and($report->totalOrders)->toBe(2)
            ->and($report->periodLabel)->toBe('Daily: Jan 15, 2024');
    });

    it('excludes orders from other dates', function () {
        $targetDate = Carbon::create(2024, 1, 15);
        $otherDate = Carbon::create(2024, 1, 16);
        $menuItem = MenuItem::factory()->create(['name' => 'Coffee']);

        createCompletedOrderWithBill($targetDate->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '4.00', 'quantity' => 1],
        ]);
        createCompletedOrderWithBill($otherDate->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '4.00', 'quantity' => 5],
        ]);

        $report = $this->service->dailyReport($targetDate);

        expect($report->totalRevenue)->toBe(4.0)
            ->and($report->totalOrders)->toBe(1);
    });

    it('excludes non-completed orders', function () {
        $date = Carbon::create(2024, 1, 15);
        $menuItem = MenuItem::factory()->create(['name' => 'Juice']);
        $table = Table::factory()->create();

        // Active order with a bill (should not count)
        $activeOrder = Order::factory()->create([
            'table_id' => $table->id,
            'status' => OrderStatus::Active,
        ]);
        OrderItem::factory()->create([
            'order_id' => $activeOrder->id,
            'menu_item_id' => $menuItem->id,
            'unit_price' => '10.00',
            'quantity' => 1,
        ]);
        Bill::factory()->create([
            'order_id' => $activeOrder->id,
            'table_id' => $table->id,
            'grand_total' => '10.00',
            'billed_at' => $date,
        ]);

        // Completed order (should count)
        createCompletedOrderWithBill($date->copy()->setTime(12, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '5.00', 'quantity' => 1],
        ]);

        $report = $this->service->dailyReport($date);

        expect($report->totalRevenue)->toBe(5.0)
            ->and($report->totalOrders)->toBe(1);
    });

    it('returns the correct period label format', function () {
        $date = Carbon::create(2024, 3, 5);
        $report = $this->service->dailyReport($date);

        expect($report->periodLabel)->toBe('Daily: Mar 05, 2024');
    });
});

describe('weeklyReport', function () {
    it('aggregates data for 7-day period starting from startDate', function () {
        $startDate = Carbon::create(2024, 1, 15);
        $menuItem = MenuItem::factory()->create(['name' => 'Latte', 'price' => '6.00']);

        // Day 1
        createCompletedOrderWithBill($startDate->copy()->setTime(9, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.00', 'quantity' => 2],
        ]);
        // Day 4
        createCompletedOrderWithBill($startDate->copy()->addDays(3)->setTime(14, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.00', 'quantity' => 3],
        ]);
        // Day 7 (last day of the week)
        createCompletedOrderWithBill($startDate->copy()->addDays(6)->setTime(20, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.00', 'quantity' => 1],
        ]);

        $report = $this->service->weeklyReport($startDate);

        expect($report->totalRevenue)->toBe(36.0)
            ->and($report->totalOrders)->toBe(3)
            ->and($report->periodLabel)->toBe('Weekly: Jan 15 - Jan 21, 2024');
    });

    it('excludes orders outside the 7-day window', function () {
        $startDate = Carbon::create(2024, 1, 15);
        $menuItem = MenuItem::factory()->create(['name' => 'Espresso']);

        // Inside the period
        createCompletedOrderWithBill($startDate->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '3.00', 'quantity' => 1],
        ]);
        // Day 8 (outside)
        createCompletedOrderWithBill($startDate->copy()->addDays(7)->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '3.00', 'quantity' => 5],
        ]);

        $report = $this->service->weeklyReport($startDate);

        expect($report->totalRevenue)->toBe(3.0)
            ->and($report->totalOrders)->toBe(1);
    });
});

describe('monthlyReport', function () {
    it('aggregates data for the full calendar month', function () {
        $menuItem = MenuItem::factory()->create(['name' => 'Cappuccino', 'price' => '7.00']);

        // Jan 1
        createCompletedOrderWithBill(Carbon::create(2024, 1, 1, 8, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '7.00', 'quantity' => 2],
        ]);
        // Jan 15
        createCompletedOrderWithBill(Carbon::create(2024, 1, 15, 12, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '7.00', 'quantity' => 3],
        ]);
        // Jan 31
        createCompletedOrderWithBill(Carbon::create(2024, 1, 31, 23, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '7.00', 'quantity' => 1],
        ]);

        $report = $this->service->monthlyReport(2024, 1);

        expect($report->totalRevenue)->toBe(42.0)
            ->and($report->totalOrders)->toBe(3)
            ->and($report->periodLabel)->toBe('Monthly: January 2024');
    });

    it('excludes orders from other months', function () {
        $menuItem = MenuItem::factory()->create(['name' => 'Mocha']);

        // January (target)
        createCompletedOrderWithBill(Carbon::create(2024, 1, 15, 10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '8.00', 'quantity' => 1],
        ]);
        // February (outside)
        createCompletedOrderWithBill(Carbon::create(2024, 2, 1, 10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '8.00', 'quantity' => 5],
        ]);

        $report = $this->service->monthlyReport(2024, 1);

        expect($report->totalRevenue)->toBe(8.0)
            ->and($report->totalOrders)->toBe(1);
    });
});

describe('yearlyReport', function () {
    it('aggregates data for the full calendar year', function () {
        $menuItem = MenuItem::factory()->create(['name' => 'Hot Chocolate', 'price' => '6.50']);

        // January
        createCompletedOrderWithBill(Carbon::create(2024, 1, 10, 9, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.50', 'quantity' => 2],
        ]);
        // June
        createCompletedOrderWithBill(Carbon::create(2024, 6, 15, 14, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.50', 'quantity' => 4],
        ]);
        // December
        createCompletedOrderWithBill(Carbon::create(2024, 12, 31, 22, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '6.50', 'quantity' => 1],
        ]);

        $report = $this->service->yearlyReport(2024);

        // (6.50*2) + (6.50*4) + (6.50*1) = 13.00 + 26.00 + 6.50 = 45.50
        expect($report->totalRevenue)->toBe(45.5)
            ->and($report->totalOrders)->toBe(3)
            ->and($report->periodLabel)->toBe('Yearly: 2024');
    });

    it('excludes orders from other years', function () {
        $menuItem = MenuItem::factory()->create(['name' => 'Iced Tea']);

        // 2024 (target)
        createCompletedOrderWithBill(Carbon::create(2024, 6, 1, 10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '4.50', 'quantity' => 2],
        ]);
        // 2023 (outside)
        createCompletedOrderWithBill(Carbon::create(2023, 12, 31, 23, 59), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '4.50', 'quantity' => 10],
        ]);

        $report = $this->service->yearlyReport(2024);

        expect($report->totalRevenue)->toBe(9.0)
            ->and($report->totalOrders)->toBe(1);
    });
});

describe('item-wise sales and top-5 ranking', function () {
    it('returns item-wise sales count grouped by menu item name', function () {
        $date = Carbon::create(2024, 1, 15);
        $tea = MenuItem::factory()->create(['name' => 'Green Tea']);
        $coffee = MenuItem::factory()->create(['name' => 'Espresso']);

        createCompletedOrderWithBill($date->copy()->setTime(10, 0), [
            ['menu_item_id' => $tea->id, 'unit_price' => '3.00', 'quantity' => 2],
            ['menu_item_id' => $coffee->id, 'unit_price' => '4.00', 'quantity' => 1],
        ]);
        createCompletedOrderWithBill($date->copy()->setTime(14, 0), [
            ['menu_item_id' => $tea->id, 'unit_price' => '3.00', 'quantity' => 3],
        ]);

        $report = $this->service->dailyReport($date);

        $teaSales = collect($report->itemSales)->firstWhere('name', 'Green Tea');
        $coffeeSales = collect($report->itemSales)->firstWhere('name', 'Espresso');

        expect($teaSales['quantity_sold'])->toBe(5)
            ->and($teaSales['revenue'])->toBe(15.0)
            ->and($coffeeSales['quantity_sold'])->toBe(1)
            ->and($coffeeSales['revenue'])->toBe(4.0);
    });

    it('returns top 5 items sorted by quantity sold descending', function () {
        $date = Carbon::create(2024, 1, 15);

        $items = [];
        for ($i = 1; $i <= 7; $i++) {
            $items[] = MenuItem::factory()->create(['name' => "Item $i"]);
        }

        // Create orders with varying quantities
        createCompletedOrderWithBill($date->copy()->setTime(10, 0), [
            ['menu_item_id' => $items[0]->id, 'unit_price' => '5.00', 'quantity' => 10], // Item 1 - 10
            ['menu_item_id' => $items[1]->id, 'unit_price' => '5.00', 'quantity' => 8],  // Item 2 - 8
            ['menu_item_id' => $items[2]->id, 'unit_price' => '5.00', 'quantity' => 6],  // Item 3 - 6
            ['menu_item_id' => $items[3]->id, 'unit_price' => '5.00', 'quantity' => 4],  // Item 4 - 4
            ['menu_item_id' => $items[4]->id, 'unit_price' => '5.00', 'quantity' => 3],  // Item 5 - 3
            ['menu_item_id' => $items[5]->id, 'unit_price' => '5.00', 'quantity' => 2],  // Item 6 - 2
            ['menu_item_id' => $items[6]->id, 'unit_price' => '5.00', 'quantity' => 1],  // Item 7 - 1
        ]);

        $report = $this->service->dailyReport($date);

        expect(count($report->topItems))->toBe(5);

        $topNames = array_column($report->topItems, 'name');
        expect($topNames)->toBe(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5']);

        // Verify descending order
        $topQuantities = array_column($report->topItems, 'quantity_sold');
        expect($topQuantities)->toBe([10, 8, 6, 4, 3]);
    });

    it('returns fewer than 5 items when less than 5 distinct items sold', function () {
        $date = Carbon::create(2024, 1, 15);
        $menuItem1 = MenuItem::factory()->create(['name' => 'Only Item 1']);
        $menuItem2 = MenuItem::factory()->create(['name' => 'Only Item 2']);

        createCompletedOrderWithBill($date->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem1->id, 'unit_price' => '5.00', 'quantity' => 3],
            ['menu_item_id' => $menuItem2->id, 'unit_price' => '4.00', 'quantity' => 1],
        ]);

        $report = $this->service->dailyReport($date);

        expect(count($report->topItems))->toBe(2);
    });

    it('calculates item revenue as sum of unit_price × quantity', function () {
        $date = Carbon::create(2024, 1, 15);
        $menuItem = MenuItem::factory()->create(['name' => 'Fancy Drink']);

        createCompletedOrderWithBill($date->copy()->setTime(10, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '12.50', 'quantity' => 3],
        ]);
        createCompletedOrderWithBill($date->copy()->setTime(15, 0), [
            ['menu_item_id' => $menuItem->id, 'unit_price' => '12.50', 'quantity' => 2],
        ]);

        $report = $this->service->dailyReport($date);

        $itemSale = collect($report->itemSales)->firstWhere('name', 'Fancy Drink');
        // (12.50 * 3) + (12.50 * 2) = 37.50 + 25.00 = 62.50
        expect($itemSale['quantity_sold'])->toBe(5)
            ->and($itemSale['revenue'])->toBe(62.5);
    });
});

describe('no-data case', function () {
    it('returns zero values for daily report with no sales', function () {
        $date = Carbon::create(2024, 1, 15);
        $report = $this->service->dailyReport($date);

        expect($report->totalRevenue)->toBe(0.0)
            ->and($report->totalOrders)->toBe(0)
            ->and($report->itemSales)->toBe([])
            ->and($report->topItems)->toBe([])
            ->and($report->periodLabel)->toBe('Daily: Jan 15, 2024');
    });

    it('returns zero values for weekly report with no sales', function () {
        $startDate = Carbon::create(2024, 1, 15);
        $report = $this->service->weeklyReport($startDate);

        expect($report->totalRevenue)->toBe(0.0)
            ->and($report->totalOrders)->toBe(0)
            ->and($report->itemSales)->toBe([])
            ->and($report->topItems)->toBe([]);
    });

    it('returns zero values for monthly report with no sales', function () {
        $report = $this->service->monthlyReport(2024, 1);

        expect($report->totalRevenue)->toBe(0.0)
            ->and($report->totalOrders)->toBe(0)
            ->and($report->itemSales)->toBe([])
            ->and($report->topItems)->toBe([]);
    });

    it('returns zero values for yearly report with no sales', function () {
        $report = $this->service->yearlyReport(2024);

        expect($report->totalRevenue)->toBe(0.0)
            ->and($report->totalOrders)->toBe(0)
            ->and($report->itemSales)->toBe([])
            ->and($report->topItems)->toBe([]);
    });
});
