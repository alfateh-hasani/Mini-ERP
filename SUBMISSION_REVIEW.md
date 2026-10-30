# Job-test submission review

Reviewed 2026-10-30 against the supplied **Smart Test.pdf**, physical pages 2-3. The PDF is the employer's specification; the much larger internal implementation plan is not an additional employer requirement. No business logic or application schema was changed during this review.

**Recommendation: address the security and correctness findings below before submission.** The required features are present, and the relational design is appropriate for a small ERP exercise. Describing the project as perfect, fully SOLID, or production-ready would be inaccurate. The employer explicitly prefers a small, clean, secure solution over a large unstable one.

## Findings to address first

| Priority | Finding and evidence | Recommended correction |
|---|---|---|
| Critical before sharing a running instance | The documented development router returns existing files directly: `index.php:45-52`. Unauthenticated local GET requests to `/.env` and `/.git/HEAD` both returned HTTP 200. The environment response contains database credential assignments; no credential values were printed or included in this report. Root `.htaccess` also has no explicit deny rules for these paths. This confirms local exposure, not that the remote GitHub repository contains `.env` or that an Internet deployment was inspected. | Allow only intended static assets through the router. Deny hidden files, Git metadata, dependencies, tests and non-public source at the web-server boundary. Verify secret/source paths return 403/404 while intended assets load. Git ignore rules do not provide HTTP protection. |
| High | `MY_Controller::requireAuth()` checks the session's saved user only (`application/core/MY_Controller.php:14-22`). User deactivation, demotion or warehouse reassignment does not update existing sessions. | Reload the authoritative active user once per request, or enforce a session authorization version. Test all three changes using an already logged-in session. |
| High | `Inventory::adjust()` begins a transaction, writes stock and a movement, then commits without checking transaction/write success (`application/controllers/Inventory.php:72-81`). With database debug disabled, a failed movement statement can leave a stock change committed without its audit record and still report success. | Move adjustment into a checked service/repository transaction; validate integer bounds, roll back on either write failure, and test failure of the second write. Check commit results as well as transaction status. |
| High | Invoice quantities/IDs are cast before validation; a quantity such as `1.9` becomes `1`. Duplicate product lines silently keep the first discount (`application/services/InvoiceService.php:58-62`). The UI prevents duplicates, but direct requests bypass that rule. Scalar/array payload mismatches and oversized monetary calculations can escape as runtime errors. | Validate payload shape, positive integer IDs/quantities and allowed ranges before casting. Reject duplicate lines, or explicitly require identical discounts when merging. Bound integer multiplication and every persisted money value; return translated validation errors. |
| High | A product can be created without an inventory row, and adjustment rejects any missing row. There is no normal UI workflow to stock that product later or add an existing product to another warehouse (`Products.php:86-91`, `Inventory.php:73-75`). Product creation and initial stock are also separate writes. | Provide an admin stock-enrolment path and make initial product/stock creation atomic. Test creating a product without initial stock, then stocking it, including a second warehouse. |
| High for reliable handoff | README previously claimed PHP 7.4+, but locked `league/csv` 9.29.0 requires PHP 8.2 and locked development dependency `doctrine/instantiator` 2.1.0 requires PHP 8.4. A plain `composer install` cannot satisfy the lock on PHP 7.4-8.3. | Use the documented, tested PHP 8.4 environment for this submission, or deliberately resolve and test a lower-version-compatible lock. The employer allows PHP 7.4 **or newer**, so using 8.4 is acceptable. Do not advertise untested compatibility. |
| Medium | Fresh SQL and migrations differ in collation on all 12 application tables. SQL import uses `utf8mb4_unicode_ci`; migrations and the current database use `utf8mb4_0900_ai_ci`. | Choose one explicit collation in SQL and migrations, with a forward migration for existing installs if needed. Recheck uniqueness and comparison behavior before converting existing data. |
| Medium | PHP uses UTC while MySQL uses system Arab Standard Time. The current five invoices contain three cancellations timestamped before creation. `InvoiceRepository::markCancelled()` uses PHP time; creation timestamps default to MySQL time. | Set a consistent application/connection time policy before accepting new invoices. Review historical corrections separately; do not silently rewrite existing timestamps. Check invoice-date rollover near midnight. |
| Medium | The PDF's optional low-stock report requires a warehouse filter, name/code search and shortage amount. Search and `quantity <= alert_quantity` exist, but `views/reports/low_stock.php` has no visible warehouse filter or shortfall column. Its AJAX source and CSV link also discard a warehouse selected through the URL. The inventory shortfall column does not complete this report feature. | Carry one filter state through report HTML, AJAX and CSV. Add the warehouse selector and `max(0, alert_quantity - quantity)` column; equality is included in the report with zero shortage. |

