# Requirements Document

## Introduction

WhiteJersey Cafe POS is a complete point-of-sale and business management system for a cafe. The system handles the full business cycle from menu configuration and order-taking through billing, inventory tracking, and financial reporting. The application is containerized for portable deployment and accessible on mobile devices via a Progressive Web App (PWA) with a black and red UI theme.

## Glossary

- **POS_System**: The WhiteJersey Cafe point-of-sale application encompassing all modules (menu, orders, billing, reporting, inventory)
- **Menu_Manager**: The module responsible for creating, updating, and organizing menu items and categories
- **Menu_Item**: A sellable product in the cafe (e.g., Green Tea, French Fries) with a name, price, and category
- **Category**: A top-level grouping of menu items (e.g., Tea, Coffee, Juices, Food)
- **Sub_Variety**: A specific variant within a category (e.g., Lemon Tea and Green Tea are sub-varieties of Tea)
- **Order_Manager**: The module responsible for creating, modifying, and tracking customer orders
- **Order**: A collection of menu items requested by a customer at a specific table
- **Table**: A physical seating location in the cafe, identified by a unique table number
- **Billing_Module**: The module responsible for generating and managing bills from orders
- **Bill**: A financial document summarizing the items ordered, quantities, prices, and total amount for a table
- **Sales_Reporter**: The module responsible for generating sales analytics and reports
- **Inventory_Manager**: The module responsible for tracking raw material purchases and costs
- **Inventory_Item**: A raw material or supply purchased for cafe operations (e.g., milk, sugar, eggs)
- **Purchase_Entry**: A record of an inventory item purchase including item name, quantity, cost, and date
- **Profit_Loss_Reporter**: The module responsible for calculating and displaying profit/loss statements
- **PWA**: Progressive Web App — a web application that can be installed on mobile devices and works offline-capable

## Requirements

### Requirement 1: Menu Item Management

**User Story:** As a cafe manager, I want to add and manage menu items with categories and sub-varieties, so that the digital menu accurately reflects what the cafe offers.

#### Acceptance Criteria

1. WHEN a manager submits a new menu item with a name, price, and category, THE Menu_Manager SHALL create the menu item and persist it in the database
2. WHEN a manager updates an existing menu item's name, price, or category, THE Menu_Manager SHALL save the changes and reflect them in all subsequent order screens
3. WHEN a manager assigns a sub-variety to a menu item, THE Menu_Manager SHALL associate the sub-variety (defined by a name of 1 to 100 characters and an optional price adjustment) with the parent category and display it as a selectable option under that item
4. THE Menu_Manager SHALL enforce that each menu item has a name between 1 and 100 characters (excluding leading/trailing whitespace), a price between 0.01 and 99,999.99, and an assigned category from the available categories list
5. IF a manager submits a menu item that fails validation, THEN THE Menu_Manager SHALL reject the submission, display an error message indicating which fields are invalid, and preserve the entered data in the form
6. WHEN a manager deactivates a menu item, THE Menu_Manager SHALL hide the item from the ordering screen while preserving it in historical order records
7. THE Menu_Manager SHALL support the following categories at minimum: Tea, Coffee, Juices, Food
8. WHEN a category contains sub-varieties, THE Menu_Manager SHALL display sub-varieties grouped under their parent category
9. IF a manager submits a menu item with a name that already exists within the same category, THEN THE Menu_Manager SHALL reject the submission and display an error message indicating the duplicate name conflict

### Requirement 2: Order Creation and Table Association

**User Story:** As a cafe staff member, I want to take orders associated with specific tables, so that I can track which table ordered what.

#### Acceptance Criteria

1. WHEN a staff member selects a table and adds menu items, THE Order_Manager SHALL create a single open order linked to that table with a unique system-generated order identifier, and SHALL prevent creating a second open order for a table that already has one
2. WHEN a staff member adds items to an existing open order for a table, THE Order_Manager SHALL append the items to the current order; IF the same menu item already exists in the order, THEN THE Order_Manager SHALL increase the quantity of the existing line item
3. THE Order_Manager SHALL record the quantity of each menu item in an order as an integer between 1 and 99 inclusive
4. WHEN a staff member submits an order, THE Order_Manager SHALL store the order with a timestamp and status of "active"
5. IF a staff member attempts to create an order without selecting a table, THEN THE Order_Manager SHALL display an error message indicating that table selection is required and SHALL not create the order
6. IF a staff member attempts to add a menu item with a quantity less than 1 or greater than 99, THEN THE Order_Manager SHALL reject the item and display a validation error indicating the allowed quantity range
7. WHEN a staff member views the table overview, THE Order_Manager SHALL display all tables with their current order status (open, vacant)

