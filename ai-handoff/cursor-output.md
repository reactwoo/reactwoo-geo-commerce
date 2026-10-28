# Cursor output

## Status

done

## Summary

0.6.0 adds manual per-currency amounts for flat-rate shipping-class costs and block-checkout local pickup. A cart that needs shipping uses the visitor currency only when every applicable rate has an amount in that currency. A missing amount keeps the cart in the store currency (GBP). No automatic conversion, no carrier quotes, no zone rewrite. Draft PR only. Not merged and not tagged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-shipping-prices.php | Class-cost fields and all-or-nothing swap; pickup side option, rate cost before tax, currency decision |
| includes/class-rwgcm-shipping-block.php | Cart and Checkout block notice script |
| includes/class-rwgcm-price-book-admin.php | Shipping tab currency select, pickup cards, save |
| includes/class-rwgcm-admin.php | Enqueue the column script; Shipping tab redirect |
| admin/css/rwgcm-admin.css | One currency column below 782px; stacked pickup cards |
| admin/css/rwgcm-shipping-modal.css | Full-width method-modal fields on a phone |
| admin/js/rwgcm-shipping-currency.js | Currency select does not save |
| assets/js/shipping-currency-notice.js | Block notice from the Store API string; empty string renders nothing |
| assets/css/shipping-currency-notice.css | Notice wraps on a narrow screen |
| tests/test-shipping-prices.php | Class costs, pickup, zones untouched, block notice |
| tests/test-admin-nav.php | Pickup save returns to the Shipping tab |
| tests/test-gateway-fallback.php | Version 0.6.0 |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.6.0 |

## What was not changed

- Phase 2 conversion and phase 3 carrier or table-rate quotes
- Coupon minimums, geo fees, FX feeds, per-currency decimals
- Shipping zones, zone locations, method order, and store-currency `cost` / `class_cost_*` / `no_class_cost` / pickup location `cost`
- Free-shipping minimum behaviour (already manual in 0.5.0; fields gained a visible helper)
- No merge and no `v*` tag

## Commands run

```bash
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-shipping-block.php
php -l includes/class-rwgcm-price-book-admin.php
php -l includes/class-rwgcm-admin.php
for f in tests/*.php; do php "$f" || exit 1; done
```

All test files passed (0 failed). No WordPress site is running here, so the staging checklist in the PR is the browser pass.

## Remaining errors

None.
