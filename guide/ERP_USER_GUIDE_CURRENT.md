# ERP User Guide

**System:** Wholesale & Distribution ERP  
**Guide type:** End-User Guide  
**Last updated:** August 8, 2026  
**Audience:** Administrators, managers, accountants, purchasing staff, sales staff, warehouse staff, finance staff, and reporting users

---

# 1. About This Guide

This guide explains how to use the ERP for normal business operations.

It is intended for users of the application, not developers.

The screens you can see depend on:

- your company/tenant;
- your assigned branch;
- your role;
- your permissions.

If a menu or action mentioned in this guide is not visible, you may not have permission to use it.

---

# 2. Main ERP Areas

The ERP is organized around these business areas:

- Dashboard
- Company and Branch Administration
- Users, Roles, and Permissions
- Products and Master Data
- Customers
- Suppliers
- Purchasing
- Goods Receipt
- Purchase Returns
- Sales
- Sales Allocation and Dispatch
- Sales Invoices
- Customer Receipts
- Sales Returns and Credit Notes
- Customer Refunds
- Inventory
- Accounts Receivable
- Accounts Payable
- General Ledger
- Treasury and Banking
- Bank Reconciliation
- Financial Reports
- Management Reports
- Export Requests
- Operations and System Administration

Your sidebar may contain only the modules your role is allowed to access.

---

# 3. Understanding Companies, Tenants, and Branches

## 3.1 Tenant / Company

A tenant is a separate company or business operating inside the ERP.

Example:

```text
Tenant 1 = Company A
Tenant 2 = Company B
```

If your organization currently uses only one company, you normally work only with Tenant 1.

Data belonging to one tenant is kept separate from other tenants.

## 3.2 Branch

A tenant can have multiple branches.

Example:

```text
Company A
├── Head Office
├── Dhaka Branch
└── Chattogram Branch
```

Your user account may have:

- access to one branch;
- access to several branches;
- company-wide access.

You should only see records belonging to branches you are authorized to access.

---

# 4. Logging In

Open the ERP login page and enter your assigned credentials.

After login, you are taken to the ERP dashboard.

If you cannot log in:

1. confirm your email/username;
2. confirm your password;
3. make sure your user account is active;
4. contact an administrator if the problem continues.

Never share your ERP password with another person.

---

# 5. Dashboard

The dashboard provides a summary of the business areas available to you.

Depending on your permissions, it may display information such as:

- sales activity;
- purchases;
- receivables;
- payables;
- inventory;
- operational alerts;
- recent transactions;
- financial summaries.

Use the sidebar to move between modules.

---

# 6. Recommended Initial Setup Order

Before normal transactions begin, an administrator should complete the main setup.

Recommended order:

1. Company Settings
2. Branches
3. Warehouses
4. Users
5. Roles and Permissions
6. Currencies
7. Exchange Rates
8. Taxes
9. Accounting/Fiscal Periods
10. Document Numbering
11. Chart of Accounts
12. Product Categories
13. Brands
14. Units
15. Products
16. Suppliers
17. Customers

Do not begin major transactional work until the required master data is complete.

---

# 7. Company Settings

Company Settings contain the main business information used by the ERP.

Depending on configuration, this may include:

- company name;
- business information;
- contact information;
- address;
- accounting settings;
- default currency;
- operational settings.

Only authorized administrators should change company-level settings.

---

# 8. Branches

Use the Branches area to manage business locations or operating units.

Typical branch information includes:

- branch name;
- code;
- address;
- status.

A branch can be active or inactive.

Do not deactivate a branch without confirming that no current business process depends on it.

---

# 9. Warehouses

Warehouses represent stock-holding locations.

Typical examples:

- Main Warehouse
- Finished Goods Warehouse
- Dhaka Warehouse
- Chattogram Warehouse

Warehouses are important for:

- Goods Receipt;
- stock balances;
- sales allocation;
- dispatch;
- purchase returns;
- sales returns;
- inventory reporting.

Choose the correct warehouse whenever a transaction asks for one.

---

# 10. Users

Administrators can manage ERP users.

A user normally belongs to:

- one tenant/company;
- one or more authorized branches;
- one or more roles.

