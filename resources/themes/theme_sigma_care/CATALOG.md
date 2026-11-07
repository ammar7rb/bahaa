# Editable medical departments — 2 October 2026

## Wide department menu update

### Hover and pinned-click refinement

### Search dismissal refinement

### Products page grid/list refinement

Care's product listing removes its duplicate search field and product-type selector, plus digital-only publisher/author filters. A hidden search query retains global-header searches when sorting or filtering. Shared Care product cards omit comparison controls; wishlist, quick view and cart remain functional. Catalog images use equal square canvases with contain fitting; grid cards stretch to consistent row heights and list cards use fixed image columns. The narrow mobile list resets inherited maximum summary width and minimum image width. Native radio controls for grid/list are keyboard focusable, styled as a single selector. The sidebar price/category/brand filters and existing sorting/AJAX routes remain available.

Validation: 181 Blade views compiled. Local category filtering returned the expected four laboratory items with no comparison/type controls and one search input. Desktop grid cards measured 271x413px with 247px image canvases; desktop list rows measured 844x204px with 180px images. At 390px, grid cards measured 167x301px with 151px images and all mobile list rows 351x154px with 112px images; no document overflow. Production confirmed all seven grid cards had equal dimensions, one header search, no type filter or comparison icon, and working grid/list toggles. All five deployed hashes matched; the prior runtime was backed up privately. No product/admin data changed.

Care now owns its autocomplete in `care-search.js`. Its custom.js override preserves the production-matching Aster utility script except for the two inherited search keyup handlers. Input events cover typing, paste, and native search clearing. A 180ms debounce, AbortController, and generation checks prevent stale responses from reopening dismissed results. Clear, whitespace, outside pointer, focus leaving, Escape, and form submission dismiss results. Arrow keys move through real result links; Escape from a result returns focus to the search without restarting a request. Empty successful searches display a localized no-results message; failed requests dismiss. The full search form and product links retain existing routes.

Validation: race/order/dismissal automated cases passed, both JS files passed syntax checks, and 180 Blade templates compiled. Production browser confirmed results appeared, then were completely hidden (`display:none`, empty contents, `aria-expanded=false`) after clearing; Escape from a result and outside click also closed results. Desktop and 390px mobile passed without document overflow. All three deployed file hashes matched. Deployment backed up the prior runtime privately; categories, admin, and other themes were unchanged.

The department button now owns its interaction instead of sharing Bootstrap's delegated click handler. Mouse entry opens an unpinned preview; leaving the button/panel closes it after 160ms regardless of lingering button focus. A click pins it; another click closes it. Escape dismisses and resets pinned state; Up/Down navigate department links. Touch uses click toggling. The mobile header puts the full-width department button above a separate scrollable navigation row, increases action targets, and calculates panel height against the viewport and bottom dock. CSS/JS cache versions are now 4.

Verification: scripted fine-pointer and touch interaction cases passed; browser clicks on the deployed site produced expanded=true then false. Arrow navigation and Escape passed locally. 180 Blade templates compiled. Mobile widths 320 and 390 had no document overflow; panels stayed above the bottom dock. Only five theme runtime files were deployed with a private runtime backup; category/admin data was unchanged.

The later update expands the catalog to fifteen editable root categories. Gases is now **شبكات الغاز / Medical Gas Pipeline Systems**, retaining its existing ID and slug. Added **اجهزه تعويضيه / Prosthetic Devices**, **اجهزه مساعده / Assistive Devices**, **اثاث طبي / Medical Furniture**, **معدات الاكسجين / Oxygen Equipment**, and **الاسعافات الاوليه / First Aid Supplies**. All fifteen English translations were standardized in database translation records.

Care's dropdown now uses four columns on desktop, three on tablet, and two on phones, with bounded internal scrolling. Fine-pointer desktop hover opens the Bootstrap dropdown; click, touch, keyboard and Escape remain available. A 120ms entrance respects reduced-motion preferences. All fifteen departments appear in homepage shortcuts. Five additional original SVG/PNG icons use the same color and stroke language.

Terminology references: [WHO assistive technology](https://www.who.int/news-room/fact-sheets/detail/assistive-technology) and [NHS medical gas pipeline systems](https://www.england.nhs.uk/publication/health-technical-memorandum-02-01-medical-gas-pipeline-systems/).

Validation for this update: 180 Care/Aster Blade templates compiled without errors; JavaScript syntax passed. Desktop Arabic menu displays four columns; 390px mobile displays two without document overflow, and Escape closes it. Production returned HTTP 200 for all fifteen category routes, the category index, and fifteen PNG assets. All seventeen deployed hashes matched; product relationships were unchanged. English production HTML contained all fifteen correct translated labels. A private runtime/catalog backup precedes the update. Earlier validation below describes the preceding ten-category release.

The storefront now shows ten real database categories, ordered by descending admin priority: مستهلكات عامة، اسنان، عظام، معامل، ملابس طبيه، اجهزه طبيه، حضانات، غازات، رعايه مركزه، عمليات. Their names, translations, image/library icon, homepage visibility, and priority can be changed at `/admin/category/view`. Availability and priority controls are also available at `/admin/category/medical-management`, under existing Catalog permissions.

Individual category links were removed from Care's main navigation; departments are available through **All categories**, homepage shortcuts, and the categories page. The configured copyright line is no longer rendered by Care. Other footer links remain available; an empty footer-bottom band is omitted.

The ten medical icons are original SVG line drawings with a consistent 1.8-unit stroke and `#1a428a` color. Sources and 128px PNG exports are in `public/assets/front-end/img/medical-categories/`. PNG assets support the existing user-app image interface. The standard `medical_icon` category field selects them; `icon_full_url` resolves the selected library asset across storefront, admin and API responses. Uploading an image through the existing admin form remains supported.

Existing root IDs and slugs for general consumables and laboratory were retained. Product category relationships were unchanged. The obsolete test-card subdivision was archived with `is_active=false`, rather than deleting products or category records. Archived categories remain accessible in management; shopper/API category queries exclude them.

Validation: 13 existing theme tests passed (125 assertions), 179 Care/Aster Blade templates compiled, desktop/mobile Arabic navigation and the admin icon picker were checked. All ten production category routes and ten PNG assets returned HTTP 200; 30 deployed file hashes matched. Production HTML contained the six general navigation links and ten departments, omitted the copyright text and archived subdivision, and preserved every product relationship. The active theme was already Sigma Care at verification; this update did not change theme activation.
