# Implementation Plan: Expense Tracking

## Overview

This plan implements the Expense Tracking feature for the existing Laravel 11 + Inertia + Vue 3 + SQLite cafe POS, and integrates recorded expenses into the P&L reports. Work proceeds bottom-up: database layer → contracts/DTOs → `ExpenseService` → P&L integration → controllers/routes → frontend → seeding → tests. Each step builds on the previous one and ends by wiring the new code into the running application (service bindings, routes, nav link). All code follows the existing patterns described in the design (`Design › Components and Interfaces`): service-layer validation with `$errors` + `ValidationException::withMessages`, `round($x, 2)` money math, Carbon date validation, thin controllers, and the black/red Inertia UI.

The frontend is built inside Docker (`docker exec wj-cafe-app npm run build`) and tests run via `docker exec wj-cafe-app ./test.sh` (in-memory SQLite, Pest + `RefreshDatabase`).

## Tasks

- [ ] 1. Database layer: migrations, models, factories
  - [ ] 1.1 Create the `expense_categories` and `expense_entries` migrations
    - Add `database/migrations/2024_01_01_000009_create_expense_categories_table.php`: `id`, `name` string(100) `unique()`, `is_active` boolean default `true`, `timestamps()`
    - Add `database/migrations/2024_01_01_000010_create_expense_entries_table.php`: `id`, `expense_category_id` foreignId `constrained('expense_categories')->restrictOnDelete()`, `amount` decimal(10,2), `description` string(255) nullable, `expense_date` date, `timestamps()`; add `index('expense_date')` and `index(['expense_category_id', 'expense_date'])`
    - Follow the exact schema in _Design › Migrations (SQLite)_ including the `restrictOnDelete` FK decision
    - _Requirements: 1.1, 1.10, 2.1, 2.7_

  - [ ] 1.2 Create the `ExpenseCategory` and `ExpenseEntry` Eloquent models
    - `app/Models/ExpenseCategory.php`: `HasFactory`, `$fillable = ['name', 'is_active']`, `casts()` → `is_active => 'boolean'`, `expenseEntries()` HasMany relationship
    - `app/Models/ExpenseEntry.php`: `HasFactory`, `$fillable = ['expense_category_id', 'amount', 'description', 'expense_date']`, `casts()` → `amount => 'decimal:2'`, `expense_date => 'date'`, `expenseCategory()` BelongsTo relationship
    - Match the model definitions in _Design › Models_
    - _Requirements: 1.1, 1.10, 2.1, 2.7_

  - [ ] 1.3 Create the `ExpenseCategoryFactory` and `ExpenseEntryFactory`
    - `database/factories/ExpenseCategoryFactory.php`: `name` = unique short phrase, `is_active` = true; add `inactive()` state helper setting `is_active = false`
    - `database/factories/ExpenseEntryFactory.php`: `expense_category_id` via `ExpenseCategory::factory()`, `amount` = `randomFloat(2, 0.01, 9999999.99)`, `description` = optional nullable sentence, `expense_date` = `dateTimeBetween('-1 year', 'today')`
    - Mirror `PurchaseEntryFactory`; follow _Design › Testing Strategy › Factories_
    - _Requirements: 1.1, 2.1_

- [ ] 2. Define service contracts and DTOs
  - [ ] 2.1 Create `ExpenseServiceInterface` and the two expense DTOs
    - `app/Contracts/ExpenseServiceInterface.php` with the full method set from _Design › Interface_: `createCategory`, `updateCategory`, `deactivateCategory`, `activateCategory`, `listCategories`, `activeCategories`, `recordExpense`, `dailyExpenses`, `monthlyExpenses`, `expenseTotalForPeriod`, `expenseBreakdownForPeriod`
    - `app/DTOs/DailyExpenseDTO.php`: readonly `date: Carbon`, `categories: array`, `grandTotal: float`
    - `app/DTOs/MonthlyExpenseDTO.php`: readonly `year: int`, `month: int`, `categoryTotals: array`, `grandTotal: float`
    - Match constructor-promoted readonly shapes in _Design › DTOs_
    - _Requirements: 3.1, 3.2, 3.3, 4.1, 4.2, 4.3_

