# Implementation Plan: WhiteJersey Cafe POS

## Overview

This plan implements a full-featured cafe POS system using Laravel 11, Vue 3 + Inertia.js, MySQL 8, and Docker. The implementation proceeds from foundational setup through each business domain (menu, orders, billing, reporting, inventory, P&L), then adds PWA support and theming, and finally wires everything together.

## Tasks

- [x] 1. Project foundation and Docker setup
  - [x] 1.1 Set up Docker Compose configuration with PHP 8.3/Nginx app container and MySQL 8 database container
    - Create `docker-compose.yml` with app and db services
    - Create `Dockerfile` for the Laravel app container (PHP 8.3-FPM + Nginx)
    - Configure volume mount for MySQL data persistence
    - Add environment variables for database credentials, host port, and application mode
    - Add health check and `depends_on` with condition for database readiness
    - Create `.env.example` with all required environment variables
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [x] 1.2 Set up Laravel 11 project structure with core dependencies
    - Install/configure Laravel 11 with PHP 8.3
    - Install and configure Inertia.js server-side adapter
    - Install and configure Vue 3 with Vite
    - Install Tailwind CSS and configure the black/red theme palette
    - Install Pest PHP testing framework
    - Set up database connection configuration for MySQL 8
    - _Requirements: 7.4, 9.1_

  - [x] 1.3 Create database migrations for all tables
    - Create migration for `categories` table
    - Create migration for `menu_items` table (with category foreign key, unique name per category)
    - Create migration for `sub_varieties` table (with menu_item foreign key)
    - Create migration for `tables` table (with table_number and status enum)
    - Create migration for `orders` table (with table foreign key and status enum)
    - Create migration for `order_items` table (with order, menu_item, sub_variety foreign keys)
    - Create migration for `bills` table (with order and table foreign keys, status enum)
    - Create migration for `purchase_entries` table
    - _Requirements: 1.1, 2.1, 3.1, 5.1_

  - [x] 1.4 Create Eloquent models with relationships and attribute casting
    - Create `Category` model with `menuItems` relationship
    - Create `MenuItem` model with `category`, `subVarieties`, `orderItems` relationships
    - Create `SubVariety` model with `menuItem` relationship
    - Create `Table` model with `orders`, `bills` relationships and status enum cast
    - Create `Order` model with `table`, `orderItems`, `bill` relationships and status enum cast
    - Create `OrderItem` model with `order`, `menuItem`, `subVariety` relationships
    - Create `Bill` model with `order`, `table` relationships and status enum cast
    - Create `PurchaseEntry` model with date cast
    - _Requirements: 1.1, 2.1, 3.1, 5.1_

  - [x] 1.5 Create service interfaces and DTO classes
    - Create `MenuServiceInterface`, `OrderServiceInterface`, `BillingServiceInterface`
    - Create `SalesReportServiceInterface`, `InventoryServiceInterface`, `ProfitLossServiceInterface`
    - Create `SalesReportDTO`, `ProfitLossDTO`, `DailySpendingDTO`, `MonthlySpendingDTO`
    - Register interface bindings in a service provider
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1, 6.1_

