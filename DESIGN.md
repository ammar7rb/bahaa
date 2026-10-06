---
name: SIGMA Vendor Workspace
description: Light Arabic vendor operations workspace inheriting Sigma Care's visual identity.
colors:
  primary: "#1a428a"
  primary-dark: "#14356f"
  primary-light: "#edf2fb"
  ink: "#172942"
  muted: "#52627a"
  page: "#f4f7fb"
  surface: "#fff"
  row-unread: "#f8faff"
  border: "#dce4f0"
  divider: "#e1e8f2"
  count-alert: "#c62828"
  count-success: "#11875d"
typography:
  display:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "30px"
    fontWeight: 750
    lineHeight: 1.4
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "28px"
    fontWeight: 750
    lineHeight: 1.4
  title:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "16px"
    fontWeight: 700
  body:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "14px"
    lineHeight: 1.8
  navigation:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "13px"
    lineHeight: 1.7
  label:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontSize: "12px"
  button:
    fontFamily: "VendorCare, Cairo, sans-serif"
    fontWeight: 650
rounded:
  field: "10px"
  control: "12px"
  card: "16px"
  count: "6px"
spacing:
  micro: "4px"
  compact: "8px"
  control: "12px"
  mobile: "16px"
  row: "20px"
  panel: "24px"
  workspace: "28px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
  button-primary-hover:
    backgroundColor: "{colors.primary-dark}"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.control}"
  button-outline-hover:
    backgroundColor: "{colors.primary-light}"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    height: "44px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
    padding: "{spacing.panel}"
  navigation-active:
    backgroundColor: "{colors.primary-light}"
    textColor: "{colors.primary}"
    rounded: "{rounded.field}"
    height: "44px"
  count:
    backgroundColor: "{colors.count-alert}"
    textColor: "{colors.surface}"
    rounded: "{rounded.count}"
---

# Design System: SIGMA Vendor Workspace

## Overview

**Creative North Star: "Sigma Care"**

The vendor workspace extends the established Sigma Care identity into daily operations: cool paper, white containment, medical blue actions, and readable Arabic typography. The shell uses one labeled sidebar and a quiet header so business data and the next action carry the hierarchy.

This root document applies only to the vendor workspace. Source authority is `public/assets/back-end/css/vendor-workspace.css`, loaded after the inherited vendor shell and `vendor-account.css`. The user approved this surface change on 7 October 2026 in PRODUCT.md. The storefront retains its separate authority at `resources/themes/theme_sigma_care/DESIGN.md`; that file's earlier merchant-preservation statements describe its original storefront scope. This document does not redesign the administration shell, Default, or Aster.

**Key Characteristics:**
- Fixed light appearance inherited from Sigma Care.
- Locally served Arabic typography and logical RTL alignment.
- A single labeled sidebar with a mobile drawer.
- Flat cards, pale active rows, and elevated previews.
- Visible focus and touch controls of at least 44px.

## Colors

The frontmatter is normative: medical blue expresses actions and selection while cool neutrals organize dense operational content.

### Primary

- **Medical Blue** (`primary`): primary actions, active navigation, links, and keyboard focus.
- **Deep Medical Blue** (`primary-dark`): primary action hover.
- **Pale Medical Blue** (`primary-light`): selected navigation, preview icons, and outlined-control hover.

### Neutral

- **Blue Ink** (`ink`): headings, message content, and main values.
- **Slate Text** (`muted`): supporting copy, timestamps, inactive navigation, and metadata.
- **Cool Paper** (`page`): workspace background and interactive row hover.
- **White Surface** (`surface`): header, sidebar, cards, inputs, and previews.
- **Unread Paper** (`row-unread`): unread rows and table-heading surfaces.
- **Panel Border** (`border`): structural containment and input edges.
- **Quiet Divider** (`divider`): internal preview, thread, and conversation boundaries.

Counts retain semantic alert/success colors. Status badges use the shipped danger, success, warning, secondary, and information treatments; these are factual feedback, not extra branding accents.

**The Care Palette Rule.** Keep this workspace light and use the inherited fixed Care palette; preserve semantic operational status colors.

## Typography

**Display and Body Font:** local Vazirmatn, registered as `VendorCare`, with Cairo and sans-serif fallbacks. The variable face covers weights 100–900 and uses `font-display: swap`; its asset is `public/assets/back-end/fonts/vendor-care.ttf`.

### Hierarchy

