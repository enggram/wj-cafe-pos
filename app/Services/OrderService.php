<?php

namespace App\Services;

use App\Contracts\OrderServiceInterface;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Bill;
use App\Models\SubVariety;
use App\Models\Table;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService implements OrderServiceInterface
{
    /**
     * Create a new order for a table with initial items.
     *
     * @param int $tableId
     * @param array $items Array of ['menu_item_id' => int, 'quantity' => int, 'sub_variety_id' => int|null]
     * @return Order
     *
     * @throws NotFoundHttpException If table does not exist
     * @throws ConflictHttpException If table already has an active order
     * @throws ValidationException If item quantities are invalid
     */
    public function createOrder(int $tableId, array $items): Order
    {
        $table = Table::find($tableId);

        if (! $table) {
            throw new NotFoundHttpException('Table not found.');
        }

        // Check no active order exists for this table
        $existingOrder = Order::where('table_id', $tableId)
            ->where('status', OrderStatus::Active)
            ->first();

        if ($existingOrder) {
            throw new ConflictHttpException('Table already has an active order.');
        }

        // Validate items
        $this->validateItems($items);

        // Create the order
        $order = Order::create([
            'table_id' => $tableId,
            'status' => OrderStatus::Active,
            'ordered_at' => now(),
        ]);

        // Update table status to occupied
        $table->update(['status' => TableStatus::Occupied]);

        // Add items to the order
        $this->attachItems($order, $items);

        return $order->load('orderItems');
    }

    /**
     * Add items to an existing active order.
     *
     * @param int $orderId
     * @param array $items Array of ['menu_item_id' => int, 'quantity' => int, 'sub_variety_id' => int|null]
     * @return Order
     *
     * @throws NotFoundHttpException If order does not exist
     * @throws ConflictHttpException If order is not in active status
     * @throws ValidationException If item quantities are invalid
     */
    public function addItems(int $orderId, array $items): Order
    {
        $order = Order::find($orderId);

        if (! $order) {
            throw new NotFoundHttpException('Order not found.');
        }

        if ($order->status !== OrderStatus::Active) {
            throw new ConflictHttpException('Cannot add items to an order that is not active.');
        }

        // Validate items
        $this->validateItems($items);

        // Add or merge items
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'];
            $subVarietyId = $item['sub_variety_id'] ?? null;
            $quantity = $item['quantity'];

            // Check if same menu_item_id + sub_variety_id combo exists
            $existingItem = $order->orderItems()
                ->where('menu_item_id', $menuItemId)
                ->where('sub_variety_id', $subVarietyId)
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $quantity,
                ]);
            } else {
                $unitPrice = $this->resolveUnitPrice($menuItemId, $subVarietyId);

                $order->orderItems()->create([
                    'menu_item_id' => $menuItemId,
                    'sub_variety_id' => $subVarietyId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        return $order->load('orderItems');
    }

    /**
     * Get the active order for a table, or null if none exists.
     *
     * @param int $tableId
     * @return Order|null
     */
    public function getOpenOrderForTable(int $tableId): ?Order
    {
        return Order::where('table_id', $tableId)
            ->where('status', OrderStatus::Active)
            ->with('orderItems')
            ->first();
    }

    /**
     * Get all tables with their current status (vacant/occupied).
     *
     * A table is "occupied" if it has any order with status active or billed.
     * Otherwise it is "vacant".
     *
     * @return Collection
     */
    public function getTableOverview(): Collection
    {
        $tables = Table::all();

        return $tables->map(function (Table $table) {
            $activeOrder = Order::where('table_id', $table->id)
                ->whereIn('status', [OrderStatus::Active, OrderStatus::Billed])
                ->first();

            $hasBill = false;
            if ($activeOrder && $activeOrder->status === OrderStatus::Billed) {
                $hasBill = Bill::where('order_id', $activeOrder->id)->exists();
            }

            $table->status = $activeOrder
                ? TableStatus::Occupied
                : TableStatus::Vacant;

            $table->has_bill = $hasBill;

            return $table;
        });
    }

    /**
     * Validate item quantities (must be integer between 1 and 99).
     *
     * @param array $items
     * @throws ValidationException
     */
    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['At least one item is required.'],
            ]);
        }

        foreach ($items as $index => $item) {
            if (! isset($item['menu_item_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.menu_item_id" => ['Menu item is required.'],
                ]);
            }

            $quantity = $item['quantity'] ?? null;

            if (! is_int($quantity) || $quantity < 1 || $quantity > 99) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be an integer between 1 and 99.'],
                ]);
            }
        }
    }

    /**
     * Attach items to an order, snapshotting the current price.
     *
     * @param Order $order
     * @param array $items
     */
    private function attachItems(Order $order, array $items): void
    {
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'];
            $subVarietyId = $item['sub_variety_id'] ?? null;
            $quantity = $item['quantity'];

            $unitPrice = $this->resolveUnitPrice($menuItemId, $subVarietyId);

            $order->orderItems()->create([
                'menu_item_id' => $menuItemId,
                'sub_variety_id' => $subVarietyId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
        }
    }

    /**
     * Resolve the unit price for a menu item, including sub-variety price adjustment.
     *
     * @param int $menuItemId
     * @param int|null $subVarietyId
     * @return string
     */
    private function resolveUnitPrice(int $menuItemId, ?int $subVarietyId): string
    {
        $menuItem = MenuItem::findOrFail($menuItemId);
        $price = (float) $menuItem->price;

        if ($subVarietyId) {
            $subVariety = SubVariety::findOrFail($subVarietyId);
            $price += (float) $subVariety->price_adjustment;
        }

        return number_format($price, 2, '.', '');
    }
}