User status may control whether the person can access the ERP.

When an employee leaves the organization, deactivate the account rather than sharing or reusing it for someone else.

---

# 11. Roles and Permissions

Permissions control which actions a user can perform.

Examples include permission to:

- view;
- create;
- edit;
- approve;
- post;
- reverse;
- delete where permitted;
- export;
- manage settings.

A user may be able to view a module but not approve or post transactions.

If an action button is missing, first check whether your role allows that action.

---

# 12. Currencies and Exchange Rates

The ERP supports currency-related business transactions.

Before using a foreign currency:

1. make sure the currency exists;
2. make sure the correct exchange rate is available;
3. verify the transaction date;
4. confirm the calculated base-currency value.

Accounting reports may use base-currency values.

---

# 13. Taxes

Tax configuration should be completed before transactions requiring tax are entered.

When creating products, purchase documents, or sales documents, use the correct tax treatment according to your organization’s policy.

Users should not manually alter calculated tax totals unless the workflow specifically supports an adjustment.

---

# 14. Accounting / Fiscal Periods

Accounting periods control which dates are open for financial posting.

An accountant or administrator may open or close periods.

If you cannot post a transaction for a specific date, one possible reason is that the accounting period is closed.

Do not reopen a closed accounting period without authorization.

---

# 15. Document Numbering

The ERP automatically numbers transactional documents using configured document sequences.

Examples can include:

- Purchase Orders
- Goods Receipts
- Supplier Invoices
- Sales Orders
- Dispatches
- Sales Invoices
- Receipts
- Payments
- Returns
- Credit Notes
- Refunds
- Treasury Transfers

Users should not invent document numbers manually unless the specific screen explicitly allows an external/reference number.

---

# 16. Product Categories

Product Categories help organize products.

Examples:

```text
Food
Beverages
Electronics
Garments
Raw Materials
Accessories
```

Create categories before creating large numbers of products.

Use consistent naming.

---

# 17. Brands

Brands identify product brands/manufacturers.

Examples:

```text
Brand A
Brand B
Generic
```

Use existing brands instead of creating duplicate names.

---

# 18. Units

Units define how products are measured.

Examples:

- Piece
- Box
- Carton
- Kg
- Liter
- Meter

Choose the correct unit when creating a product.

Incorrect units can affect purchasing, inventory, and sales quantities.

---

# 19. Products

The Product module stores items bought, stocked, or sold by the company.

Typical product information includes:

- name;
- code/SKU;
- category;
- brand;
- unit;
- status;
- inventory-related settings;
- pricing-related information.

Before creating a new product, search first to avoid duplicates.

---

# 20. Suppliers

Suppliers are organizations or people from whom the company purchases goods or services.

Supplier information may include:

- name;
- code;
- contact information;
- address;
- status;
- accounting/payment information.

Supplier records are used throughout Purchasing and Accounts Payable.

---

# 21. Customers

Customers are organizations or people to whom the company sells goods.

Customer information may include:

- name;
- code;
- contact details;
- billing/shipping information;
- status;
- credit/accounting information.

Customer records are used throughout Sales and Accounts Receivable.

---

# 22. Purchasing Workflow

The standard purchasing flow is:

```text
Purchase Order
      ↓
Goods Receipt
      ↓
Supplier Invoice
      ↓
Supplier Payment
```

Depending on the business situation, Purchase Returns or other adjustments may also be used.

---

# 23. Purchase Orders

A Purchase Order records what the company intends to buy from a supplier.

Typical process:

1. open Purchase Orders;
2. create a new Purchase Order;
3. select the supplier;
4. select the branch;
5. enter the required date/details;
6. add products;
7. enter quantities;
8. enter prices and tax where applicable;
9. review totals;
10. save;
11. follow the available approval/confirmation workflow.

Always verify:

- supplier;
- branch;
- products;
- quantities;
- prices;
- tax;
- delivery requirements.

A Purchase Order is not the same as receiving stock.

---

# 24. Goods Receipt

Goods Receipt records the physical receipt of purchased goods.

Typical flow:

1. open Goods Receipts;
2. create or receive against the related Purchase Order;
3. select the correct warehouse;
4. enter received quantities;
5. enter accepted quantities where applicable;
6. review damaged/rejected differences if supported;
7. confirm/post the Goods Receipt.

