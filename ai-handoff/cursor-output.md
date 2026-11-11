# Cursor output

## Status

done

Checkout block (Store API) orders store the same geo attribution meta as classic checkout. Version is 0.10.2. Not tagged and not merged.

## Files changed

- `includes/class-rwgcm-order-geo.php` — classic `woocommerce_checkout_order_processed` still runs the writer. Store API `woocommerce_store_api_checkout_update_order_meta` and `woocommerce_store_api_checkout_order_processed` run it as well. The callback accepts an order ID or a `WC_Order`. Country comes from `rwgc_get_visitor_country()` and then visitor `country_code`. Meta is saved with `update_meta_data` and `save`. A non-empty value is left as stored.
- `tests/phpunit/OrderGeoAttributionTest.php`, `tests/phpunit/bootstrap.php`, `phpunit.xml.dist` — PHPUnit covers classic write-once, Store API write-once, both paths, existing meta, an empty value being filled, and the HPOS order object.
- `reactwoo-geo-commerce.php`, `readme.txt`, `CHANGELOG.md` — 0.10.2.
- `tests/test-cache-safe-price.php`, `tests/test-gateway-fallback.php`, `tests/test-product-price.php` — version assertions follow 0.10.2.
- `.gitignore` — ignore `.phpunit.cache/`.

## Not changed

- Visitor detection. This plugin still does not read checkout POST fields, the `rwgc_cc` cookie, or IP headers.
- Attribution cookie capture (`RWGCM_Attribution`).
- No git tag.

## Commands

- `phpunit` (PHPUnit 11.5.10) — OK, 7 tests, 47 assertions.
- `php tests/test-*.php` — 1024 passed, 0 failed.
- `python3 tests/test-zip-js-order.py` — passed.
- Two `python3 scripts/package_zip.py` runs, with and without `CI=true`, share SHA-256 `1ec95414e2e4ae9ca914f0a25cd219c5f6b9d25860849dc05f3621ec662d6a5a`.

## Remaining errors

- None from these tests.