- [x] 2. Menu Management module
  - [x] 2.1 Implement MenuService with create, update, deactivate, and list methods
    - Implement `createItem()` — validate name (1-100 chars trimmed), price (0.01-99999.99), category existence; enforce unique name within category; persist and return
    - Implement `updateItem()` — validate same constraints, check for duplicate name on rename, persist changes
    - Implement `deactivateItem()` — set `is_active` to false without deleting
    - Implement `listByCategory()` — return items grouped by category with sub-varieties, only active items
    - Implement `getActiveItems()` — return flat collection of active items
    - _Requirements: 1.1, 1.2, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_

  - [x] 2.2 Implement sub-variety management in MenuService
    - Add `createSubVariety(int $menuItemId, array $data)` method
    - Validate sub-variety name (1-100 chars), optional price adjustment
    - Associate sub-variety with parent menu item's category
    - Return sub-varieties grouped under parent category in listing
    - _Requirements: 1.3, 1.8_

  - [x] 2.3 Create MenuController with Inertia responses
    - Implement `index()` — list all items grouped by category
    - Implement `store()` — validate and create menu item via service
    - Implement `update()` — validate and update menu item via service
    - Implement `deactivate()` — deactivate menu item via service
    - Implement sub-variety creation endpoint
    - Return validation errors with 422 status preserving form data
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6, 1.9_

  - [x] 2.4 Create MenuManager.vue frontend component
    - Build form for creating/editing menu items (name, price, category select)
    - Display menu items grouped by category with sub-varieties
    - Show validation errors inline on form fields
    - Add deactivate toggle for menu items
    - Add sub-variety management UI under each item
    - Apply black/red theme styling with Tailwind CSS
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6, 1.7, 1.8, 9.1, 9.3_

  - [x]* 2.5 Write property tests for menu item persistence (Property 1)
    - **Property 1: Menu item persistence round-trip**
    - Generate random valid menu item data (name 1-100 chars, price 0.01-99999.99, valid category)
    - Create item, retrieve it, assert all fields match
    - **Validates: Requirements 1.1, 1.2**

  - [x]* 2.6 Write property tests for menu item validation (Property 2)
    - **Property 2: Menu item validation accepts valid and rejects invalid**
    - Generate mix of valid and invalid inputs (names, prices, categories)
    - Assert valid inputs are accepted, invalid inputs rejected with per-field errors
    - **Validates: Requirements 1.4, 1.5**

  - [x]* 2.7 Write property tests for sub-variety association (Property 3)
    - **Property 3: Sub-variety association**
    - Generate random valid sub-variety data
    - Associate with menu item, verify it appears in item's sub-varieties under correct category
    - **Validates: Requirements 1.3**

  - [x]* 2.8 Write property tests for deactivated item hiding (Property 4)
    - **Property 4: Deactivated items hidden from active listing**
    - Create items, deactivate some, verify active listing excludes them, historical references retain them
    - **Validates: Requirements 1.6**

  - [x]* 2.9 Write property tests for duplicate name rejection (Property 5)
    - **Property 5: Duplicate name rejection within category**
    - Create item, attempt to create another with same name in same category, assert rejection
    - **Validates: Requirements 1.9**

- [x] 3. Order Management module
  - [x] 3.1 Implement OrderService with order creation, item addition, and table overview
    - Implement `createOrder()` — check no existing open order for table, create order with status "active" and timestamp, add items
    - Implement `addItems()` — append items to existing order; if same menu item exists, increment quantity
    - Implement `getOpenOrderForTable()` — return active order for a table or null
    - Implement `getTableOverview()` — return all tables with status (vacant/occupied based on active/billed orders)
    - Validate quantity is integer between 1-99, reject if table not selected
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

  - [x] 3.2 Create OrderController with Inertia responses
    - Implement `tableOverview()` — display all tables with status
    - Implement `create()` — order creation form for selected table
    - Implement `store()` — validate and create order via service
    - Implement `addItems()` — validate and add items to existing order
    - Return validation errors (missing table, invalid quantity) with 422 status
    - _Requirements: 2.1, 2.4, 2.5, 2.6, 2.7_

  - [x] 3.3 Create TableOverview.vue and OrderScreen.vue frontend components
    - Build table grid showing all tables with status indicators (vacant/occupied)
    - Build order screen with table selector and menu item list
    - Implement quantity controls (increment/decrement) with validation (1-99)
    - Show active order items for selected table
    - Apply black/red theme with 44x44px minimum touch targets
    - _Requirements: 2.1, 2.7, 8.2, 9.1, 9.3_

  - [x]* 3.4 Write property tests for single open order per table (Property 6)
    - **Property 6: At most one open order per table**
    - Attempt multiple order creations for same table, verify only one active order exists
    - **Validates: Requirements 2.1**

  - [x]* 3.5 Write property tests for item quantity aggregation (Property 7)
    - **Property 7: Order item quantity aggregation**
    - Add same item multiple times with random quantities, verify final quantity equals sum
    - **Validates: Requirements 2.2**

  - [x]* 3.6 Write property tests for quantity validation (Property 8)
    - **Property 8: Order item quantity validation**
    - Generate random integers, assert accepted if 1-99, rejected otherwise
    - **Validates: Requirements 2.3, 2.6**

  - [x]* 3.7 Write property tests for table overview state (Property 9)
    - **Property 9: Table overview reflects actual state**
    - Set up tables with various order states, verify overview matches expected statuses
    - **Validates: Requirements 2.7**

