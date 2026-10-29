# Cursor output

## Status

done

## Summary

Geo Commerce 0.9.2 purges cached prices when a pricing rule changes, applies the geo rule when the cart falls back to the store currency, and stores country conditions as ISO codes. Not tagged and not merged.

## Root cause

Generic rules live in `wp_rwgcm_rules`, so saving one did not hit the option purge. A shipping fallback to the store currency skipped the geo rule whenever a store-currency book price existed, including for a product that also had a visitor-currency book. Country names were stored as entered.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-cache-purge.php | One pricing-rule purge per request, scoped when the rule names products |
| includes/class-rwgcm-rule-store.php | Purge on insert, update, and delete |
| includes/class-rwgcm-price-book-apply.php | During a shipping fallback, rule the store-currency amount |
| includes/class-rwgcm-exchange.php | Apply the geo rule to store prices without converting |
| includes/class-rwgcm-rule-sanitizer.php | Save country names as ISO codes |
| includes/class-rwgcm-condition-evaluator.php | Normalize country names |
| includes/class-rwgcm-rule-migration.php | Rewrite stored names once on upgrade |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.9.2 |
| tests/test-cache-purge.php, tests/test-exchange.php | Purge scope, fallback pricing, country codes |
| tests/test-product-price.php, tests/test-cache-safe-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.9.2 |

## What was not changed

A fixed price in the currency the cart is charging, when that currency was selected for the visitor. No tag and no merge.

## Commands run

```bash
php tests/test-exchange.php
php tests/test-cache-purge.php
for f in tests/test-*.php; do php "$f"; done
```

`tests/test-exchange.php`: 100 passed, 0 failed.
`tests/test-cache-purge.php`: 52 passed, 0 failed.
Every `tests/test-*.php` script passed.

## Remaining errors

None.
