<?php

use App\Contracts\ProfitLossServiceInterface;
use App\DTOs\ProfitLossDTO;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\Order;
use App\Models\PurchaseEntry;
use App\Models\Table;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = app(ProfitLossServiceInterface::class);
});

describe('weeklyReport', function () {
    it('calculates revenue minus spending for the Monday-Sunday week', function () {
        $monday = Carbon::parse('2024-01-15'); // Monday

        // Create completed order with bill in the week
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '500.00',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        // Create purchase entry in the week
        PurchaseEntry::factory()->create([
            'cost' => '200.00',
            'purchase_date' => '2024-01-17',
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result)->toBeInstanceOf(ProfitLossDTO::class)
            ->and($result->totalEarnings)->toBe(500.0)
            ->and($result->totalSpending)->toBe(200.0)
            ->and($result->netAmount)->toBe(300.0)
            ->and($result->status)->toBe('profit');
    });

    it('adjusts any given date to the Monday of that week', function () {
        // Pass a Wednesday, should still cover Monday-Sunday
        $wednesday = Carbon::parse('2024-01-17'); // Wednesday

        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '100.00',
            'billed_at' => Carbon::parse('2024-01-15 12:00:00'), // Monday
        ]);

        $result = $this->service->weeklyReport($wednesday);

        expect($result->totalEarnings)->toBe(100.0)
            ->and($result->periodLabel)->toBe('Weekly: Jan 15 - Jan 21, 2024');
    });

    it('generates correct period label for the week', function () {
        $monday = Carbon::parse('2024-01-15');

        $result = $this->service->weeklyReport($monday);

        expect($result->periodLabel)->toBe('Weekly: Jan 15 - Jan 21, 2024');
    });

    it('excludes bills from non-completed orders', function () {
        $monday = Carbon::parse('2024-01-15');

        $table = Table::factory()->create();

        // Bill for a billed (not completed) order - should be excluded
        $billedOrder = Order::factory()->billed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $billedOrder->id,
            'table_id' => $table->id,
            'grand_total' => '300.00',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result->totalEarnings)->toBe(0.0);
    });

    it('excludes bills outside the week period', function () {
        $monday = Carbon::parse('2024-01-15');

        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);

        // Bill from previous week
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '400.00',
            'billed_at' => Carbon::parse('2024-01-14 23:59:59'), // Sunday before
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result->totalEarnings)->toBe(0.0);
    });

    it('excludes purchase entries outside the week period', function () {
        $monday = Carbon::parse('2024-01-15');

        // Purchase from previous week
        PurchaseEntry::factory()->create([
            'cost' => '150.00',
            'purchase_date' => '2024-01-14', // Sunday before
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result->totalSpending)->toBe(0.0);
    });

    it('returns loss status when spending exceeds revenue', function () {
        $monday = Carbon::parse('2024-01-15');

        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '100.00',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        PurchaseEntry::factory()->create([
            'cost' => '300.00',
            'purchase_date' => '2024-01-17',
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result->netAmount)->toBe(-200.0)
            ->and($result->status)->toBe('loss');
    });

    it('returns break-even status when revenue equals spending', function () {
        $monday = Carbon::parse('2024-01-15');

        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '250.00',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        PurchaseEntry::factory()->create([
            'cost' => '250.00',
            'purchase_date' => '2024-01-17',
        ]);

        $result = $this->service->weeklyReport($monday);

        expect($result->netAmount)->toBe(0.0)
            ->and($result->status)->toBe('break-even');
    });
});

describe('monthlyReport', function () {
    it('calculates revenue minus spending for the calendar month', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '1000.00',
            'billed_at' => Carbon::parse('2024-03-15 10:00:00'),
        ]);

        PurchaseEntry::factory()->create([
            'cost' => '400.00',
            'purchase_date' => '2024-03-10',
        ]);

        $result = $this->service->monthlyReport(2024, 3);

        expect($result)->toBeInstanceOf(ProfitLossDTO::class)
            ->and($result->totalEarnings)->toBe(1000.0)
            ->and($result->totalSpending)->toBe(400.0)
            ->and($result->netAmount)->toBe(600.0)
            ->and($result->status)->toBe('profit');
    });

    it('generates correct period label', function () {
        $result = $this->service->monthlyReport(2024, 1);

        expect($result->periodLabel)->toBe('Monthly: January 2024');
    });

    it('excludes data from other months', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);

        // Bill in February, not March
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '500.00',
            'billed_at' => Carbon::parse('2024-02-28 23:59:59'),
        ]);

        // Purchase in April, not March
        PurchaseEntry::factory()->create([
            'cost' => '200.00',
            'purchase_date' => '2024-04-01',
        ]);

        $result = $this->service->monthlyReport(2024, 3);

        expect($result->totalEarnings)->toBe(0.0)
            ->and($result->totalSpending)->toBe(0.0);
    });

    it('aggregates multiple bills and purchases within the month', function () {
        $table = Table::factory()->create();

        // Multiple completed orders with bills in January
        $order1 = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order1->id,
            'table_id' => $table->id,
            'grand_total' => '150.00',
            'billed_at' => Carbon::parse('2024-01-05 10:00:00'),
        ]);

        $order2 = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order2->id,
            'table_id' => $table->id,
            'grand_total' => '250.00',
            'billed_at' => Carbon::parse('2024-01-20 10:00:00'),
        ]);

        // Multiple purchases in January
        PurchaseEntry::factory()->create([
            'cost' => '80.00',
            'purchase_date' => '2024-01-03',
        ]);
        PurchaseEntry::factory()->create([
            'cost' => '120.00',
            'purchase_date' => '2024-01-25',
        ]);

        $result = $this->service->monthlyReport(2024, 1);

        expect($result->totalEarnings)->toBe(400.0)
            ->and($result->totalSpending)->toBe(200.0)
            ->and($result->netAmount)->toBe(200.0);
    });

    it('returns loss status when spending exceeds revenue', function () {
        PurchaseEntry::factory()->create([
            'cost' => '500.00',
            'purchase_date' => '2024-06-15',
        ]);

        $result = $this->service->monthlyReport(2024, 6);

        expect($result->netAmount)->toBe(-500.0)
            ->and($result->status)->toBe('loss');
    });
});