### Requirement 3: Bill Generation

**User Story:** As a cafe staff member, I want to generate a bill for a table's order, so that I can present the customer with an accurate total.

#### Acceptance Criteria

1. WHEN a staff member requests a bill for a table, THE Billing_Module SHALL calculate the total by summing (item price × quantity) for all items in the table's active order, rounding each line total and the grand total to exactly two decimal places
2. THE Billing_Module SHALL display the bill with a unique bill identifier, individual item names, quantities, unit prices, line totals, and a grand total
3. WHEN a bill is generated, THE Billing_Module SHALL mark the associated order as "billed" and prevent further item additions to that order
4. WHEN a staff member requests a bill for a table whose order is already in "billed" status, THE Billing_Module SHALL re-display the existing bill without creating a duplicate
5. WHEN a bill is settled (paid), THE Billing_Module SHALL mark the order as "completed" and free the table for new orders
6. IF a staff member requests a bill for a table with no active order, THEN THE Billing_Module SHALL display a message indicating no items to bill
7. THE Billing_Module SHALL include the table number, date, and time on each generated bill

### Requirement 4: Sales Reporting

**User Story:** As a cafe owner, I want to view sales reports across different time periods and identify popular dishes, so that I can make informed business decisions.

#### Acceptance Criteria

1. WHEN an owner selects a daily report, THE Sales_Reporter SHALL display total revenue, total orders, and item-wise sales count for the selected date
2. WHEN an owner selects a weekly report, THE Sales_Reporter SHALL display total revenue, total orders, and item-wise sales count aggregated for the selected seven-day period
3. WHEN an owner selects a monthly report, THE Sales_Reporter SHALL display total revenue, total orders, and item-wise sales count aggregated for the selected calendar month
4. WHEN an owner selects a yearly report, THE Sales_Reporter SHALL display total revenue, total orders, and item-wise sales count aggregated for the selected calendar year
5. WHEN a report is generated, THE Sales_Reporter SHALL rank menu items by total quantity sold in descending order and display the top 5 items as "most popular dishes"
6. WHEN a report is generated, THE Sales_Reporter SHALL display both total revenue and total number of orders for the period
7. IF no sales data exists for the selected period, THEN THE Sales_Reporter SHALL display a message indicating no sales were recorded and show zero for revenue and order totals

### Requirement 5: Inventory Purchase Tracking

**User Story:** As a cafe manager, I want to record daily purchases of raw materials with their costs, so that I can track spending on inventory.

#### Acceptance Criteria

1. WHEN a manager records a purchase entry with item name, quantity, cost, and date, THE Inventory_Manager SHALL persist the purchase entry in the database
2. THE Inventory_Manager SHALL allow multiple purchase entries per day for different inventory items
3. WHEN a manager views daily spending, THE Inventory_Manager SHALL display all purchase entries for the selected date showing item name, quantity, cost per entry, and a total cost sum across all entries for that date
4. WHEN a manager views monthly spending, THE Inventory_Manager SHALL sum purchase costs by inventory item for the selected month and display each item with its total cost
5. THE Inventory_Manager SHALL enforce that each purchase entry has a non-empty item name of at most 100 characters, a quantity greater than zero, a cost between 0.01 and 999999.99, and a date that is not in the future
6. IF a manager submits a purchase entry with missing required fields or values that fail validation, THEN THE Inventory_Manager SHALL display a validation error identifying each field that is missing or invalid without persisting the entry

### Requirement 6: Profit and Loss Reporting

**User Story:** As a cafe owner, I want to see profit and loss statements comparing earnings against inventory spending, so that I can understand the financial health of the business.

#### Acceptance Criteria

