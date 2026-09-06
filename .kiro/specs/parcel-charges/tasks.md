# Implementation Plan: Parcel Charges

## Overview

This plan implements per-item parcel (take-away) charges for the WhiteJersey Cafe POS by threading a `parcel_rate` value through the existing menu → order-item → bill data path, snapshotting it onto the order item at add time (mirroring the `unit_price` snapshot), and surfacing it on the bill as a distinct line. Work is dependency-ordered: additive migrations first, then models, then the service layer (Menu → Order → Billing), then controllers, then frontend, then factories and the full test suite. No new services, interfaces, bindings, or tables are introduced.

The design uses PHP/Laravel 11 (Service + Interface architecture, Inertia + Vue 3, in-memory SQLite for tests), so all tasks use PHP/Vue. Tests run via `./test.sh` (Pest, `RefreshDatabase`). Because the frontend is built inside the Docker image with no source bind-mount, UI changes require a Docker image rebuild to verify.

Convert the feature design into a series of prompts for a code-generation LLM that will implement each step with incremental progress. Make sure that each prompt builds on the previous prompts, and ends with wiring things together. There should be no hanging or orphaned code that isn't integrated into a previous step. Focus ONLY on tasks that involve writing, modifying, or testing code.

## Tasks

- [ ] 1. Create additive schema migrations for parcel columns
  - [ ] 1.1 Add `parcel_rate` to `menu_items`
    - Create `database/migrations/2024_01_01_000011_add_parcel_rate_to_menu_items.php`
    - `up()`: `$table->decimal('parcel_rate', 8, 2)->default(0.00)->after('price');`
    - `down()`: `$table->dropColumn('parcel_rate');`
    - _Design: Components §1 Migrations; Data Models table_
    - _Requirements: 1.1, 7.1, 7.4_

  - [ ] 1.2 Add `is_parcel` and `parcel_rate` to `order_items`
    - Create `database/migrations/2024_01_01_000012_add_parcel_to_order_items.php`
    - `up()`: `$table->boolean('is_parcel')->default(false)->after('unit_price');` then `$table->decimal('parcel_rate', 8, 2)->default(0.00)->after('is_parcel');`
    - `down()`: `$table->dropColumn(['is_parcel', 'parcel_rate']);`
    - _Design: Components §1 Migrations; Data Models table_
    - _Requirements: 2.6, 7.2, 7.5_

  - [ ] 1.3 Add `items_subtotal` and `parcel_charges_total` to `bills`
    - Create `database/migrations/2024_01_01_000013_add_parcel_charges_to_bills.php`
    - `up()`: `$table->decimal('items_subtotal', 10, 2)->default(0)->after('grand_total');` then `$table->decimal('parcel_charges_total', 10, 2)->default(0)->after('items_subtotal');`
    - `down()`: `$table->dropColumn(['items_subtotal', 'parcel_charges_total']);`
    - _Design: Components §1 Migrations; Data Models table_
    - _Requirements: 4.4, 6.4_

- [ ] 2. Update Eloquent models with fillable fields and casts
  - [ ] 2.1 Extend `MenuItem` model
    - Add `'parcel_rate'` to `$fillable`
    - Add `'parcel_rate' => 'decimal:2'` to `casts()`
    - _Design: Components §2 Models_
    - _Requirements: 1.1, 7.1_

  - [ ] 2.2 Extend `OrderItem` model
    - Add `'is_parcel'` and `'parcel_rate'` to `$fillable`
    - Add `'is_parcel' => 'boolean'` and `'parcel_rate' => 'decimal:2'` to `casts()`
    - _Design: Components §2 Models_
    - _Requirements: 2.1, 2.6, 7.2, 7.5_

  - [ ] 2.3 Extend `Bill` model
    - Add `'items_subtotal'` and `'parcel_charges_total'` to `$fillable`
    - Add both as `'decimal:2'` in `casts()`
    - _Design: Components §2 Models_
    - _Requirements: 4.4, 6.4_

