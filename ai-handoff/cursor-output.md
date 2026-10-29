# Cursor output

## Status

done

## Summary

Geo Commerce 0.8.0 adds a cache purge service and cache-safe geo prices. Staging QA on 22ccc7b: the price route now applies visitor-country price books and pricing rules, REST responses do not set `rwgc_country`, and a refused Clear Geo cache request returns 403. Not tagged and not merged.

## Root cause

Country prices were rendered into cached HTML. A price-book, pricing-rule, or exchange-rate save did not purge the product, shop, category archives, or pages that print the price element. Page caches that do not vary by country could keep serving one country's price.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-cache-adapters.php | LiteSpeed, WP Rocket, W3TC, WP Super Cache, and Cloudflare purge calls, each gated on the plugin being active |
| includes/class-rwgcm-cache-purge.php | Purge on price book meta, `rwgcm_pricing`, and exchange rates; usage index; Elementor CSS; Clear Geo cache; `rwgcm_cache_purged` |
| includes/class-rwgcm-cache-safe-price.php | `rwgc_country` vary cookie, LiteSpeed vary, Off/Auto/AJAX mode, public no-store price route |
| includes/class-rwgcm-product-price.php | Placeholder when cache-safe AJAX mode is on; cart, checkout, and editors stay server-rendered |
| includes/class-rwgcm-plugin.php | Boot the purge service and cache-safe prices |
| admin/views/settings-commerce.php | Cache-safe price mode and Clear Geo cache (`manage_woocommerce` + nonce) |
| assets/js/cache-safe-price.js | One batched GET per group of placeholders, no nonce |
| assets/css/product-price.css | Pending price placeholder |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.8.0 and the cache limits |
| tests/test-cache-purge.php, tests/test-cache-safe-price.php | Unit coverage |
| tests/test-product-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.8.0 |

## What was not changed

Cart, checkout, mini-cart, emails, Store API, and variation JSON prices stay server-rendered. With no cache plugin and mode Auto (the default), price HTML is unchanged and the vary cookie is not set. No tag and no merge.

## Commands run

```bash
php tests/test-cache-purge.php
php tests/test-cache-safe-price.php
for f in tests/test-*.php; do php "$f"; done
```

`tests/test-cache-purge.php`: 44 passed, 0 failed.
`tests/test-cache-safe-price.php`: 38 passed, 0 failed.
Every `tests/test-*.php` script passed.

## Remaining errors

None. The settings screen was not opened in a browser; this environment has no WordPress site. The button, nonce, and capability are covered by the unit tests and the settings template.
