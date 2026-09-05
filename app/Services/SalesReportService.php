<?php

namespace App\Services;

use App\Contracts\SalesReportServiceInterface;
use App\DTOs\SalesReportDTO;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService implements SalesReportServiceInterface
{
    public function dailyReport(Carbon $date): SalesReportDTO
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $periodLabel = 'Daily: ' . $date->format('M d, Y');

        return $this->buildReport($startOfDay, $endOfDay, $periodLabel);
    }

    public function weeklyReport(Carbon $startDate): SalesReportDTO
    {
        $start = $startDate->copy()->startOfDay();
        $end = $startDate->copy()->addDays(6)->endOfDay();

        $periodLabel = 'Weekly: ' . $start->format('M d') . ' - ' . $end->format('M d, Y');

        return $this->buildReport($start, $end, $periodLabel);
    }

    public function monthlyReport(int $year, int $month): SalesReportDTO
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $periodLabel = 'Monthly: ' . $start->format('F Y');

        return $this->buildReport($start, $end, $periodLabel);
    }

    public function yearlyReport(int $year): SalesReportDTO
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        $periodLabel = 'Yearly: ' . $year;

        return $this->buildReport($start, $end, $periodLabel);
    }

    private function buildReport(Carbon $start, Carbon $end, string $periodLabel): SalesReportDTO
    {
        // Get bills within the period that belong to completed orders
        $bills = Bill::whereBetween('billed_at', [$start, $end])
            ->whereHas('order', function ($query) {
                $query->where('status', OrderStatus::Completed);
            })
            ->get();

        $totalRevenue = (float) $bills->sum('grand_total');
        $totalOrders = $bills->pluck('order_id')->unique()->count();

        // Item-wise sales: aggregate order_items for completed orders in the period
        $itemSales = $this->getItemSales($start, $end);

        // Top 5 items by quantity sold
        $topItems = collect($itemSales)
            ->sortByDesc('quantity_sold')
            ->take(5)
            ->values()
            ->toArray();

        return new SalesReportDTO(
            totalRevenue: $totalRevenue,
            totalOrders: $totalOrders,
            itemSales: $itemSales,
            topItems: $topItems,
            periodLabel: $periodLabel,
        );
    }

    private function getItemSales(Carbon $start, Carbon $end): array
    {
        $orderItems = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('bills', 'bills.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('bills.billed_at', [$start, $end])
            ->selectRaw('menu_items.name as name, SUM(order_items.quantity) as quantity_sold, SUM(order_items.unit_price * order_items.quantity) as revenue')
            ->groupBy('menu_items.name')
            ->orderByDesc('quantity_sold')
            ->get();

        return $orderItems->map(function ($item) {
            return [
                'name' => $item->name,
                'quantity_sold' => (int) $item->quantity_sold,
                'revenue' => round((float) $item->revenue, 2),
            ];
        })->toArray();
    }
}