- [ ] 3. Implement MenuService parcel rate validation and persistence
  - [ ] 3.1 Extend `validateItemData` to validate optional `parcel_rate`
    - Mirror the `price_adjustment` pattern: when `parcel_rate` is present and non-empty, reject non-numeric with "must be a numeric value" and reject `< 0.00` or `> 9999.99` with "must be between 0.00 and 9999.99"; accumulate into `$errors` and throw a single `ValidationException`
    - When omitted, null, or empty string, default `$data['parcel_rate'] = 0.00`
    - Treat `0.00` as valid (no charge)
    - _Design: Components §3 MenuService; Error Handling_
    - _Requirements: 1.3, 1.4, 1.5, 1.6, 7.1_

  - [ ] 3.2 Persist `parcel_rate` in `createItem` and `updateItem`
    - Add `'parcel_rate' => $data['parcel_rate']` to the `MenuItem::create([...])` payload in `createItem`
    - Add `'parcel_rate' => $data['parcel_rate']` to the `$menuItem->update([...])` payload in `updateItem`
    - _Design: Components §3 MenuService_
    - _Requirements: 1.1, 1.2_

  - [ ]* 3.3 Write property test for parcel rate persistence
    - **Property 1: Valid parcel rate is persisted with two-decimal precision**
    - Random valid rates (0.00–9999.99) → `createItem`/`updateItem`; assert stored `parcel_rate == round(input, 2)`; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 1: valid rate persisted with 2dp`
    - **Validates: Requirements 1.1, 1.2, 1.6**

  - [ ]* 3.4 Write property test for parcel rate rejection
    - **Property 2: Out-of-range or non-numeric parcel rate is rejected**
    - Random non-numeric strings and out-of-range numbers → assert `ValidationException` with `parcel_rate` key; assert nothing persisted; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 2: invalid rate rejected`
    - **Validates: Requirements 1.4, 1.5**

  - [ ]* 3.5 Write example/edge tests for MenuService defaults and boundaries
    - Create with no `parcel_rate` → stored `0.00`; boundary `0.00` and `9999.99` accepted; just-outside rejected
    - _Requirements: 1.3, 1.6, 7.1, 7.4_

- [ ] 4. Implement OrderService parcel snapshotting and merge identity
  - [ ] 4.1 Add `resolveParcelRate` helper and snapshot in `attachItems`
    - Add `private function resolveParcelRate(int $menuItemId): string` mirroring `resolveUnitPrice` (returns `number_format((float) $menuItem->parcel_rate, 2, '.', '')`)
    - In `attachItems`, read `$isParcel = (bool) ($item['is_parcel'] ?? false)` and snapshot `parcel_rate` + set `is_parcel` on each created order item
    - Update docblock `@param` lines on `createOrder`, `addItems`, `attachItems` to document the new `is_parcel` key
    - _Design: Components §4 OrderService_
    - _Requirements: 2.1, 2.2, 2.6, 2.8, 7.2, 7.5_

  - [ ] 4.2 Update `addItems` merge identity to include `is_parcel`
    - Change the existing-line lookup to match on `(menu_item_id, sub_variety_id, is_parcel)` so parcel and dine-in lines of the same item stay separate; merge quantity only on matching flag
    - On new lines, snapshot `parcel_rate` and set `is_parcel`
    - _Design: Components §4 OrderService; Data Models "Order-item line identity"_
    - _Requirements: 2.3, 2.4, 2.5_

  - [ ]* 4.3 Write property test for parcel flag persistence
    - **Property 3: Order line preserves the submitted parcel flag**
    - Random line sets with random/omitted `is_parcel` via `createOrder` and `addItems`; assert stored `is_parcel` matches submitted/default (false); ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 3: order line preserves parcel flag`
    - **Validates: Requirements 2.1, 2.2, 2.3, 2.4, 7.2, 7.5**

  - [ ]* 4.4 Write property test for parcel rate snapshot
    - **Property 4: Order item snapshots the menu item's current parcel rate**
    - Random menu `parcel_rate` (including 0.00); add a line; assert order item `parcel_rate == menu rate at add time`; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 4: order item snapshots menu rate`
    - **Validates: Requirements 2.6, 2.8, 7.1**

  - [ ]* 4.5 Write property test for snapshot immutability
    - **Property 5: Snapshotted parcel rate is immutable under later menu edits**
    - Add a line, then mutate the menu item's `parcel_rate` to a different random value; assert the order item's `parcel_rate` is unchanged; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 5: snapshot immutable under menu edits`
    - **Validates: Requirements 2.7**

  - [ ]* 4.6 Write property test for merge identity separation
    - **Property 6: Merge keeps parcel and dine-in lines of the same item separate**
    - Add the same item once dine-in and once parcel via `addItems`; assert two distinct lines with differing flags; assert same-flag re-adds merge quantities; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 6: parcel and dine-in lines stay separate`
    - **Validates: Requirements 2.5**