## Other concrete improvements

- **Cancellation integrity:** restoration updates can affect zero rows without failing, and cancellation is then marked complete (`InvoiceRepository::restore()`). Verify every required inventory row exists/is restored. The admin cancellation path also bypasses an inactive-warehouse check because it has no assigned scope, although the view hides cancellation for an inactive warehouse. Enforce the same rule at the server boundary.
- **Validation consistency:** user password length is validated on create but not admin update; role input defaults to admin rather than rejecting an unknown role. Validate both create/update paths and return useful errors for missing update IDs and duplicate-key races.
- **Persistence failures:** product/customer repositories return IDs without consistently checking the underlying write. `CustomerService` temporarily changes the database driver's debug setting and treats only duplicate-key errors explicitly. Translate persistence failures in repositories and keep driver internals out of services.
- **Database integrity:** add suitable arithmetic, discount and cancellation-state checks. Current constraints allow positive but inconsistent amounts. Validate storage limits in services too; constraints alone do not give good user feedback.
- **Stock audit:** manual adjustments have movements; invoice creation/cancellation and starting stock do not. This is a partial adjustment log, not a reconcilable stock ledger. A complete ledger is useful future work, not an explicit minimum PDF requirement.
- **Historical billing:** product details/prices are snapshotted correctly. Customer identity/contact information is not; editing a customer changes old invoice presentation. Snapshot billing data if immutable invoice documents are intended.
- **Quantity model:** quantities are integers even for units such as kilograms/litres/metres. Whole-unit sales can be an explicit exercise limitation. Fractional quantities require a coordinated change across stock, movements, invoices, validation and arithmetic.
- **Indexes and ranges:** `idx_products_search_code` duplicates the unique code index; movement deltas use signed INT while balances are unsigned INT. Choose bounded quantities or align the ranges. No indexing overhaul is justified by this small fixture.
- **Duplicate product codes:** application prechecks ignore soft-deleted products, while the database unique index reserves their codes. Choose one policy and translate concurrent duplicate writes consistently.
- **List performance:** most HTML list actions load the entire dataset before requesting a paginated AJAX result. Inventory is bounded, but products, invoices, customers and admin lists are not. Extract repeated pagination parsing and return a bounded initial page.
- **Localization:** some server successes remain hard-coded English (for example `Users::save()`), and CSV headers are English. Validate visible text and RTL/LTR with a final browser pass; do not claim every message is localized.
- **Presentation and deployment:** `company_name` is editable but the layout brand remains hard-coded. Tabler CSS needs Internet access. HTTPS cookies and headers need deployment configuration; Apache `.htaccess` headers do not apply to the documented PHP development server. No deployed server was inspected.

## Architecture and SOLID assessment

The useful structure is **CI3 controller -> service -> repository -> MySQL**, with constructor injection in services, shared auth/scope checks, ordered stock locks and repository transaction wrappers. These are good choices for the exercise. Product snapshots are deliberate denormalization, not bad normalization. Keeping stored invoice totals is also appropriate for an issued document.

| Principle / quality | Assessment |
|---|---|
| Single responsibility | Partially met. Invoice business logic has a service; inventory writes, pagination and much CRUD still mix HTTP, business rules and SQL in controllers. |
| Open/closed | Not a claim to certify globally. Adding another storage mechanism or modifying list behavior currently touches several concrete classes. Extract repeated behavior only where there is a real extension need. |
| Liskov substitution | No specific runtime substitution defect was established. Repository subclass test doubles do not prove general compliance. |
| Interface segregation | There are no meaningful service-facing repository contracts yet. This is not automatically a defect in a small application. Introduce narrow contracts if they materially simplify testing or alternate implementations. |
| Dependency inversion | Partial: constructor injection is present, but services depend on concrete repositories; `CustomerService` reaches through to the CI driver. |
| Clean code | Naming and folder responsibilities are generally understandable. Many controllers, migrations and views are compressed into very long lines, making review and extension unnecessarily difficult. Expand touched workflows into readable methods before adding more layers. |
| Design patterns | Repository/service and transaction-boundary patterns are useful here. Additional factories, event buses, generic repositories, microservices or a large rewrite are unnecessary for this test. |

