# Admin workspace integration — 10 October 2026

## Follow-up refinement

The subsequent reference-image request adds a fifth website visits card. Its total
is the sum of `platform_daily_metrics.page_views` for channel `web`, permissioned
by `reports`; it represents recorded page views rather than unique visitors.
Cards now follow the supplied colored icon/corner treatment. Rich notification
previews include product thumbnails/vendor names, order customer/items/amount,
payment amount/status and support sender/text/unread state. Server rendering and
silent refresh use the same item payload. Popovers clamp to viewport edges and
the remaining height, with the full mobile header kept clear. Added regression
coverage distinguishes web/app counts and verifies RTL/LTR popup positioning.

The dashboard owns a full-width, centered container inside the v2 main grid,
without the legacy `.content` class. It follows the available space when the
sidebar opens or collapses. Five equal cards use grouped icon/label rows and
aligned totals. Periodic 30-second refresh is removed; data refreshes on navigation
or return to the page. Browser checks confirmed zero center offset,
equal card widths and no mobile horizontal overflow. Deployment packages only
this admin feature; unrelated local route and language edits are excluded.

The existing admin-v2 interface now provides a concise dashboard, inline global
search, category review previews and a unified Communication Center. This extends
the admin shell and keeps its branding, sidebar, configured colors, local assets,
language switching and persistent appearance controls. Root `DESIGN.md` and
`.impeccable/design.json` remain vendor-specific and unchanged. The admin surface
contract is `.impeccable/surfaces/admin-dashboard.md`.

## Changed integration

| Area | Source and behavior |
| --- | --- |
| Dashboard | `DashboardController::index` delegates to `AdminWorkspaceService`; `system/dashboard.blade.php` renders four totals and the activation partial. |
| Header | `layouts/admin/partials/v2/_header.blade.php` uses the same service snapshot for payments, orders, products and messages. Previews link to complete review lists. |
| Styling | `layouts/admin/app.blade.php` loads `admin-workspace.css` after the existing v2 and dark stylesheets. New rules reuse admin tokens and logical spacing. |
| Interaction | `_workspace-script.blade.php` supplies translated configuration; `admin-workspace.js` handles search and refresh. `admin-v2.js` synchronizes dropdown expanded state and Escape closure. |
| Communication | `AdminWorkspaceController` and `system/communication.blade.php` paginate the service's SQL union, preserving links to contact, ticket, vendor support and customer-chat pages. |
| Record navigation | Customer-chat deep links select the requested customer. Payment URLs identify their exact source records; existing review/deposit/report controllers retain their management behavior. |

The dashboard no longer renders its previous chart, leaderboards and operational
shortcut sections, and the v2 header no longer uses its modal search/operational-feed
polling block. The underlying management pages and routes were not removed. The
new dashboard links still open orders, customer/vendor lists, products and activation
review. Only permitted totals are returned; restricted modules render locked cards.
Dashboard totals count all records, whereas header badges count actionable review
records. Walking customers are excluded from customer totals.

## Read models and permissions

`AdminWorkspaceService` is the shared authority for initial header rendering and
`GET /admin/workspace/snapshot`. Pending orders use `pending_admin_review` when the
commerce-flow column exists, with legacy `order_status=pending` fallback for null
flow values. Pending products are vendor products with `request_status=0`.
Payment previews/counts merge pending offline orders, vendor deposits/packages and
insurances, customer deposits, post-purchase invoices and customer order insurance;
each source respects its module permission and its own pending status. Rejected
offline orders are excluded. Review pages paginate 25 records; header previews cap
at six.

Search is `GET /admin/workspace/search?q=...` (2–100 characters). It returns bounded,
grouped records and permission-filtered navigation pages. It supports names/full
names, Arabic digits, normalized Egyptian phone formats, account/deposit references,
product identifiers/translations, order/payment references and conversation bodies.
Only GET navigation entries are included. People, catalog, orders and reports
permissions gate their queries/results. New workspace routes remain inside the
existing admin authentication/access group; communication/read routes also require
the people module. Search and snapshot responses use `private, no-store`.
Separate admin-scoped limiters allow 90 search and 30 snapshot requests per minute.

Communication Center and header messages share `conversations()` and `sum(unread)`:

| Channel | Unread meaning | Open/read behavior |
| --- | --- | --- |
| Contact | One for `seen=0`; requester reply resets the existing seen state. | Existing contact page; explicit center mark-read updates `seen`. |
| Customer support ticket | One when never read or a customer message has an ID beyond the stored read cursor. Account-activation tickets are excluded. | Existing support-ticket page; explicit center mark-read records timestamp and latest conversation ID. |
| Vendor support | Zero; no persistent unread marker exists for this channel. | Existing vendor-support thread. |
| Customer chat | Number of incoming messages with `seen_by_admin=0`, grouped by customer and scoped to `admin_id=0`. | Redirect selects the exact customer in the existing admin chat page. |

