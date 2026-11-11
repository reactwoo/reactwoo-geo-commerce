# Cursor output

## Status

done

An empty cart, or a cart whose lines are not all priced in the visitor currency, stays in the store currency. Product display, the mini-cart, the Store API cart, and the order use that same currency. Version is 0.10.3. Not tagged and not merged.

## Files changed

- `includes/class-rwgcm-price-book-currency.php` — empty cart (and a cart that is not ready yet) uses the visitor currency only when `currency_is_priceable()` is true: a price book has an amount in that currency, or an enabled exchange-rate path can produce it. Otherwise the store currency. A cart with lines is unchanged: every line must be priced, and shipping still has to support the currency. Filters: `rwgcm_currency_is_priceable`, `rwgcm_cart_currency_fallback`.
- `includes/class-rwgcm-price-book-order.php` — Checkout block sets the order currency to the cart currency before writing price-book meta. Classic checkout already did this.
- `tests/phpunit/CartCurrencyFallbackTest.php`, `tests/phpunit/bootstrap.php` — empty cart, cart not ready, exchange on/off, mixed cart, fully priced ZAR cart, EUR book, Store API cart, classic and block checkout order currency, geo country meta, guard, both filters, country gateway rule.
- `tests/test-geo-hints.php` — the pre-`wp_loaded` currency path still does not read the cart. It now calls `empty_cart_currency()`.
- `reactwoo-geo-commerce.php`, `readme.txt`, `CHANGELOG.md` — 0.10.3.
- `tests/test-cache-safe-price.php`, `tests/test-gateway-fallback.php`, `tests/test-product-price.php` — version assertions follow 0.10.3.
- `ai-handoff/decisions.md` — ZA stays on GBP until a price exists.

## Not changed

- `_rwgcm_visitor_country_iso2` and `_rwgcm_geo_captured_at_gmt`. Country still comes from Geo Core.
- Gateway filtering by country (`RWGCM_Gateway_Availability::apply_country_rule`).
- A fully priced book (EUR on FR, ZAR when every line has a ZAR amount) and the shipping fallback notice.
- Catalog relabel of an unpriced product while the active currency really is the visitor currency.
- No git tag and no release.

## Commands

- `phpunit` (PHPUnit 11.5.10) — OK, 22 tests, 112 assertions.
- `php tests/test-*.php` — 1024 passed, 0 failed.
- `python3 tests/test-zip-js-order.py` — passed.

## Remaining errors

- None from these tests.