- [x] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Billing module
  - [x] 5.1 Implement BillingService with bill generation, idempotency, and settlement
    - Implement `generateBill()` — calculate total as sum of (unit_price × quantity) rounded to 2 decimal places, create bill record, mark order as "billed"
    - Implement idempotent bill retrieval — if order already billed, return existing bill
    - Implement `settleBill()` — mark order as "completed", mark bill as "paid", set table status to "vacant"
    - Handle edge case: no active order for table returns appropriate message
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_

  - [x] 5.2 Create BillingController with Inertia responses
    - Implement `generate()` — generate or retrieve bill for table
    - Implement `settle()` — settle bill and free table
    - Include bill identifier, table number, date/time, line items, and grand total in response
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 3.6, 3.7_

  - [x] 5.3 Create BillView.vue frontend component
    - Display bill with unique identifier, table number, date and time
    - Show itemized list with item name, quantity, unit price, and line total
    - Display grand total prominently
    - Add "Settle/Pay" button to mark bill as paid
    - Apply black/red theme styling
    - _Requirements: 3.2, 3.7, 9.1, 9.3_

  - [x]* 5.4 Write property tests for bill calculation (Property 10)
    - **Property 10: Bill calculation correctness**
    - Generate random order items (price decimal 2 places, quantity 1-99), verify grand total equals sum of rounded line totals
    - **Validates: Requirements 3.1**

  - [x]* 5.5 Write property tests for bill required fields (Property 11)
    - **Property 11: Bill contains all required fields**
    - Generate bill, verify it contains bill ID, table number, date/time, line items with all fields, and grand total
    - **Validates: Requirements 3.2, 3.7**

  - [x]* 5.6 Write property tests for bill idempotency (Property 12)
    - **Property 12: Bill generation is idempotent**
    - Generate bill, request again, verify same bill ID and total returned
    - **Validates: Requirements 3.4**

  - [x]* 5.7 Write property tests for billed order rejection (Property 13)
    - **Property 13: Billed orders reject further additions**
    - Bill an order, attempt to add items, verify rejection and unchanged totals
    - **Validates: Requirements 3.3**

  - [x]* 5.8 Write property tests for bill settlement (Property 14)
    - **Property 14: Bill settlement frees the table**
    - Settle bill, verify order status is "completed" and table status is "vacant"
    - **Validates: Requirements 3.5**

- [x] 6. Sales Reporting module
  - [x] 6.1 Implement SalesReportService with daily, weekly, monthly, and yearly reports
    - Implement `dailyReport()` — sum bill grand totals and count completed orders for selected date
    - Implement `weeklyReport()` — aggregate for 7-day period
    - Implement `monthlyReport()` — aggregate for calendar month
    - Implement `yearlyReport()` — aggregate for calendar year
    - Implement item-wise sales count and top-5 popularity ranking by quantity sold
    - Handle no-data case returning zero values with appropriate message
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [x] 6.2 Create SalesReportController with Inertia responses
    - Implement endpoints for each period type (daily, weekly, monthly, yearly)
    - Return SalesReportDTO data including revenue, order count, item sales, and top items
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [x] 6.3 Create SalesReport.vue frontend component
    - Build period selector (daily/weekly/monthly/yearly) with date picker
    - Display total revenue and total orders
    - Display item-wise sales table with quantity and revenue columns
    - Highlight top 5 most popular dishes
    - Show "no sales recorded" message when data is empty
    - Apply black/red theme styling
    - _Requirements: 4.1, 4.5, 4.6, 4.7, 9.1, 9.3_

  - [x]* 6.4 Write property tests for sales aggregation (Property 15)
    - **Property 15: Sales report aggregation correctness**
    - Generate random completed orders with bills in a time period, verify revenue equals sum of grand totals and order count matches
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.6**

  - [x]* 6.5 Write property tests for popularity ranking (Property 16)
    - **Property 16: Sales popularity ranking**
    - Generate random order items, verify top-5 list is sorted descending by quantity and no excluded item has higher quantity
    - **Validates: Requirements 4.5**

