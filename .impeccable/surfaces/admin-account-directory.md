---
name: SIGMA Admin Account Directory
description: Scoped documentation of the incumbent admin customer and vendor directory.
rounded:
  panel: "14px"
  field: "8px"
  action: "7px"
spacing:
  filter-gap: "14px"
  desktop-cell: "18px"
  mobile-cell: "16px"
---

# Admin account directory

## Overview

An ordinary extension of the existing administration system: a quiet, readable, full-width directory that makes customer and vendor identity clear before an administrator acts. The Customer List entry appears in the Home navigation below POS. The six-column table keeps profile links, account type, contact information and management actions together.

This contract applies only to the account directory. PRODUCT.md preserves the administration appearance; its later merchant redesign authorization applies to the vendor workspace. Root DESIGN.md and .impeccable/design.json remain vendor-only, and the existing admin-dashboard brief remains unchanged.

## Colors

Use the incumbent semantic admin tokens from public/assets/back-end/css/admin-v2.css and admin-dark-mode.css. Surface, raised surface, foreground, dim foreground, border, strong border, primary and warning-soft roles are consumed directly through --v2-* properties. Business settings can supply the primary color; dark mode remaps these roles. Do not copy the vendor light-only palette into this surface or create a second admin token authority.

The primary marks identifiers, links and search. Type badges use primary-soft for customers and warning-soft for vendors. Destructive actions have explicit light and dark danger treatments. Status has text as well as a small dot, so color alone never communicates account state.

## Typography

Inherit the admin system's installed typography. The table and fields use readable body sizing (13px); labels, column headings and compact actions use smaller sizing (12px). Identity references use tabular numerals and stronger weight. Secondary person/store labels and status use small supporting text (11px). Names, email addresses and telephone numbers must remain readable when long or Arabic.

## Layout

The page fills available content width up to a centered maximum (1500px). Filters wrap above the table; the search field receives flexible space. The table retains six columns: row number, identifier, name, type, contacts and management. Its minimum width (940px) belongs inside a keyboard-focusable scroll region. At narrow widths, only that region scrolls horizontally; the page itself must fit a 390px viewport.

Below the mobile breakpoint (650px), search takes a full row, filters wrap, panel spacing contracts and row-action touch height increases (44px). Logical margins, padding and text alignment support Arabic RTL and supported-language LTR. Use bidi isolation for identifiers and contacts.

## Elevation & Depth

The table panel uses a restrained border and tonal header surface. Row hover uses primary-softer. Reserve the incumbent large shadow for the deletion dialog, whose backdrop dims the surrounding page. There is no decorative hero or additional summary-card layer.

## Shapes

Use gently rounded panel and dialog corners, slightly smaller field corners and compact action/badge corners from the scoped scale above. Preserve the admin system's icon language and visible focus treatment.

## Components

- Filters: labeled search, type, status and page-size controls, Search and conditional Reset. Search accepts display references, names, store names, email and primary telephone data.
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
