# Customer release activation runbook

Prepared only; not authorization to merge, deploy, run production migrations, or move production photos.

## Before approval

Review the three `codex/page1-4-*-20261104` branches named in the companion review report. Record exact approved commit IDs. Do not deploy an application build against an older backend lacking its routes or migrations.

Production was last observed at `.deploy-current = 5bc2718f4f917e60707e5f06435a340e3bc7f43e`. Confirm again before activation; do not assume this marker proves every production file matches Git.

## After explicit approval

1. Back up the production database, configured environment, OAuth keys, public account photo directories and private storage to a location outside the public document root. Verify the backups can be read. Retain the preceding application release for rollback.
2. Put the existing application into maintenance mode before replacing application files. Preserve production `.env`, storage and uploaded files. Install the exact reviewed release with the existing production PHP 8.3 runtime and locked Composer dependencies.
3. Clear stale configuration/route/view caches. Run `php8.3 artisan migrate --force --no-interaction` while maintenance mode remains enabled. Stop on failure; do not serve the new code against an incomplete schema.
4. Check `php8.3 artisan migrate:status`. The following new migrations must be applied, along with any restored historical migrations that are genuinely pending:
   - `2026_11_04_150000_add_organization_name_to_users`
   - `2026_11_04_151000_add_customer_package_auto_renewal`
   - `2026_11_04_152000_add_customer_order_cancellation_review`
   - `2026_11_04_160000_create_customer_wallet_withdrawals`
   - `2026_11_04_161000_add_seller_withdrawal_review_deadlines`
5. Run `php8.3 artisan theme:sync-assets theme_aster`. Inspect `php8.3 artisan route:list` and `php8.3 scripts/audit-route-actions.php`; no missing controller actions may remain.
6. Run `php8.3 artisan accounts:privatize-photos` to preview. Confirm backups, then run `php8.3 artisan accounts:privatize-photos --apply`. The command verifies copied bytes before retiring each public original. Resolve every unrecognized file or collision rather than assuming a successful partial move completes privacy. Invalidate any external CDN cache of old account photo URLs.
7. Verify `php8.3 artisan schedule:list` contains `renew-expired-customer-packages`, `purge-expired-wallet-deposit-notifications`, `customer-package-refunds:release` and existing seller settlement tasks. Production already has a per-minute `schedule:run` cron; check it still targets the active release. Do not add a second cron.
8. Restart long-running queue workers so they load the reviewed code. Enable the application, then run the owner/admin/other-user photo checks, signup and checkout checks, and test-account financial scenarios in the companion report.

No automatic external transfer is implied by a scheduled wallet refund or approval of a withdrawal. External payouts require administration to transfer funds and record the transfer reference.

## Rollback

Do not roll back financial tables destructively after customers have created requests or ledger entries. Retain the database evidence and choose a compatible application rollback with the reviewer. If reverting to code that expects public photos, restore photo availability from the verified backup before enabling that release; copying only application files will not restore moved originals. Suspend the scheduler/queue during recovery, and restore their previous configuration once the compatible release is verified.