- [x] 7. Inventory Purchase Tracking module
  - [x] 7.1 Implement InventoryService with purchase recording and spending summaries
    - Implement `recordPurchase()` — validate (name 1-100, qty > 0, cost 0.01-999999.99, date ≤ today), persist entry
    - Implement `dailySpending()` — list all entries for a date with total sum
    - Implement `monthlySpending()` — sum costs per item for a month
    - Allow multiple entries per day for different items
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [x] 7.2 Create InventoryController with Inertia responses
    - Implement `store()` — validate and record purchase entry
    - Implement `dailyView()` — display daily spending
    - Implement `monthlyView()` — display monthly spending by item
    - Return validation errors with 422 status
    - _Requirements: 5.1, 5.3, 5.4, 5.6_

  - [x] 7.3 Create InventoryEntry.vue frontend component
    - Build purchase entry form (item name, quantity, cost, date)
    - Display daily spending list with item details and total
    - Display monthly spending summary by item
    - Show validation errors inline
    - Apply black/red theme styling
    - _Requirements: 5.1, 5.3, 5.4, 5.6, 9.1, 9.3_

  - [x]* 7.4 Write property tests for purchase entry round-trip (Property 17)
    - **Property 17: Purchase entry persistence round-trip**
    - Generate random valid purchase data, create and retrieve, assert all fields match
    - **Validates: Requirements 5.1**

  - [x]* 7.5 Write property tests for spending aggregation (Property 18)
    - **Property 18: Inventory spending aggregation**
    - Generate random entries for a date/month, verify daily total and monthly per-item totals are correct sums
    - **Validates: Requirements 5.3, 5.4**

  - [x]* 7.6 Write property tests for purchase validation (Property 19)
    - **Property 19: Purchase entry validation**
    - Generate mix of valid/invalid entries, assert acceptance/rejection matches validation rules
    - **Validates: Requirements 5.5, 5.6**

- [x] 8. Profit and Loss Reporting module
  - [x] 8.1 Implement ProfitLossService with weekly, monthly, and yearly P&L reports
    - Implement `weeklyReport()` — calculate revenue (sum of bill grand totals) minus spending (sum of purchase costs) for Monday-Sunday
    - Implement `monthlyReport()` — same calculation for calendar month
    - Implement `yearlyReport()` — same calculation for calendar year
    - Determine status: "profit" if net > 0, "loss" if net < 0, "break-even" if net = 0
    - Format all monetary values to 2 decimal places with currency symbol
    - Handle no-data case with zero values
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

  - [x] 8.2 Create ProfitLossController with Inertia responses
    - Implement endpoints for weekly, monthly, and yearly reports
    - Return ProfitLossDTO data with earnings, spending, net amount, and status label
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

  - [x] 8.3 Create ProfitLossReport.vue frontend component
    - Build period selector (weekly/monthly/yearly) with date picker
    - Display total earnings, total spending, and net amount with currency symbol
    - Show "Profit" label with distinct green/accent color when positive
    - Show "Loss" label with distinct red/warning color when negative
    - Include secondary visual cue (icon) alongside color for each status
    - Apply black/red theme with 4.5:1 contrast ratios
    - _Requirements: 6.4, 6.5, 6.6, 6.7, 9.1, 9.2, 9.4_

  - [x]* 8.4 Write property tests for P&L calculation (Property 20)
    - **Property 20: Profit/Loss calculation**
    - Generate random bills and purchase entries for a period, verify net = revenue - spending
    - **Validates: Requirements 6.1, 6.2, 6.3**

  - [x]* 8.5 Write property tests for monetary formatting (Property 21)
    - **Property 21: Profit/Loss monetary formatting**
    - Generate random monetary values, verify formatted to exactly 2 decimal places with currency symbol
    - **Validates: Requirements 6.4**

  - [x]* 8.6 Write property tests for status labeling (Property 22)
    - **Property 22: Profit/Loss status labeling**
    - Generate random net amounts, verify positive → "profit", negative → "loss", zero → "break-even"
    - **Validates: Requirements 6.5, 6.6**

