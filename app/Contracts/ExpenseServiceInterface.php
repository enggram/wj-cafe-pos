<?php

namespace App\Contracts;

use App\DTOs\DailyExpenseDTO;
use App\DTOs\MonthlyExpenseDTO;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface ExpenseServiceInterface
{
    // Category management
    public function createCategory(array $data): ExpenseCategory;

    public function updateCategory(int $id, array $data): ExpenseCategory;

    public function deactivateCategory(int $id): void;

    public function activateCategory(int $id): void;

    public function listCategories(): Collection;

    public function activeCategories(): Collection;

    // Expense entries
    public function recordExpense(array $data): ExpenseEntry;

    // Views
    public function dailyExpenses(Carbon $date): DailyExpenseDTO;

    public function monthlyExpenses(int $year, int $month): MonthlyExpenseDTO;

    // Consumed by ProfitLossService
    public function expenseTotalForPeriod(Carbon $start, Carbon $end): float;

    public function expenseBreakdownForPeriod(Carbon $start, Carbon $end): array;
}
