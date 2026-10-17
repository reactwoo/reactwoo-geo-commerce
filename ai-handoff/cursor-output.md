# Cursor output

## Status

needs-review

## Summary

0.4.2 adds an opt-in per-gateway currency list. Paystack WooCommerce Payment Gateway 5.8.5 (id `paystack`) disables its settings unless `get_woocommerce_currency()` is in `woocommerce_paystack_supported_currencies`. That filter only widens the allow-list, so the gateway settings screen uses a scoped `woocommerce_currency` filter. Product saves, REST, cron, and WP-CLI stay on the store currency. Checkout offers a configured gateway only when the cart or order currency is in its list.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-gateway-currency.php | Allow-list, settings-screen currency, checkout filter, Paystack allow-list merge, minor units |
| includes/class-rwgcm-price-book-admin.php | Gateway currency fields on the existing gateway rules screen |
| includes/class-rwgcm-price-book-currency.php | Public product-sync flag so the settings override cannot run during a sync |
| includes/class-rwgcm-plugin.php | Load and init the new class |
| tests/test-gateway-currency.php | Settings scope, checkout offer, ZAR minor units |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.4.2 |

## What was not changed

- 0.4.1 store-currency enforcement for product saves, REST, cron, and WP-CLI
- Country gateway rules
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-gateway-currency.php
php -l includes/class-rwgcm-price-book-admin.php
php -l includes/class-rwgcm-plugin.php
php tests/test-gateway-currency.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — gateway currency 33 passed; price book 86 passed; rules 8 passed.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
