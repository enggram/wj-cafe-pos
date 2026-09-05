<?php

namespace App\Http\Controllers;

use App\Contracts\InventoryServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryServiceInterface $inventoryService
    ) {}

    /**
     * Main inventory page — shows both daily and monthly spending.
     */
    public function index(Request $request): Response
    {
        // Daily
        $date = $request->query('date', Carbon::today()->toDateString());
        $daily = $this->inventoryService->dailySpending(Carbon::parse($date));

        // Monthly (defaults to current month; can be overridden via query params)
        $year  = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);
        $monthly = $this->inventoryService->monthlySpending($year, $month);

        return Inertia::render('Inventory/Index', [
            'dailySpending' => [
                'date'      => $daily->date->toDateString(),
                'entries'   => $daily->entries,
                'totalCost' => $daily->totalCost,
            ],
            'monthlySpending' => [
                'year'       => $monthly->year,
                'month'      => $monthly->month,
                'itemTotals' => $monthly->itemTotals,
                'grandTotal' => $monthly->grandTotal,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name'     => 'required|string|max:100',
            'quantity'      => 'required|numeric|gt:0',
            'cost'          => 'required|numeric|min:0.01|max:999999.99',
            'purchase_date' => 'required|date|before_or_equal:today',
        ]);

        $this->inventoryService->recordPurchase($validated);

        return redirect()->back()->with('success', 'Purchase entry recorded.');
    }

    public function dailyView(Request $request): Response
    {
        $date  = $request->query('date', Carbon::today()->toDateString());
        $daily = $this->inventoryService->dailySpending(Carbon::parse($date));

        return Inertia::render('Inventory/Daily', [
            'spending' => [
                'date'      => $daily->date->toDateString(),
                'entries'   => $daily->entries,
                'totalCost' => $daily->totalCost,
            ],
        ]);
    }

    public function monthlyView(Request $request): Response
    {
        $year    = (int) $request->query('year', Carbon::now()->year);
        $month   = (int) $request->query('month', Carbon::now()->month);
        $monthly = $this->inventoryService->monthlySpending($year, $month);

        return Inertia::render('Inventory/Monthly', [
            'spending' => [
                'year'       => $monthly->year,
                'month'      => $monthly->month,
                'itemTotals' => $monthly->itemTotals,
                'grandTotal' => $monthly->grandTotal,
            ],
        ]);
    }
}