- [ ] 3. Implement `ExpenseService` and bind it
  - [ ] 3.1 Implement category management methods
    - `createCategory`, `updateCategory`, `deactivateCategory`, `activateCategory`, `listCategories` (ordered by name), `activeCategories` (active only, ordered by name)
    - Private `validateCategoryName(?string, ?int $ignoreId)`: `trim`, required message, `max:100` message, unique-after-trim message ignoring current id on update; build `$errors` and throw `ValidationException::withMessages` exactly like `InventoryService`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10_

  - [ ] 3.2 Implement `recordExpense` and `validateExpenseData`
    - `recordExpense` delegates to the private `validateExpenseData` and creates the `ExpenseEntry`
    - `validateExpenseData` implements the full rules table in _Design › validateExpenseData rules_: category required (2.3), category exists AND `is_active` (2.4), amount numeric (2.5), amount in [0.01, 9999999.99] (2.6), amount stored as `round($amount, 2)` (2.7), description ≤255 and normalized to `''` when omitted (2.8, 2.9, 2.10), date required (2.11), date parseable via `Carbon::parse` in try/catch (2.12), date not future via `->startOfDay()->gt(Carbon::today())` (2.13)
    - Error keys match `useForm` field names (`expense_category_id`, `amount`, `description`, `expense_date`)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 2.11, 2.12, 2.13_

  - [ ] 3.3 Implement `dailyExpenses` and `monthlyExpenses` view methods
    - `dailyExpenses(Carbon $date)`: group that date's entries by category, each category → `{category_name, entries:[{description, amount}], total}` with rounded total, plus rounded `grandTotal` (0.00 when empty); return `DailyExpenseDTO`
    - `monthlyExpenses(int $year, int $month)`: entries scoped to year+month, grouped to `{category_name, total}` category totals with rounded values, plus rounded `grandTotal` (0.00 when empty); return `MonthlyExpenseDTO`
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5_

  - [ ] 3.4 Implement `expenseTotalForPeriod` and `expenseBreakdownForPeriod`
    - `expenseTotalForPeriod(Carbon $start, Carbon $end)`: rounded sum of `amount` where `expense_date` in `[start, end]`
    - `expenseBreakdownForPeriod(Carbon $start, Carbon $end)`: eager-load category, group by category name to `[{category_name, total}]` with rounded totals, `values()->all()` (empty array when none)
    - Follow the implementations in _Design › Service — ExpenseService_
    - _Requirements: 5.3, 5.5, 5.7, 5.11_

  - [ ] 3.5 Bind `ExpenseServiceInterface` in `DomainServiceProvider`
    - Add `$this->app->bind(ExpenseServiceInterface::class, \App\Services\ExpenseService::class);` per _Design › Service Provider Binding_
    - _Requirements: 2.1, 3.1, 4.1_

  - [ ]* 3.6 Write property-based test for category creation trims and activates
    - **Property 1: Category creation trims and activates** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 1: Category creation trims and activates`
    - **Validates: Requirements 1.1, 1.2**

  - [ ]* 3.7 Write property-based test for category name uniqueness after trim
    - **Property 2: Category names are unique after trim** — whitespace-padded variants rejected, no second row (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 2: Category names are unique after trim`
    - **Validates: Requirements 1.5**

  - [ ]* 3.8 Write property-based test for whitespace-only name rejection
    - **Property 3: Whitespace-only category names are rejected** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 3: Whitespace-only category names are rejected`
    - **Validates: Requirements 1.3**

  - [ ]* 3.9 Write property-based test for active-only category selection
    - **Property 4: Only active categories are selectable** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 4: Only active categories are selectable`
    - **Validates: Requirements 1.9**

  - [ ]* 3.10 Write property-based test for deactivation retaining entries
    - **Property 5: Deactivation retains expense entries** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 5: Deactivation retains expense entries`
    - **Validates: Requirements 1.10**

  - [ ]* 3.11 Write property-based test for valid expense persistence and rounding
    - **Property 6: Valid expenses persist with amount rounded to two decimals** (≥100 iterations; include >2-decimal amounts and same-date multiples)
    - Tag: `Feature: expense-tracking, Property 6: Valid expenses persist with amount rounded to two decimals`
    - **Validates: Requirements 2.1, 2.2, 2.7**

  - [ ]* 3.12 Write property-based test for amount range enforcement
    - **Property 7: Out-of-range amounts are rejected and in-range amounts accepted** (≥100 iterations; straddle 0.01 / 9999999.99 boundaries)
    - Tag: `Feature: expense-tracking, Property 7: Out-of-range amounts are rejected and in-range amounts accepted`
    - **Validates: Requirements 2.6**

  - [ ]* 3.13 Write property-based test for future-date rejection
    - **Property 8: Future-dated expenses are rejected** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 8: Future-dated expenses are rejected`
    - **Validates: Requirements 2.13**

  - [ ]* 3.14 Write property-based test for invalid/inactive category rejection
    - **Property 9: Invalid or inactive category on record is rejected** (≥100 iterations; missing, non-existent, inactive)
    - Tag: `Feature: expense-tracking, Property 9: Invalid or inactive category on record is rejected`
    - **Validates: Requirements 2.4**

  - [ ]* 3.15 Write property-based test for daily grouping totals
    - **Property 10: Daily grouping totals are consistent and rounded** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 10: Daily grouping totals are consistent and rounded`
    - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

  - [ ]* 3.16 Write property-based test for monthly grouping totals
    - **Property 11: Monthly grouping totals are consistent, month-scoped, and rounded** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 11: Monthly grouping totals are consistent, month-scoped, and rounded`
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5**

  - [ ]* 3.17 Write example/edge-case Pest tests for `ExpenseService`
    - Category update to new valid name (1.6); deactivate→inactive (1.7); deactivate-then-activate round-trip (1.8)
    - Missing category (2.3), non-numeric amount (2.5), description length 0/255 accepted and 256 rejected (2.8, 2.9), omitted description stored as `''` (2.10), missing date (2.11), unparseable date (2.12)
    - Empty daily (3.5) and monthly (4.5) periods return empty result + `0.00`
    - _Requirements: 1.6, 1.7, 1.8, 2.3, 2.5, 2.8, 2.9, 2.10, 2.11, 2.12, 3.5, 4.5_

