# Implementation status

Reviewed 2026-10-30 against the employer's Smart Test.pdf.

Core catalogue, multi-warehouse inventory and invoice features are implemented. The project is not yet recommended for final submission: HTTP secret-file protection, session authorization refresh, stock-write integrity, input validation, stock enrolment, timestamps and schema collation need attention. The optional low-stock report also needs its warehouse selector/shortfall and filter propagation completed.

See [SUBMISSION.md](SUBMISSION.md) for features, design decisions, limitations and a reviewer walkthrough. See [SUBMISSION_REVIEW.md](SUBMISSION_REVIEW.md) for prioritized evidence, PDF requirement mapping and verification results. Setup and demo credentials are in [README.md](README.md).

## Verified in this review

- PHP 8.4.5, MySQL 8.0.30 and Node 22.14.0; the full dependency lock requires PHP 8.4.
- 14 migrations; both fresh SQL and migration installations succeed. Logical structures match, but collation differs, so exact schema parity fails.
- Both fresh paths seed 3 warehouses, 4 users, 30 products, 90 inventory rows, 15 customers, 20 invoices and 20 items; reruns preserve counts and stock.
- PHPUnit: 33 tests / 47 assertions passed. JavaScript: 22 tests passed. Syntax/line-ending checks, Composer validation and installed platform requirements passed.
- Isolated MySQL integration test: four concurrent invoice saves, numbering, stock/items, rollback and cancellation passed. It does not establish a general production-load or single-final-unit contention guarantee.
- Local authenticated HTTP checks passed for admin and three warehouse accounts. Forged warehouse IDs did not widen inventory scope.
- No new rendered-browser/mobile audit, MariaDB validation or production deployment test was performed.

No application business data was changed by the schema/seed review: seed checks used temporary databases. Existing timestamp anomalies are documented, not silently repaired. No external submission was sent.
