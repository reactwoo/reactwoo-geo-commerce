# Cursor output

## Status

needs-review

## Summary

0.5.1 puts the classic-cart shipping-currency notice inside the cart totals table, as our own row, so the shipping calculator replaces it with the totals and cart.js does not move it to the top notice wrapper. Checkout still uses the checkout fragment. The cart hash and refreshed fragments include the active currency, and the classic cart triggers a header fragment refresh after the calculator updates. Draft PR only. Not merged and not tagged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-shipping-prices.php | Cart totals row, cart hash, fragments, fragment-refresh script |
| tests/test-shipping-prices.php | Cart notice, hash, and fragment coverage |
| tests/test-gateway-fallback.php | Version assertion is 0.5.1 |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.5.1 |

## What was not changed

- Checkout notice markup and `woocommerce_update_order_review_fragments`
- Store API `shipping_currency_notice`
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-shipping-prices.php
php tests/*.php
```

475 passed, 0 failed.

## Remaining errors

None.
