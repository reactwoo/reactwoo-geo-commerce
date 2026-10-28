# Cursor output

## Status

done

## Summary

The classic cart shipping calculator now clears the shipping-currency notice when the new address uses the store currency. A ZAR fallback followed by a GB calculation no longer leaves "Prices are shown in GBP because shipping to United Kingdom (UK) isn't priced in ZAR." Checkout already cleared that notice. Still draft PR #12. Not merged and not tagged. Version stays 0.6.0.

## Root cause

The calculator updates the customer shipping country, but currency still followed the geo visitor (ZA → ZAR). Shipping to GB is not priced in ZAR, so the cart stayed in GBP and the fallback notice was rewritten for the United Kingdom. Checkout posts the billing country GB, so the visitor currency becomes the store currency and the notice is empty.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-price-book-currency.php | Remember the calculator country, then resolve again; drop a fallback when the visitor currency is the store currency |
| includes/class-rwgcm-shipping-prices.php | No notice when the fallback is the store currency or the cart is already in the visitor currency |
| tests/test-checkout-country.php | ZA fallback, then calculator to GB, notice cleared; calculator back to ZA still explains ZAR |
| tests/test-shipping-prices.php | Store-currency fallback and an already-matched visitor currency produce no notice |
| CHANGELOG.md, readme.txt | Note the fix under 0.6.0 |

## What was not changed

- Version (still 0.6.0)
- Phase 2 and phase 3
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-shipping-prices.php
php tests/test-checkout-country.php
php tests/test-shipping-prices.php
php tests/test-shipping-cache.php
php tests/test-store-api-currency.php
```

All passed (0 failed).

## Remaining errors

None.
