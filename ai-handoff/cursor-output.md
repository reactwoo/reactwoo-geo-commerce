# Cursor output

## Status

needs-review

## Summary

0.5.1 keeps the classic-cart shipping-currency notice inside the totals table. The header mini-cart now posts `get_refreshed_fragments` itself after the calculator response, and that request uses the shipping country the calculator saved (the same destination the cart totals used) instead of the geo visitor country still in the previous session. Draft PR only. Not merged and not tagged. Version stays 0.5.1.

## Root cause

The cart page script only triggered `wc_fragment_refresh`. WooCommerce does not enqueue `wc-cart-fragments` on the cart, so at 1280 nothing listened and no `get_refreshed_fragments` request was sent. Where a listener existed (390), the script armed a refresh on calculator submit, 50ms later, before the new shipping country was stored, and `updated_wc_div` fired another as soon as the HTML arrived. Both ran against the previous session (geo ZA), so both mini-carts came back in ZAR. `ajaxComplete` re-armed that same timer for the fragment response's own completion path only when the calculator data was still attached, and the follow-up merely triggered `wc_fragment_refresh` again, which did not send a later request. A full reload rendered the header from the saved session, which is why only a reload matched the totals.

`get_refreshed_fragments` resolves `get_woocommerce_currency()` from the customer shipping country via the shipping-priced check. Until `woocommerce_calculated_shipping` has written the session, that country is still the geo default. Shipping to ZA is priced in ZAR, so the mini-cart stays R499.00 while the calculator request, which set GB in memory before rendering, shows £23.00.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-shipping-prices.php | Cart page posts `get_refreshed_fragments` after totals events; debounce ignores its own response and queues a later switch; filterable header selectors |
| includes/class-rwgcm-price-book-currency.php | Persist the customer session on `woocommerce_calculated_shipping`, before the response is flushed |
| tests/test-shipping-prices.php | Script, queue, and header-fragment coverage |
| tests/test-checkout-country.php | Geo ZA + shipping GB resolves GBP; session save |
| readme.txt, CHANGELOG.md | Describe the refresh; version stays 0.5.1 |

## What was not changed

- Checkout notice markup and `woocommerce_update_order_review_fragments`
- Store API `shipping_currency_notice`
- Version (still 0.5.1)
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-price-book-currency.php
php tests/*.php
```

490 passed, 0 failed. Raw-byte scan found no redaction marker.

## Remaining errors

None.
