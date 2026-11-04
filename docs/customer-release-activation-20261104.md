# Customer release activation runbook — prepared, not executed

No merge, push to main, production deployment, production migration, or public-photo removal is authorized by this document. Stop until the exact review commits are approved. Photo removal needs a separate explicit approval after the backup has been checked.

## Recorded production evidence

Read-only `php8.3 artisan migrate:status --no-ansi` was captured on 2026-11-04 in `docs/qa/production-migrate-status-20261104.txt`. The seven restored October migrations are already recorded:

| Migration | Batch | State |
|---|---:|---|
| 2026_10_15_000001_create_vendor_extra_ad_purchases | 186 | Ran |
| 2026_10_15_000002_create_vendor_funding_intents | 187 | Ran |
| 2026_10_16_000001_create_activation_conversation_reads | 188 | Ran |
| 2026_10_17_220000_remove_vendor_withdrawal_destination_approval | 189 | Ran |
| 2026_10_18_030000_add_vendor_slot_reservations | 191 | Ran |
| 2026_10_23_030000_create_order_financial_events | 194 | Ran |
| 2026_10_25_000001_create_company_medical_devices | 195 | Ran |

The fourth migration updates payout destination approval data. Do not replay it, edit its migration ledger entry, roll it back, or use `migrate:fresh` / `migrate:refresh`. If any historical migration is missing from the ledger, stop for separate schema/data review and approval. A `hasTable` check alone would not make replaying its data changes safe.

## 1. Approval and backup (operator only, after approval)

The following commands are a runbook, not a command executed by this review. Set `RELEASE` to the unpacked, exact approved release outside the live directory. Keep production environment and uploads in the existing deployment process; never copy a QA `.env` or OAuth keys into production.

```bash
set -euo pipefail
: "${APPROVED_SITE_COMMIT:?Set the exact owner-approved site commit hash first}"
SITE=/home/sigma-eg/htdocs/sigma-eg.net
RELEASE=/home/sigma-eg/git/sigma-approved-release-$APPROVED_SITE_COMMIT
BACKUP=/home/sigma-eg/backups/customer-release-$(date -u +%Y%m%dT%H%M%SZ)
test ! -e "$RELEASE"
install -d -m 700 "$RELEASE"
git --git-dir=/home/sigma-eg/git/sigma-site.git archive "$APPROVED_SITE_COMMIT" | tar -xf - -C "$RELEASE"
install -d -m 700 /home/sigma-eg/backups
cd "$SITE"
php8.3 artisan migrate:status --no-ansi
SIGMA_APP_ROOT="$SITE" php8.3 "$RELEASE/scripts/release-migration-preflight.php"
php8.3 artisan down --retry=60
# The reviewed backup utility loads the production environment from SITE.
SIGMA_APP_ROOT="$SITE" php8.3 "$RELEASE/scripts/backup-customer-release.php" "$BACKUP"
(cd "$BACKUP" && sha256sum -c SHA256SUMS)
cp .deploy-current "$BACKUP/deploy-current.txt"
```

The backup utility writes a database dump and a private archive of `.env` and all local storage, including OAuth keys and account photos. It follows storage symlinks, checks that the archive is readable, and records SHA-256 hashes. It refuses a destination inside the application. It has been syntax-checked, not run against production. Verify an isolated database restore and image readability before continuing. If storage uses S3, obtain and verify a separate bucket backup/versioned snapshot before authorizing photo deletion; this utility backs up local storage only. Keep all backups outside the public document root, access-restricted.

Do not switch the application release if the full test suite is failing without an explicitly reviewed disposition of each failure.

## 2. Install the approved release, keep maintenance enabled

Install only the extracted approved release, preserving the production `.env`, storage, dependencies until Composer runs, and OAuth keys. The branch push hook must not be used to deploy an unreviewed main revision. Maintenance remains enabled during the copy. The following overlay does not delete files; review any required removal separately rather than using an unrestricted recursive delete or `rsync --delete` against uploads.

