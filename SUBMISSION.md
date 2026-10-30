# Mini ERP Stock & Sales - implementation notes

Repository: https://github.com/alfateh-hasani/Mini-ERP

This is a CodeIgniter 3 exercise implementing a small multi-warehouse catalogue, inventory and sales application. Setup is in [README.md](README.md); the importable SQL is [database/schema.sql](database/schema.sql). The supplied employer brief is the scope reference. The larger internal implementation plan records project history and is not a claim that every proposed tool/check was implemented.

**Handoff status:** functionality is implemented, but the pre-submission findings in [SUBMISSION_REVIEW.md](SUBMISSION_REVIEW.md) remain open. This document describes the current implementation rather than asserting production readiness.

## Implemented

- Product creation/editing/deactivation, category filtering, code/name search and paginated AJAX tables.
- Multiple warehouses with a unique stock row per product/warehouse, admin stock adjustments and quantity-range/product/warehouse inventory filters. Admins can inspect inactive warehouse stock without adjusting it.
- Invoice customer selection, warehouse-scoped product search, add/remove lines, stock availability feedback, percentage discounts and live totals. Prices and totals are recalculated on the server.
- Atomic invoice/line/stock writes, ordered inventory locks, unique daily invoice numbers, invoice listing/details/printing, cancellation with stock restoration and creator attribution.
- Low-stock reporting using `quantity <= alert_quantity`, search and CSV export. Inventory has a shortage column; the low-stock report's warehouse selector/shortage presentation is still incomplete.
- Authentication with password hashes, login throttling, CSRF protection, role checks and warehouse-scoped access. User permission changes are not yet refreshed in existing sessions.
- Arabic/English RTL/LTR screens, fixed SAR currency, customers, categories, warehouse/user administration, profile settings, migrations and demo data.

## Main decisions

| Decision | Reason / tradeoff |
|---|---|
| Shared CI3 controller set with warehouse scoping | Avoid duplicated admin and warehouse implementations. Admins may choose a warehouse; ordinary users are bound to their assignment. |
| Services and repositories for core workflows | Keep invoice calculations and transaction behavior testable. CRUD/pagination extraction is incomplete; the remaining mixed responsibilities are disclosed in the review. |
| InnoDB transactions and ascending product locks | Check stock and decrement it atomically. Concurrent cooperating requests cannot both consume the same units. |
| Decimal storage; integer-halalah invoice calculations | Avoid floating-point accumulation for invoice discounts and totals, rounding positive halves up. Numeric validation/storage bounds still need strengthening. |
| Product snapshots on invoice lines | Preserve issued product descriptions, codes, unit prices and calculated amounts after catalogue edits. Customer billing identity is currently referenced, not snapshotted. |
| Daily query-derived invoice sequence under an advisory lock | Include cancelled invoices and avoid `COUNT(*) + 1`. The lock spans transaction commit/rollback. This serializes invoice creation on one MySQL server; all writers must follow the same protocol. Do not delete issued/cancelled invoices. |
| Fixed two-language fields and SAR only | Fit the exercise without an exchange-rate subsystem or general translation schema. |
| Plain JavaScript and bounded AJAX endpoints | No front-end build step. Tabler styling is loaded from a pinned CDN URL, so Internet access is required for styling. |

## Known gaps and deliberately omitted features

The immediate fixes are HTTP protection of secret/source files, refreshing session authorization, checked stock-adjustment transactions, strict invoice numeric/shape validation, a missing-inventory-row enrolment workflow, consistent timestamps and schema collation. See the review for exact evidence and verification steps.

The project does not implement a complete stock ledger, fractional quantities, customer billing snapshots, invoice request idempotency, accounting/tax integration, purchasing, transfers or distributed numbering. Those are beyond the employer's required small sales exercise. The optional low-stock report needs its visible warehouse filter/shortfall and consistent CSV filtering finished.

No claim is made of complete SOLID/PSR-12 conformance, production hardening or comprehensive test coverage. `composer lint` checks syntax and line endings. Existing tests and manual checks are documented in [SUBMISSION_REVIEW.md](SUBMISSION_REVIEW.md).

## Reviewer walkthrough

1. Follow the README using PHP 8.4 and MySQL 8; install dependencies, configure a local `.env`, migrate/import and seed a disposable demo database.
2. Sign in as `test@smartlife.com` / `87654321`. Browse Products; search by name/code, choose a category and change pages.
3. Browse Inventory; select a warehouse/product, apply a quantity range and inspect alert-level shortages. Inactive warehouses are read-only.
4. Create an invoice: explicitly select a customer, select a warehouse, search for a stocked product and change quantity/discount. Confirm live totals, save, inspect the details and cancel an issued invoice in an active warehouse to verify stock restoration.
5. Sign in as a warehouse account from the README. Check that warehouse stock/invoices stay scoped even when a different warehouse ID is submitted.
6. Inspect the low-stock report and CSV; note the currently documented filter/shortage gap.
7. Run the listed checks. The schema parity audit currently reports collation differences and exits 1; ordinary unit, JavaScript and invoice concurrency checks pass.

Use seeded credentials only in disposable demo environments. Neither real `.env` values nor database dumps containing live accounts/customer data are part of the submission.
