---
name: Sigma Care
description: Light SIGMA storefront with a fixed medical blue palette and real commerce controls.
colors:
  primary: "#1a428a"
  primary-dark: "#14356f"
  primary-light: "#edf2fb"
  ink: "#172942"
  muted: "#52627a"
  placeholder: "#59677d"
  page: "#f4f7fb"
  surface: "#fff"
  product-surface: "#f8faff"
  border: "#dce4f0"
  subtle-border: "#e1e8f2"
typography:
  display:
    fontFamily: "Care, sans-serif"
    fontSize: "46px"
    fontWeight: 800
    lineHeight: 1.4
    letterSpacing: "-0.025em"
  display-mobile:
    fontFamily: "Care, sans-serif"
    fontSize: "30px"
    fontWeight: 800
    lineHeight: 1.5
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Care, sans-serif"
    fontSize: "24px"
    fontWeight: 750
    lineHeight: 1.4
  shelf-title:
    fontFamily: "Care, sans-serif"
    fontSize: "23px"
    fontWeight: 750
    lineHeight: 1.4
  shelf-title-mobile:
    fontFamily: "Care, sans-serif"
    fontSize: "19px"
    fontWeight: 750
    lineHeight: 1.4
  body:
    fontFamily: "Care, sans-serif"
    fontSize: "14px"
    lineHeight: 1.7
  product-title:
    fontFamily: "Care, sans-serif"
    fontSize: "14px"
    lineHeight: 1.5
  navigation:
    fontFamily: "Care, sans-serif"
    fontSize: "13px"
    fontWeight: 600
  button:
    fontFamily: "Care, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 650
rounded:
  field: "10px"
  control: "12px"
  product: "14px"
  card: "16px"
  hero-mobile: "18px"
  shelf: "20px"
  hero: "22px"
  pill: "50px"
spacing:
  micro: "4px"
  compact: "8px"
  mobile-grid: "12px"
  field: "16px"
  desktop-grid: "18px"
  section-mobile: "20px"
  panel: "24px"
  section: "26px"
  masthead: "28px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "0.5rem 1.5rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-dark}"
    textColor: "{colors.surface}"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.primary}"
    typography: "{typography.button}"
    rounded: "{rounded.control}"
    padding: "0.5rem 1.5rem"
  button-outline-hover:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
    padding: "{spacing.panel}"
  product-card:
    backgroundColor: "{colors.product-surface}"
    rounded: "{rounded.product}"
    padding: "{spacing.compact}"
  product-card-hover:
    backgroundColor: "{colors.primary-light}"
  product-action:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "50%"
    size: "44px"
  category-circle:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "50%"
    size: "72px"
  discount-badge:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "7px"
  bottom-navigation:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.muted}"
    padding: "9px 4px max(9px, env(safe-area-inset-bottom))"
---

# Design System: Sigma Care

## Overview

**Creative North Star: "Sigma Care"**

The approved preview 4 establishes this theme's visual identity: a cool light field, white shopping surfaces, a broad photographic medical hero, circular category shortcuts, and framed product shelves. Compact supporting navigation and generously padded panels make real catalog content easy to scan. Blue carries the primary shopping actions and active states; product photography remains the main visual material.

This document applies only to the selectable storefront theme `theme_sigma_care`. Its overrides load before maintained Aster commerce views, and its stylesheet loads after the inherited styles. Shared Default customer forms enter through the Care layout bridge. Default and Aster keep their configured colors and appearance; this document does not prescribe an admin or merchant dashboard redesign.

Source authority is [sigma-care.css](public/assets/css/sigma-care.css), the theme's layout/header/home/card views, and the inherited Aster Bootstrap/runtime styles. The [final review verdict](.impeccable/review/finish-verdict.md) resolves six scored corrections after thirteen required captures; its ship disposition covers that correction batch and does not certify every commerce flow or deployment.

The integration is prepared for dormant installation and private review. Deployment and public activation are separate rollout steps and are not established by this document. The deployment target keeps Default active until the owner explicitly selects another theme. An authenticated admin can initiate an encrypted, HttpOnly, two-hour browser preview; admin pages, APIs, and the merchant portal ignore that preview, with merchant registration explicitly included in storefront preview scope.

**Key Characteristics:**
- Fixed light appearance with a medical blue accent.
- Local Arabic and Latin typography supporting RTL and LTR.
- White and pale-blue containment with restrained overlay shadows.
- Original SIGMA user-app silhouettes and configured store identity.
- Visible commerce actions, readable field hints, and separate mobile purchase/navigation docks.

## Colors

The palette combines a fixed medical blue with cool paper surfaces and dark blue text. The frontmatter records normative Care values from the final stylesheet; it does not replace existing business settings or semantic status colors.

### Primary

- **Medical Blue** (`primary`): shopping buttons, prices, selected navigation, focus outlines, utility strip, and footer.
- **Deep Medical Blue** (`primary-dark`): hover and active primary buttons.
- **Pale Medical Blue** (`primary-light`): hero ground, active profile menu, product hover, category rings, and reversed footer controls.