- [ ] 5. Implement BillingService parcel charge aggregation
  - [ ] 5.1 Compute and store the three bill totals in `generateBill`
    - `items_subtotal = round(Σ unit_price × quantity, 2)`
    - `parcel_charges_total = round(Σ parcel_rate × quantity where is_parcel, 2)`
    - `grand_total = round(items_subtotal + parcel_charges_total, 2)`
    - Store all three on the `Bill::create([...])`; leave idempotency, order lookup, and status transitions unchanged
    - _Design: Components §5 BillingService_
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 6.1, 7.3_

  - [ ]* 5.2 Write property test for per-line parcel charge
    - **Property 7: Per-line parcel charge equals rate times quantity, zero for dine-in**
    - Random rate, quantity, flag; assert per-line charge `== is_parcel ? round(rate*qty, 2) : 0.00`; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 7: per-line charge = rate*qty or 0`
    - **Validates: Requirements 3.1, 3.2, 3.4**

  - [ ]* 5.3 Write property test for parcel charges total
    - **Property 8: Parcel charges total equals the sum of per-line parcel charges**
    - Random orders; assert `parcel_charges_total == round(Σ per-line parcel charges, 2)` (only parcel lines contribute); ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 8: parcel_charges_total = Σ per-line`
    - **Validates: Requirements 3.3, 4.2**

  - [ ]* 5.4 Write property test for grand total composition
    - **Property 9: Grand total equals items subtotal plus parcel charges total**
    - Random orders; assert `grand_total == round(items_subtotal + parcel_charges_total, 2)` and `items_subtotal == round(Σ unit_price*qty, 2)`; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 9: grand_total = items_subtotal + parcel_charges_total`
    - **Validates: Requirements 4.1, 4.3, 4.5, 6.1**

  - [ ]* 5.5 Write property test for dine-in-only orders
    - **Property 10: A dine-in-only order has zero parcel charges and grand total equal to subtotal**
    - Random dine-in-only orders; assert `parcel_charges_total == 0.00` and `grand_total == items_subtotal`; ≥100 iterations
    - Tag: `// Feature: parcel-charges, Property 10: dine-in-only → 0 parcel, grand=subtotal`
    - **Validates: Requirements 4.6, 7.3**

