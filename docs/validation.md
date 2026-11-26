# Validation record

Updated 16 November 2026. This records completed checks separately from remaining submission evidence. The user confirmed Laravel is approved and excluded GitHub creation/publication from this task.

## Automated checks

- Complete regression suite after the Record Sale/Checkout separation: **68 passing tests, 787 assertions, zero errors and zero failures** on PHP 8.3.16 and MariaDB 10.4.32 with InnoDB.
- The focused form/sales/checkout run also passed: **26 tests, 270 assertions**. Existing sales tests verify the assignment's price, seller, optional customer, stock decrement and overselling rejection behavior.
- Modified PHP files were formatted with Pint. `--dirty` requires Git, so the changed files were passed explicitly; no repository was initialized.
- `node --check public/js/app.js`: passed.
- There are **32 named application routes**, including protected Record Sale, separate Checkout, history, receipts and admin-only stock management.
- PHPUnit uses only the dedicated `counter_pos_test` database. The current local application database is the recovered `counter_pos` on port 3308, running in normal mode.
- Earlier HTTP smoke checks confirmed that the local login page responds successfully. The required forms are rendered and checked by feature tests; visual browser and print review remain pending.
- Current test output is saved under `storage/framework/testing/assignment-results.xml`. A local passing run does not establish hosted deployment or reproducibility on a different machine.

## Acceptance coverage

| Plan ID | Automated evidence |
| --- | --- |
| T01 | AuthenticationTest iterates every protected route and HTTP method as a guest. |
| T02 | Normalized login, logout, generic failed credentials and five-attempt rate limit. |
| T03 | Product CRUD, escaped names, prepared/resized images, removed product rejection. |
| T04 | Customer/staff CRUD, duplicate customer emails, hashed passwords, avatar crop, self-removal rejection. |
| T05 | Invalid fields, byte-limited passwords, corrupt/SVG/PHP/oversized/overdimension uploads; CSRF middleware explicitly enabled for rejection. |
| T06 | 3 × 125.50 = 376.50; stock 10 → 7; correct authenticated seller/customer. |
| T07 | Sell all stock, then reject further sale. |
| T08 | Stock 2, request 3: unchanged stock, no sale, retained form quantity and clear error. |
| T09 | Walk in customer; posted total and seller tampering ignored. |
| T10 | Exception at sale insertion and after the SQL stock update: no persisted sale or stock change. |
| T11 | Two independently authenticated PHP kernels/connections overlap on the same MySQL/MariaDB InnoDB lock; exactly one sale, final stock 1. |
| T12 | Price changes, renames and soft deletions preserve saved names, prices, currency and totals. |
| T13 | Stale stock adjustments and excessive decimal totals rejected. |
| T14 | Real-commit avatar replacement removes old file; failed replacement removes new file; removed user loses access. |
| T15 | AssignmentWorkflowTest verifies the required one-product form and separate optional Checkout form are linked and use different input structures. |
| T16 | Checkout tests cover exact cash/change calculations, noncash recording, whole-cart rollback, line snapshots, invalid inputs and replay protection. |
| T17 | Stock movement tests cover opening balances, restocks, removals, reasoned count corrections, staff attribution, retries and rollback. |

## Pending manual and hosted checks

Visual browser/print review and hosted verification have not been completed. No hosted URL or authorized deployment target has been supplied. The following checks have not been represented as complete:

- Review login, navigation, tables, forms, validation errors, removal dialogs and pagination on a wide screen and a narrow phone screen.
- Check keyboard focus, visible field labels, and the mobile logout control.
- Upload a product photo and staff avatar; check resize/crop appearance and public image URLs.
- Log in, add product and customer, add staff with avatar, record a valid sale, reject excess quantity, inspect history, and log out in a clean browser session.
- If required for presentation, capture screenshots of CRUD, prepared images, Record Sale, rejection, and Sales History. No screenshot deliverable is claimed here.
- Deploy to the authorized host, run the same acceptance flow, redeploy, then confirm images, stock and history persist.
- Record the hosted URL and arrange grader access through the authorized course channel.

GitHub creation/publication was excluded by the user. No repository was initialized and no remote data was published.
