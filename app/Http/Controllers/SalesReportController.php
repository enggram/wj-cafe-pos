<?php

namespace App\Http\Controllers;

use App\Contracts\SalesReportServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    public function __construct(
        private readonly SalesReportServiceInterface $salesReportService
    ) {}

    /**
     * Display sales report for the selected period.
     *
     * Query params:
     *  - period: daily|weekly|monthly|yearly (default: daily)
     *  - date: Y-m-d (for daily, default: today)
     *  - start_date: Y-m-d (for weekly, default: start of current week)
     *  - year: int (for monthly/yearly, default: current year)
     *  - month: int (for monthly, default: current month)
     */
    public function index(Request $request): Response
    {
        $period = $request->input('period', 'daily');

        $report = match ($period) {
            'weekly' => $this->getWeeklyReport($request),
            'monthly' => $this->getMonthlyReport($request),
            'yearly' => $this->getYearlyReport($request),
            default => $this->getDailyReport($request),
        };

        return Inertia::render('Reports/Sales', [
            'report' => [
                'totalRevenue' => $report->totalRevenue,
                'totalOrders' => $report->totalOrders,
                'itemSales' => $report->itemSales,
                'topItems' => $report->topItems,
                'periodLabel' => $report->periodLabel,
            ],
            'filters' => [
                'period' => $period,
                'date' => $request->input('date', now()->toDateString()),
                'start_date' => $request->input('start_date', now()->startOfWeek()->toDateString()),
                'year' => (int) $request->input('year', now()->year),
                'month' => (int) $request->input('month', now()->month),
            ],
        ]);
    }

    private function getDailyReport(Request $request): \App\DTOs\SalesReportDTO
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));

        return $this->salesReportService->dailyReport($date);
    }

    private function getWeeklyReport(Request $request): \App\DTOs\SalesReportDTO
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfWeek()->toDateString()));

        return $this->salesReportService->weeklyReport($startDate);
    }

    private function getMonthlyReport(Request $request): \App\DTOs\SalesReportDTO
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return $this->salesReportService->monthlyReport($year, $month);
    }

    private function getYearlyReport(Request $request): \App\DTOs\SalesReportDTO
    {
        $year = (int) $request->input('year', now()->year);

        return $this->salesReportService->yearlyReport($year);
    }
}
