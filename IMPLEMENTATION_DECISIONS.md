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
| Counter-free invoice numbering (v3.3) | Query the retained daily maximum under a database-scoped advisory lock held through transaction completion; keep the unique index. |
| Database-backed duplicate protection | Service checks improve messages but cannot prevent races; nullable unique indexes are the final authority. |
| `LIKE` search | It reliably supports partial names and hyphenated codes at this project scale. |
| CSRF token in request body | This follows CI3's default CSRF verification behaviour. Static JavaScript cannot evaluate PHP. |
| Output escaping, not blanket `xss_clean()` | Context-aware escaping at render/export sinks is predictable and avoids corrupting valid input. |

## Schema notes

`database/schema.sql` creates the application database and all required tables:

- Authentication/session tables: `users`, `login_attempts`, `ci_sessions`
- Catalogue and stock: `categories`, `products`, `warehouses`, `inventory`
- Sales: `customers`, `invoices`, `invoice_items`
- Configuration: `settings`
- Manual adjustment log: `inventory_movements` (not a complete stock ledger)

It includes the critical uniqueness and foreign-key constraints, SAR monetary fields, item snapshots, and does not include a currency or inventory-version table.

## Current implementation and remaining work

The application has been implemented since the original planning revision. Controllers, services, repositories, views, 14 migrations, CLI seed tooling and automated checks are present. The earlier statement that only the framework distribution existed is obsolete.

Current features, tradeoffs and omissions are described in [SUBMISSION.md](SUBMISSION.md). [SUBMISSION_REVIEW.md](SUBMISSION_REVIEW.md) maps the actual employer brief to code and identifies unresolved issues. Those documents take precedence over historical plan claims about implementation or verification.

Fresh SQL and migrated/live tables match in columns and constraints but differ in collation. The full committed dependency lock needs PHP 8.4; older PHP compatibility and MariaDB support have not been verified. Runtime security and correctness gaps remain open.