- **Display:** dashboard greeting; reduces to 26px on compact screens.
- **Headline:** inbox page titles; reduces to 25px on compact screens. Conversation titles use 26px, reducing to 22px.
- **Title:** thread subjects and concise section labels. Overview section headings use 20px and weight 700.
- **Body:** thread descriptions and supporting page copy; mobile row copy reduces to 13px. Page introductions cap at 65ch; message paragraphs at 70ch.
- **Navigation / label:** compact navigation, timestamps, and supporting metadata. Keep Arabic text naturally cased and preserve logical alignment.
- **Numbers:** statistics and tables use tabular numerals; preserve real currency and date formatting.

## Layout

Desktop uses a single sidebar (256px), header (76px), and flexible main column. Main padding follows the workspace spacing token, and ordinary content caps at 1440px. Inbox content caps at 1080px; conversations cap at 900px. Cards use panel padding, reducing to 18px on compact screens.

At or below 1200px, header spacing contracts and the greeting/actions stack. At or below 1023px, the shell becomes one column with a 68px header and a fixed drawer (280px) behind a translucent backdrop. The drawer enters from the correct logical edge in RTL. Main padding becomes 24px. At or below 600px, main padding becomes 20px 16px, the header contracts, and rows wrap while keeping metadata readable.

Header previews are 384px wide, capped by viewport width minus 32px; preview lists scroll within 370px. At the drawer breakpoint they anchor beneath the header with a 16px inline inset. Keep the standalone Messages and Notifications hierarchy: header preview, View All page, then issue conversation.

**The Single Sidebar Rule.** Keep one labeled navigation hierarchy; use its drawer representation on mobile rather than adding a second icon rail.

## Elevation & Depth

Routine cards and the sidebar have no shadow. White surfaces, borders, and pale active/unread rows establish depth. Floating inbox previews use `0 16px 48px rgba(23,41,66,.18)`; the mobile drawer backdrop uses `rgba(23,41,66,.3)`.

**The Overlay Depth Rule.** Reserve the strong ambient shadow for floating previews; everyday operational cards remain flat.

## Shapes

Use the field, control, and card radius roles from the frontmatter. Structural borders are 1px. Navigation items have field corners; section toggles and preview icons have control corners. Compact unread-count badges use their separate count radius. Respect clipped card boundaries while keeping focus outlines visible.

## Components

### Buttons

Primary actions use medical blue with white text and deeper blue hover. Outlined actions use white with blue text, a panel border, and pale-blue hover. Common buttons and icon controls have a final minimum target of 44px; icon controls also have a minimum width of 44px. Keep real labels, handlers, and permission-dependent availability.

### Cards / Containers

Use white, card corners, thin panel borders, and no resting shadow. Use panel padding for ordinary card bodies and the documented compact-screen reduction. Tables use pale headings, muted header text, and tabular data.

### Chips / Badges

Alert counts have white text, weight 700, and a compact rectangular silhouette. Success counts use the corresponding semantic fill. Badges communicate actual status; they do not serve as additional independent touch targets.

### Inputs / Fields

Fields use white surfaces, panel borders, field corners, and minimum height 44px. Text entry uses a medical-blue caret. Focus-visible elements use a 3px medical-blue outline with a 3px offset. Preserve inherited form validation and disabled behavior.

### Navigation

Section toggles use muted text, control corners, and 48px minimum height. Active sections and links use pale medical blue with blue text. Links use a final minimum height of 44px and wrapping labels. The mobile toggle and close control retain 44px targets. Section chevrons rotate with a 160ms ease-out transition; the drawer moves over 180ms ease-out. Reduced-motion preference disables transitions and animations within the workspace.

### Inbox and Conversation

Preview rows use compact subjects, supporting snippets, and timestamps; unread rows have a pale resting fill. Thread and notification rows use larger subjects, generous padding, and explicit status controls. Conversation content is a white bordered history with text wrapping, preserved line breaks, and clear author/time metadata. Empty states use one pale icon tile, a concise heading, and readable supporting copy.

## Do's and Don'ts

### Do:
- **Do** keep the vendor workspace light and preserve SIGMA's factual logo.
- **Do** use local VendorCare typography, logical RTL spacing, and wrapping Arabic labels.
- **Do** retain flat everyday cards, clear borders, pale active rows, and visible keyboard focus.
- **Do** maintain 44px touch controls and the single sidebar's mobile drawer.
- **Do** preserve real counts, statuses, permissions, and explicit acknowledgment actions.

### Don't:
- **Don't** apply this vendor document to the administration shell or other storefront themes.
- **Don't** reintroduce a second navigation rail or hover-only operational actions.
- **Don't** use decorative elevation on everyday cards or entrance motion that hides content.
- **Don't** turn an unread color treatment into an implicit acknowledgment when a page opens.
