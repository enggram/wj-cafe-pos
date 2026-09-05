<?php

namespace App\Services;

use App\Contracts\ProfitLossServiceInterface;
use App\DTOs\ProfitLossDTO;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\PurchaseEntry;
use Carbon\Carbon;

class ProfitLossService implements ProfitLossServiceInterface
{
    public function weeklyReport(Carbon $startDate): ProfitLossDTO
    {
        // Adjust to Monday of the given week
        $monday = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);

        $revenue = $this->calculateRevenue($monday, $sunday);
        $spending = $this->calculateSpending($monday, $sunday);
        $net = round($revenue - $spending, 2);

        $periodLabel = 'Weekly: ' . $monday->format('M d') . ' - ' . $sunday->format('M d, Y');

        return new ProfitLossDTO(
            totalEarnings: round($revenue, 2),
            totalSpending: round($spending, 2),
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
        );
    }

    public function monthlyReport(int $year, int $month): ProfitLossDTO
    {
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $revenue = $this->calculateRevenue($startOfMonth, $endOfMonth);
        $spending = $this->calculateSpending($startOfMonth, $endOfMonth);
        $net = round($revenue - $spending, 2);

        $periodLabel = 'Monthly: ' . $startOfMonth->format('F Y');

        return new ProfitLossDTO(
            totalEarnings: round($revenue, 2),
            totalSpending: round($spending, 2),
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
        );
    }

    public function yearlyReport(int $year): ProfitLossDTO
    {
        $startOfYear = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::create($year, 12, 31)->endOfDay();

        $revenue = $this->calculateRevenue($startOfYear, $endOfYear);
        $spending = $this->calculateSpending($startOfYear, $endOfYear);
        $net = round($revenue - $spending, 2);

        $periodLabel = 'Yearly: ' . $year;

        return new ProfitLossDTO(
            totalEarnings: round($revenue, 2),
            totalSpending: round($spending, 2),
            netAmount: $net,
            status: $this->determineStatus($net),
            periodLabel: $periodLabel,
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
     * Calculate spending as sum of purchase_entries.cost within the period.
     */
    private function calculateSpending(Carbon $start, Carbon $end): float
    {
        return (float) PurchaseEntry::whereBetween('purchase_date', [
            $start->toDateString(),
            $end->toDateString(),
        ])->sum('cost');
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
