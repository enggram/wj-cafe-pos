<?php

namespace App\Http\Controllers;

use App\Contracts\ExpenseServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseServiceInterface $expenseService
    ) {}

    /**
     * Main expenses page — shows both daily and monthly expenses plus category management.
     */
    public function index(Request $request): Response
    {
        // Daily
        $date  = $request->query('date', Carbon::today()->toDateString());
        $daily = $this->expenseService->dailyExpenses(Carbon::parse($date));

        // Monthly (defaults to current month; can be overridden via query params)
        $year    = (int) $request->query('year', Carbon::now()->year);
        $month   = (int) $request->query('month', Carbon::now()->month);
        $monthly = $this->expenseService->monthlyExpenses($year, $month);

        return Inertia::render('Expenses/Index', [
            'categories'       => $this->expenseService->listCategories(),
            'activeCategories' => $this->expenseService->activeCategories(),
            'dailyExpenses' => [
                'date'       => $daily->date->toDateString(),
                'categories' => $daily->categories,
                'grandTotal' => $daily->grandTotal,
            ],
            'monthlyExpenses' => [
                'year'           => $monthly->year,
                'month'          => $monthly->month,
                'categoryTotals' => $monthly->categoryTotals,
                'grandTotal'     => $monthly->grandTotal,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->expenseService->recordExpense($request->all());

        return back()->with('success', 'Expense recorded.');
    }

    public function dailyView(Request $request): Response
    {
        $date  = $request->query('date', Carbon::today()->toDateString());
        $daily = $this->expenseService->dailyExpenses(Carbon::parse($date));

        return Inertia::render('Expenses/Daily', [
            'expenses' => [
                'date'       => $daily->date->toDateString(),
                'categories' => $daily->categories,
                'grandTotal' => $daily->grandTotal,
            ],
        ]);
    }

    public function monthlyView(Request $request): Response
    {
        $year    = (int) $request->query('year', Carbon::now()->year);
        $month   = (int) $request->query('month', Carbon::now()->month);
        $monthly = $this->expenseService->monthlyExpenses($year, $month);

        return Inertia::render('Expenses/Monthly', [
            'expenses' => [
                'year'           => $monthly->year,
                'month'          => $monthly->month,
                'categoryTotals' => $monthly->categoryTotals,
                'grandTotal'     => $monthly->grandTotal,
            ],
        ]);
    }
}