### Neutral

- **Blue Ink** (`ink`): headings, input values, and account/navigation labels.
- **Slate Text** (`muted`): body copy and inactive bottom navigation.
- **Readable Slate Hint** (`placeholder`): final input and textarea hint color, with full opacity.
- **Cool Paper** (`page`): page and search backgrounds.
- **White Surface** (`surface`): fields, photos, shelves, cards, and mobile docks.
- **Catalog Paper** (`product-surface`): resting product-card frames.
- **Panel Border** (`border`): fields, panels, and mobile navigation boundary.
- **Quiet Divider** (`subtle-border`): header navigation, search border, product-action borders, and service strip.

**The Care Palette Rule.** Care stays light and uses its fixed primary palette. Preserve the configured Default/Aster themes and factual brand assets; retain business-driven announcements and semantic commerce feedback where their existing contracts require them.

## Typography

**Display and Body Font:** Vazirmatn, registered locally under the CSS family `Care`, with `sans-serif` fallback. The variable font is served from `public/assets/care/vazirmatn.ttf`, covers weights 100–900, and uses `font-display: swap`. Its SIL Open Font License is bundled in `public/assets/care/OFL.txt`.

The single Arabic/Latin family connects bold, balanced headings to compact navigation and product data. Respect the document's actual direction; use logical alignment and spacing, and isolate telephone numbers where the view already uses bidirectional markup.

### Hierarchy

- **Display:** the frontmatter records the observed upper bound of the general heading role. The actual CSS uses `clamp(28px, 3.3vw, 46px)`; the mobile hero has its own larger-line-height role. Balance heading wraps and preserve the approved two-level hero treatment.
- **Headline / shelf title:** use the distinct general-heading and product-shelf roles. Shelf titles reduce to their mobile role at the Care mobile breakpoint.
- **Body:** use the body role for ordinary content. Hero supporting copy uses (15px, line-height 1.9, maximum 38ch) on desktop and (13px, maximum 40ch) on mobile.
- **Product title:** clamp to two lines; desktop minimum height is (40px), mobile uses (12px type, 36px minimum height).
- **Navigation / labels:** desktop navigation uses its frontmatter role; mobile navigation uses (12px), header labels (11px), and bottom navigation labels (10px).
- **Numbers:** product prices and commerce tables use tabular numerals. Preserve existing currency formatting and real discount/stock values.

**The Original Silhouette Rule.** Use the copied Flutter user-app silhouettes, tinted through CSS masks where implemented. Do not replace shopping/account shapes with a newly drawn icon family.

## Layout

The storefront uses the inherited Bootstrap container: fluid width with (12px) inline gutters, capped at (540px) from 576px, (720px) from 768px, (960px) from 992px, (1140px) from 1200px, and (1320px) from 1400px. These container thresholds coexist with Care's explicit maximum-width rules.

| Care condition | Implemented behavior |
| --- | --- |
| Above 1199px | Three-part masthead with (170px) logo column, flexible search, account controls; homepage product grid has five columns. |
| At or below 1199px | Masthead logo column becomes (150px); category navigation scrolls horizontally; selected category links hide; homepage products use four columns; bottom navigation is shown through the inherited `d-xl-none` rule and body reserves (74px). |
| At or below 991px | Product purchase dock sits above bottom navigation at `calc(68px + env(safe-area-inset-bottom))`; content reserves (190px) beneath product details. |
| At or below 767px | Search spans the second masthead row; hero stacks; home/listing product grids have two columns; promotions and footer banners stack; services form two columns. |

The desktop home hero uses a (.92fr / 1.08fr) split, minimum height (382px), and copy padding (44px 40px). The intermediate minimum height is (340px). Mobile copy padding is (26px 24px), and image padding is (0 16px 16px). Configured banner imagery keeps its intrinsic ratio and uses containment.

Homepage section spacing uses the `section` token and its mobile counterpart. Product shelves use `panel` padding, changing to `field` on mobile. Homepage grid gaps use `desktop-grid`, then `mobile-grid`; listings use four desktop columns and two mobile columns. The existing list view remains available, with photo columns (150px) on desktop and (112px) on mobile.

Category shortcuts scroll horizontally on mobile. Main commerce pages have padding (28px 0 48px), reduced to (20px 0 32px) on mobile, and a minimum content height (55vh). Keep configuration-driven homepage section order, visibility, real banners, and separate Care builder/banner caches.

**The Two Docks Rule.** Product purchase controls and mobile navigation must remain simultaneously visible and tappable. Preserve the measured navigation/safe-area offset, opaque purchase surface, (44px) quantity/buttons, and final scroll clearance.

## Elevation & Depth

Everyday hierarchy comes from white surfaces, pale-blue fields, and light borders. Cards and product hover states have no shadow. Elevated overlays use soft blue-ink shadows; the fixed product dock uses an upward shadow to separate it from scrolling content.

### Shadow Vocabulary

