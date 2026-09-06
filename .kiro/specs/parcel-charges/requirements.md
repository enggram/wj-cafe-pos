# Requirements Document

## Introduction

This feature adds Parcel Charges to the WhiteJersey Cafe POS. A parcel charge is an additional per-unit fee applied to menu items that a customer takes away rather than consumes at the table. Each menu item carries an optional parcel rate configured by admins during menu management. When an order is taken, each individual line item can be independently marked as parcel or dine-in, so a single order can mix both. For parcel lines, the system charges the item's parcel rate multiplied by the quantity. The parcel rate is snapshotted onto the order item at order time (like unit price) so later menu rate changes do not alter existing orders.

At billing, parcel charges appear as a separate line: an items subtotal, then a Parcel Charges total, then a grand total equal to the sum of the two. Because parcel charges are part of the bill grand total, they are automatically reflected in Revenue for the existing Sales and Profit & Loss reports, which sum bill grand totals. The feature preserves the current access model: staff and admin take orders and generate and settle bills, while setting parcel rates is admin-only. Existing menu items default to a parcel rate of 0.00 and existing order items default to dine-in, so the change is backward compatible.

## Glossary

- **POS**: The WhiteJersey Cafe point-of-sale application.
- **Admin**: An authenticated user with the admin role, authorized to manage menu data including parcel rates.
- **Staff**: An authenticated user with the staff role, authorized to take orders and handle billing.
- **Menu_Item**: A sellable product with a name, price, active state, category, and a parcel rate.
- **Parcel_Rate**: A per-unit monetary decimal fee configured on a menu item, with exactly two decimal places, between 0.00 and 9999.99 inclusive. A value of 0.00 means no parcel charge applies to that item.
- **Order**: A collection of order items associated with a table.
- **Order_Item**: A single line on an order, consisting of a menu item, an optional sub-variety, a quantity, a snapshotted unit price, a parcel flag, and a snapshotted parcel rate.
- **Parcel_Flag**: A boolean on an order item indicating whether the line is a parcel line (true) or a dine-in line (false).
- **Snapshotted_Parcel_Rate**: The parcel rate value copied from the menu item onto the order item at the time the line is added, preserved against later menu rate changes.
- **Menu_Service**: The backend service responsible for creating and editing menu items, including parcel rates.
- **Order_Service**: The backend service responsible for creating orders and adding items to existing orders.
- **Billing_Service**: The backend service responsible for generating and settling bills.
- **Items_Subtotal**: The sum of unit price multiplied by quantity across all order items on an order.
- **Parcel_Charges_Total**: The sum of snapshotted parcel rate multiplied by quantity across all order items whose parcel flag is true.
- **Grand_Total**: The sum of the Items_Subtotal and the Parcel_Charges_Total for a bill.
- **Bill**: A generated statement for an order recording the items subtotal, parcel charges total, grand total, status, and billed timestamp.
- **Revenue**: The sum of grand totals for completed and paid bills within a reporting period.
- **SalesReport_Service**: The existing service that reports sales revenue for a period.
- **ProfitLoss_Service**: The existing service that calculates Revenue minus costs for a period.

## Requirements

### Requirement 1: Set and Validate Per-Item Parcel Rate

**User Story:** As an admin, I want to set an optional parcel rate on each menu item, so that different items can carry different take-away charges.

#### Acceptance Criteria

1. WHEN an admin creates a menu item with a parcel rate between 0.00 and 9999.99 inclusive, THE Menu_Service SHALL store the parcel rate on the menu item as a decimal value with exactly two decimal places.
2. WHEN an admin edits a menu item and submits a parcel rate between 0.00 and 9999.99 inclusive, THE Menu_Service SHALL update the parcel rate on the menu item.
3. WHERE an admin omits the parcel rate when creating a menu item, THE Menu_Service SHALL store a parcel rate of 0.00 on the menu item.
4. IF an admin submits a parcel rate that is not numeric, THEN THE Menu_Service SHALL reject the submission and return a validation message indicating the parcel rate must be numeric.
5. IF an admin submits a parcel rate less than 0.00 or greater than 9999.99, THEN THE Menu_Service SHALL reject the submission and return a validation message indicating the parcel rate must be between 0.00 and 9999.99.
6. THE Menu_Service SHALL treat a parcel rate of 0.00 as a valid value meaning no parcel charge applies to the menu item.

### Requirement 2: Mark Order Line Items as Parcel

**User Story:** As a staff member or admin, I want to mark each order line as parcel or dine-in independently, so that a single order can contain both take-away and dine-in items.

#### Acceptance Criteria

1. WHEN a staff or admin user creates an order and marks a line item as parcel, THE Order_Service SHALL set the parcel flag of that order item to true.
2. WHEN a staff or admin user creates an order and does not mark a line item as parcel, THE Order_Service SHALL set the parcel flag of that order item to false.
3. WHEN a staff or admin user adds items to an existing order and marks a line item as parcel, THE Order_Service SHALL set the parcel flag of that added order item to true.
4. WHEN a staff or admin user adds items to an existing order and does not mark a line item as parcel, THE Order_Service SHALL set the parcel flag of that added order item to false.
5. THE Order_Service SHALL allow a single order to contain both parcel line items and dine-in line items.
6. WHEN the Order_Service adds an order item, THE Order_Service SHALL copy the current parcel rate of the referenced menu item onto that order item as the snapshotted parcel rate.
7. WHILE a menu item parcel rate is changed after an order item has been added, THE Order_Service SHALL retain the snapshotted parcel rate recorded on the existing order item.
8. WHEN a staff or admin user marks a line item as parcel and the referenced menu item has a parcel rate of 0.00, THE Order_Service SHALL create the order item with a parcel flag of true and a snapshotted parcel rate of 0.00.

