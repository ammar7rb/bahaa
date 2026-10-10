# Admin account directory

## Scope and entry point

The administration Customer List is a unified customer/vendor directory. The navigation entry belongs in Home below POS. Named routes use admin.account-directory.*; the page is served at the admin customer-list path. This is an extension of the existing admin-v2 appearance with admin-dark-mode support.

Design guidance lives in .impeccable/surfaces/admin-account-directory.md. Root DESIGN.md and .impeccable/design.json remain the vendor workspace authority; the admin-dashboard brief is preserved. No authentication-data migration is part of this implementation.

## Implementation

- app/Services/AdminAccountDirectoryService.php projects users and sellers into one paginated read model, omits walking-customer records, searches and filters results, reads secondary vendor contacts and handles deletion safeguards.
- app/Http/Controllers/Admin/AccountDirectoryController.php validates filters, authorizes people access, changes status and enforces deletion confirmation.
- resources/views/admin-views/accounts/index.blade.php renders the six-column table, existing profile links, filters, contact disclosures, feedback, pagination and deletion dialog.
- public/assets/back-end/css/admin-account-directory.css consumes existing semantic admin tokens and scopes responsive layout to this surface.
- app/Exports/AdminAccountDirectoryExport.php produces real XLSX with additional contacts and explicit text binding.
- resources/views/admin-views/accounts/print.blade.php and public/assets/back-end/css/admin-account-directory-print.css render the standalone report.
- public/assets/back-end/js/admin-account-directory.js handles mobile disclosure, modal focus, identifier validation, submission feedback and admin soft-navigation initialization.
- tests/Unit/AdminAccountDirectoryTest.php exercises the projection and protected actions with isolated SQLite fixtures.

Display references are lowercase c plus the customer ID and m plus the vendor ID. They do not mutate stored IDs or replace AdministrativeReferenceService. Vendor person/store names are separately labeled. Additional contacts come from actual vendor_account_profiles phone_numbers and shops.contact; the primary contact is excluded from the disclosure when it matches.

## Access and status

Directory read and status actions require an authenticated administrator with the people module permission. Permanent deletion additionally requires admin_role_id 1. Customers use is_active; vendors map approved to active and suspended to frozen. Pending/rejected vendors require their existing review workflow and cannot be approved through Reactivate.

Customer freeze revokes Passport access tokens, refresh tokens and authorization codes. Authenticate and APIGuest reject frozen customers on existing API sessions; incumbent customer web and seller gates enforce their existing status checks. Freeze clears remember tokens where available and vendor auth_token. Reactivation does not restore revoked tokens.

## Permanent deletion and retention

Deletion requires exact display-reference confirmation and an accepted irreversible-action acknowledgment in the backend. Existing retention is preserved: account balances, financial/order/support/product dependencies, customer referrals, matching contact history, discovered foreign keys and recognized account/subject references can block deletion. The conservative scan may retain an account even when a broader policy could allow removal; that policy review is a separate requested follow-up.

Only accounts without blockers may be physically deleted. Vendor-owned shop/profile rows or customer token rows are removed inside the same database transaction. A deletion failure rolls back those database changes, preventing partial database deletion. This is a database guarantee; it is not a general claim about unrelated external side effects.

## Classification, search and output

Classification provides type/status, inclusive joining-date and order-date ranges, first N, sorting and direction. Sort fields are joining date (default descending), name, numeric identifier, status, order count and order amount, with stable kind/ID tie breakers. Name sorting uses the projected first-name field. First N applies before pagination; page sizes are 20, 50 and 100.

Order-date classification requires a matching non-guest customer order or seller-owned vendor order in the range. Count and amount sorting use all qualifying account orders, rather than only those in the range. Search includes references, names, store names, email and telephone data, including additional vendor phones and shop contacts.

Screen, Excel and print share validated filters and the service query. Both outputs include all matching records, rather than the current page; first N still applies. XLSX reads query records in batches of 500 and includes additional contacts, joining date and order count. Explicit string binding preserves phone zeroes and prevents account data from becoming spreadsheet formulas. Query batching is not a constant-memory guarantee for the spreadsheet writer.

Print opens a standalone light report with count, timestamp, classification summary and six columns: row number, reference, name, type, contacts and status. CSS requests A4 landscape, repeating headings, rows kept together where possible, and hidden print controls on paper. Read, export, print and status each require authenticated admin people access; deletion retains its additional role/confirmation requirements.

## Verification and evidence

The reported final directory/workspace regression run passed 21 tests and 147 assertions using PHP with an explicit `-d extension=zip` runtime flag. Local zip is off by default; the server uses its existing Excel stack. Coverage includes actual XLSX values and formula protection, date boundaries, activity sorting, first-N/pagination consistency, output authorization/date validation and existing protected actions, rollback, contacts and page structure.

The fresh refinement review returned ship within fixture and source scope, with no material fixes. Five valid captures are recorded:

- .impeccable/review/directory-refined-light-desktop.jpg
- .impeccable/review/directory-refined-dark-desktop.jpg
- .impeccable/review/directory-refined-light-mobile.jpg
- .impeccable/review/directory-refined-dark-mobile.jpg
- .impeccable/review/directory-refined-print.jpg

Desktop captures use 1440px and Arabic RTL in both themes. Mobile captures use 390px, English LTR in light mode and Arabic RTL in dark mode. Print verifies standalone report layout. Full production header/sidebar composition and actual physical-paper pagination were not captured. The single detector warning about Inter in print was accepted as intentional reuse of the incumbent installed font, rather than a new system rule.

The read-only production MySQL candidate check confirmed 25 accounts (16 customers and nine vendors), phone/reference searches for both types, first-N=3 with actual total 3, joining-date classification returning 25 and order-date classification returning 12. It also confirmed actual contacts and retained-dependency categories. No production account was frozen or deleted during verification.

Release verification checks the deployed revision, protected routes, authenticated server rendering and actual Excel/print outputs separately from the visual fixtures. An authenticated production browser session and physical printing remain outside this evidence.