- **Modal:** `0 16px 48px rgba(23,41,66,.18)`.
- **Dropdown:** `0 10px 30px rgba(23,41,66,.1)`.
- **Mobile purchase dock:** `0 -4px 16px rgba(23,41,66,.1)`.

## Shapes

Fields, controls, product frames, and panels follow the frontmatter's progressively softer corner vocabulary. The hero and shelves have the largest rectangular corners; category shortcuts and product actions are circular. Search and hero shopping actions use pill geometry. The mobile hero and contact band share the gentler mobile-hero radius.

Keep thin (1px) panel/control boundaries and the category circle's (5px) pale ring. A category circle reduces from its desktop size to (64px) on mobile. Photos remain contained and legible; do not introduce an inherited photo wash over product images.

## Components

### Buttons

Primary controls are solid medical blue with white text; outlined controls use the same blue for border/text and fill on hover. Common buttons have minimum height (44px), frontmatter button typography, and the inherited standard padding. Hero shopping buttons use pill corners and padding (10px 24px). Color transitions last (160ms), with `ease-out`; there is no product-hiding entrance motion.

Disabled add-to-cart controls use opacity (.6) and a not-allowed cursor; stock and authentication still determine actual availability. Preserve real action handlers and validation.

### Cards / Containers

General cards use the card radius, panel border, and no shadow; body padding becomes (18px) on mobile. Shelves form white frames around the catalog-paper product cards. Product photography sits on white square thumbnails with (12px) internal image padding, reduced to (8px) on mobile.

Product wishlist, compare, and quick-view actions are visible at rest. The final scoped override forces (44×44px) circular targets, removes inherited opacity/visibility/transform and delay, and disables the thumbnail wash. Hover and active wishlist actions use blue fill with white silhouettes. The card's textual shopping action opens the existing quick view and respects stock.

### Chips / Badges

Discount badges use the small badge corner, primary fill, white text, and (10px) type. Header counts are compact pill/circle indicators with (9px) type and (16px) minimum width/height. They are indicators, not independent touch controls; preserve live counts and conditional discounts.

### Inputs / Fields

Fields use white surfaces, ink values, panel borders, and minimum height (44px). Search is a pale pill with a visible label for assistive technology and a named submit button. The final scoped placeholder rule applies to inputs and textareas at full opacity. Validation, error, password/OTP, and disabled branches retain the existing commerce/authentication contracts.

All focus-visible elements use a (3px) solid primary outline with (4px) offset. Preserve room for that outline on clipped or horizontally scrolling surfaces.

### Navigation

The shell places a blue utility strip above logo/search/account controls and category navigation. Utility actions include language and merchant entry; merchant sign-in leads to the existing portal, and registration keeps its real form. Main navigation links and profile menu entries use minimum height (44px); selected profile rows use pale-blue fill.

Bottom navigation has five equal columns for home, categories, cart, wishlist, and profile. Its white surface has a panel-border top edge, (44px) minimum link height, active blue state, and bottom padding that includes the safe area. Search remains visible on mobile. Utility currency and order-tracking shortcuts may hide at mobile width; merchant entry remains available.

### Login, Footer, and Purchase Dock

Login shows alternate-provider columns/dividers only when actual enabled social/OTP options render; unsupported or absent providers must not leave an empty region. The footer keeps the configured SIGMA logo on a white backing with padding (10px 14px), control corners, and a (180×68px) logo box.

At the purchase-dock breakpoint, quantity and action controls share a compact grid with an (88px) quantity column, flexible actions, and (8px) gaps. Both purchase actions remain above bottom navigation; do not change checkout or payment behavior to achieve the layout.

For reduced-motion preference, Care shortens transition/animation durations to (.01ms) and resets smooth scrolling to `auto`. Existing server-rendered content remains visible throughout.

## Do's and Don'ts

### Do:
- **Do** keep Care light, use its fixed palette, and preserve configured SIGMA brand assets.
- **Do** preserve the original Flutter user-app silhouettes and local Vazirmatn font/license.
- **Do** keep real catalog, variant, price, stock, discount, account, checkout, invoice, support, and policy content governed by existing settings and authentication.
- **Do** keep product actions visible at rest with 44px targets and retain visible keyboard focus.
- **Do** maintain two-column mobile shopping grids, visible search, and safe-area separation between purchase and navigation docks.
- **Do** keep theme identity/configuration separate from the Aster runtime family and validate inherited/shared forms inside the Care shell.

### Don't:
- **Don't** apply Care's palette or layout to Default, Aster, admin pages, or the merchant dashboard.
- **Don't** hide product shopping actions behind hover or restore inherited photo overlays.
- **Don't** reserve login columns for unavailable providers or reduce placeholder opacity.
- **Don't** tint or redraw the factual SIGMA wordmark; preserve its readable white footer backing.
- **Don't** add entrance motion that hides commerce content or ignore reduced-motion preference.
- **Don't** treat private browser preview, installation, or the addon's available flag as public theme activation.
