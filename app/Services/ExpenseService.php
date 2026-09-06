<?php

namespace App\Services;

use App\Contracts\ExpenseServiceInterface;
use App\DTOs\DailyExpenseDTO;
use App\DTOs\MonthlyExpenseDTO;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExpenseService implements ExpenseServiceInterface
{
    // ── Category management ──

    public function createCategory(array $data): ExpenseCategory
    {
        $name = $this->validateCategoryName($data['name'] ?? null, null);

        return ExpenseCategory::create([
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function updateCategory(int $id, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::findOrFail($id);
        $name = $this->validateCategoryName($data['name'] ?? null, $category->id);
        $category->update(['name' => $name]);

        return $category;
    }

    public function deactivateCategory(int $id): void
    {
        ExpenseCategory::findOrFail($id)->update(['is_active' => false]);
    }

    public function activateCategory(int $id): void
    {
        ExpenseCategory::findOrFail($id)->update(['is_active' => true]);
    }

    public function listCategories(): Collection
    {
        return ExpenseCategory::orderBy('name')->get();
    }

    public function activeCategories(): Collection
    {
        return ExpenseCategory::where('is_active', true)->orderBy('name')->get();
    }

    // ── Expense entries ──

    public function recordExpense(array $data): ExpenseEntry
    {
        $data = $this->validateExpenseData($data);

        return ExpenseEntry::create([
            'expense_category_id' => $data['expense_category_id'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'expense_date' => $data['expense_date'],
        ]);
    }

    // ── Views ──

    public function dailyExpenses(Carbon $date): DailyExpenseDTO
    {
        $entries = ExpenseEntry::with('expenseCategory')
            ->whereDate('expense_date', $date->toDateString())
            ->get();

        $categories = $entries->groupBy(fn (ExpenseEntry $entry) => $entry->expenseCategory->name)
            ->map(fn (Collection $group, string $name) => [
                'category_name' => $name,
                'entries' => $group->map(fn (ExpenseEntry $entry) => [
                    'description' => $entry->description,
                    'amount' => (float) $entry->amount,
                ])->all(),
                'total' => round($group->sum(fn (ExpenseEntry $entry) => (float) $entry->amount), 2),
            ])
            ->values()
            ->all();

        $grandTotal = round($entries->sum(fn (ExpenseEntry $entry) => (float) $entry->amount), 2);

        return new DailyExpenseDTO(
            date: $date,
            categories: $categories,
            grandTotal: $grandTotal,
        );
    }

    public function monthlyExpenses(int $year, int $month): MonthlyExpenseDTO
    {
        $entries = ExpenseEntry::with('expenseCategory')
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->get();

        $categoryTotals = $entries->groupBy(fn (ExpenseEntry $entry) => $entry->expenseCategory->name)
            ->map(fn (Collection $group, string $name) => [
                'category_name' => $name,
                'total' => round($group->sum(fn (ExpenseEntry $entry) => (float) $entry->amount), 2),
            ])
            ->values()
            ->all();

        $grandTotal = round($entries->sum(fn (ExpenseEntry $entry) => (float) $entry->amount), 2);

        return new MonthlyExpenseDTO(
            year: $year,
            month: $month,
            categoryTotals: $categoryTotals,
            grandTotal: $grandTotal,
        );
    }

    // ── Consumed by ProfitLossService ──

    public function expenseTotalForPeriod(Carbon $start, Carbon $end): float
    {
        return round((float) ExpenseEntry::whereBetween('expense_date', [
            $start->toDateString(),
            $end->toDateString(),
        ])->sum('amount'), 2);
    }

    public function expenseBreakdownForPeriod(Carbon $start, Carbon $end): array
    {
        return ExpenseEntry::with('expenseCategory')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (ExpenseEntry $entry) => $entry->expenseCategory->name)
            ->map(fn (Collection $group, string $name) => [
                'category_name' => $name,
                'total' => round($group->sum(fn (ExpenseEntry $entry) => (float) $entry->amount), 2),
            ])
            ->values()
            ->all();
    }

    // ── Private validators (mirror InventoryService) ──

    private function validateCategoryName(?string $name, ?int $ignoreId): string
    {
        $errors = [];

        $name = $name !== null ? trim($name) : '';

        if ($name === '') {
            $errors['name'] = ['The name field is required.'];
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = ['The name must not exceed 100 characters.'];
        } else {
            $query = ExpenseCategory::where('name', $name);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                $errors['name'] = ['A category with this name already exists.'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $name;
    }

    private function validateExpenseData(array $data): array
    {
        $errors = [];

        // Validate expense_category_id
        if (! isset($data['expense_category_id']) || $data['expense_category_id'] === '' || $data['expense_category_id'] === null) {
            $errors['expense_category_id'] = ['The category field is required.'];
        } else {
            $categoryExists = ExpenseCategory::where('id', $data['expense_category_id'])
                ->where('is_active', true)
                ->exists();
            if (! $categoryExists) {
                $errors['expense_category_id'] = ['The selected category is invalid.'];
            }
        }

        // Validate amount
        if (! isset($data['amount']) || ! is_numeric($data['amount'])) {
            $errors['amount'] = ['The amount field is required and must be numeric.'];
        } elseif ((float) $data['amount'] < 0.01 || (float) $data['amount'] > 9999999.99) {
            $errors['amount'] = ['The amount must be between 0.01 and 9999999.99.'];
        } else {
            $data['amount'] = round((float) $data['amount'], 2);
        }

        // Validate description (optional)
        $description = isset($data['description']) && $data['description'] !== null ? $data['description'] : '';
        if (mb_strlen($description) > 255) {
            $errors['description'] = ['The description must not exceed 255 characters.'];
        }
        $data['description'] = $description;

        // Validate expense_date
        if (! isset($data['expense_date']) || $data['expense_date'] === '') {
            $errors['expense_date'] = ['The expense date field is required.'];
        } else {
            try {
                $date = Carbon::parse($data['expense_date']);
                if ($date->startOfDay()->gt(Carbon::today())) {
                    $errors['expense_date'] = ['The expense date must not be in the future.'];
                }
                $data['expense_date'] = $date->toDateString();
            } catch (\Exception $e) {
                $errors['expense_date'] = ['The expense date must be a valid date.'];
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
