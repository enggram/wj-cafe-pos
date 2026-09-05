<?php

use App\Contracts\OrderServiceInterface;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\SubVariety;
use App\Models\Table;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->service = app(OrderServiceInterface::class);
});

describe('createOrder', function () {
    it('creates an order with items for a valid table', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => '10.50']);

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        expect($order)->toBeInstanceOf(Order::class)
            ->and($order->table_id)->toBe($table->id)
            ->and($order->status)->toBe(OrderStatus::Active)
            ->and($order->ordered_at)->not->toBeNull()
            ->and($order->orderItems)->toHaveCount(1)
            ->and($order->orderItems->first()->quantity)->toBe(2)
            ->and($order->orderItems->first()->unit_price)->toBe('10.50');

        // Table should be marked as occupied
        $table->refresh();
        expect($table->status)->toBe(TableStatus::Occupied);
    });

    it('snapshots menu item price including sub-variety adjustment', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => '10.00']);
        $subVariety = SubVariety::factory()->create([
            'menu_item_id' => $menuItem->id,
            'price_adjustment' => '2.50',
        ]);

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1, 'sub_variety_id' => $subVariety->id],
        ]);

        expect($order->orderItems->first()->unit_price)->toBe('12.50');
    });

    it('throws NotFoundHttpException if table does not exist', function () {
        $menuItem = MenuItem::factory()->create();

        $this->service->createOrder(9999, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    })->throws(NotFoundHttpException::class);

    it('throws ConflictHttpException if table already has an active order', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        // Create first order
        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        // Attempt second order on same table
        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    })->throws(ConflictHttpException::class);

    it('rejects quantity less than 1', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 0],
        ]);
    })->throws(ValidationException::class);

    it('rejects quantity greater than 99', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 100],
        ]);
    })->throws(ValidationException::class);

    it('rejects non-integer quantity', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2.5],
        ]);
    })->throws(ValidationException::class);

    it('rejects empty items array', function () {
        $table = Table::factory()->create();

        $this->service->createOrder($table->id, []);
    })->throws(ValidationException::class);
});

describe('addItems', function () {
    it('appends new items to an existing order', function () {
        $table = Table::factory()->create();
        $menuItem1 = MenuItem::factory()->create(['price' => '5.00']);
        $menuItem2 = MenuItem::factory()->create(['price' => '8.00']);

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem1->id, 'quantity' => 1],
        ]);

        $updatedOrder = $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem2->id, 'quantity' => 3],
        ]);

        expect($updatedOrder->orderItems)->toHaveCount(2);
    });

    it('increments quantity for duplicate menu_item_id + sub_variety_id combo', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => '5.00']);

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        $updatedOrder = $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 3],
        ]);

        expect($updatedOrder->orderItems)->toHaveCount(1)
            ->and($updatedOrder->orderItems->first()->quantity)->toBe(5);
    });

    it('treats different sub_variety_id as separate line items', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create(['price' => '5.00']);
        $subVariety = SubVariety::factory()->create([
            'menu_item_id' => $menuItem->id,
            'price_adjustment' => '1.00',
        ]);

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1, 'sub_variety_id' => null],
        ]);

        $updatedOrder = $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2, 'sub_variety_id' => $subVariety->id],
        ]);

        expect($updatedOrder->orderItems)->toHaveCount(2);
    });

    it('throws NotFoundHttpException if order does not exist', function () {
        $menuItem = MenuItem::factory()->create();

        $this->service->addItems(9999, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    })->throws(NotFoundHttpException::class);

    it('throws ConflictHttpException if order is billed', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $order = Order::factory()->billed()->create(['table_id' => $table->id]);

        $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    })->throws(ConflictHttpException::class);

    it('throws ConflictHttpException if order is completed', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $order = Order::factory()->completed()->create(['table_id' => $table->id]);

        $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);
    })->throws(ConflictHttpException::class);

    it('rejects invalid quantity when adding items', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $order = $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 1],
        ]);

        $this->service->addItems($order->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 100],
        ]);
    })->throws(ValidationException::class);
});

describe('getOpenOrderForTable', function () {
    it('returns the active order with items for a table', function () {
        $table = Table::factory()->create();
        $menuItem = MenuItem::factory()->create();

        $this->service->createOrder($table->id, [
            ['menu_item_id' => $menuItem->id, 'quantity' => 2],
        ]);

        $result = $this->service->getOpenOrderForTable($table->id);

        expect($result)->toBeInstanceOf(Order::class)
            ->and($result->status)->toBe(OrderStatus::Active)
            ->and($result->orderItems)->toHaveCount(1);
    });

    it('returns null when no active order exists', function () {
        $table = Table::factory()->create();

        $result = $this->service->getOpenOrderForTable($table->id);

        expect($result)->toBeNull();
    });

    it('does not return billed or completed orders', function () {
        $table = Table::factory()->create();
        Order::factory()->billed()->create(['table_id' => $table->id]);

        $result = $this->service->getOpenOrderForTable($table->id);

        expect($result)->toBeNull();
    });
});

describe('getTableOverview', function () {
    it('returns all tables with status', function () {
        Table::factory()->count(3)->create();

        $overview = $this->service->getTableOverview();

        expect($overview)->toHaveCount(3);
    });

    it('marks table as occupied when it has an active order', function () {
        $table = Table::factory()->create();
        Order::factory()->create(['table_id' => $table->id, 'status' => OrderStatus::Active]);

        $overview = $this->service->getTableOverview();
        $tableInOverview = $overview->firstWhere('id', $table->id);

        expect($tableInOverview->status)->toBe(TableStatus::Occupied);
    });

    it('marks table as occupied when it has a billed order', function () {
        $table = Table::factory()->create();
        Order::factory()->billed()->create(['table_id' => $table->id]);

        $overview = $this->service->getTableOverview();
        $tableInOverview = $overview->firstWhere('id', $table->id);

        expect($tableInOverview->status)->toBe(TableStatus::Occupied);
    });

    it('marks table as vacant when all orders are completed', function () {
        $table = Table::factory()->create();
        Order::factory()->completed()->create(['table_id' => $table->id]);

        $overview = $this->service->getTableOverview();
        $tableInOverview = $overview->firstWhere('id', $table->id);

        expect($tableInOverview->status)->toBe(TableStatus::Vacant);
    });

    it('marks table as vacant when no orders exist', function () {
        $table = Table::factory()->create();

        $overview = $this->service->getTableOverview();
        $tableInOverview = $overview->firstWhere('id', $table->id);

        expect($tableInOverview->status)->toBe(TableStatus::Vacant);
    });
});