The badge is therefore a shared aggregate of channel-specific unread units, rather
than a uniform count of conversations or every unseen message in all channels.
Contact audience uses stored sender type when available, with legacy fallback.
Read actions require POST/CSRF and validate supported kinds and existing records.

## Migration and rollout

Apply `database/migrations/2026_10_10_120000_add_admin_read_at_to_support_tickets.php`
before the changed header is served. It adds nullable `support_tickets.admin_read_at`,
`support_tickets.admin_read_message_id` and `contacts.sender_type`. Existing tickets
begin unread until explicitly marked read. Existing contact rows retain legacy
classification fallback until their sender type is recorded. The migration's down
path removes all three fields; do not run it while the new header/service is active.
These columns are required by the shared conversation query, not only by the center.

Deploy the Blade, PHP and asset changes together with the migration. Clear/rebuild
the deployment's Laravel view/route/config caches using its established procedure.
The CSS/JS references carry the `20261010.1` cache version. The loopback QA router
and generated SQLite fixture HTML/JSON are development evidence and are not a
production authentication or data endpoint.

## Visual system comparison

The source preserves admin `--v2-*` colors, surfaces, borders, warning states and
shadows. Light defaults include blue primary (`#1F4FD6`) on white; dark defaults
include lighter blue (`#6E9BFA`) on navy (`#1A2236`) surfaces. Business-setting
overrides and incumbent dark-mode rules can change resolved colors. No vendor
light-only palette is substituted. Typography inherits the existing admin font
stack/assets; regular rounded icons remain in use.

The new panels are flat with 14px corners; search results alone reuse the existing
large popover shadow. The observed title/total/body sizes are 26/38/14px, with 32px
totals on small screens. Four summary columns become two at 900px. Below 560px,
the header has logo/navigation, utilities and search rows; logical properties and
directional arrows support RTL/LTR. Visible focus, 44px utility targets and reduced
motion treatment are present in source. These observations document the admin
surface rather than broadening the vendor design authority.

Pre-existing drift is left untouched: `admin-v2.css` contains hardcoded light colors
and repeated explicit font stacks in older component rules despite its tokenized
shell. Their presence does not establish new rules for future admin surfaces. The
root vendor system and the admin palette differ by intended scope; this extension
does not repair or unify them.

## Validation evidence and remaining production checks

Local MySQL was unavailable and the unauthenticated live dashboard redirected to
login. Functional tests exercise the real Laravel service/controller against isolated
SQLite fixtures. Those tests render real Blade output and export HTML/JSON for
browser QA; `tools/admin-workspace-qa-router.php` replays those fixture exports and
serves repository assets. This verifies implementation rendering/interactions but
does not prove MySQL query behavior, live permissions or authenticated navigation.

The final reported functional result is 23 tests and 134 assertions, plus two passing
theme tests. Added regressions cover full names, references/wallet matching,
page-search permissions and separate admin-scoped throttle limits. The finish
reviewer accepted four valid desktop/mobile light/dark RTL/LTR captures saved under
`.impeccable/review/` and returned `ship` within the isolated-fixture and source-review
scope. All four material source findings were resolved, with none remaining in that
reviewed scope.

That disposition has explicit limits: real company branding and the full sidebar
were omitted from the fixture; English copy and populated dropdown/search states
were not independently captured by the reviewer. MySQL behavior and authenticated
production remain unverified. The review does not approve deployment.

Before production acceptance:

- Confirm the migration is applied and all required communication tables/columns
  exist; run the shared snapshot/search queries on the production MySQL version.
- Sign in as full and restricted admins; verify module-scoped counts, denied results,
  activation feature enabled/disabled states and all exact-record destination links.
- Compare each pending payment source, order/product review count and mixed-channel
  unread aggregate with its management page. Mark contact/ticket read, send a new
  requester/customer reply and confirm the shared badge/center returns to unread.
- Check stored and blocked-storage theme behavior, reload persistence, language
  switching, keyboard search/dropdowns, empty/error states, throttling and refresh
  recovery with real authenticated responses.
- Recapture desktop/tablet/mobile in Arabic RTL and supported-language LTR for light
  and dark themes. Review logo visibility, sidebar/header geometry, long names,
  large counts, wrapping, focus and target sizes against the final build.
- Record authenticated production verification and the missing branding/sidebar,
  English-copy and populated interaction captures before production acceptance.

Relative notification timestamps now use ISO 8601 offsets for payment/message
query rows. A server-seeded monotonic browser clock recalculates visible time text
each second without network polling; absolute localized date/time is available
on hover. Tests cover seconds/minutes/hours, equivalent UTC offsets and Cairo data.
