---
name: SIGMA Admin Account Directory
description: Scoped documentation of the incumbent admin customer and vendor directory.
rounded:
  panel: "14px"
  field: "8px"
  action: "7px"
spacing:
  filter-row-gap: "20px"
  filter-column-gap: "24px"
  desktop-cell: "18px"
  mobile-cell: "16px"
---

# Admin account directory

## Overview

An ordinary extension of the existing administration system: a quiet, readable, full-width directory that makes customer and vendor identity clear before an administrator acts. The Customer List entry appears in the Home navigation below POS. The six-column table keeps profile links, account type, contact information and management actions together.

## Refinement direction contract

THESIS: Make account administration easy to scan, borrowing the existing customer list's useful classification without expanding its six-column structure.

OWN-WORLD: Preserve admin semantic tokens, fonts and rounded bordered surfaces in light and dark themes. Group a quiet filter panel above the table, with person initials and restrained account badges.

STORY: Choose account type/status or dates, search, inspect the matching accounts, and export or print the applied results before taking an account action.

FIRST VIEWPORT: A compact heading, collapsible classification panel, matching count and search/export/print toolbar lead into the actual unified table. Mobile collapses classification first and reserves horizontal scrolling for the table.

SIGNATURE INTERACTION: Native filter disclosure keeps advanced date and order sorting controls available while letting the administrator reclaim table space. Export and print use the same validated backend query as pagination.

BUILD PATH: Code-led refinement of the incumbent account directory and existing customer list; no new visual authority or vendor-system change. Verify actual XLSX data, permission gates, date semantics, Arabic RTL, English LTR and paper formatting.

This contract applies only to the account directory. PRODUCT.md preserves the administration appearance; its later merchant redesign authorization applies to the vendor workspace. Root DESIGN.md and .impeccable/design.json remain vendor-only, and the existing admin-dashboard brief remains unchanged.

## Colors

Use the incumbent semantic admin tokens from public/assets/back-end/css/admin-v2.css and admin-dark-mode.css. Surface, raised surface, foreground, dim foreground, border, strong border, primary and warning-soft roles are consumed directly through --v2-* properties. Business settings can supply the primary color; dark mode remaps these roles. Do not copy the vendor light-only palette into this surface or create a second admin token authority.

The primary marks identifiers, links and search. Type badges use primary-soft for customers and warning-soft for vendors. Destructive actions have explicit light and dark danger treatments. Status has text as well as a small dot, so color alone never communicates account state.

## Typography

Inherit the admin system's installed typography. The table and fields use readable body sizing (13px); labels, column headings and compact actions use smaller sizing (12px). Identity references use tabular numerals and stronger weight. Secondary person/store labels and status use small supporting text (11px). Names, email addresses and telephone numbers must remain readable when long or Arabic.

## Layout

The page fills available content width up to a centered maximum (1500px). A separate native classification disclosure sits above the table. Its controls use three columns, two below 1000px and one below 650px. Paired date and sorting fields remain grouped; the table toolbar combines count, flexible search, Excel and print. The table retains six columns: row number, identifier, name, type, contacts and management. Its minimum width (940px) belongs inside a keyboard-focusable scroll region. At narrow widths, only that region scrolls horizontally; the page itself must fit a 390px viewport.

Below the mobile breakpoint (650px), classification closes during initialization, search and its submit button occupy one row, Excel and print share the next, panel spacing contracts and row-action touch height increases (44px). Logical margins, padding and text alignment support Arabic RTL and supported-language LTR. Use bidi isolation for identifiers and contacts.

## Elevation & Depth

The table panel uses a restrained border and tonal header surface. Row hover uses primary-softer. Reserve the incumbent large shadow for the deletion dialog, whose backdrop dims the surrounding page. There is no decorative hero or additional summary-card layer.

## Shapes

Use gently rounded panel and dialog corners, slightly smaller field corners and compact action/badge corners from the scoped scale above. Preserve the admin system's icon language and visible focus treatment.

## Components

- Classification: type/status, first N, inclusive joining-date and order-date ranges, sorting and direction, Apply and Reset. Sort fields are joining date, name, numeric identifier, status, order count and order amount. First N selects the ordered set before pagination.
- Toolbar and footer: flexible search, Excel and print above the table; page size below it. Search includes references, person/store names, email and primary/additional vendor phones. Output uses applied filters and ordering across all matching records, including first N.
- Identity: circular initials anchor each person and order count appears below the name. Order-date filtering requires a matching order; aggregate count/amount sorting still uses all qualifying account orders.
- Excel: real XLSX with additional contacts and text-bound account values to protect formulas and preserve phone zeroes; query reads are batched.
- Print: standalone light report with count, timestamp, applied classification summary and six report columns. A4 landscape rules repeat table headings and hide print controls on paper.
- Identity: lowercase c/customer and m/vendor display references link to existing profiles. Stored numeric IDs and AdministrativeReferenceService conventions remain unchanged. Vendors show separately labeled person and store names plus an explicit type badge.
- Contacts: primary email/phone remain visible; actual vendor profile phone numbers and shop contacts appear in a native details disclosure after primary-contact deduplication.
- Management: small labeled View, Freeze/Reactivate and eligible Delete controls. Pending/rejected vendors retain review states; reactivation never auto-approves them.
- Feedback: inline status/alert messages, descriptive empty results, result count and pagination. Submission sets a busy state and disables submitting controls; page restoration clears it.
- Delete dialog: named account and display reference, exact identifier entry, irreversible-action acknowledgment and Cancel/Confirm. Native modal behavior, input focus and focus return preserve keyboard continuity. Backend permissions and validation remain authoritative.

## Do's and Don'ts

Keep profile clarity ahead of density; preserve the six-column table, visible action labels and inherited light/dark tokens. Verify Arabic RTL and LTR with long values, empty results, pending vendors and mobile widths. Keep horizontal overflow inside the table region. Never present an identifier as a replacement database ID or imply that every account can be permanently deleted.

Evidence and functional limitations are recorded in docs/admin-account-directory.md. This surface contract does not establish a broader deletion or retention policy.

A read-only MySQL candidate check confirmed 25 real accounts (16 customers and nine vendors), phone/reference searches for both types, contact extraction and actual retained-dependency categories. No production account was frozen or deleted as a verification step.

The final source review also accepted the retained-FK safeguards, additional-phone search and fullscreen soft-navigation initialization. Customer status-write failure is covered by a rollback/error-feedback test.

The fresh refinement review accepted source and five valid fixture captures: Arabic RTL in both themes at 1440px desktop, English LTR/light and Arabic RTL/dark at 390px mobile, plus standalone print. Full production header/sidebar composition and physical-paper pagination were not captured. The single detector warning about Inter in print reflects intentional incumbent-font reuse, not a new typography authority. Release verification is separate from this visual evidence; freeze/delete safeguards are unchanged.
