# Cursor output

## Status

done

## Summary

QA15: preloaded `extensions.rwgcm` was empty because the notice depended on the currency filter and a route flag, and a GB destination kept the previous ZAR fallback. The fallback is now computed from the cart lines, the shipping destination, and the zone configuration. The same decision feeds the Store API extension, the classic notice, and the currency filter. A GBP country clears it. The block script reconciles the notice on every cart update. Draft only. Not tagged and not merged.

## Files changed

| File | Why |
|------|-----|
| includes/class-rwgcm-price-book-currency.php | `shipping_currency_state()` does not read session rates or the hydration route. A destination mapped to the store currency has an empty fallback. |
| includes/class-rwgcm-shipping-prices.php | Extension data and the classic notice use that decision. |
| assets/js/shipping-currency-notice.js | On every `wc/store/cart` snapshot, clear the notice when the string is empty and add it when present, one id. |
| tests/test-checkout-country.php | Two hydration passes with warm session rates still return the ZAR notice. GB after ZA is empty. |
| tests/test-shipping-prices.php, tests/test-geo-hints.php | Source checks. |
| CHANGELOG.md | 0.10.0 note. |

## What was not changed

- Zone rows, GBP costs, exchange rates, cache purge, country codes, the satellite updater, and the Shipping admin layout
- No merge and no `v*` tag

## Commands run

```bash
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-shipping-prices.php
for f in tests/test-*.php; do php "$f" || exit 1; done
```

All `tests/test-*.php` passed. No WordPress site is running here.

## Remaining errors

None.