`composer lint` checks syntax and line endings only. It does not verify PSR-12, SOLID, design quality or security. There is no configured PHPStan/PHPMD/coverage gate. The internal plan contains ambitions beyond the employer's scope; they should not be represented as completed checks.

## Employer requirement mapping

| PDF requirement | Evidence / status |
|---|---|
| CI3, PHP 7.4 or newer, MySQL/MariaDB, ordinary JS AJAX | CI3 and plain JS are present; tested on PHP 8.4.5 / MySQL 8.0.30. MariaDB and older PHP were not tested. |
| Product list, name/code search, category filter, pagination, add/edit/disable | Implemented in Products, ProductService, ProductRepository and product views. |
| Multiple warehouses and per-product stock per warehouse | Implemented with unique `(warehouse_id, product_id)` inventory rows. Missing-row stock enrolment is a workflow gap noted above. |
| Invoice customer/warehouse selection, product search, quantity/remove, live totals, header/items persisted | Implemented. Server recalculates catalogue prices and totals instead of trusting the browser. |
| Reject insufficient stock and support correctly rounded percentage discounts | Implemented for ordinary valid requests under locks; malformed/overflow and conflicting duplicate-line inputs need stricter validation. |
| Low-stock report: threshold, warehouse filter, name/code search, shortage | Partial: threshold and search exist; visible warehouse filter/shortage and filter propagation are incomplete. |
| Admin all warehouses; warehouse user own warehouse | Existing scope is enforced; forged scope requests return only assigned-warehouse stock. Session changes are not refreshed, as above. |
| Explain/handle simultaneous sales of last available stock | Ordered `FOR UPDATE` inside invoice transactions prevents overselling among cooperating writers. Four concurrent saves were tested; the current integration fixture does not test two buyers competing for a single final unit. |
| Invoice list/details and low-stock CSV | Implemented; report CSV filter consistency still needs correction. |
| SQL file in repository | `database/schema.sql` installs successfully. Logical structure matches the migrated/live schema; collation differs. |
| Run instructions; what was built, decisions and omissions; multiple commits; Git link | README and SUBMISSION.md prepared; substantial commit history exists. Share the repository link after the chosen fixes and final verification are committed/pushed. No email or external submission was sent. |

## Schema comparison and verification evidence

The comparison creates two randomly named temporary databases, imports the SQL into one and invokes the actual CLI migrations in the other. It compares MySQL `SHOW CREATE TABLE` output, ignoring only AUTO_INCREMENT counters and the framework-managed `migrations` table. It also reads the configured application's schema. Temporary databases are removed in `finally`; application rows are not modified.

- 12 application tables have matching columns, indexes, foreign keys and checks after removing collation declarations for comparison. The metadata difference is the expected framework `migrations` table (version 14), which a plain SQL import does not need.
- All 12 differ in collation, so **exact parity fails**. The audit deliberately exits 1 until that drift is resolved. This is not a successful parity check disguised as a pass.
- Both fresh installations seed successfully: 3 warehouses, 4 users, 30 products, 90 inventory rows, 15 customers, 20 invoices and 20 items. A second seed preserves these counts and quantities. The seed can backfill known demo categories, so it should remain demo-only.
- Read-only current-data checks found 5 invoices, no mismatched header/item arithmetic and no orphaned inventory rows; 3 cancellation timestamps precede creation. The small sample is not proof of complete integrity.
- PHPUnit: **33 tests / 47 assertions passed**. Browser-script tests: **22 passed**. PHP syntax/line endings passed. Composer validation and installed platform requirements passed on PHP 8.4.5.
- Isolated MySQL test passed four concurrent invoice saves, unique numbering, stock/item writes, cancelled numbers, suffix gaps, 9999 rollover, rollback, cancellation restoration and advisory lock cleanup.
- Local HTTP checks passed inventory/invoices/create/low-stock pages for admin and all three warehouse accounts; admin catalogue/customer/user/warehouse/settings pages and CSV responded successfully. Forged warehouse IDs did not widen warehouse-user inventory scope.
- No fresh rendered-browser/mobile/accessibility audit, external penetration test, production load test, full MariaDB test, or end-to-end authorization-revocation test was performed in this review.

Run `php application/tests/integration/schema_parity.php --seed-smoke` to reproduce the schema/installation audit. It needs CREATE/DROP DATABASE permissions and reads the configured database without changing its business data. See [DATABASE_REVIEW.md](DATABASE_REVIEW.md) for per-table/column considerations and [SUBMISSION.md](SUBMISSION.md) for the handoff description.