After posting, stock-related records may be affected.

Do not enter accepted quantities higher than what was actually accepted.

---

# 25. Purchase Returns

Use Purchase Returns when accepted goods need to be returned to a supplier.

A Purchase Return should normally be based on available returnable quantities.

The ERP protects the relationship between:

```text
Accepted Quantity
Returned Quantity
Reserved-for-Return Quantity
```

Users should not attempt to return more than the available accepted quantity.

Typical process:

1. locate the related Goods Receipt;
2. create a Purchase Return;
3. choose the lines/items;
4. enter return quantities;
5. review;
6. complete the required approval/posting steps.

---

# 26. Supplier Invoices

Supplier Invoices record amounts owed to suppliers.

Typical process:

1. open Supplier Invoices;
2. create an invoice;
3. select supplier;
4. enter supplier invoice/reference number;
5. select relevant Purchase Order/Goods Receipt if required;
6. add invoice lines;
7. verify quantities, rates, tax, and totals;
8. save;
9. approve/post according to permissions.

After posting, the invoice may affect:

- Accounts Payable;
- supplier open items;
- General Ledger.

A posted invoice should not be casually edited.

Use the proper reversal/adjustment process where required.

---

# 27. Supplier Payments

Supplier Payments reduce amounts owed to suppliers.

Typical process:

1. open Supplier Payments;
2. choose the supplier;
3. choose payment account/bank/cash source;
4. enter payment date;
5. enter payment amount;
6. allocate the payment against payable items;
7. verify allocations;
8. post/confirm.

Do not allocate more than the available payable amount.

---

# 28. Accounts Payable

Accounts Payable represents money the company owes suppliers.

Typical AP information includes:

- supplier balances;
- unpaid invoices;
- credits;
- outstanding amounts;
- payment allocations;
- aging.

Use AP reports to monitor upcoming and overdue obligations.

---

# 29. Sales Workflow

The standard sales flow is:

```text
Sales Order
      ↓
Stock Allocation
      ↓
Dispatch
      ↓
Sales Invoice
      ↓
Customer Receipt
```

Returns, Credit Notes, Credit Applications, and Refunds may follow when needed.

---

# 30. Sales Orders

A Sales Order records what a customer wants to buy.

Typical process:

1. open Sales Orders;
2. create a Sales Order;
3. select customer;
4. select branch;
5. enter order information;
6. add products;
7. enter quantities;
8. enter prices/discounts/taxes as permitted;
9. review totals;
10. save;
11. complete approval/confirmation as required.

A Sales Order itself does not necessarily mean the goods have left the warehouse.

---

# 31. Stock Allocation

Stock Allocation reserves available stock for a Sales Order.

Typical process:

1. open the Sales Order;
2. review required products;
3. allocate from the correct warehouse;
4. confirm available quantities;
5. save/confirm allocation.

Allocation may be limited by:

- stock availability;
- warehouse;
- branch;
- existing reservations;
- order status.

Do not promise stock to a customer based only on a manual stock estimate. Use ERP availability.

---

# 32. Dispatch

Dispatch records goods leaving the warehouse for the customer.

Typical process:

1. open eligible allocated Sales Orders;
2. create a Dispatch;
3. choose the warehouse;
4. verify dispatchable quantities;
5. enter shipping/delivery information;
6. confirm/post dispatch.

Dispatching normally affects inventory.

Make sure physical goods match the dispatch record.

---

# 33. Sales Invoices

Sales Invoices record the amount the customer owes.

Typical process:

1. open Sales Invoices;
2. create or generate the invoice from the related sales workflow;
3. verify customer;
4. verify products and quantities;
5. verify pricing, discount, tax, and total;
6. review invoice date;
7. save;
8. approve/post.

After posting, a Sales Invoice may affect:

- Accounts Receivable;
- customer open items;
- General Ledger.

---

# 34. Customer Receipts

Customer Receipts record money received from customers.

Typical process:

1. open Customer Receipts;
2. select customer;
3. select the receiving cash/bank account;
4. enter receipt date;
5. enter amount;
6. allocate against open receivables;
7. verify remaining unapplied amount if supported;
8. post/confirm.

