<?php

namespace App\Http\Controllers;

use App\Contracts\ExpenseServiceInterface;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ExpenseServiceInterface $expenseService
    ) {}

    public function store(Request $request)
    {
        $this->expenseService->createCategory($request->all());

        return redirect()->back()->with('success', 'Expense category created.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->expenseService->updateCategory($expenseCategory->id, $request->all());

        return back()->with('success', 'Expense category updated.');
    }

    public function deactivate(ExpenseCategory $expenseCategory)
    {
        $this->expenseService->deactivateCategory($expenseCategory->id);

        return back()->with('success', 'Expense category deactivated.');
    }

    public function activate(ExpenseCategory $expenseCategory)
    {
        $this->expenseService->activateCategory($expenseCategory->id);

        return back()->with('success', 'Expense category activated.');
    }
}
