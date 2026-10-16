# Cursor output

## Status

needs-review

## Summary

Added fixed per-currency price books (0.4.0). Billing country selects ZAR, GBP, EUR, or USD; Geo Core country is used only before a billing country is known. Cart, checkout, and the order use that currency and the stored price with no conversion. Subscription renewals keep the original currency. Existing store-currency geo price rules stay on via a migrated option.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-price-book.php | Currency list, country map, price selection, cart fallback, base-adjustment decision |
| includes/class-rwgcm-price-book-currency.php | Billing vs geo resolution and `woocommerce_currency` |
| includes/class-rwgcm-price-book-apply.php | Fixed regular, sale, and sign-up prices on products and cart lines |
| includes/class-rwgcm-price-book-order.php | Order currency meta and renewal lock |
| includes/class-rwgcm-price-book-admin.php | Product, variation, and settings UI |
| includes/class-rwgcm-plugin.php | Boot the new classes |
| includes/class-rwgcm-pricing-apply.php | Skip geo adjustments when a fixed book price or non-base currency is in effect |
| includes/class-rwgcm-catalog-price.php | Same skip for catalog prices |
| includes/class-rwgcm-catalog-price-variable.php | Same skip for variation price cache |
| includes/class-rwgcm-fee-rules-apply.php | Do not add store-currency geo fees into another currency |
| admin/views/settings-commerce.php | Migrated “keep store-currency geo adjustments” control |
| tests/test-price-book.php | Decision tests, including Essential monthly 849 / 39 / 45 / 49 |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.4.0 |

## What was not changed

- Geo Core detection, rule evaluation, and the pricing-rule engine
- Weather merchandising, overlays, and licence client
- No subscription products were created in the database
- No tag and no merge

## Commands run

```bash
php -l includes/class-rwgcm-price-book.php
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-price-book-apply.php
php -l includes/class-rwgcm-price-book-order.php
php -l includes/class-rwgcm-price-book-admin.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — `tests/test-price-book.php` 33 passed; `tests/test-rules-unified.php` 8 passed.

No WordPress or browser runtime is available here, so the product editor, checkout, and Revolut charge were not exercised live.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