Do not allocate a receipt to another customer's invoice.

The ERP enforces customer ownership for settlement allocations.

---

# 35. Accounts Receivable

Accounts Receivable represents money customers owe the company.

AR records may include:

- Sales Invoices;
- Customer Receipts;
- Credit Notes;
- Credit Applications;
- Refunds;
- AR Adjustments.

Common reports include:

- AR Aging;
- Customer Aging;
- customer balances;
- outstanding receivables.

---

# 36. Sales Returns

Use a Sales Return when goods previously sold are returned by the customer.

Typical process:

1. locate the original transaction;
2. create a Sales Return;
3. select returned items;
4. enter quantities;
5. select receiving warehouse where applicable;
6. review;
7. complete approval/posting.

The return may affect inventory and accounting depending on the workflow.

---

# 37. Customer Credit Notes

A Customer Credit Note reduces the amount owed by a customer.

It may be connected to:

- returned goods;
- pricing corrections;
- approved commercial adjustments.

After posting, the credit can normally be applied against receivables or handled through an authorized refund workflow.

---

# 38. Customer Credit Applications

A Credit Application applies available customer credit against an outstanding receivable.

Typical process:

1. select customer;
2. select available credit;
3. select receivable/open invoice;
4. enter the amount to apply;
5. verify both items belong to the same customer and branch;
6. post/confirm.

The ERP protects settlement ownership.

Do not attempt to apply one customer's credit to another customer's invoice.

---

# 39. Customer Refunds

Use a Customer Refund when money must be returned to a customer.

Typical process:

1. open Customer Refunds;
2. select customer;
3. select eligible customer credit/open item;
4. choose refund account/payment source;
5. enter refund amount;
6. review allocation;
7. approve/post.

Refunds are financial transactions and should be completed only by authorized users.

---

# 40. AR Adjustments

Accounts Receivable adjustments should be used only for legitimate approved corrections.

Examples may include:

- approved balance corrections;
- write-offs;
- authorized accounting adjustments.

Do not use AR Adjustments simply to force a customer's balance to match an expected number.

Always keep supporting documentation.

---

# 41. Inventory

The Inventory area provides stock information by warehouse/product.

Common information may include:

- quantity on hand;
- inventory value;
- average cost;
- stock movement history.

Inventory can be affected by:

- Goods Receipt;
- Dispatch;
- Purchase Return;
- Sales Return;
- other authorized stock workflows.

---

# 42. Inventory Balance

Inventory balance represents the current stock position.

Important rules:

- negative stock should not occur in normal workflows;
- stock value should not be negative;
- stock quantity and stock value should remain consistent.

If you believe inventory is wrong:

1. do not manually change posted records;
2. review the stock ledger;
3. identify the source transaction;
4. use an authorized reconciliation/correction process.

---

# 43. Stock Ledger

The Stock Ledger shows stock movements.

Use it when investigating:

- unexpected quantities;
- incorrect warehouse balances;
- return discrepancies;
- dispatch discrepancies;
- inventory valuation questions.

A stock balance should be traceable to ledger movements.

---

# 44. General Ledger

The General Ledger is the accounting foundation of the ERP.

Posted journals should always balance.

Users with GL access may review:

- journal entries;
- debit/credit lines;
- account balances;
- source references;
- branch/tenant ownership.

Do not directly edit posted journal lines.

If a posted accounting transaction is wrong, use the correct reversal or adjustment workflow.

---

# 45. Chart of Accounts

The Chart of Accounts defines the financial accounts used by the ERP.

Examples include:

- Cash
- Bank
- Accounts Receivable
- Inventory
- Accounts Payable
- Sales Revenue
- Cost of Goods Sold
- Expenses
- Equity

Only authorized accounting administrators should change account configuration.

---

# 46. Treasury

Treasury features help manage transfers between authorized cash/bank accounts or branches.

Typical Treasury Transfer process:

1. create transfer;
2. select source branch/account;
3. select destination branch/account;
4. enter amount;
5. enter transfer date/reference;
6. review;
7. approve/post.

Verify source and destination carefully before posting.

---

# 47. Banking

Banking features may include:

