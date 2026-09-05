<?php

use App\Contracts\BillingServiceInterface;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Bill;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->service = app(BillingServiceInterface::class);
});

describe('generateBill', function () {
    it('calculates grand total as sum of (unit_price × quantity) rounded to 2 decimal places', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '10.50',
            'quantity' => 2,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '7.75',
            'quantity' => 3,
        ]);

        $bill = $this->service->generateBill($table->id);

        // (10.50 * 2) + (7.75 * 3) = 21.00 + 23.25 = 44.25
        expect($bill->grand_total)->toBe('44.25');
    });

    it('creates a bill record with correct attributes', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '5.00',
            'quantity' => 1,
        ]);

        $bill = $this->service->generateBill($table->id);

        expect($bill)->toBeInstanceOf(Bill::class)
            ->and($bill->order_id)->toBe($order->id)
            ->and($bill->table_id)->toBe($table->id)
            ->and($bill->grand_total)->toBe('5.00')
            ->and($bill->status)->toBe(BillStatus::Unpaid)
            ->and($bill->billed_at)->not->toBeNull();
    });

    it('marks the order as billed after bill generation', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '10.00',
            'quantity' => 1,
        ]);

        $this->service->generateBill($table->id);

        $order->refresh();
        expect($order->status)->toBe(OrderStatus::Billed);
    });

    it('returns existing bill when order is already billed (idempotent)', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);

        $existingBill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '25.00',
        ]);

        $bill = $this->service->generateBill($table->id);

        expect($bill->id)->toBe($existingBill->id)
            ->and($bill->grand_total)->toBe('25.00');

        // No new bill should have been created
        expect(Bill::count())->toBe(1);
    });

    it('throws NotFoundHttpException when no active order exists for table', function () {
        $table = Table::factory()->create();

        $this->service->generateBill($table->id);
    })->throws(NotFoundHttpException::class, 'No active order for this table');

    it('throws NotFoundHttpException when table only has completed orders', function () {
        $table = Table::factory()->create();
        Order::factory()->completed()->create(['table_id' => $table->id]);

        $this->service->generateBill($table->id);
    })->throws(NotFoundHttpException::class, 'No active order for this table');

    it('handles decimal precision correctly for line totals', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        // 3.33 * 3 = 9.99
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '3.33',
            'quantity' => 3,
        ]);

        $bill = $this->service->generateBill($table->id);

        expect($bill->grand_total)->toBe('9.99');
    });

    it('handles single item orders', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => '15.99',
            'quantity' => 1,
        ]);

        $bill = $this->service->generateBill($table->id);

        expect($bill->grand_total)->toBe('15.99');
    });

    it('handles multiple items with various quantities', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => '1.50', 'quantity' => 10]);
        OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => '0.99', 'quantity' => 5]);
        OrderItem::factory()->create(['order_id' => $order->id, 'unit_price' => '25.00', 'quantity' => 1]);

        $bill = $this->service->generateBill($table->id);

        // (1.50 * 10) + (0.99 * 5) + (25.00 * 1) = 15.00 + 4.95 + 25.00 = 44.95
        expect($bill->grand_total)->toBe('44.95');
    });
});

describe('settleBill', function () {
    it('marks bill as paid', function () {
        $table = Table::factory()->occupied()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);

        $settledBill = $this->service->settleBill($bill->id);

        expect($settledBill->status)->toBe(BillStatus::Paid);
    });

    it('marks associated order as completed', function () {
        $table = Table::factory()->occupied()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);

        $this->service->settleBill($bill->id);

        $order->refresh();
        expect($order->status)->toBe(OrderStatus::Completed);
    });

    it('marks associated table as vacant', function () {
        $table = Table::factory()->occupied()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);

        $this->service->settleBill($bill->id);

        $table->refresh();
        expect($table->status)->toBe(TableStatus::Vacant);
    });

    it('returns the updated bill', function () {
        $table = Table::factory()->occupied()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '50.00',
        ]);

        $settledBill = $this->service->settleBill($bill->id);

        expect($settledBill->id)->toBe($bill->id)
            ->and($settledBill->grand_total)->toBe('50.00')
            ->and($settledBill->status)->toBe(BillStatus::Paid);
    });

    it('throws NotFoundHttpException if bill does not exist', function () {
        $this->service->settleBill(9999);
    })->throws(NotFoundHttpException::class, 'Bill not found.');

    it('allows a new order on the table after settlement', function () {
        $table = Table::factory()->occupied()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
        ]);

        $this->service->settleBill($bill->id);

        // Table should now be vacant - a new order can be created
        $table->refresh();
        expect($table->status)->toBe(TableStatus::Vacant);

        // Verify no active/billed order remains
        $activeOrder = Order::where('table_id', $table->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::Billed])
            ->first();
        expect($activeOrder)->toBeNull();
    });
});

describe('getBillForTable', function () {
    it('returns the bill for a billed order on the table', function () {
        $table = Table::factory()->create();
        $order = Order::factory()->billed()->create(['table_id' => $table->id]);
        $bill = Bill::factory()->create([
            'order_id' => $order->id,
            'table_id' => $table->id,
            'grand_total' => '30.00',
        ]);

        $result = $this->service->getBillForTable($table->id);

        expect($result)->toBeInstanceOf(Bill::class)
            ->and($result->id)->toBe($bill->id);
    });

    it('returns null when no active or billed order exists', function () {
        $table = Table::factory()->create();

        $result = $this->service->getBillForTable($table->id);

        expect($result)->toBeNull();
    });

    it('returns null when table only has completed orders', function () {
        $table = Table::factory()->create();
        Order::factory()->completed()->create(['table_id' => $table->id]);

        $result = $this->service->getBillForTable($table->id);

        expect($result)->toBeNull();
    });

    it('returns null for active order with no bill yet', function () {
        $table = Table::factory()->create();
        Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        $result = $this->service->getBillForTable($table->id);

        expect($result)->toBeNull();
    });
});
