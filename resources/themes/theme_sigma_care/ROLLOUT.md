# Sigma Care installation — 2 October 2026

Sigma Care (`theme_sigma_care`) is installed on `https://sigma-eg.net` alongside Default and Aster. The active public storefront remains Default. The new theme is light only with primary `#1a428a`; it changes storefront views and merchant registration, not the admin or merchant dashboard design.

## Private preview

Sign into the existing admin account with Themes and Addons permission. Open **System setup → Themes**, then **Preview storefront** on Sigma Care. The preview lasts two hours in that browser. **End preview** returns it to the active storefront. The encrypted cookie selects storefront appearance only; normal customer and merchant authentication continues to apply. Admin and API requests ignore the preview cookie. Preview pages have `noindex, nofollow` metadata.

Direct authenticated entry: `https://sigma-eg.net/admin/system-setup/theme/preview/theme_sigma_care`.

## Validation

- 13 targeted tests passed with 125 assertions, covering theme contracts, published assets, homepage readiness, filled-cart Blade compilation, and encrypted preview restrictions.
- 179 Care/Aster Blade templates compiled without errors. The new catalog adapter passed JavaScript syntax validation.
- Local anonymous and customer QA covered catalog, account, cart, real shipping inputs, policies, and merchant registration. Search and price sorting retained the same three matching products and search term. No payment, order, registration, or policy-acceptance submission was performed.
- Independent visual review resolved six findings. Desktop and mobile captures are kept locally in `.impeccable/review/`; those review files were excluded from deployment.
- Production checks returned HTTP 200 for 13 private storefront routes, including a real product page and merchant registration; Care styling and preview metadata were present. Public homepage, products, and categories stayed on Default. Anonymous admin preview requests reached normal authentication.
- All 140 scoped deployment file hashes matched local source. The new CSS was served successfully. Care's assets and independent homepage layout were ready; `WEB_THEME` remained `default`.

The runtime uses Aster's maintained commerce views, with Default view bridges for existing shared account controllers. Keep both installed theme dependencies available.

## Recovery

The 40 modified existing runtime files have a scoped pre-installation backup at `/home/sigma-eg/private/sigma-care-final-baseline-20261002.tar.gz`. Restoring those files and clearing Laravel route/view caches restores the previous theme runtime. The newly added Care directory and its separate homepage layout can remain dormant. Normal storefront activation is still an explicit admin action.
