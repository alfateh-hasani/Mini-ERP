# SmartLife Mini ERP

Arabic/English, SAR-only stocks and sales application built on CodeIgniter 3.1.13, PHP 7.4+ and MySQL 8.

## Setup

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env`, then set `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and optionally `DB_DATABASE` (defaults to `mini_erp_smart_life`) using the same details as TablePlus. The real `.env` is ignored by Git.
3. Set a unique production `CI_ENCRYPTION_KEY` and `APP_BASE_URL` in `.env`.
4. Initialize with either `mysql -u USER -p < database/schema.sql` or `php index.php cli/migrate`. The schema import creates the database; migrations require that the empty `mini_erp_smart_life` database already exists.
5. Optionally create idempotent demo data: `php index.php cli/seed`.
6. Run locally with `php -S localhost:8080 index.php`, then open `http://localhost:8080`.

Demo login after seeding: `test@smartlife.com` / `87654321`. Change it before deployment.

## Commands

- `composer test` runs PHPUnit.
- `composer lint` runs PHP_CodeSniffer after dependencies are installed.
- `php index.php cli/migrate` applies the included migrations.
- `php index.php cli/seed` idempotently creates or backfills demo warehouses, users, products, inventory, customers, and invoices.

## Security and business rules

Inventory supports warehouse and product filters plus inclusive minimum/maximum stock quantities. Leave either quantity bound empty for no limit; use `0` to include zero stock. Product choices follow the selected warehouse. Apply filters to update the results, or Reset to clear filters and table search. Warehouse users remain restricted to their assigned warehouse.

Admins can browse stock in inactive warehouses. Inactive warehouses are marked in the inventory filter and are read-only; quantity adjustments require an active warehouse.

Invoice creation requires an explicit customer selection. Each item shows its catalogue unit price and totals before/after its discount; the summary updates as products, quantities, discounts, and lines change. Preview calculations use integer halalas and the server's half-up rounding. Unit discounts and discounted unit prices are labelled separately from full-line discounts. Saved prices and totals are still calculated by the server.

Invoice product rows show compact available/remaining stock badges. Quantity fields enforce the available quantity as the maximum and flag over-stock entries inline. Product or warehouse changes clear the previous stock limit; the server rechecks stock under lock when saving.

- SAR is fixed; there is no currencies table, selector, conversion, or exchange-rate feature.
- CI3 CSRF is enabled. Static JavaScript reads the token from layout metadata, sends it in `FormData`, and refreshes it from each JSON response.
- Role and effective warehouse scope are enforced centrally in `MY_Controller`; warehouse users cannot widen scope with a submitted warehouse ID.
- Invoice creation locks warehouse inventory by product ID in ascending order, allocates a number from a locked daily counter, and writes product snapshots. Cancellation locks the invoice and inventory rows before restoring stock.
- Import `database/schema.sql` for the canonical fresh installation. Migrations are an equivalent application installation path.
