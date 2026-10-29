# Implementation status

SmartLife Mini ERP provides the v3.2 SAR-only foundation: bilingual Arabic/English RTL/LTR UI, central role/warehouse scope enforcement, products, categories, warehouses, users, customers, inventory, invoicing, cancellation stock restoration, low-stock CSV reporting, settings, migrations, and idempotent demo data.

## Verified locally

- `php index.php cli/migrate` applies the twelve migrations.
- `php index.php cli/seed` is idempotent and creates three warehouses, four users, thirty seeded products, ninety inventory rows, fifteen customers, and twenty invoices with two cancelled invoices.
- Server-side JSON pagination/search endpoints are available for inventory, products, customers, invoices, low-stock reporting, categories, warehouses, and users. The response also includes DataTables-compatible `draw`, `recordsTotal`, and `recordsFiltered` metadata when requested.
- Invoice products are selected with the warehouse-aware AJAX search endpoint.
- `composer test` passes eleven tests with twenty assertions, including sorted inventory locking, double-cancel prevention, and warehouse-scope authorization coverage.
- `composer lint` and PHP/JavaScript syntax checks pass.

## Deployment notes

- Copy `.env.example` to `.env`; never commit the real `.env`.
- Run locally with `php -S localhost:8080 index.php`, then open `http://localhost:8080`.
- CI3 core files are intentionally unmodified. PHP 8.2+ dynamic-property deprecation notices are suppressed at the front controller; PHP 7.4–8.1 remains the preferred CI3 runtime range.