- bank accounts;
- bank statements;
- bank statement lines;
- reconciliation.

Keep ERP bank accounts aligned with actual company bank accounts.

---

# 48. Bank Statements

A Bank Statement represents transactions received from a bank.

Typical activities include:

- importing/entering statement data;
- reviewing statement lines;
- matching transactions;
- identifying unmatched lines.

Do not mark a bank item reconciled unless the corresponding ERP transaction is correct.

---

# 49. Bank Reconciliation

Bank Reconciliation compares ERP transactions with the bank statement.

Typical process:

1. select bank account;
2. select statement/reconciliation period;
3. review statement lines;
4. match eligible ERP transactions;
5. investigate differences;
6. complete the reconciliation when balanced.

Unexplained differences should be investigated before completion.

---

# 50. Financial Reports

The ERP includes financial reporting areas such as:

- Trial Balance
- Profit & Loss
- Balance Sheet
- Cash Flow
- Accounts Receivable reports
- Accounts Payable reports

Reports are based on posted ERP data.

If a report appears incorrect, first verify:

- report date;
- branch filter;
- customer/supplier filter;
- currency;
- posting status;
- accounting period;
- source transactions.

---

# 51. Trial Balance

The Trial Balance summarizes debit and credit balances by account.

Use it to verify overall accounting balance and review account activity.

A balanced Trial Balance does not automatically mean every individual transaction is correct, but an unbalanced General Ledger requires investigation.

---

# 52. Profit & Loss

The Profit & Loss report summarizes revenue and expenses over a period.

Always confirm:

- start date;
- end date;
- branch/company scope.

Use posted accounting data for official reporting.

---

# 53. Balance Sheet

The Balance Sheet shows financial position at a selected date.

It generally includes:

- assets;
- liabilities;
- equity.

Review the selected date carefully.

---

# 54. Cash Flow

The Cash Flow report summarizes cash movement according to the ERP's reporting configuration.

Verify the reporting period before using it for management decisions.

---

# 55. AR Aging

AR Aging groups customer receivables by age.

It helps identify:

- current receivables;
- overdue receivables;
- long-outstanding customer balances.

Use Customer Aging when you need detailed aging for one customer.

---

# 56. AP Aging

AP Aging groups supplier payables by age.

Use it for:

- payment planning;
- overdue supplier analysis;
- cash-flow planning.

---

# 57. Search, Filters, and Pagination

Many ERP lists support:

- search;
- filters;
- date ranges;
- status;
- branch;
- customer;
- supplier;
- pagination.

Before assuming a record is missing:

1. clear filters;
2. verify the date range;
3. verify branch;
4. verify status;
5. search by document number/reference.

---

# 58. Statuses

Different modules use status values such as:

```text
draft
pending
approved
confirmed
posted
completed
reversed
cancelled
```

Exact statuses vary by module.

General rule:

- **Draft** — still editable;
- **Approved/Confirmed** — authorized for the next step;
- **Posted** — financial/inventory impact has been committed;
- **Reversed** — an explicit reversal has been recorded;
- **Cancelled** — workflow was cancelled according to allowed rules.

Do not assume every module allows the same status transitions.

---

# 59. Approval and Posting

Approval and posting are different concepts.

A document may require:

```text
Create
→ Review
→ Approve
→ Post
```

Posting is usually the step that creates final accounting or inventory impact.

Only users with appropriate permissions should post.

Before posting, verify the document carefully.

---

# 60. Reversals

A posted financial document should not simply be deleted or rewritten.

When correction is required, use the available:

- reversal;
- return;
- adjustment;
- credit;
- refund;
- reconciliation workflow.

This protects audit history.

---

# 61. Exports

Some reports and lists support export.

Available export formats may include:

- CSV;
- XLSX.

Large exports may be processed in the background.

If an export is queued:

1. submit the export request;
2. allow the ERP to process it;
3. check the Export Requests area;
4. download the completed file when available.

Do not repeatedly submit the same large export unless necessary.

---

# 62. Export Requests

The Export Requests area may show:

- requested export;
- status;
- requested time;
- completion time;
- downloadable result;
- failure information where applicable.

Typical states can include queued, processing, completed, or failed.

