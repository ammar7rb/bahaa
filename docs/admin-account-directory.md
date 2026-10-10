# Admin account directory

## Scope and entry point

The administration Customer List is a unified customer/vendor directory. The navigation entry belongs in Home below POS. Named routes use admin.account-directory.*; the page is served at the admin customer-list path. This is an extension of the existing admin-v2 appearance with admin-dark-mode support.

Design guidance lives in .impeccable/surfaces/admin-account-directory.md. Root DESIGN.md and .impeccable/design.json remain the vendor workspace authority; the admin-dashboard brief is preserved. No authentication-data migration is part of this implementation.

## Implementation

- app/Services/AdminAccountDirectoryService.php projects users and sellers into one paginated read model, omits walking-customer records, searches and filters results, reads secondary vendor contacts and handles deletion safeguards.
- app/Http/Controllers/Admin/AccountDirectoryController.php validates filters, authorizes people access, changes status and enforces deletion confirmation.
- resources/views/admin-views/accounts/index.blade.php renders the six-column table, existing profile links, filters, contact disclosures, feedback, pagination and deletion dialog.
- public/assets/back-end/css/admin-account-directory.css consumes existing semantic admin tokens and scopes responsive layout to this surface.
- public/assets/back-end/js/admin-account-directory.js handles modal focus, exact identifier validation and submission feedback.
- tests/Unit/AdminAccountDirectoryTest.php exercises the projection and protected actions with isolated SQLite fixtures.

Display references are lowercase c plus the customer ID and m plus the vendor ID. They do not mutate stored IDs or replace AdministrativeReferenceService. Vendor person/store names are separately labeled. Additional contacts come from actual vendor_account_profiles phone_numbers and shops.contact; the primary contact is excluded from the disclosure when it matches.

## Access and status

Directory read and status actions require an authenticated administrator with the people module permission. Permanent deletion additionally requires admin_role_id 1. Customers use is_active; vendors map approved to active and suspended to frozen. Pending/rejected vendors require their existing review workflow and cannot be approved through Reactivate.

Customer freeze revokes Passport access tokens, refresh tokens and authorization codes. Authenticate and APIGuest reject frozen customers on existing API sessions; incumbent customer web and seller gates enforce their existing status checks. Freeze clears remember tokens where available and vendor auth_token. Reactivation does not restore revoked tokens.

## Permanent deletion and retention

Deletion requires exact display-reference confirmation and an accepted irreversible-action acknowledgment in the backend. Existing retention is preserved: account balances, financial/order/support/product dependencies, customer referrals, matching contact history, discovered foreign keys and recognized account/subject references can block deletion. The conservative scan may retain an account even when a broader policy could allow removal; that policy review is a separate requested follow-up.

Only accounts without blockers may be physically deleted. Vendor-owned shop/profile rows or customer token rows are removed inside the same database transaction. A deletion failure rolls back those database changes, preventing partial database deletion. This is a database guarantee; it is not a general claim about unrelated external side effects.

## Verification and evidence

The final regression run passed 18 tests and 122 assertions (ten directory tests and eight existing workspace tests), including the foreign-key dependency scan. Coverage includes union search/filter/pagination, real secondary contacts, freeze/revocation/reactivation, retained-history blockers, deletion of empty accounts, backend permissions/confirmation, frozen-session rejection, transaction rollback and table/sidebar structure.

The review reported a ship result within scoped source review and five isolated SQLite-backed captures:

- .impeccable/review/directory-light-desktop.jpg
- .impeccable/review/directory-dark-desktop.jpg
- .impeccable/review/directory-light-mobile.jpg — Arabic fixture in LTR layout
- .impeccable/review/directory-dark-mobile.jpg — RTL layout
- .impeccable/review/directory-delete.jpg

These captures cover light/dark, desktop/mobile table behavior and the destructive-action dialog. The detector view returned an empty finding list once. Evidence does not include an authenticated production browser flow or a full-sidebar capture. Production integration and navigation appearance therefore remain outside this visual evidence. No deployment or production activation is claimed by this document.

A read-only MySQL candidate check confirmed 25 real accounts (16 customers and nine vendors), phone/reference searches for both types, contact extraction and actual retained-dependency categories. No production account was frozen or deleted as a verification step.

The final source review also accepted the retained-FK safeguards, additional-phone search and fullscreen soft-navigation initialization. Customer status-write failure is covered by a rollback/error-feedback test.