describe('yearlyReport', function () {
    it('calculates revenue minus spending for the calendar year', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '5000.00',
            'billed_at' => Carbon::parse('2024-06-15 10:00:00'),
        ]);

        PurchaseEntry::factory()->create([
            'cost' => '2000.00',
            'purchase_date' => '2024-03-10',
        ]);

        $result = $this->service->yearlyReport(2024);

        expect($result)->toBeInstanceOf(ProfitLossDTO::class)
            ->and($result->totalEarnings)->toBe(5000.0)
            ->and($result->totalSpending)->toBe(2000.0)
            ->and($result->netAmount)->toBe(3000.0)
            ->and($result->status)->toBe('profit');
    });

    it('generates correct period label', function () {
        $result = $this->service->yearlyReport(2024);

        expect($result->periodLabel)->toBe('Yearly: 2024');
    });

    it('excludes data from other years', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);

        // Bill in 2023, not 2024
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '1000.00',
            'billed_at' => Carbon::parse('2023-12-31 23:59:59'),
        ]);

        // Purchase in 2025, not 2024
        PurchaseEntry::factory()->create([
            'cost' => '500.00',
            'purchase_date' => '2025-01-01',
        ]);

        $result = $this->service->yearlyReport(2024);

        expect($result->totalEarnings)->toBe(0.0)
            ->and($result->totalSpending)->toBe(0.0);
    });

    it('aggregates data across all months in the year', function () {
        $table = Table::factory()->create();

        // Bills in different months of 2024
        $order1 = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order1->id,
            'table_id' => $table->id,
            'grand_total' => '1000.00',
            'billed_at' => Carbon::parse('2024-01-15 10:00:00'),
        ]);

        $order2 = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order2->id,
            'table_id' => $table->id,
            'grand_total' => '2000.00',
            'billed_at' => Carbon::parse('2024-12-20 10:00:00'),
        ]);

        // Purchases in different months of 2024
        PurchaseEntry::factory()->create([
            'cost' => '300.00',
            'purchase_date' => '2024-02-01',
        ]);
        PurchaseEntry::factory()->create([
            'cost' => '700.00',
            'purchase_date' => '2024-11-30',
        ]);

        $result = $this->service->yearlyReport(2024);

        expect($result->totalEarnings)->toBe(3000.0)
            ->and($result->totalSpending)->toBe(1000.0)
            ->and($result->netAmount)->toBe(2000.0);
    });
});

describe('no-data handling', function () {
    it('returns zero values for weekly report with no data', function () {
        $result = $this->service->weeklyReport(Carbon::parse('2024-01-15'));

        expect($result->totalEarnings)->toBe(0.0)
            ->and($result->totalSpending)->toBe(0.0)
            ->and($result->netAmount)->toBe(0.0)
            ->and($result->status)->toBe('break-even');
    });

    it('returns zero values for monthly report with no data', function () {
        $result = $this->service->monthlyReport(2024, 1);

        expect($result->totalEarnings)->toBe(0.0)
            ->and($result->totalSpending)->toBe(0.0)
            ->and($result->netAmount)->toBe(0.0)
            ->and($result->status)->toBe('break-even');
    });

    it('returns zero values for yearly report with no data', function () {
        $result = $this->service->yearlyReport(2024);

        expect($result->totalEarnings)->toBe(0.0)
            ->and($result->totalSpending)->toBe(0.0)
            ->and($result->netAmount)->toBe(0.0)
            ->and($result->status)->toBe('break-even');
    });
});

describe('status determination', function () {
    it('returns profit when net is positive', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '100.00',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        $result = $this->service->weeklyReport(Carbon::parse('2024-01-15'));

        expect($result->status)->toBe('profit');
    });

    it('returns loss when net is negative', function () {
        PurchaseEntry::factory()->create([
            'cost' => '100.00',
            'purchase_date' => '2024-01-16',
        ]);

        $result = $this->service->weeklyReport(Carbon::parse('2024-01-15'));

        expect($result->status)->toBe('loss');
    });

    it('returns break-even when net is zero', function () {
        $result = $this->service->weeklyReport(Carbon::parse('2024-01-15'));

        expect($result->status)->toBe('break-even');
    });
});

describe('monetary precision', function () {
    it('returns values rounded to 2 decimal places', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->completed()->create(['table_id' => $table->id]);
        Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '33.33',
            'billed_at' => Carbon::parse('2024-01-16 10:00:00'),
        ]);

        PurchaseEntry::factory()->create([
            'cost' => '11.11',
            'purchase_date' => '2024-01-17',
        ]);

        $result = $this->service->weeklyReport(Carbon::parse('2024-01-15'));

        expect($result->totalEarnings)->toBe(33.33)
            ->and($result->totalSpending)->toBe(11.11)
            ->and($result->netAmount)->toBe(22.22);
    });
});
