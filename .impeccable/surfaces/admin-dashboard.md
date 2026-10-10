# Admin dashboard

Operate surface approved 10 October 2026. This brief applies to the admin header,
dashboard and Communication Center only. The vendor light-only brief does not apply.

Preserve the existing v2 navigation, branding, locally served typography, regular
rounded icon library and semantic color tokens. Use four flat, prominent summary
cards followed by a concise activation list. No banner, chart or operational
shortcut section. Use v2 theme tokens for both light and dark, logical spacing for
Arabic RTL and supported-language LTR. Search is a wide inline combobox with grouped
results, permission-scoped database queries and keyboard support. Header utilities
are payments, orders, product review, messages, language and appearance, plus account
and essential navigation. Totals and pending-review badges are different read models.

## Implemented surface contract

This is an extension of the incumbent admin-v2 system, not a replacement identity.
`DESIGN.md` and `.impeccable/design.json` describe SIGMA Vendor Workspace and remain
unchanged; their light-only vendor palette is not an admin token authority.
Admin styling comes from `public/assets/back-end/css/admin-v2.css` and
`admin-dark-mode.css`, with scoped additions in `admin-workspace.css`.

The dashboard contains four database-backed totals (orders, customers, vendors,
products) and a maximum of six current activation cases. Permission-denied totals
show a restricted state. Walking-customer records are excluded from customer totals.
Activation has enabled/disabled and empty states. Removing dashboard charts and
operational shortcuts does not remove their underlying management pages.

The header preserves the v2 rail/sidebar, configured logo, account menu, language
switcher and theme controls. Its four category previews show up to six rows and
link to complete review/communication pages. Counts above 99 display `99+` while
accessible labels retain the full count. Appearance preference uses the existing
`ba_admin_theme` storage key and survives reload when browser storage is available.

## Existing tokens and observed geometry

Reuse `--v2-fg`, `--v2-fg-dim`, `--v2-surface`, `--v2-surface-2`, `--v2-border`,
`--v2-border-strong`, `--v2-primary`, its soft/softer variants, and `--v2-warn-soft`.
The admin light defaults use white surfaces and blue primary; dark overrides use
navy surfaces and lighter blue. Business-setting overrides remain authoritative.
The inherited `--v2-font` stack and locally supplied font/icon assets remain in use.
Regular rounded `fi-rr-*` icons identify categories; no new icon system is introduced.

Observed type sizes are 26px page title, 20px activation title, 38px totals (32px
on small screens), 14px supporting copy, and 12–13px row metadata/actions. Flat
summary/activation panels use 14px corners, 24px desktop padding, and an 18px grid
gap. Search uses 10px corners; its results use 12px corners and `--v2-shadow-lg`.
These are surface observations, not additions to the vendor design system.

The content caps at 1440px. Four summary columns become two at 900px. The header
places search on a second row at 900px and uses three rows with 164px header height
at 560px; its logo remains visible. Logical inline spacing supports RTL/LTR, and
directional review arrows reverse for LTR. Utility targets are 44px. Summary cards
have restrained 160ms color/border transitions, disabled for reduced motion.

## Interaction and data authority

`AdminWorkspaceService` owns totals, review categories and the SQL conversation
projection used by both header messages and Communication Center. Search queries
real records, with permission filtering before each module query. The combobox has
Ctrl/Cmd+K focus, arrow selection, Enter navigation, Escape dismissal, loading,
empty/error states, 300ms debounce and cancellation of stale requests. Polling uses
the real snapshot endpoint every 30 seconds while visible and retains last values
on failure. Preview replacement preserves keyboard focus in a preview being used.

The migration `2026_10_10_120000_add_admin_read_at_to_support_tickets.php` adds
ticket read timestamps/message cursors and contact sender classification. Apply it
before serving the changed header; it queries these columns on every v2 page.
Unread is the same summed projection in the header and center: one per unseen
contact/ticket, plus unseen incoming chat messages. Vendor support threads currently
contribute zero because that channel has no persisted unread marker. This limitation
must not be represented as complete per-message unread tracking across all channels.

## Evidence and release status

Source comparison confirms that the previous chart/operational-feed dashboard and
modal header search were replaced by the concise dashboard and inline search.
Underlying management routes remain present. Existing root vendor documents were
compared with admin tokens and intentionally preserved. Pre-existing hardcoded light
colors and type declarations elsewhere in `admin-v2.css` are not reconciled or
canonized by this extension.

Local MySQL is unavailable and the live admin dashboard redirects to login. Browser
QA therefore uses actual Blade/service output produced from isolated SQLite test
fixtures, served by the loopback QA router; its JSON responses replay fixture output.
This is comparison evidence for rendering and interaction, not authenticated MySQL
production verification. The finish reviewer accepted four valid desktop/mobile
light/dark RTL/LTR captures in `.impeccable/review/` and returned `ship` within the
isolated-fixture and source-review scope. All four material source findings were
resolved, with none remaining in that reviewed scope. The fixture omits real company
branding and the full sidebar; the reviewer did not independently capture English
copy or populated dropdown/search states. Authenticated MySQL production remains
unverified, and this disposition does not approve deployment. See
`docs/admin-workspace-redesign.md` for migration, validation and production checks.
