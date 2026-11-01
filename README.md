# SmartLife Mini ERP

Arabic/English, SAR-only stocks and sales application built on CodeIgniter 3.1.13. The current locked dependency set is tested with PHP 8.4.5 and MySQL 8.0.30. Use PHP 8.4 for a full development installation; the lock is not compatible with the previously advertised PHP 7.4 minimum.

Read [SUBMISSION.md](SUBMISSION.md) for implemented features, decisions and omissions, and [SUBMISSION_REVIEW.md](SUBMISSION_REVIEW.md) for the open pre-submission findings. This is an exercise implementation with known issues, not a production-ready ERP.

## Setup

Prerequisites: PHP 8.4, Composer, MySQL 8 (8.0.16 or newer for enforced CHECK constraints), and PHP extensions `mysqli`, `mbstring`, `dom`, `xml`, `xmlwriter`, `simplexml`, `tokenizer` and `fileinfo`. Node.js is needed only for JavaScript tests (verified with Node 22). MariaDB was not tested. Internet access is needed for Composer installation and CDN styling.

1. From the repository root, install PHP dependencies with `composer install`, then run `composer check-platform-reqs`.
2. Copy `.env.example` to `.env`, then set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and optionally `DB_DATABASE` (defaults to `mini_erp_smart_life`) for your local MySQL account. The real `.env` is ignored by Git; it must also be blocked from HTTP access.
3. Set a unique production `CI_ENCRYPTION_KEY` and `APP_BASE_URL` in `.env`.
4. Initialize an empty database using one of these paths. SQL import: `mysql -u USER -p -e "SOURCE database/schema.sql"` creates and selects the fixed database `mini_erp_smart_life`. Migration path: create the database named in `.env` first, then run `php index.php cli/migrate` (14 migrations). Use migrations for a custom database name. The two paths currently differ in collation; see Schema verification below.
5. Optionally create idempotent demo data: `php index.php cli/seed`.
6. For local development only, run `php -S localhost:8080 index.php`, then open `http://localhost:8080`. **Known blocker:** the current router exposes existing hidden files, including `.env` and Git metadata, through HTTP. Do not expose this server through a public interface, proxy or tunnel. Apply the file-access protections in the submission review before sharing a running instance.

Demo accounts after seeding (password `87654321` for each):

| Account | Role / warehouse |
|---|---|
| `test@smartlife.com` | Admin, all warehouses |
| `ahmed@smartlife.com` | Riyadh warehouse |
| `sara@smartlife.com` | Jeddah warehouse |
| `khalid@smartlife.com` | Dammam warehouse |

Use these credentials only in a disposable demo database. Seeding creates missing demo records but does not reset existing passwords or warehouse statuses. It can backfill categories for known demo product codes. Back up any existing database before using demo tooling.

## Commands

- `composer test` runs PHPUnit.
- `composer lint` checks PHP syntax and line endings with PHP_CodeSniffer; it does not enforce PSR-12 or SOLID.
- Run the browser-script tests with `node application/tests/js/app-fetch.test.cjs`, `node application/tests/js/invoice-submit.test.cjs`, `node application/tests/js/invoice-totals.test.cjs` and `node application/tests/js/validation.test.cjs`.
- `php index.php cli/migrate` applies the included migrations (including migration 014, which removes the legacy invoice counter table).
- `php application/tests/integration/invoice_numbering.php` verifies numbering with concurrent MySQL connections in an isolated temporary database; the database user needs CREATE/DROP DATABASE permissions.
- `php application/tests/integration/dashboard.php` verifies issued-only dashboard totals and warehouse isolation in an isolated temporary database; the database user needs CREATE/DROP DATABASE permissions.
- `php index.php cli/seed` idempotently creates or backfills demo warehouses, users, products, inventory, customers, and invoices.

## Security and business rules

Dashboard invoice count and sales total include issued invoices across all dates. Cancelled invoices are excluded from both cards. Admins see all warehouses unless they select a warehouse; warehouse users always see only their assigned warehouse. The recent invoice list retains both issued and cancelled history.

Inventory supports warehouse and product filters plus inclusive minimum/maximum stock quantities. Leave either quantity bound empty for no limit; use `0` to include zero stock. Product choices follow the selected warehouse. Apply filters to update the results, or Reset to clear filters and table search. Warehouse users remain restricted to their assigned warehouse.

Admins can browse stock in inactive warehouses. Inactive warehouses are marked in the inventory filter and are read-only; quantity adjustments require an active warehouse.

Invoice creation requires an explicit customer selection. Each item shows its catalogue unit price and totals before/after its discount; the summary updates as products, quantities, discounts, and lines change. Preview calculations use integer halalas and the server's half-up rounding. A compact line-discount column follows the percentage input; per-unit discount detail panels are omitted. Discounts accept 0 through 100 percent; clearing the field hides that line's before/after amounts until a valid value is entered (use 0 for no discount). Saved prices and totals are still calculated by the server.

Invoice product rows show compact available/remaining stock badges. Quantity fields enforce the available quantity as the maximum and flag over-stock entries inline. Product or warehouse changes clear the previous stock limit; the server rechecks stock under lock when saving.

- SAR is fixed; there is no currencies table, selector, conversion, or exchange-rate feature.
- CI3 CSRF is enabled. Static JavaScript reads the token from layout metadata, sends it in `FormData`, and refreshes it from each JSON response.
- Role and effective warehouse scope are enforced centrally in `MY_Controller`; warehouse users cannot widen scope with a submitted warehouse ID.
- Invoice creation locks warehouse inventory by product ID in ascending order, allocates the next daily number from retained invoices under a database-scoped advisory lock, and writes product snapshots. Cancellation locks the invoice and inventory rows before restoring stock.
- Import `database/schema.sql` for a fresh installation, or apply migrations. Columns and constraints match; the collation discrepancy is documented below.

See [DATABASE_REVIEW.md](DATABASE_REVIEW.md) for the column-by-column schema review and unapplied recommendations. Invoice numbers must be allocated through `InvoiceRepository::numberedTransaction()`; existing invoices are retained, including cancellations.

## Schema verification

`php application/tests/integration/schema_parity.php --seed-smoke` compares the SQL import, actual migrations and configured database schema. It creates and removes two randomly named test databases and reads the configured application schema without changing its business records. CREATE/DROP DATABASE permissions are required. The optional flag also seeds both temporary databases twice and checks row counts and stock preservation.

Current result: all 12 application tables have matching columns, indexes, foreign keys and checks, but SQL imports use `utf8mb4_unicode_ci` while migrations/current tables use `utf8mb4_0900_ai_ci`. The audit exits 1 until exact parity is achieved. Migrations also maintain a framework `migrations` table (version 14); a standalone SQL import does not include that bookkeeping table.
