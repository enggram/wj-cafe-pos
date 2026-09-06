# Requirements Document

## Introduction

This feature adds Expense Tracking to the WhiteJersey Cafe POS and integrates recorded expenses into the existing Profit & Loss (P&L) reporting. It introduces admin-managed, custom expense categories (for example Salary, Rent, Electricity, Gas, Maintenance, Miscellaneous) and admin-recorded expense entries, with daily and monthly expense views grouped by category. It also enhances the existing weekly, monthly, and yearly P&L reports so that costs are presented as separate line items — Inventory Purchases (existing) and Expenses (new) — rather than a single merged spending figure.

Salary is treated as an ordinary expense category; there is no separate staff-linked salary module. Salary payments are recorded as expense entries under the Salary category, using the description field to note the staff member's name.

All capabilities in this feature (managing expense categories, recording and viewing expenses, and the enhanced P&L reports) are admin-only, consistent with the current application where staff cannot access financial information.

## Glossary

- **POS**: The WhiteJersey Cafe point-of-sale application.
- **Admin**: An authenticated user with the admin role, authorized to manage financial and administrative data.
- **Staff**: An authenticated user with the staff role, restricted to taking orders and handling billing.
- **Expense_Category**: An admin-managed classification for expenses (for example Salary, Rent), with a unique name and an active/inactive state.
- **Expense_Entry**: A single recorded expense consisting of an expense category, an amount, an optional description, and an expense date.
- **Expense_Service**: The backend service responsible for managing expense categories, recording expense entries, and producing expense views.
- **Category_Total**: The sum of all expense entry amounts belonging to a single expense category within a reporting period.
- **Grand_Total**: The sum of all expense entry amounts across all categories within a reporting period.
- **ProfitLoss_Service**: The existing service that calculates Revenue minus costs for a period and reports a profit/loss/break-even status.
- **Revenue**: The sum of grand totals for completed/billed orders within a period.
- **Inventory_Purchases**: The sum of purchase entry costs within a period (existing spending source).
- **Total_Expenses**: The sum of all expense entry amounts within a period.
- **Net_Amount**: Revenue minus the sum of Inventory Purchases and Total Expenses for a period.
- **Amount**: A positive monetary decimal value with exactly two decimal places, between 0.01 and 9,999,999.99 inclusive.

## Requirements

### Requirement 1: Manage Expense Categories

**User Story:** As an admin, I want to create, edit, and deactivate custom expense categories, so that I can classify expenses in ways that match my cafe's operations.

#### Acceptance Criteria

1. WHEN an admin submits a new expense category with a name between 1 and 100 characters that is unique after trimming, THE Expense_Service SHALL create the expense category in an active state.
2. WHEN an admin creates an expense category, THE Expense_Service SHALL store the category name with leading and trailing whitespace removed.
3. IF an admin submits an expense category name that is empty after trimming, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the name is required.
4. IF an admin submits an expense category name longer than 100 characters, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the maximum length is 100 characters.
5. IF an admin submits an expense category name that matches an existing category name after trimming, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the name must be unique.
6. WHEN an admin edits an existing expense category with a name between 1 and 100 characters that is unique after trimming, THE Expense_Service SHALL update the category name.
7. WHEN an admin deactivates an active expense category, THE Expense_Service SHALL set the category state to inactive.
8. WHEN an admin activates an inactive expense category, THE Expense_Service SHALL set the category state to active.
9. WHILE an expense category is inactive, THE Expense_Service SHALL exclude the category from the list of categories available for selection when recording a new expense entry.
10. THE Expense_Service SHALL retain expense entries previously recorded under a category after that category is deactivated.

### Requirement 2: Record Expense Entries

**User Story:** As an admin, I want to record individual expenses with a category, amount, description, and date, so that I can track all cafe spending outside of inventory purchases.

#### Acceptance Criteria

1. WHEN an admin submits an expense entry with a valid active expense category, a valid amount, an optional description, and a valid expense date, THE Expense_Service SHALL create the expense entry.
2. THE Expense_Service SHALL allow an admin to record multiple expense entries for the same expense date.
3. IF an admin submits an expense entry without selecting an expense category, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the category is required.
4. IF an admin submits an expense entry referencing an expense category that does not exist or is inactive, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the category selection is invalid.
5. IF an admin submits an expense entry with an amount that is not numeric, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the amount must be numeric.
6. IF an admin submits an expense entry with an amount less than 0.01 or greater than 9,999,999.99, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the amount must be between 0.01 and 9,999,999.99.
7. THE Expense_Service SHALL store expense amounts as decimal values with exactly two decimal places.
8. WHERE an admin provides a description, THE Expense_Service SHALL accept a description of up to 255 characters.
9. IF an admin submits a description longer than 255 characters, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the maximum length is 255 characters.
10. WHERE an admin omits the description, THE Expense_Service SHALL create the expense entry with an empty description.
11. IF an admin submits an expense entry without an expense date, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the expense date is required.
12. IF an admin submits an expense entry with an expense date that cannot be parsed as a valid date, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the expense date must be a valid date.
13. IF an admin submits an expense entry with an expense date later than the current date, THEN THE Expense_Service SHALL reject the submission and return a validation message indicating the expense date must not be in the future.

