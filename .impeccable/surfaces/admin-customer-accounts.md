---
name: SIGMA Admin Customer Accounts
description: Scoped incumbent admin customer account and payment workflows.
rounded:
  panel: "14px"
  dialog: "16px"
  icon-action: "8px"
spacing:
  panel: "20px"
  compact-panel: "16px"
---

# Admin customer accounts

## Overview

An ordinary extension of the incumbent Sigma administration appearance for customer accounts, payments, debts and subscriptions. Preserve the administration shell and existing light/dark system. PRODUCT.md remains product authority; root DESIGN.md and .impeccable/design.json describe the vendor workspace and must remain unchanged by this surface.

The account directory surface remains the related reference, not a replacement for this customer-only workspace. Opening an account action keeps the directory visible behind a centered dialog. Full profile uses the existing customer profile in an iframe rather than a new profile implementation.

## Colors

Consume incumbent --v2-surface, --v2-surface-2, --v2-fg, --v2-fg-dim, --v2-border, --v2-border-strong and --v2-primary roles, with their soft primary and semantic success, warning and danger variants. Their authoritative definitions and theme remapping remain in admin-v2.css and admin-dark-mode.css. Do not establish a separate admin palette or import the vendor light-only theme.

## Typography

Inherit installed admin typography. Body is 14px with 1.7 line height; table values are 13px, headings 28px (24px below 600px), section headings 18px and table labels 12px. Use tabular numerals and bidi isolation for financial amounts, account references and contacts. Long names and email wrap without breaking reference readability.

## Layout

The content fills available width up to 1560px. Panels use the scoped spacing above; search controls wrap and become full-width below 600px. Summary values use four columns, two below 900px. Tables scroll within focusable regions; the customer table has an 1140px minimum width, sticky header and sticky reference column. Keep page overflow separate from table scrolling.

Dialogs are centered with automatic margins, a 760px maximum width, 32px total viewport clearance and 90dvh maximum height. Full profile expands to 1240px maximum width and 90dvh height. The open profile modal uses a bounded flex column with a non-shrinking header and a flexible iframe with min-height:0; the outer profile modal hides overflow. The iframe document owns profile scrolling. On same-origin load, embedded html/body height and overflow constraints are released, the app shell becomes automatic-height with visible overflow, and main content has automatic height and visible overflow. Embedded header and navigation chrome are hidden. The existing profile supplies its content and permissions.

## Elevation & Depth

Quiet borders and tonal table headers establish the resting hierarchy. Dialog backdrops dim with rgb(5 12 25 / .55) and blur(7px). Existing 200ms entry and 130ms closing motion remains; reduced-motion users bypass animation and transitions. This is a centered modal despite retained drawer animation names in the stylesheet.

## Shapes

Use gently rounded panels and dialogs from frontmatter, restrained field borders and the incumbent icon language. Icon actions and form controls have a minimum 44px target. Preserve the visible primary focus outline with 3px offset.

## Components

- Customer list: customer references, names, contacts, package and account state remain clear before management actions. This surface and autocomplete query customers only; vendors belong to their existing workspace.
- Remote actions: same-origin action markup loads into native dialogs without top-level navigation. Loading failures provide an explicit dialog. Escape/close restores focus to the opener. Full profile reuses the existing profile route in a titled iframe. Its close control uses the incumbent fi-rr-cross-small icon, a 44px target, Arabic accessible name and title; the icon is hidden from assistive technology. Keep the close header visible while long profile content scrolls within the iframe.
- Customer lookup: search by name, reference or phone after two characters, debounced 250ms; return at most 12 customer matches. Selection sets the hidden customer and confirmation reference. Changing the search clears that selection. Empty and failed searches have explicit feedback.
- Payments: navigation label is مدفوعات. Active debts, settled debts and archived history are separate views; settled debts can move to history while retaining their record. Global payment views work without choosing a customer first. Subscription views retain their existing financial boundaries.
- Account actions: suspend/reactivate, password assignment and eligible closure retain operation-specific permission checks and reference confirmation. Account-list reasons can be optional; financial reasons remain required. Password recovery is removed only from the customer navigation grouping.
- Financial tools: distinguish confirmed payment, administrative adjustment and approved return credit. Confirmed payment allocates to oldest outstanding debt first and credits only the remainder to the wallet. Adjustment and return credit bypass debt recovery. Verified approval, reason and account confirmation remain required; confirmed payment/return also require an approval reference. Reusing that reference within its operation kind resolves idempotently for the same signature and rejects different operation details.
- Permissions: people module access gates the workspace; financial_view gates non-list pages. CustomerAccountAccess grants role 1 or the relevant customer_* permission. Wallet adjustment, debt management, refund management, password and account management/closure remain separate server-enforced actions. UI visibility never replaces authorization.
- History: retain source, administrator, reason and financial reference. Allocation details show gross, recovered debt, wallet net and remaining debt. Existing records stay in their original ledgers.