If an export fails repeatedly, report it to an administrator rather than creating many duplicate requests.

---

# 63. Notifications

Depending on configuration, the ERP may generate notifications for important operational events.

Examples may include:

- approvals;
- failed jobs;
- export completion;
- scheduled operational alerts.

Use notifications together with the relevant module, not as a replacement for reviewing the transaction itself.

---

# 64. Audit Trail

Important ERP actions are intended to remain traceable.

Users should avoid:

- sharing accounts;
- using another person's login;
- changing posted transactions outside approved workflows;
- creating false backdated records.

Use your own account so the audit history correctly identifies who performed an action.

---

# 65. Common User Errors

## Wrong branch

Symptom:

- transaction is not visible;
- warehouse/customer/supplier options appear different.

Action:

- confirm you are working in the correct branch;
- contact an administrator if branch access is wrong.

## Missing permission

Symptom:

- Create/Edit/Approve/Post button is missing.

Action:

- ask your administrator to review your role.

## Closed accounting period

Symptom:

- transaction cannot be posted for the selected date.

Action:

- verify the accounting period;
- contact accounting administration.

## Insufficient stock

Symptom:

- Sales Allocation or Dispatch cannot continue.

Action:

- review inventory by warehouse;
- check reservations/allocations;
- do not override stock manually.

## Missing document numbering

Symptom:

- a transaction cannot generate its number.

Action:

- contact the ERP administrator.

## Incorrect customer/supplier balance

Action:

- review open items;
- review allocations;
- review receipts/payments;
- review credit notes/adjustments;
- do not manually overwrite balances.

---

# 66. Daily Purchasing Checklist

Purchasing staff should regularly review:

- Purchase Orders awaiting action;
- expected receipts;
- received quantities;
- Purchase Returns;
- Supplier Invoices;
- outstanding supplier issues.

Always compare ERP data with actual supplier documents.

---

# 67. Daily Sales Checklist

Sales staff should regularly review:

- open Sales Orders;
- stock availability;
- allocations;
- pending dispatches;
- invoices;
- customer credit/balance where relevant.

Do not confirm customer delivery based on stock that has not been allocated.

---

# 68. Daily Warehouse Checklist

Warehouse staff should review:

- expected Goods Receipts;
- goods physically received;
- accepted quantities;
- Sales Allocations;
- Dispatches;
- Purchase Returns;
- Sales Returns;
- unusual inventory balances.

ERP quantities should match physical movement.

---

# 69. Daily Finance Checklist

Finance/accounting users should review:

- Customer Receipts;
- Supplier Payments;
- AR balances;
- AP balances;
- journals;
- bank activity;
- unreconciled statement lines;
- unusual adjustments/refunds.

Investigate discrepancies before period close.

---

# 70. Month-End Checklist

A typical month-end review may include:

1. complete pending Goods Receipts;
2. complete pending Dispatches;
3. post valid Supplier Invoices;
4. post valid Sales Invoices;
5. post receipts/payments;
6. complete returns/credits/refunds;
7. review AR Aging;
8. review AP Aging;
9. reconcile bank accounts;
10. review stock balances;
11. review Trial Balance;
12. review Profit & Loss;
13. review Balance Sheet;
14. review Cash Flow;
15. investigate unresolved differences;
16. close the accounting period when authorized.

Your organization's accounting policy may require additional steps.

---

# 71. Data Entry Best Practices

Always:

- search before creating new master records;
- use correct dates;
- use correct branch;
- use correct warehouse;
- verify quantities;
- verify currency;
- verify customer/supplier;
- verify document references;
- review totals before posting.

Avoid abbreviations that other users will not understand.

---

# 72. Security Best Practices for Users

- Never share passwords.
- Lock your computer when leaving your desk.
- Do not save ERP passwords in shared browsers.
- Do not approve/post transactions using another user's account.
- Report suspicious access immediately.
- Verify customer/supplier banking details through approved company procedures.
- Do not download sensitive reports to public/shared computers.

---

# 73. When to Contact an Administrator

Contact your ERP administrator when:

- you cannot log in;
- your branch access is wrong;
- a required menu is missing;
- you need a new role/permission;
- document numbering is missing;
- a master-data record must be deactivated;
- the queue/export system repeatedly fails;
- an accounting period needs controlled reopening;
- system configuration must change.

---

# 74. When to Contact Finance/Accounting

Contact Finance/Accounting when:

- a posted invoice is wrong;
- a posted receipt/payment is wrong;
- a customer/supplier balance appears incorrect;
- a journal appears wrong;
- a refund or adjustment requires approval;
- the accounting period is closed;
- a bank reconciliation difference cannot be explained.

Do not correct financial postings through unauthorized shortcuts.

---

# 75. When to Contact Warehouse/Inventory Control

Contact Warehouse/Inventory Control when:

- physical stock differs from ERP stock;
- a return quantity is unavailable;
- goods were dispatched incorrectly;
- Goods Receipt quantities are incorrect;
- inventory valuation looks unusual;
- stock appears in the wrong warehouse.

---

# 76. Important Rules to Remember

## Rule 1 — Use the correct company

Never try to use another company's records.

## Rule 2 — Use the correct branch

Branch access controls which operational records you can use.

## Rule 3 — Verify before posting

Posted transactions can affect accounting and/or inventory.

## Rule 4 — Never directly manipulate balances

Use the correct business workflow.

## Rule 5 — Use reversals and adjustments

Do not erase audit history.

## Rule 6 — Check source documents

ERP entries should be supported by real business documents.

## Rule 7 — Ask when unsure

For financial or stock-impacting actions, confirm the correct process before posting.

---

# 77. Quick Workflow Reference

## Purchase

```text
Supplier
   ↓
Purchase Order
   ↓
Goods Receipt
   ↓
Supplier Invoice
   ↓
Supplier Payment
```

## Sale

```text
Customer
   ↓
Sales Order
   ↓
Allocation
   ↓
Dispatch
   ↓
Sales Invoice
   ↓
Customer Receipt
```

## Customer Return / Credit

```text
Sales Transaction
   ↓
Sales Return / Credit Note
   ↓
Credit Application
      or
Customer Refund
```

## Supplier Return

```text
Goods Receipt
   ↓
Purchase Return
```

## Banking

```text
ERP Bank Transactions
        +
Bank Statement
        ↓
Bank Reconciliation
```

---

# 78. Glossary

**AP**  
Accounts Payable — money the company owes suppliers.

**AR**  
Accounts Receivable — money customers owe the company.

**Allocation**  
Reserving or applying an available quantity/amount to another transaction.

**Branch**  
A business operating location within a tenant/company.

**Credit Note**  
A document reducing an amount owed.

**Dispatch**  
A record of goods leaving the warehouse for a customer.

**Goods Receipt**  
A record of purchased goods physically received.

**General Ledger (GL)**  
The main accounting ledger.

**Open Item**  
An invoice/credit/payment-related balance that has not been fully settled.

**Posting**  
Finalizing a transaction so its accounting/inventory effect is recorded.

**Purchase Return**  
Goods returned to a supplier.

**Reconciliation**  
Comparing two sets of records and resolving differences.

**Reversal**  
A controlled transaction that reverses a previously posted transaction.

**Sales Return**  
Goods returned by a customer.

**Tenant**  
A separate company/business inside the ERP.

**Warehouse**  
A physical or logical stock-holding location.

---

# 79. Final User Checklist Before Posting Any Transaction

Before clicking **Post**, **Confirm**, **Approve**, or another final action, check:

- Am I in the correct company?
- Am I using the correct branch?
- Is the customer/supplier correct?
- Is the date correct?
- Is the warehouse correct?
- Are the products correct?
- Are the quantities correct?
- Are prices and discounts correct?
- Is the currency correct?
- Is tax correct?
- Is the total correct?
- Is the supporting document/reference correct?
- Am I authorized to complete this action?

If any answer is uncertain, review the transaction before continuing.

---

# 80. Guide Maintenance

This guide should be updated whenever the ERP gains or materially changes:

- a user-facing module;
- an approval workflow;
- a posting workflow;
- reporting behavior;
- permissions;
- operational procedures.

The current ERP application remains the final source of truth for actual available menus, fields, statuses, and permissions.

---

**End of ERP User Guide**