### Requirement 3: View Daily Expenses

**User Story:** As an admin, I want to view all expenses for a specific day grouped by category, so that I can review daily spending at a glance.

#### Acceptance Criteria

1. WHEN an admin requests the expenses for a specific date, THE Expense_Service SHALL return all expense entries recorded on that date grouped by expense category.
2. WHEN an admin requests the expenses for a specific date, THE Expense_Service SHALL return a Category_Total for each expense category present on that date.
3. WHEN an admin requests the expenses for a specific date, THE Expense_Service SHALL return a Grand_Total equal to the sum of all Category_Totals for that date.
4. THE Expense_Service SHALL round each Category_Total and the Grand_Total to two decimal places.
5. IF no expense entries exist for the requested date, THEN THE Expense_Service SHALL return an empty result with a Grand_Total of 0.00.

### Requirement 4: View Monthly Expenses

**User Story:** As an admin, I want to view all expenses for a specific month grouped by category, so that I can review monthly spending trends.

#### Acceptance Criteria

1. WHEN an admin requests the expenses for a specific year and month, THE Expense_Service SHALL return the expense entries recorded within that month grouped by expense category.
2. WHEN an admin requests the expenses for a specific year and month, THE Expense_Service SHALL return a Category_Total for each expense category present within that month.
3. WHEN an admin requests the expenses for a specific year and month, THE Expense_Service SHALL return a Grand_Total equal to the sum of all Category_Totals for that month.
4. THE Expense_Service SHALL round each Category_Total and the Grand_Total to two decimal places.
5. IF no expense entries exist for the requested year and month, THEN THE Expense_Service SHALL return an empty result with a Grand_Total of 0.00.

### Requirement 5: Integrate Expenses into Profit & Loss Reports

**User Story:** As an admin, I want the P&L report to show inventory purchases and expenses as separate line items, so that I can see exactly where my costs come from and how they affect net profit.

#### Acceptance Criteria

1. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL calculate Revenue for the period as defined by the existing revenue calculation.
2. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL calculate Inventory_Purchases for the period as the sum of purchase entry costs within that period.
3. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL calculate Total_Expenses for the period as the sum of expense entry amounts within that period.
4. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL return Inventory_Purchases and Total_Expenses as separate line items.
5. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL return a breakdown of Total_Expenses by expense category, with a Category_Total for each category that has expense entries in the period.
6. WHEN an admin requests a weekly, monthly, or yearly P&L report, THE ProfitLoss_Service SHALL calculate Net_Amount as Revenue minus the sum of Inventory_Purchases and Total_Expenses.
7. THE ProfitLoss_Service SHALL round Revenue, Inventory_Purchases, Total_Expenses, each expense Category_Total, and Net_Amount to two decimal places.
8. WHEN Net_Amount is greater than zero, THE ProfitLoss_Service SHALL report the status as "profit".
9. WHEN Net_Amount is less than zero, THE ProfitLoss_Service SHALL report the status as "loss".
10. WHEN Net_Amount is equal to zero, THE ProfitLoss_Service SHALL report the status as "break-even".
11. IF no expense entries exist within the reporting period, THEN THE ProfitLoss_Service SHALL report Total_Expenses as 0.00 and an empty expense category breakdown.

### Requirement 6: Restrict Expense Features to Admins

**User Story:** As a cafe owner, I want expense management and financial reports restricted to admins, so that staff cannot view or alter financial data.

#### Acceptance Criteria

1. IF a staff user attempts to create, edit, deactivate, or activate an expense category, THEN THE POS SHALL deny the request with a 403 authorization error.
2. IF a staff user attempts to record an expense entry, THEN THE POS SHALL deny the request with a 403 authorization error.
3. IF a staff user attempts to view daily or monthly expense reports, THEN THE POS SHALL deny the request with a 403 authorization error.
4. IF a staff user attempts to view any P&L report, THEN THE POS SHALL deny the request with a 403 authorization error.
5. IF an unauthenticated request attempts to access any expense management or P&L report endpoint, THEN THE POS SHALL redirect the request to authentication.
6. WHEN an admin accesses expense management or P&L report endpoints, THE POS SHALL grant access.

### Requirement 7: Present Expense Interfaces Consistent With Existing UI

**User Story:** As an admin, I want the expense screens to match the existing look and behavior of the app, so that the feature feels like a natural part of the POS.

#### Acceptance Criteria

1. THE POS SHALL render expense category management, expense entry recording, and expense report views as Inertia pages using the existing black-and-red themed UI patterns.
2. WHEN an admin recording an expense selects an expense category, THE POS SHALL present only active expense categories as selectable options.
3. WHEN a validation error occurs during expense category or expense entry submission, THE POS SHALL display the validation messages returned by the Expense_Service on the corresponding form.
4. WHEN an admin views a daily or monthly expense report that contains no entries, THE POS SHALL display an empty-state message indicating no expenses were recorded for the selected period.
5. WHEN an admin views a P&L report, THE POS SHALL display Revenue, Inventory_Purchases, Total_Expenses with its per-category breakdown, and Net_Amount with its profit/loss/break-even status.
