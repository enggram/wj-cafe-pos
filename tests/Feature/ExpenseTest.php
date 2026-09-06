<?php

use App\Enums\UserRole;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use App\Models\User;
use App\Contracts\ExpenseServiceInterface;
use App\Contracts\ProfitLossServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->service = app(ExpenseServiceInterface::class);
});

// ── Category management ──
it('creates an expense category trimmed and active', function () {
    $cat = $this->service->createCategory(['name' => '  Rent  ']);
    expect($cat->name)->toBe('Rent');
    expect($cat->is_active)->toBeTrue();
});

it('rejects duplicate category name', function () {
    ExpenseCategory::factory()->create(['name' => 'Rent']);
    $this->service->createCategory(['name' => 'Rent']);
})->throws(ValidationException::class);

it('rejects blank category name', function () {
    $this->service->createCategory(['name' => '   ']);
})->throws(ValidationException::class);

it('deactivation retains entries', function () {
    $cat = ExpenseCategory::factory()->create();
    ExpenseEntry::factory()->count(3)->create(['expense_category_id' => $cat->id]);
    $this->service->deactivateCategory($cat->id);
    expect($cat->fresh()->is_active)->toBeFalse();
    expect(ExpenseEntry::where('expense_category_id', $cat->id)->count())->toBe(3);
});

it('activeCategories returns only active', function () {
    ExpenseCategory::factory()->count(2)->create();
    ExpenseCategory::factory()->inactive()->count(3)->create();
    expect($this->service->activeCategories())->toHaveCount(2);
});

// ── Expense recording ──
it('records a valid expense with rounded amount', function () {
    $cat = ExpenseCategory::factory()->create();
    $entry = $this->service->recordExpense([
        'expense_category_id' => $cat->id,
        'amount' => 1234.567,
        'description' => 'Test',
        'expense_date' => today()->toDateString(),
    ]);
    expect((float) $entry->amount)->toBe(1234.57);
});

it('rejects amount out of range', function () {
    $cat = ExpenseCategory::factory()->create();
    $this->service->recordExpense([
        'expense_category_id' => $cat->id,
        'amount' => 0,
        'expense_date' => today()->toDateString(),
    ]);
})->throws(ValidationException::class);

it('rejects future date', function () {
    $cat = ExpenseCategory::factory()->create();
    $this->service->recordExpense([
        'expense_category_id' => $cat->id,
        'amount' => 100,
        'expense_date' => today()->addDay()->toDateString(),
    ]);
})->throws(ValidationException::class);

it('rejects inactive category', function () {
    $cat = ExpenseCategory::factory()->inactive()->create();
    $this->service->recordExpense([
        'expense_category_id' => $cat->id,
        'amount' => 100,
        'expense_date' => today()->toDateString(),
    ]);
})->throws(ValidationException::class);

// ── Daily / monthly ──
it('groups daily expenses by category with totals', function () {
    $rent = ExpenseCategory::factory()->create(['name' => 'Rent']);
    $salary = ExpenseCategory::factory()->create(['name' => 'Salary']);
    ExpenseEntry::factory()->create(['expense_category_id' => $rent->id, 'amount' => 100, 'expense_date' => '2026-01-15']);
    ExpenseEntry::factory()->create(['expense_category_id' => $salary->id, 'amount' => 200, 'expense_date' => '2026-01-15']);
    ExpenseEntry::factory()->create(['expense_category_id' => $salary->id, 'amount' => 50, 'expense_date' => '2026-01-15']);

    $dto = $this->service->dailyExpenses(Carbon::parse('2026-01-15'));
    expect($dto->grandTotal)->toBe(350.0);
    expect($dto->categories)->toHaveCount(2);
});

it('returns empty daily result with 0 total', function () {
    $dto = $this->service->dailyExpenses(Carbon::parse('2026-06-01'));
    expect($dto->categories)->toBe([]);
    expect($dto->grandTotal)->toBe(0.0);
});

// ── P&L integration ──
it('P&L breakdown sums to total expenses and net subtracts them', function () {
    $pl = app(ProfitLossServiceInterface::class);
    $cat = ExpenseCategory::factory()->create(['name' => 'Rent']);
    ExpenseEntry::factory()->create(['expense_category_id' => $cat->id, 'amount' => 500, 'expense_date' => '2026-03-10']);

    $report = $pl->monthlyReport(2026, 3);
    expect($report->totalExpenses)->toBe(500.0);
    expect($report->inventoryPurchases)->toBe(0.0);
    expect($report->totalSpending)->toBe(500.0);
    expect($report->netAmount)->toBe(-500.0);
    expect($report->status)->toBe('loss');
    $sum = array_sum(array_column($report->expenseBreakdown, 'total'));
    expect(round($sum, 2))->toBe($report->totalExpenses);
});

// ── Period boundary (regression) ──
it('includes expenses on the last day of the period', function () {
    $pl = app(ProfitLossServiceInterface::class);
    $cat = ExpenseCategory::factory()->create(['name' => 'Salary']);
    // Sunday 2026-09-06 is the LAST day of the week starting Mon 2026-08-31
    ExpenseEntry::factory()->create([
        'expense_category_id' => $cat->id,
        'amount' => 2000,
        'expense_date' => '2026-09-06',
    ]);

    $report = $pl->weeklyReport(Carbon::parse('2026-08-31'));
    expect($report->totalExpenses)->toBe(2000.0);
    expect($report->expenseBreakdown)->toHaveCount(1);
});

// ── Access control ──
it('blocks staff from expense endpoints', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff)->get('/expenses')->assertStatus(403);
    $this->actingAs($staff)->get('/reports/profit-loss')->assertStatus(403);
});

it('allows admin to view expenses', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/expenses')->assertStatus(200);
});

it('redirects guests to login', function () {
    $this->get('/expenses')->assertRedirect('/login');
});