- [ ] 6. Checkpoint - backend service layer
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Update controllers for HTTP validation and bill formatting
  - [ ] 7.1 Add parcel validation rules to `OrderController`
    - Add `'items.*.is_parcel' => 'nullable|boolean'` to both `store` and `addItems` validation (existing rules unchanged)
    - _Design: Components §6 Controllers; Error Handling_
    - _Requirements: 2.1, 2.3, 7.5_

  - [ ] 7.2 Add parcel rate validation rules to `MenuController`
    - Add `'parcel_rate' => 'nullable|numeric|min:0|max:9999.99'` to both `store` and `update` (no route change; menu routes stay inside the `admin` middleware group)
    - _Design: Components §6 Controllers; Error Handling_
    - _Requirements: 1.1, 1.2, 1.4, 1.5_

  - [ ] 7.3 Extend `BillingController::formatBill` with parcel data
    - Add per-line `is_parcel` (bool), `parcel_rate`, and `parcel_line_total` (`is_parcel ? round(parcel_rate*qty, 2) : 0.0`)
    - Add top-level `items_subtotal` and `parcel_charges_total` alongside `grand_total`
    - _Design: Components §6 Controllers `formatBill`_
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 6.4_

  - [ ]* 7.4 Write example test for `formatBill` payload shape
    - Assert per-line `is_parcel`, `parcel_rate`, `parcel_line_total` and top-level `items_subtotal`, `parcel_charges_total`, `grand_total`; parcel-free order shows `parcel_charges_total` 0.00
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 6.4_

  - [ ]* 7.5 Write admin-only authorization tests for parcel rate editing
    - Staff `POST /menu` or `PUT /menu/{id}` with `parcel_rate` → 403; admin → success; unauthenticated `POST /menu` → redirect to login; staff creates parcel order + generates/settles bill → success
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 8. Update frontend Vue views for parcel UI
  - [ ] 8.1 Add parcel rate input and display to `Menu/Index.vue`
    - Add a "Parcel Rate (₹)" number input (`step="0.01" min="0" max="9999.99"`) to the create/edit form bound to a `parcel_rate` field (default `0`); show a "Parcel ₹X.XX" tag on item rows when `parcel_rate > 0`; follow the black/red theme
    - _Design: Components §7 Frontend_
    - _Requirements: 1.1, 1.2, 5.5_

  - [ ] 8.2 Add per-line parcel toggle to `Orders/Create.vue`
    - Add a per-line "Parcel" toggle/checkbox next to the quantity stepper; key selection state on `menu_item_id + is_parcel` so a dine-in and a parcel line of the same item are separate; include `is_parcel` per line in the submitted `items` payload; optionally preview `parcel_rate × qty`
    - _Design: Components §7 Frontend_
    - _Requirements: 2.1, 2.2, 2.5, 5.5_

  - [ ] 8.3 Add parcel indicators and totals rows to `Billing/Show.vue`
    - Add a "Parcel" tag on rows where `item.is_parcel`; show line parcel charge (`item.parcel_line_total`); replace the single Grand Total block with three rows — Items Subtotal (`bill.items_subtotal`), Parcel Charges (`bill.parcel_charges_total`), Grand Total (`bill.grand_total`); keep print styles and theme
    - _Design: Components §7 Frontend_
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [ ] 9. Update test factories for parcel fields
  - [ ] 9.1 Update `MenuItemFactory`
    - Add `'parcel_rate' => fake()->randomFloat(2, 0, 9999.99)` default; add `parcelRate(float $r)` state helper and a `0.00`/without-parcel-rate state for backward-compat cases
    - _Design: Testing Strategy → Factory updates_
    - _Requirements: 1.1, 7.1_

  - [ ] 9.2 Update `OrderItemFactory`
    - Add `'is_parcel' => false` and `'parcel_rate' => 0.00` defaults; add a `parcel(float $rate)` state that sets `is_parcel => true` and a matching `parcel_rate`
    - _Design: Testing Strategy → Factory updates_
    - _Requirements: 2.6, 7.2, 7.5_

  - [ ] 9.3 Update `BillFactory`
    - Add `'items_subtotal'` and `'parcel_charges_total'` consistent with `grand_total` (subtotal + parcel = grand_total) so factory-built bills stay internally consistent
    - _Design: Testing Strategy → Factory updates_
    - _Requirements: 4.4, 6.4_

  - [ ]* 9.4 Write reporting integration tests for parcel revenue
    - Generate 1–2 paid bills including parcel charges; assert `SalesReportService` revenue and `ProfitLossService` revenue for the period include the parcel portion (grand totals already summed); 1–3 representative examples
    - _Requirements: 6.2, 6.3_

- [ ] 10. Final checkpoint - full suite and Docker rebuild
  - Run the full backend suite via `docker exec wj-cafe-app ./test.sh` and confirm all property, example, authorization, and integration tests pass
  - Rebuild the Docker image + frontend via `docker compose up -d --build` (no source bind-mount) and manually verify the Menu, Orders/Create, and Billing/Show UI changes
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional (property-based tests and non-critical test coverage) and can be skipped for a faster MVP path; the unmarked tasks form the required implementation path.
- Property-based tests (3.3, 3.4, 4.3–4.6, 5.2–5.5) implement the 10 correctness properties from the design, each ≥100 iterations and tagged `// Feature: parcel-charges, Property N: ...`.
- Each task references specific requirement clauses and design sections for traceability.
- Checkpoints (tasks 6 and 10) ensure incremental validation; the final checkpoint also covers the Docker image rebuild needed to see UI changes since there is no source bind-mount.
- Migrations are additive with defaults, so existing rows behave as dine-in with no charge (backward compatibility).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["2.1", "2.2", "2.3"] },
    { "id": 2, "tasks": ["3.1", "9.1", "9.2", "9.3"] },
    { "id": 3, "tasks": ["3.2", "4.1"] },
    { "id": 4, "tasks": ["3.3", "3.4", "3.5", "4.2"] },
    { "id": 5, "tasks": ["4.3", "4.4", "4.5", "4.6", "5.1"] },
    { "id": 6, "tasks": ["5.2", "5.3", "5.4", "5.5", "7.1", "7.2", "7.3"] },
    { "id": 7, "tasks": ["7.4", "7.5", "8.1", "8.2", "8.3", "9.4"] }
  ]
}
```
