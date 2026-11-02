# Cursor output

## Status

done

## Summary

QA14: the preloaded cart extensions were empty because block hydration dispatches `/wc/store/v1/cart` while the browser URL is still `/checkout/`, so currency resolution treated the request as ordinary REST and skipped the shipping fallback. The Cart and Checkout blocks also kept the previous totals after an address change. The Shipping screen was 1300px wide at a 1280px viewport. Draft only. Not tagged and not merged.

## Files changed

| File | Why |
|------|-----|
| includes/class-rwgcm-price-book-currency.php | `rest_pre_dispatch` records the Store API route so hydration does not force GBP. After `update-customer`, cached shipping rates are dropped and the cart is recalculated. |
| includes/class-rwgcm-shipping-prices.php | Extension data is read from the same fallback state as the notice, after the cart is ready. |
| assets/js/shipping-currency-notice.js | Subscribe to `wc/store/cart` `getCartData().extensions.rwgcm`. On a shipping-country change, `GET /wc/store/v1/cart` and `receiveCart`. |
| includes/class-rwgcm-admin.php, admin/css/rwgcm-admin.css | Shipping tab body class. `#wpbody-content` is clipped. The matrix is not sticky and cannot widen the page. |
| tests/test-checkout-country.php | Extension data equals the fallback in the same request, including a hydration-shaped request. |
| tests/test-shipping-prices.php | Source checks for the cart subscription, refetch, and the contained column. |
| CHANGELOG.md | 0.10.0 notes. |

## What was not changed

- Zone rows, GBP costs, exchange rates, cache purge, country codes, and the satellite updater
- No merge and no `v*` tag

## Commands run

```bash
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-admin.php
for f in tests/test-*.php; do php "$f" || exit 1; done
```

All `tests/test-*.php` passed. No WordPress site is running here.

## Remaining errors

None.
