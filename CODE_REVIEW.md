# Code and architecture review — 2026-10-30

## Assessment

The application follows the amended plan's CI3 directory layout, single controller set, bilingual UI, SAR-only policy, constructor-injected sales/catalogue/customer services, and repository-owned invoice transactions. It does **not** yet meet every architecture or verification claim in the original plan. Passing the current checks does not establish full SOLID compliance, PSR-12 compliance, concurrency safety under load, or absence of regressions.

Reviewed application controllers, shared controller, services, repositories, shared browser scripts/styles, affected views, schema/migration structure, test suite, implementation amendments and local Git history. Vendor and framework internals are outside this review.

## Fixes in this change

- Desktop sidebar: restore fixed positioning expected by Tabler's page wrapper, eliminating the sidebar's normal-flow space above the header. Give the sidebar brand its own full-width row and allow its text to wrap.
- Shared tables: keep controls outside horizontal scrolling, place page size in the footer, combine category/search controls, debounce server searches, cancel superseded requests, and show a translated load error instead of leaving an unhandled rejection.
- Demo catalogue: distribute legacy `GEN-*` demo products across five categories on fresh installs and reruns. Interleaving inserts alone cannot change IDs already in the database. Only known demo codes are backfilled; stock is not reset.
- Invoice history: authorize the assigned warehouse independently of its active status for index/data/show. Creation, product lookup and cancellation retain the existing active-warehouse requirement. Unknown warehouse IDs remain forbidden; warehouse users cannot widen their assigned scope.
- Invoice product results: use an opaque background, readable hover/focus states, bounded height and elevated stacking.
- Login throttling: delete expired failed attempts, preserving attempts inside the 15-minute window.

## Remaining findings, ordered by impact

1. **High — existing sessions retain outdated permissions.** `MY_Controller::requireAuth()` checks the session snapshot only. Deactivating a user or changing their role/warehouse in `Users` does not revoke or refresh their existing session. Refresh authorization from the database once per request, or implement explicit session invalidation. Cover deactivation, demotion and warehouse reassignment in integration tests.
2. **High — inventory adjustments do not check transaction success.** `Inventory::adjust()` commits and flashes success without verifying `trans_status()`. In non-debug environments a failed stock or movement write can produce misleading success. Move this workflow to an inventory service/repository using the checked transaction wrapper, and test rollback on movement insertion failure.
3. **Medium — service/repository separation is incomplete.** Most CRUD/list queries remain in controllers; `CustomerService` accesses `database()` and changes CI driver settings directly. Move persistence error translation into repositories and inject narrow contracts where they are useful. Do not add pattern layers solely for their names.
4. **Medium — product creation and initial stock are separate writes.** `Products::save()` persists the product before validating/creating its inventory row. Use one repository transaction for the complete workflow to avoid a partially created product after warehouse or stock failure.
5. **Medium — update validation is inconsistent.** `Users::save()` applies password length rules only on creation, although updates also accept passwords. Repository uniqueness checks also need consistent duplicate-key translation when concurrent writes bypass the precheck.
6. **Medium — server tables still load full lists on initial HTML requests.** Controllers query all rows to render a fallback, then JavaScript immediately requests a paginated list. Pagination therefore does not bound initial request memory/query cost. Render a bounded first page or an empty loading state, and extract repeated pagination parsing without introducing a generic query framework.
7. **Medium — sales input and transaction coverage need expansion.** Duplicate product lines retain only the first line's discount; quantities are cast to integers. Define and validate conflicting discounts and fractional/overflow quantities explicitly. Existing mock-based lock tests do not prove MySQL concurrent numbering, deadlock behaviour or double cancellation under simultaneous requests.
8. **Verification gap.** `.phpcs.xml` checks syntax and line endings only. PHPStan, PHPMD, coverage thresholds, database concurrency tests and browser regression tests described in v3.0/v3.1 are not configured. Do not describe `composer lint` as a PSR-12 or SOLID check.
9. **Documentation drift.** `IMPLEMENTATION_DECISIONS.md` still describes the application as unimplemented. Later customer-access and warehouse-policy features differ from earlier plan text. The v3.1/v3.2 amendments are authoritative for SAR-only and shared controllers; subsequent product decisions need a current revision record.

## Validation

- PHPUnit: 16 tests, 26 assertions, including warehouse history scope and throttling regressions.
- Repository lint, JavaScript syntax checks and `git diff --check`.
- Live local HTTP checks: products, invoices, inventory, low-stock reports, customers, warehouses and invoice creation pages respond successfully as admin.
- Reran the demo seeder; the first product page contains five distinct categories.
- Temporarily deactivated the seeded Riyadh warehouse: admin and its assigned warehouse user could list/read invoices; new invoice creation remained forbidden for that warehouse user. Restored the original warehouse status afterward.
- No rendered browser/screenshot verification was available. Header alignment, RTL/LTR controls, dropdown overlap and mobile layouts still need visual confirmation in a browser.

## Branch workflow

Recent `main` release commits have two parents: the previous release and a feature commit on `develop`. Feature work must stay off `main`; integrate through `develop`, then create a release merge into `main`. This change is prepared on `fix/layout-tables-warehouse-history`; no release merge or tag is implied by the checks above.
