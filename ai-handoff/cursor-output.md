# Cursor output

## Status

done

## Summary

QA17: the Checkout notice was created for both the cart and checkout notice lists, and the status-region fallback was inserted into every matching block node, so the sentence showed up four times after ZA → GB → ZA. The country change also ran shipping twice because update-customer calculated totals in our hook and WooCommerce calculated them again. One notice context is used, the fallback is removed once that list shows the text, and the shipping decision is cached for the request. Draft only. Not tagged and not merged.

## Files changed

| File | Why |
|------|-----|
| assets/js/shipping-currency-notice.js | Checkout uses only `wc/checkout` (cart block only `wc/cart`). One fallback node, removed when a notice banner shows the text. |
| includes/class-rwgcm-price-book-currency.php | Per-request cache for `shipping_currency_state()`. Address changes drop cached rates and the memo, and do not call `calculate_totals()`. |
| tests/test-checkout-country.php | Three reads of one destination calculate once. |
| tests/test-shipping-prices.php, tests/test-geo-hints.php | Source checks. |
| CHANGELOG.md | 0.10.0 note. |

## What was not changed

- The fallback rules, zone data, and the Shipping admin layout
- No merge and no `v*` tag

## Commands run

```bash
for f in tests/test-*.php; do php "$f" || exit 1; done
```

All passed. No WordPress site is running here.

## Remaining errors

None.