### Requirement 3: Calculate Per-Unit Parcel Charges

**User Story:** As a staff member or admin, I want parcel charges calculated per unit, so that the take-away fee scales with quantity.

#### Acceptance Criteria

1. WHERE an order item parcel flag is true, THE Billing_Service SHALL calculate the parcel charge for that order item as the snapshotted parcel rate multiplied by the order item quantity.
2. WHERE an order item parcel flag is false, THE Billing_Service SHALL calculate the parcel charge for that order item as 0.00.
3. THE Billing_Service SHALL calculate the Parcel_Charges_Total as the sum of the parcel charges across all order items on the order.
4. THE Billing_Service SHALL round each order item parcel charge and the Parcel_Charges_Total to two decimal places.

### Requirement 4: Generate Bill With Separate Parcel Charges Line

**User Story:** As a staff member or admin, I want the bill to show parcel charges separately from item costs, so that the take-away fee is transparent and the grand total is correct.

#### Acceptance Criteria

1. WHEN a staff or admin user generates a bill for a table, THE Billing_Service SHALL calculate the Items_Subtotal as the sum of unit price multiplied by quantity across all order items on the order.
2. WHEN a staff or admin user generates a bill for a table, THE Billing_Service SHALL calculate the Parcel_Charges_Total as the sum of snapshotted parcel rate multiplied by quantity across all order items whose parcel flag is true.
3. WHEN a staff or admin user generates a bill for a table, THE Billing_Service SHALL calculate the Grand_Total as the sum of the Items_Subtotal and the Parcel_Charges_Total.
4. WHEN the Billing_Service generates a bill, THE Billing_Service SHALL store the Items_Subtotal, the Parcel_Charges_Total, and the Grand_Total on the bill.
5. THE Billing_Service SHALL round the Items_Subtotal, the Parcel_Charges_Total, and the Grand_Total to two decimal places.
6. IF an order contains no parcel line items, THEN THE Billing_Service SHALL store a Parcel_Charges_Total of 0.00 and a Grand_Total equal to the Items_Subtotal.

### Requirement 5: Display Parcel Indicators and Parcel Charges Line on the Bill

**User Story:** As a staff member or admin, I want the bill view to show which items are parcel and a parcel charges line, so that I can explain the total to the customer.

#### Acceptance Criteria

1. WHEN a staff or admin user views a bill, THE POS SHALL display each order item with an indicator showing whether the line is a parcel line or a dine-in line.
2. WHEN a staff or admin user views a bill, THE POS SHALL display the parcel charge amount for each parcel line item.
3. WHEN a staff or admin user views a bill, THE POS SHALL display the Items_Subtotal, the Parcel_Charges_Total, and the Grand_Total as separate lines.
4. WHEN a staff or admin user views a bill for an order with no parcel line items, THE POS SHALL display a Parcel_Charges_Total of 0.00.
5. THE POS SHALL render the bill view using the existing black-and-red themed UI patterns.

### Requirement 6: Reflect Parcel Charges in Revenue and Reports

**User Story:** As an admin, I want parcel charges included in revenue, so that Sales and P&L reports account for take-away fees collected.

#### Acceptance Criteria

1. THE Billing_Service SHALL include the Parcel_Charges_Total within the Grand_Total stored on each bill.
2. WHEN the SalesReport_Service calculates Revenue for a period, THE SalesReport_Service SHALL sum the grand totals of completed and paid bills, thereby including parcel charges.
3. WHEN the ProfitLoss_Service calculates Revenue for a period, THE ProfitLoss_Service SHALL sum the grand totals of completed and paid bills, thereby including parcel charges.
4. THE Billing_Service SHALL store the Parcel_Charges_Total on each bill so the parcel portion of the Grand_Total is identifiable.

### Requirement 7: Backward Compatibility and Defaults

**User Story:** As a cafe owner, I want existing menu items and orders to keep working after parcel charges are added, so that the upgrade does not disrupt current data.

#### Acceptance Criteria

1. THE Menu_Service SHALL treat existing menu items that have no configured parcel rate as having a parcel rate of 0.00.
2. THE Order_Service SHALL treat existing order items that have no parcel flag as dine-in lines with a parcel flag of false.
3. WHEN a bill is generated for an order whose order items are all dine-in lines, THE Billing_Service SHALL calculate a Grand_Total equal to the Items_Subtotal.
4. THE POS SHALL apply a default parcel rate of 0.00 to any menu item created without an explicit parcel rate.
5. THE POS SHALL apply a default parcel flag of false to any order item created without an explicit parcel selection.

### Requirement 8: Restrict Parcel Rate Editing to Admins

**User Story:** As a cafe owner, I want only admins to set parcel rates while staff can still take orders and bill, so that pricing control stays with admins without blocking daily service.

#### Acceptance Criteria

1. IF a staff user attempts to create or edit a menu item parcel rate, THEN THE POS SHALL deny the request with a 403 authorization error.
2. WHEN an admin creates or edits a menu item parcel rate, THE POS SHALL grant the request.
3. WHEN a staff or admin user marks an order line item as parcel while creating an order or adding items, THE POS SHALL grant the request.
4. WHEN a staff or admin user generates or settles a bill that includes parcel charges, THE POS SHALL grant the request.
5. IF an unauthenticated request attempts to access any menu management endpoint that sets a parcel rate, THEN THE POS SHALL redirect the request to authentication.