```bash
cd "$SITE"
tar -C "$RELEASE" --exclude='./.env' --exclude='./storage' --exclude='./vendor' --exclude='./.git' --exclude='./public/storage' -cf - . | tar -C "$SITE" -xf -
php8.3 /usr/local/bin/composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php8.3 artisan optimize:clear
php8.3 scripts/release-migration-preflight.php
php8.3 artisan migrate:status --no-ansi
```

Stop if the migration status contains an unexpected pending historical migration. The restored seven must remain `Ran`. Do not run a blanket migration against an unaudited pending list.

## 3. Apply only the five reviewed new migrations

```bash
cd "$SITE"
for migration in \
  2026_11_04_150000_add_organization_name_to_users \
  2026_11_04_151000_add_customer_package_auto_renewal \
  2026_11_04_152000_add_customer_order_cancellation_review \
  2026_11_04_160000_create_customer_wallet_withdrawals \
  2026_11_04_161000_add_seller_withdrawal_review_deadlines
do
  php8.3 artisan migrate --force --no-interaction --path="database/migrations/$migration.php"
done
php8.3 artisan migrate:status --no-ansi
php8.3 artisan theme:sync-assets theme_aster
php8.3 artisan theme:sync-assets theme_sigma_care
php8.3 artisan route:list --json
php8.3 scripts/audit-route-actions.php
php8.3 scripts/lint-blade-views.php
```

Both themes must be synchronized. `view:cache` alone does not PHP-lint compiled templates. The lint utility compiles and PHP-lints every project Blade template, including modules and all themes. Stop on any failed command.

## 4. Photos — preview, then STOP for separate approval

```bash
cd "$SITE"
php8.3 artisan accounts:privatize-photos
```

Show the preview count, backup directory, verified hashes and restore-check results to the owner. Wait for explicit approval to remove the public originals. Only after that separate approval:

```bash
cd "$SITE"
php8.3 artisan accounts:privatize-photos --apply
php8.3 artisan accounts:privatize-photos
```

Resolve all unrecognized paths or hash collisions; do not count a partial move as complete privacy. Verify owner and administrator access, rejection of another customer/merchant, and 404 for retired public URLs. Invalidate CDN copies of retired account-photo URLs if used.

## 5. Scheduler and workers

```bash
cd "$SITE"
php8.3 artisan schedule:list
crontab -l
php8.3 artisan queue:restart
```

Check these registered tasks: `renew-expired-customer-packages`, `purge-expired-wallet-deposit-notifications`, `customer-package-refunds:release`, and existing seller settlement tasks. Production was observed with the following cron; keep exactly one entry targeting the active release:

```cron
* * * * * cd /home/sigma-eg/htdocs/sigma-eg.net && /usr/bin/php8.3 artisan schedule:run >/dev/null 2>&1
```

Do not run `schedule:run` as a harmless diagnostic: it can debit opted-in renewals and release approved wallet refunds. Do not trigger due production financial jobs during this review.

## 6. Enable and verify

After the prior checks, migrations, photo approval and worker checks have passed:

```bash
cd "$SITE"
php8.3 artisan up
printf '%s\n' "$APPROVED_SITE_COMMIT" > .deploy-current
```

Run the approved customer scenarios with test accounts: quick and ordinary signup; leading-zero typing on all four screens; normal/mobile Settings and support navigation; guest-orders review; first-payment then package; exhausted/expired credit blocking only new checkout; retained paid/preparing orders; cancellation requests; wallet notifications; withdrawal fee snapshots; merchant operating refunds and sales payouts. Compare balances and ownership before/after. Record failures; do not claim an automatic bank transfer, as administration executes external transfers and records their references.

## Rollback

Keep the preceding application release and verified backups. Do not destructively roll back financial tables after new requests or ledger entries exist. Use a compatible application rollback reviewed against the current schema. If reverting to code that expects public photos, restore photos from the verified backup before enabling that code. Coordinate scheduler/queue suspension during recovery and restore their previous configuration after verification. Do not replay historical data migrations as a recovery action.