## Do's and Don'ts

Do preserve Arabic RTL, supported LTR layout, light/dark semantic roles, focus return, customer-only lookup and authoritative backend validation. Do keep financial choices explicit and distinguish pending requests from confirmed funds. Do preserve retired administrative activation as removed; phone OTP remains independent as configured.

Don't turn the full profile into a parallel data implementation, infer approval from opening a dialog, apply these changes to vendors, or rewrite the root vendor design artifacts. Do not use synthetic fixture captures as evidence of a deployed production shell.

Verification and evidence limits: E:/project/deliverables/customer-account-dialogs-20261107/design-verification.md. Profile scrolling follow-up: E:/project/deliverables/sigma-site-reconcile-20261107/design-verification.md.

## Customer profile navigation and retained history extension — 7 November 2026

This ordinary extension keeps the incumbent customer workspace and vendor-only design authority unchanged. A sticky profile navigation provides four labeled links: إدارة الحساب, المحفظة والشحن, الديون and الاشتراكات. It persists across the existing profile, customer account sections and customer-scoped payment review, including navigation inside the same profile iframe. Financial links retain financial_view gating. Search, reset and review links preserve the selected customer rather than silently returning to a global view.

Subscription administration exposes four distinct intentions: activation, package renewal, upgrade and downgrade, plus purchasing-limit renewal. Direct buttons select an explicit intent carried to the backend, validated against the operation and enforced under the existing transaction lock. The signature and recorded history retain that selected intent. Existing full-price, 30-day package rules remain in force; self-service request signatures remain compatible. Purchasing allowance is displayed separately from money.

History is a read-only SQL union of retained original records, with filtering and SQL pagination over the preserved history: actual account registration, administrative actions, profile changes, wallet movements, subscription payments and subscriptions, funding requests, debt creation/repayment/settlement/archive, orders, refunds, legacy receipts and purchasing-allowance transactions. It does not fabricate earlier history, migrate ledger data or invent unknown actors. Preserve provenance, statuses, available references and separate allowance fields when money is unavailable or inapplicable.

Customer-scoped payment review validates customer_id and forces the customer audience before counts, filtering and pagination. It narrows the existing queue, retaining profile navigation. Additional old wallet/order records are read-only history, without approval controls. The filter submit control uses the incumbent btn-primary treatment following contrast review.

Extension evidence and review limits: E:/project/deliverables/customer-history-20261107/design-verification.md. This extension records implemented behavior; it does not certify deployment or convert synthetic captures into real-profile evidence.

Final extension review accepted bounded shipment after filter, count/status contrast and explicit-operation-button corrections. Supplementary 390px-wide status captures use 1000px height to show the full status treatment. History also reads retained customer_purchase_package_subscriptions, customer_wallet_withdrawals and customer_insurance_ledger_entries per customer; these are read-only records and do not restore insurance operations. Scoped payment review includes legacy subscriptions as read-only history. Purchasing-allowance events carry a null money amount and distinct quantity fields. See the extension verification note for final evidence counts and deployment limits.