- [ ] 4. Checkpoint - service layer
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Integrate expenses into Profit & Loss
  - [ ] 5.1 Extend `ProfitLossDTO` with new fields
    - Add `inventoryPurchases = 0.0`, `totalExpenses = 0.0`, `expenseBreakdown = []` **after** existing params (defaults preserve positional/backward compatibility); keep `totalSpending` as the combined figure per _Design › Extending ProfitLossDTO_
    - _Requirements: 5.4, 5.7_

  - [ ] 5.2 Update `ProfitLossService` to compute the new line items
    - Inject `ExpenseServiceInterface` in the constructor
    - Rename `calculateSpending` → `calculateInventoryPurchases` (same purchase-cost sum query)
    - In `weeklyReport`, `monthlyReport`, `yearlyReport`, compute `revenue`, `inventoryPurchases`, `totalExpenses` (via `expenseTotalForPeriod`), `expenseBreakdown` (via `expenseBreakdownForPeriod`), `spending = round(inventory + expenses, 2)`, `net = round(revenue - spending, 2)`; construct the extended `ProfitLossDTO`; `determineStatus` unchanged
    - Follow _Design › Extending ProfitLossService_
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 5.11_

  - [ ]* 5.3 Write property-based test for P&L line items equal source sums
    - **Property 12: P&L line items equal the rounded sum of their sources** (≥100 iterations; factory-built Bill/PurchaseEntry/ExpenseEntry, no mocking)
    - Tag: `Feature: expense-tracking, Property 12: P&L line items equal the rounded sum of their sources`
    - **Validates: Requirements 5.2, 5.3, 5.7**

  - [ ]* 5.4 Write property-based test for expense breakdown summing to total
    - **Property 13: Expense breakdown sums to total expenses** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 13: Expense breakdown sums to total expenses`
    - **Validates: Requirements 5.5, 5.11**

  - [ ]* 5.5 Write property-based test for net amount math
    - **Property 14: Net amount equals revenue minus combined costs** (≥100 iterations; also assert `totalSpending == round(inventory + expenses, 2)`)
    - Tag: `Feature: expense-tracking, Property 14: Net amount equals revenue minus combined costs`
    - **Validates: Requirements 5.6, 5.7**

  - [ ]* 5.6 Write property-based test for status classification
    - **Property 15: Status classification follows the sign of net** (≥100 iterations)
    - Tag: `Feature: expense-tracking, Property 15: Status classification follows the sign of net`
    - **Validates: Requirements 5.8, 5.9, 5.10**

  - [ ]* 5.7 Write example tests for P&L integration edge cases
    - No expenses → `totalExpenses = 0.00` and empty breakdown (5.11); net == 0 → break-even (5.10); revenue unchanged from existing calculation (5.1); inventory/expenses exposed as separate DTO fields (5.4)
    - _Requirements: 5.1, 5.4, 5.10, 5.11_

- [ ] 6. Controllers and routes
  - [ ] 6.1 Create `ExpenseCategoryController`
    - `store`, `update`, `deactivate`, `activate` mirroring `CategoryController`, delegating to `ExpenseService`, route-model binding on `ExpenseCategory`, `back()->with('success', ...)` messages per _Design › Controllers_
    - _Requirements: 1.1, 1.6, 1.7, 1.8_

  - [ ] 6.2 Create `ExpenseController`
    - `index` (today's daily + this month's monthly + `activeCategories` + `listCategories`), `store` (`recordExpense`), `dailyView` (`?date=`), `monthlyView` (`?year=&month=`), mirroring `InventoryController`; Inertia `render('Expenses/Index', ...)` payload per _Design › Controllers › index() payload_
    - _Requirements: 2.1, 3.1, 4.1, 7.2_

  - [ ] 6.3 Update `ProfitLossController` to pass the new fields
    - Extend `index()` `report` payload with `inventoryPurchases`, `totalExpenses`, `expenseBreakdown`; add `formatted` currency entries for `inventory` and `expenses` per _Design › Controllers › ProfitLossController_
    - _Requirements: 5.4, 5.5, 7.5_

  - [ ] 6.4 Register routes inside the auth+admin middleware group
    - Add the four `/expense-categories` routes and four `/expenses` routes inside the existing `Route::middleware('auth')->group(... admin ...)` block in `routes/web.php` per _Design › Routes_
    - _Requirements: 6.1, 6.2, 6.3, 6.5, 6.6_

  - [ ]* 6.5 Write authorization/integration feature tests for all new endpoints
    - For `/expense-categories` CRUD, `/expenses`, `/expenses/daily`, `/expenses/monthly`, and `/reports/profit-loss`: staff → 403 (6.1–6.4), guest → redirect to `/login` (6.5), admin → 200 + expected Inertia component (6.6, 7.1)
    - Inertia prop assertions: expense entry form receives only active categories (7.2); P&L page receives `inventoryPurchases`, `totalExpenses`, `expenseBreakdown`, `netAmount`, `status` (7.5)
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 7.1, 7.2, 7.5_

- [ ] 7. Frontend (black/red Inertia UI)
  - [ ] 7.1 Build `resources/js/Pages/Expenses/Index.vue`
    - Category management panel: list all categories, inline add (`useForm` → POST `/expense-categories`), edit (PUT), activate/deactivate (PATCH) via `router` + `preserveScroll`; inline `form.errors` with `input-field-error` class (7.3)
    - Expense entry form: `useForm({ expense_category_id, amount, description, expense_date })`; `<select>` populated from `activeCategories` only (7.2); `amount` input `step="0.01" min="0.01" max="9999999.99"`; `expense_date` `type="date" :max="today"`
    - Daily/Monthly tabs (`activeTab` pattern like Inventory): daily lists categories → entries → per-category total → grand total; monthly lists category totals → grand total; empty-state message when no entries (7.4)
    - Use black/red theme utility classes (`bg-brand-black`, `card`, `btn-primary`/`btn-secondary`, `text-brand-red-accent`, `border-brand-red`)
    - _Requirements: 7.1, 7.2, 7.3, 7.4_

  - [ ] 7.2 Update `resources/js/Pages/Reports/ProfitLoss.vue`
    - Add Revenue, Inventory Purchases, Expenses, and Net Amount cards/rows (keep existing status indicator); render per-category expense breakdown table under Expenses with an empty-state row when empty; changes are additive so existing props stay valid
    - _Requirements: 5.4, 5.5, 7.5_

  - [ ] 7.3 Add the admin-only Expenses nav link in `AppLayout.vue`
    - Add `{ href: '/expenses', label: 'Expenses', adminOnly: true }` to `allLinks` per _Design › Vue Pages_
    - _Requirements: 7.1_

- [ ] 8. Seed default expense categories
  - [ ] 8.1 Add default categories to `InitialDataSeeder`
    - Idempotently `firstOrCreate` `Salary`, `Rent`, `Electricity`, `Gas`, `Maintenance`, `Miscellaneous` with `is_active = true`, matching the existing menu-category seeding style per _Design › Seeding consideration_
    - _Requirements: 1.1_

- [ ] 9. Final checkpoint - full verification
  - Run the full test suite via `docker exec wj-cafe-app ./test.sh` and the frontend build via `docker exec wj-cafe-app npm run build`; ensure both pass. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional (property-based tests, example/edge-case tests, and authorization tests) and can be skipped for a faster MVP; the unmarked tasks form the required implementation path.
- Property-based tests use a PHP PBT library (e.g. `giorgiosironi/eris` integrated with Pest) at ≥100 iterations each — do not hand-roll a PBT engine (see _Design › Testing Strategy_).
- Each property test is tagged `Feature: expense-tracking, Property N: ...` and references the design property plus the requirement clauses it validates.
- Money is `decimal` in the DB and `round($x, 2)` in PHP; dates are validated with Carbon rejecting future dates.
- All expense/P&L endpoints live inside the existing `auth` + `admin` middleware group, so authorization is enforced uniformly with no per-controller checks.
- Frontend builds and tests both run inside the Docker container (`wj-cafe-app`).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "2.1"] },
    { "id": 2, "tasks": ["3.1", "3.2", "3.3", "3.4"] },
    { "id": 3, "tasks": ["3.5", "3.6", "3.7", "3.8", "3.9", "3.10", "3.11", "3.12", "3.13", "3.14", "3.15", "3.16", "3.17"] },
    { "id": 4, "tasks": ["5.1"] },
    { "id": 5, "tasks": ["5.2"] },
    { "id": 6, "tasks": ["5.3", "5.4", "5.5", "5.6", "5.7", "6.1", "6.2", "6.3"] },
    { "id": 7, "tasks": ["6.4", "8.1"] },
    { "id": 8, "tasks": ["6.5", "7.1", "7.2", "7.3"] }
  ]
}
```