- [x] 9. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. PWA configuration and offline support
  - [x] 10.1 Configure vite-plugin-pwa with manifest and service worker
    - Install and configure `vite-plugin-pwa` in `vite.config.js`
    - Create web app manifest with app name, icons (multiple sizes), black/red theme colors, `standalone` display mode
    - Configure service worker for precaching of app shell assets
    - Configure offline fallback page
    - _Requirements: 8.1, 8.3_

  - [x] 10.2 Implement offline detection and local data retention
    - Create `OfflineIndicator.vue` component showing offline banner when connectivity is lost
    - Implement IndexedDB storage for in-progress order data during offline periods
    - Implement background sync to send queued orders when connectivity restores (within 5 seconds)
    - _Requirements: 8.5, 8.6_

  - [x] 10.3 Ensure responsive design and mobile touch targets
    - Configure Tailwind responsive breakpoints for 320px to 768px viewports
    - Ensure no horizontal scrolling on all screens within the viewport range
    - Set all interactive elements (buttons, inputs, selectable items) to minimum 44x44px touch target
    - Test and adjust all Vue components for mobile layouts
    - _Requirements: 8.2, 8.4_

- [x] 11. Black and red UI theme and accessibility
  - [x] 11.1 Configure Tailwind CSS theme with black/red palette and contrast compliance
    - Define custom color palette in `tailwind.config.js`: black backgrounds, red accents, white/light text
    - Ensure 4.5:1 contrast ratio for normal text against backgrounds
    - Ensure 3:1 contrast ratio for large text against backgrounds
    - Define theme tokens for hover, focus, active, and disabled states for all interactive elements
    - _Requirements: 9.1, 9.2, 9.3_

  - [x] 11.2 Create AppLayout.vue with themed navigation and status indicators
    - Build main application shell layout with black background and red accent navigation
    - Apply themed styles to all navigation links, buttons, and headers
    - Implement status indicator system: unique color + icon/label combinations for profit, loss, errors, success states
    - Ensure each status type is visually distinct with 3:1 contrast against adjacent backgrounds
    - _Requirements: 9.1, 9.3, 9.4_

- [x] 12. Integration and wiring
  - [x] 12.1 Set up Laravel routes and navigation
    - Define web routes for all controllers (menu, orders, billing, reports, inventory, P&L)
    - Configure Inertia shared data (navigation items, flash messages)
    - Wire Vue Router/Inertia links in AppLayout navigation
    - Add database seeder for default categories (Tea, Coffee, Juices, Food) and sample tables
    - _Requirements: 1.7, 2.7_

  - [x] 12.2 Wire Docker entrypoint with migration and startup checks
    - Create Docker entrypoint script that waits for MySQL to be ready
    - Run migrations automatically on container start
    - Exit with non-zero status and log message if any service fails to start
    - Verify application responds within 60 seconds of docker-compose up
    - _Requirements: 7.3, 7.5, 7.6_

  - [x]* 12.3 Write integration tests for Docker deployment
    - Test that `docker compose up` starts all services successfully
    - Test that the app responds on the configured host port within 60 seconds
    - Test that database data persists across container restarts
    - Test container exits with non-zero code when a required service fails
    - _Requirements: 7.1, 7.2, 7.3, 7.5, 7.6_

  - [x]* 12.4 Write unit tests for bill calculation edge cases
    - Test bill with single item at minimum price (0.01) and maximum quantity (99)
    - Test bill with maximum price (99999.99) and quantity 1
    - Test rounding behavior with prices that cause floating point issues
    - _Requirements: 3.1_

- [x] 13. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document (22 properties total)
- Unit tests validate specific examples and edge cases
- The black/red theme is applied incrementally as each frontend component is created
- Docker configuration is established first to enable consistent development environments

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.5"] },
    { "id": 2, "tasks": ["1.4"] },
    { "id": 3, "tasks": ["2.1", "2.2", "3.1", "11.1"] },
    { "id": 4, "tasks": ["2.3", "2.4", "2.5", "2.6", "2.7", "2.8", "2.9", "3.2", "3.3", "3.4", "3.5", "3.6", "3.7"] },
    { "id": 5, "tasks": ["5.1", "7.1"] },
    { "id": 6, "tasks": ["5.2", "5.3", "5.4", "5.5", "5.6", "5.7", "5.8", "7.2", "7.3", "7.4", "7.5", "7.6"] },
    { "id": 7, "tasks": ["6.1", "8.1"] },
    { "id": 8, "tasks": ["6.2", "6.3", "6.4", "6.5", "8.2", "8.3", "8.4", "8.5", "8.6"] },
    { "id": 9, "tasks": ["10.1", "10.2", "10.3", "11.2"] },
    { "id": 10, "tasks": ["12.1", "12.2"] },
    { "id": 11, "tasks": ["12.3", "12.4"] }
  ]
}
```
