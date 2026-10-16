# Cursor output

## Status

needs-review

## Summary

Block checkout gateway rules now apply on Store API cart and batch routes, which is where the Checkout block reads `payment_methods`. Tests: 65 + 8 passed. Store API order 13876 had the correct order currency and empty price-book meta because Store API never fires `woocommerce_checkout_create_order`; that meta is bookkeeping, not the charge. Release is not blocked on it.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-price-book.php | Saved country map, configurable fallback, billing-vs-catalog decision |
| includes/class-rwgcm-price-book-currency.php | Geo Core visitor country only, cache vary, billing limited to cart and checkout |
| includes/class-rwgcm-gateway-availability.php | `woocommerce_available_payment_gateways` by billing country or Geo Core group |
| includes/class-rwgcm-price-book-admin.php | Map, fallback, and gateway checkboxes on the settings screen |
| includes/class-rwgcm-price-book-order.php | Order source uses the configured fallback |
| includes/class-rwgcm-plugin.php | Boot gateway availability |
| admin/views/settings-commerce.php | Point the settings intro at the new controls |
| tests/test-price-book.php | Map, fallback, cache notes, and gateway decision tests |
| readme.txt, CHANGELOG.md | 0.4.0 notes for the map, cache vary, and gateways |

## What was not changed

- Geo Core detection, MaxMind, and `rwgc_cc` cookie writing
- Rule evaluation and the pricing-rule engine
- Plugin version (remains 0.4.0)
- No tag and no merge

## Commands run

```bash
php -l includes/class-rwgcm-price-book.php
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-price-book-admin.php
php -l includes/class-rwgcm-price-book-order.php
php -l includes/class-rwgcm-gateway-availability.php
php -l includes/class-rwgcm-plugin.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — `tests/test-price-book.php` 62 passed; `tests/test-rules-unified.php` 8 passed.

No WordPress or browser runtime is available here, so the settings screen, checkout gateway list, and a cached catalog page were not exercised live.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
