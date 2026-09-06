<?php

namespace App\Http\Controllers;

use App\Contracts\ProfitLossServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function __construct(
        private readonly ProfitLossServiceInterface $profitLossService
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->query('period', 'weekly');

        $weekStart = $request->query('week_start')
            ? Carbon::parse($request->query('week_start'))
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $year  = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);

        $report = match ($period) {
            'monthly' => $this->profitLossService->monthlyReport($year, $month),
            'yearly'  => $this->profitLossService->yearlyReport($year),
            default   => $this->profitLossService->weeklyReport($weekStart),
        };

        return Inertia::render('Reports/ProfitLoss', [
            'report' => [
                'totalEarnings'      => $report->totalEarnings,
                'totalSpending'      => $report->totalSpending,
                'inventoryPurchases' => $report->inventoryPurchases,
                'totalExpenses'      => $report->totalExpenses,
                'expenseBreakdown'   => $report->expenseBreakdown,
                'netAmount'          => $report->netAmount,
                'status'             => $report->status,
                'periodLabel'        => $report->periodLabel,
            ],
            'formatted' => [
                'earnings'  => $this->money($report->totalEarnings),
                'inventory' => $this->money($report->inventoryPurchases),
                'expenses'  => $this->money($report->totalExpenses),
                'spending'  => $this->money($report->totalSpending),
                'net'       => $this->money($report->netAmount),
            ],
            'filters' => [
                'period'     => $period,
                'week_start' => $weekStart->toDateString(),
                'year'       => $year,
                'month'      => $month,
            ],
        ]);
    }

    private function money(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}