1. WHEN an owner selects a weekly profit/loss report, THE Profit_Loss_Reporter SHALL calculate profit or loss as (total sales revenue minus total inventory spending) for the selected calendar week (Monday through Sunday)
2. WHEN an owner selects a monthly profit/loss report, THE Profit_Loss_Reporter SHALL calculate profit or loss as (total sales revenue minus total inventory spending) for the selected calendar month
3. WHEN an owner selects a yearly profit/loss report, THE Profit_Loss_Reporter SHALL calculate profit or loss as (total sales revenue minus total inventory spending) for the selected calendar year
4. THE Profit_Loss_Reporter SHALL display total earnings, total spending, and net profit or loss amount for the selected period, with monetary values shown to exactly 2 decimal places and a currency symbol
5. WHEN the net amount is negative, THE Profit_Loss_Reporter SHALL indicate the result as a loss by displaying a "Loss" label and using a visually distinct color differentiating it from the profit indicator
6. WHEN the net amount is positive, THE Profit_Loss_Reporter SHALL indicate the result as a profit by displaying a "Profit" label and using a visually distinct color differentiating it from the loss indicator
7. IF no sales or inventory spending records exist for the selected period, THEN THE Profit_Loss_Reporter SHALL display the report with zero values for earnings, spending, and net amount

### Requirement 7: Containerized Deployment

**User Story:** As a system administrator, I want the application to run in Docker containers, so that it can be deployed on any server environment.

#### Acceptance Criteria

1. THE POS_System SHALL provide a Docker Compose configuration that starts all required services (application server, database) with a single command
2. THE POS_System SHALL persist database data using a mounted volume so that data survives container restarts and removals
3. WHEN the Docker containers are started, THE POS_System SHALL be accessible via the host port specified in the environment variable configuration within 60 seconds of the command completing
4. THE POS_System SHALL include environment variable configuration for database credentials, host port number, and application mode (development or production)
5. WHEN the application container starts, THE POS_System SHALL wait for the database service to be ready before accepting requests
6. IF a required service fails to start, THEN THE POS_System SHALL exit with a non-zero status code and output a log message indicating which service failed

### Requirement 8: Mobile-Accessible Progressive Web App

**User Story:** As a cafe staff member, I want to access the POS system from Android and iOS devices, so that I can take orders from anywhere in the cafe.

#### Acceptance Criteria

1. THE POS_System SHALL serve a Progressive Web App that includes a valid web app manifest and registered service worker, making it installable on Android and iOS devices
2. THE POS_System SHALL render all screens without horizontal scrolling on viewports from 320px to 768px wide, with all interactive elements having a minimum touch target size of 44x44 pixels
3. WHEN a user installs the PWA on a mobile device, THE POS_System SHALL display a home screen icon and launch in standalone mode without browser chrome
4. THE POS_System SHALL load the application shell within 3 seconds on a simulated 4G connection of 9 Mbps download, 1.5 Mbps upload, and 50ms round-trip latency
5. IF network connectivity is lost while the PWA is in use, THEN THE POS_System SHALL display an offline indicator and retain any in-progress order data locally until connectivity is restored
6. WHEN network connectivity is restored after an offline period, THE POS_System SHALL synchronize locally retained order data with the server within 5 seconds

### Requirement 9: Black and Red UI Theme

**User Story:** As the cafe owner, I want the application to use a black and red color theme, so that it matches the WhiteJersey Cafe brand identity.

#### Acceptance Criteria

1. THE POS_System SHALL use black as the primary background color and red as the primary accent color on every screen of the application
2. THE POS_System SHALL maintain a minimum contrast ratio of 4.5:1 between normal text (below 18px regular or 14px bold) and its background, and a minimum contrast ratio of 3:1 between large text (18px or above regular, 14px or above bold) and its background
3. THE POS_System SHALL apply the black and red color palette uniformly to navigation elements, buttons, headers, and interactive components, including their hover, focus, active, and disabled states
4. WHEN displaying status indicators (profit, loss, errors, success), THE POS_System SHALL differentiate each status type using a unique combination of color and a secondary visual cue (such as an icon or label) so that no two status types share the same visual treatment, and each indicator maintains a minimum 3:1 contrast ratio against its adjacent background
