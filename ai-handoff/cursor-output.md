# Cursor output

## Status

done

## Summary

Geo Commerce 0.9.0 converts a missing visitor-currency price-book amount from the store-currency price, then applies the geo pricing rule. A fixed visitor-currency price is unchanged. Not tagged and not merged.

## Root cause

`row_for()` labelled a product with no visitor-currency book price as the store currency. `should_apply_base_adjustment()` returns false when a fixed store-currency book price exists, so a rule such as ZA −15% never ran and the visitor was charged the full store price.

## Default

Conversion mode defaults to **Auto** and the rate source defaults to **Manual** with an empty table. Auto converts only when a rate exists, so an upgrade with no rates keeps 0.8.0 behaviour. Off keeps that fallback even after rates are saved.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-exchange.php | Rates, provider fetch, conversion, rounding, markup, settings save |
| includes/class-rwgcm-price-book-apply.php | Use converted prices for getters, HTML, variations, cart, and signup fees |
| includes/class-rwgcm-product-price.php | Keep the visitor currency when a rate can fill a missing book price |
| includes/class-rwgcm-price-book-currency.php | A convertible line counts as priced, so the cart can leave the store currency |
| includes/class-rwgcm-price-book-store-api.php | Store API and the checkout guard treat a converted line as priced |
| includes/class-rwgcm-price-book-order.php | Record the rate, base, and source on the order |
| includes/class-rwgcm-plugin.php | Boot the exchange service |
| includes/class-rwgcm-price-book-admin.php | Tell merchants a blank currency can be converted |
| admin/views/settings-commerce.php | Manual rates, provider, rounding, and markup |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.9.0 |
| tests/test-exchange.php | Conversion, fixed price, missing rate, rounding, cart parity |
| tests/test-product-price.php, tests/test-cache-safe-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.9.0 |

## What was not changed

Shipping still keeps a cart in the store currency when shipping is not priced in the visitor currency. Fixed book prices are not discounted. No tag and no merge.

## Commands run

```bash
php tests/test-exchange.php
for f in tests/test-*.php; do php "$f"; done
```

`tests/test-exchange.php`: 74 passed, 0 failed.
Every `tests/test-*.php` script passed.

## Remaining errors

None. The settings screen was not opened in a browser; this environment has no WordPress site. The form, nonce, and capability are covered by the unit tests and the settings template.
