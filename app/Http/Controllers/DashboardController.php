<?php

namespace App\Http\Controllers;

use App\Contracts\SalesReportServiceInterface;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SalesReportServiceInterface $salesReportService,
    ) {}

    public function index(Request $request): Response
    {
        $today = Carbon::today();

        // ── Today at a glance ──────────────────────────────────
        $todayReport = $this->salesReportService->rangeReport($today, $today, 'Today');

        // Cash collected today = sum of PAID bills billed today.
        $cashCollected = (float) Bill::where('status', BillStatus::Paid)
            ->whereDate('billed_at', $today->toDateString())
            ->sum('grand_total');

        $isAdmin = (bool) $request->user()?->isAdmin();

        // ── Item performance (best-sellers) for a selectable range ──
        // Admin-only: staff see the "today at a glance" summary but not the
        // historical item-sales tool. We skip computing/exposing it entirely.
        $itemPerformance = null;
        $filtersOut = null;

        if ($isAdmin) {
            $filters = $request->validate([
                'from' => 'nullable|date',
                'to'   => 'nullable|date',
            ]);

            $from = isset($filters['from']) ? Carbon::parse($filters['from']) : $today->copy();
            $to   = isset($filters['to'])   ? Carbon::parse($filters['to'])   : $today->copy();

            // Guard against reversed ranges.
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            $rangeLabel = $from->isSameDay($to)
                ? $from->format('M d, Y')
                : $from->format('M d, Y') . ' – ' . $to->format('M d, Y');

            $rangeReport = $this->salesReportService->rangeReport($from, $to, $rangeLabel);

            $itemPerformance = [
                'items' => $rangeReport->itemSales,   // sorted by quantity desc
                'label' => $rangeLabel,
            ];
            $filtersOut = [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ];
        }

        return Inertia::render('Dashboard/Index', [
            'isAdmin' => $isAdmin,
            'today' => [
                'date'        => $today->format('l, M d, Y'),
                'revenue'     => round($todayReport->totalRevenue, 2),
                'orders'      => $todayReport->totalOrders,
                'cash'        => round($cashCollected, 2),
                'topItems'    => array_slice($todayReport->itemSales, 0, 10),
            ],
            'itemPerformance' => $itemPerformance,
            'filters'         => $filtersOut,
        ]);
    }
}
