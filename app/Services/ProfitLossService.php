<?php

namespace App\Services;

use App\Contracts\ExpenseServiceInterface;
use App\Contracts\ProfitLossServiceInterface;
use App\DTOs\ProfitLossDTO;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\PurchaseEntry;
use Carbon\Carbon;

class ProfitLossService implements ProfitLossServiceInterface
{
    public function __construct(
        private readonly ExpenseServiceInterface $expenseService,
    ) {}

    public function weeklyReport(Carbon $startDate): ProfitLossDTO
    {
        // Adjust to Monday of the given week
        $monday = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);

        $revenue            = round($this->calculateRevenue($monday, $sunday), 2);
        $inventoryPurchases = round($this->calculateInventoryPurchases($monday, $sunday), 2);
        $totalExpenses      = $this->expenseService->expenseTotalForPeriod($monday, $sunday);
        $expenseBreakdown   = $this->expenseService->expenseBreakdownForPeriod($monday, $sunday);
        $spending           = round($inventoryPurchases + $totalExpenses, 2);
        $net                = round($revenue - $spending, 2);

        $periodLabel = 'Weekly: ' . $monday->format('M d') . ' - ' . $sunday->format('M d, Y');

        return new ProfitLossDTO(
            totalEarnings: $revenue,
            totalSpending: $spending,
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
            inventoryPurchases: $inventoryPurchases,
            totalExpenses: $totalExpenses,
            expenseBreakdown: $expenseBreakdown,
        );
    }

    public function monthlyReport(int $year, int $month): ProfitLossDTO
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $revenue            = round($this->calculateRevenue($startOfMonth, $endOfMonth), 2);
        $inventoryPurchases = round($this->calculateInventoryPurchases($startOfMonth, $endOfMonth), 2);
        $totalExpenses      = $this->expenseService->expenseTotalForPeriod($startOfMonth, $endOfMonth);
        $expenseBreakdown   = $this->expenseService->expenseBreakdownForPeriod($startOfMonth, $endOfMonth);
        $spending           = round($inventoryPurchases + $totalExpenses, 2);
        $net                = round($revenue - $spending, 2);

        $periodLabel = 'Monthly: ' . $startOfMonth->format('F Y');

        return new ProfitLossDTO(
            totalEarnings: $revenue,
            totalSpending: $spending,
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
            inventoryPurchases: $inventoryPurchases,
            totalExpenses: $totalExpenses,
            expenseBreakdown: $expenseBreakdown,
        );
    }

    public function yearlyReport(int $year): ProfitLossDTO
    {
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $revenue            = round($this->calculateRevenue($startOfYear, $endOfYear), 2);
        $inventoryPurchases = round($this->calculateInventoryPurchases($startOfYear, $endOfYear), 2);
        $totalExpenses      = $this->expenseService->expenseTotalForPeriod($startOfYear, $endOfYear);
        $expenseBreakdown   = $this->expenseService->expenseBreakdownForPeriod($startOfYear, $endOfYear);
        $spending           = round($inventoryPurchases + $totalExpenses, 2);
        $net                = round($revenue - $spending, 2);

        $periodLabel = 'Yearly: ' . $year;

        return new ProfitLossDTO(
            totalEarnings: $revenue,
            totalSpending: $spending,
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
            inventoryPurchases: $inventoryPurchases,
            totalExpenses: $totalExpenses,
            expenseBreakdown: $expenseBreakdown,
        );
    }

    /**
     * Calculate revenue as sum of bill grand_totals for completed orders within the period.
     */
    private function calculateRevenue(Carbon $start, Carbon $end): float
    {
        return (float) Bill::whereHas('order', function ($query) {
            $query->where('status', OrderStatus::Completed);
        })
            ->whereBetween('billed_at', [$start, $end])
            ->sum('grand_total');
    }

    /**
     * Calculate inventory purchases as sum of purchase_entries.cost within the period.
     */
    private function calculateInventoryPurchases(Carbon $start, Carbon $end): float
    {
        return (float) PurchaseEntry::whereDate('purchase_date', '>=', $start->toDateString())
            ->whereDate('purchase_date', '<=', $end->toDateString())
            ->sum('cost');
    }

    /**
     * Determine status label based on net amount.
     */
    private function determineStatus(float $net): string
    {
        if ($net > 0) {
            return 'profit';
        }

        if ($net < 0) {
            return 'loss';
        }

        return 'break-even';
    }
}
