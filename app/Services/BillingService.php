<?php

namespace App\Services;

use App\Contracts\BillingServiceInterface;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Bill;
use App\Models\Order;
use App\Models\Table;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BillingService implements BillingServiceInterface
{
    /**
     * Generate a bill for a table's active order.
     *
     * If the order is already billed, return the existing bill (idempotent).
     * If the order is active, calculate the total, create a bill, and mark order as billed.
     *
     * @param int $tableId
     * @return Bill
     *
     * @throws NotFoundHttpException If no active or billed order exists for the table
     */
    public function generateBill(int $tableId): Bill
    {
        // Find the active or billed order for this table
        $order = Order::where('table_id', $tableId)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::Billed])
            ->with('orderItems')
            ->first();

        if (! $order) {
            throw new NotFoundHttpException('No active order for this table');
        }

        // If order is already billed, return the existing bill (idempotent)
        if ($order->status === OrderStatus::Billed) {
            return $order->bill;
        }

        // Calculate grand total: sum of (unit_price × quantity), rounded to 2 decimal places
        $grandTotal = $order->orderItems->sum(function ($item) {
            return round((float) $item->unit_price * $item->quantity, 2);
        });
        $grandTotal = round($grandTotal, 2);

        // Create the bill
        $bill = Bill::create([
            'order_id' => $order->id,
            'table_id' => $tableId,
            'grand_total' => $grandTotal,
            'status' => BillStatus::Unpaid,
            'billed_at' => now(),
        ]);

        // Mark order as billed (prevents further item additions)
        $order->update(['status' => OrderStatus::Billed]);

        return $bill;
    }

    /**
     * Settle (pay) a bill.
     *
     * Marks the bill as paid, the order as completed, and the table as vacant.
     *
     * @param int $billId
     * @return Bill
     *
     * @throws NotFoundHttpException If bill does not exist
     */
    public function settleBill(int $billId): Bill
    {
        $bill = Bill::find($billId);

        if (! $bill) {
            throw new NotFoundHttpException('Bill not found.');
        }

        // Mark bill as paid
        $bill->update(['status' => BillStatus::Paid]);

        // Mark associated order as completed
        $bill->order()->update(['status' => OrderStatus::Completed]);

        // Mark associated table as vacant
        $bill->table()->update(['status' => TableStatus::Vacant]);

        return $bill->fresh();
    }

    /**
     * Get the bill for a table's active or billed order, or null if none exists.
     *
     * @param int $tableId
     * @return Bill|null
     */
    public function getBillForTable(int $tableId): ?Bill
    {
        $order = Order::where('table_id', $tableId)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::Billed])
            ->first();

        if (! $order) {
            return null;
        }

        return Bill::where('order_id', $order->id)->first();
    }
}
