# Cursor output

## Status

needs-review

## Summary

0.4.3 splits price books and payments by country into their own Commerce screens and pins them on the Commerce hub. `admin.php?page=rwgc-commerce-hub` leads with Rules, Price books, Configure payment gateways, and Settings. The payments card opens `admin.php?page=rwgcm-payments`. WooCommerce → Settings → Payments links to that screen. Rule-builder steps are no longer a second card, and Commerce admin inputs go full width below 782px. Not merged and not tagged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-admin.php | Price books and Payments by country routes, hub card order and “Configure payment gateways” label, WooCommerce Payments notice |
| includes/class-rwgcm-price-book-admin.php | Separate price-book and payments forms so one save does not wipe the other |
| admin/views/price-books.php | Price books screen |
| admin/views/payments-by-country.php | Payments by country screen |
| admin/views/settings-commerce.php | Links to those screens |
| admin/views/dashboard.php | Overview links, including Configure payment gateways |
| admin/css/rwgcm-admin.css | Single card level, border-box, full-width inputs, tighter padding at 782px and 600px |
| tests/test-admin-nav.php | Hub order, route labels, WooCommerce payments screen detection |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md, README.md | Version 0.4.3 and the new admin paths |

## What was not changed

- 0.4.1 store-currency enforcement
- 0.4.2 gateway currency behaviour (settings override, checkout offer, Paystack allow-list)
- Payment gateway country rules are still not an IF/THEN rule
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-admin.php
php -l includes/class-rwgcm-price-book-admin.php
php -l admin/views/payments-by-country.php
php -l admin/views/price-books.php
php -l admin/views/settings-commerce.php
php -l admin/views/dashboard.php
php -l reactwoo-geo-commerce.php
php tests/test-admin-nav.php
php tests/test-gateway-currency.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — admin nav 13 passed; gateway currency 33 passed; price book 86 passed; rules 8 passed.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
