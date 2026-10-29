# Implementation Plan Revision Record

Date: 2026-10-29

## What was done

- Revised `IMPLEMENTATION_PLAN.md` through the v3.1 amendment. The amendment is authoritative where it conflicts with v3.0.
- Reduced money handling to a single currency: Saudi Riyal (SAR). Currency switching, exchange rates, currency preferences, and currency schema/components are removed from the target design.
- Retained the language switcher (Arabic/English), dashboard, breadcrumbs, and Users CRUD.
- Replaced parallel admin/warehouse controller design with one controller set. Role changes only the effective warehouse scope.
- Added `database/schema.sql` as a fresh-install MySQL 8 schema.
- Captured CI3 lifecycle, CSRF, validation, service wiring, search, header, CSV, authorization, invoice-number, and concurrency requirements.

## Key decisions

| Decision | Rationale |
|---|---|
| SAR only, `DECIMAL(12,2)` | There are no exchange rates; merely changing a currency symbol would misrepresent stored amounts. |
| Store invoice item snapshots | Product names, units, codes, and prices must remain historically correct after a product edit. |
| One controller set | Avoids duplicate behaviour and makes authorization a reusable warehouse-scope concern. |
| Pessimistic inventory locking | Invoice processing needs atomic stock checks; use ordered `SELECT ... FOR UPDATE`, not a second optimistic locking scheme. |
| Counter-row invoice numbering | `COUNT()` cannot safely allocate numbers under concurrent requests. A dated counter row plus a unique index can. |
| Database-backed duplicate protection | Service checks improve messages but cannot prevent races; nullable unique indexes are the final authority. |
| `LIKE` search | It reliably supports partial names and hyphenated codes at this project scale. |
| CSRF token in request body | This follows CI3's default CSRF verification behaviour. Static JavaScript cannot evaluate PHP. |
| Output escaping, not blanket `xss_clean()` | Context-aware escaping at render/export sinks is predictable and avoids corrupting valid input. |

## Schema notes

`database/schema.sql` creates the application database and all required tables:

- Authentication/session tables: `users`, `login_attempts`, `ci_sessions`
- Catalogue and stock: `categories`, `products`, `warehouses`, `inventory`
- Sales: `customers`, `invoice_counters`, `invoices`, `invoice_items`
- Configuration: `settings`

It includes the critical uniqueness and foreign-key constraints, SAR monetary fields, item snapshots, and does not include a currency or inventory-version table.

## What was not done

- The CI3 application itself was not implemented; the repository currently contains the CodeIgniter base distribution and planning artifacts rather than the planned controllers, services, repositories, views, migrations, tests, or assets.
- No migration/seed CLI controller was added, so the canonical supported database initialization at this point is importing `database/schema.sql`.
- No optional `database/seed.sql` was added; demo credentials and safe bcrypt hashes should be chosen when application seed tooling is implemented.
- No dependency installation, runtime configuration, browser testing, database import, or automated tests were run.
- Existing v3.0 plan text remains for traceability; the v3.1 amendment explicitly overrides conflicting portions.

## Remaining implementation checklist

1. Build `MY_Controller` auth, locale, dependency wiring, effective warehouse scope, and breadcrumb support.
2. Implement CRUD controllers/services/repositories and route all warehouse-sensitive reads/writes through the scope guard.
3. Implement the static-JS CSRF metadata/refresh contract and JSON response token field.
4. Implement invoice creation/cancellation transactions exactly as described in v3.1.
5. Add migrations equivalent to `database/schema.sql`, CLI commands if desired, test fixtures, and regression/concurrency tests.
6. Configure headers once in the deployment web server and validate the final CSP against local/CDN assets.