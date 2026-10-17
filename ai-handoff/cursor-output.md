# Cursor output

## Status

needs-review

## Summary

Payments by country in 0.4.4 re-reads `woocommerce_payment_gateways` when the admin screen renders, lists any gateway WooCommerce’s registry missed, and warns that checkout cannot offer that gateway until WooCommerce registers it. A summary names every gateway, and the country column stays visible while the table scrolls. Checkout still uses WooCommerce’s own list. Not merged and not tagged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-gateway-availability.php | Admin-only fallback instances, recorded fallback ids, checkout warning text |
| includes/class-rwgcm-price-book-admin.php | Gateway summary, matrix wrapper, warning notice |
| admin/css/rwgcm-admin.css | Sticky country column |
| tests/test-gateway-fallback.php | Filter-only gateway is listed, and the warning names it |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.4.4 |

## What was not changed

- Checkout `filter_gateways()` still only sees the gateways WooCommerce passes in
- Store currency is not forced, and the matrix is not transposed
- No diagnostic for `wc_payment_gateways_initialized` before `plugins_loaded`
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-gateway-availability.php
php -l includes/class-rwgcm-price-book-admin.php
php tests/test-gateway-fallback.php
php tests/test-admin-nav.php
php tests/test-gateway-currency.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

php -l: no syntax errors. Tests: gateway fallback 17, admin nav 19, gateway currency 36, price book 96, rules 8. No phpcs config in the repo.

## Remaining errors

None.
