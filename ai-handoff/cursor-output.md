# Cursor output

## Status

done

## Summary

Phase 1 per-currency shipping (manual class costs and block local pickup amounts) from `cursor/per-currency-shipping-phase-1-f3e2` is on current main. Replaying `de189b8` and `db19755` onto Commerce 0.9.3 (`d84700c`) did not change shipping behaviour: that code already landed in the 0.6.0 squash (`9659f53`, PR #12). Conflicts were version and changelog files. Those were kept at 0.9.3 so exchange rates, the cache purge, GBP-fallback carts, ISO country codes, and the satellite updater stay. 0.10.0 adds tests that a blank per-currency shipping amount is not converted, and bumps the version. Draft only. Not tagged and not merged.

## Conflicts resolved

| File | Resolution |
|------|------------|
| CHANGELOG.md | Kept 0.7.0–0.9.3. The 0.6.0 phase 1 notes were already there. Added 0.10.0 above them. |
| readme.txt | Kept Stable tag and changelog through 0.9.3, then set 0.10.0. |
| reactwoo-geo-commerce.php | Kept 0.9.3 behaviour; version is now 0.10.0. Did not restore 0.6.0. |
| tests/test-gateway-fallback.php | Version assertion follows the plugin, now 0.10.0. |
| ai-handoff/cursor-output.md | Kept the 0.9.3 updater note, then replaced this file with the 0.10.0 result. |
| includes/class-rwgcm-shipping-prices.php, includes/class-rwgcm-price-book-currency.php, includes/class-rwgcm-price-book-admin.php, tests/test-shipping-prices.php, tests/test-checkout-country.php | Auto-merged with no content change. The calculator notice fix from `db19755` was already in 0.9.3. |

## Files changed

| File | Why |
|------|-----|
| tests/test-shipping-prices.php | A ZAR rate of 23 does not price or scale a GBP flat rate. Manual amounts stay as entered. GBP keys are not rewritten. Exchange, cache purge, country migration, and the updater do not touch zone or pickup storage. |
| tests/test-checkout-country.php | A shipped cart with no manual class amount stays GBP while the product can be exchanged. A virtual cart can still use ZAR. Complete manual amounts switch the cart and are not multiplied by the rate. |
| tests/test-updater.php | Installed version in the own-updater fixture is 0.10.0. The offered package is 0.10.1 so the check still counts as newer. |
| tests/test-gateway-fallback.php, tests/test-product-price.php, tests/test-cache-safe-price.php | Version assertions are 0.10.0. |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.10.0. |
| ai-handoff/decisions.md | Shipping is not converted. |

## What was not changed

- Shipping zone rows, zone locations, method order, store-currency `cost` / `class_cost_*` / `no_class_cost`, and `pickup_location_pickup_locations`
- Phase 2 opt-in conversion and phase 3 carrier or table-rate quotes
- FX conversion of product prices, the cache purge, GBP-fallback rule application, ISO country normalisation, and the satellite updater
- No merge and no `v*` tag

## Commands run

```bash
php -l reactwoo-geo-commerce.php
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-shipping-block.php
php -l includes/class-rwgcm-price-book-admin.php
php -l tests/test-shipping-prices.php
php -l tests/test-checkout-country.php
php -l tests/test-updater.php
for f in tests/test-*.php; do php "$f" || exit 1; done
```

All `tests/test-*.php` passed (0 failed). `php -l` reported no syntax errors on the PHP files checked. No WordPress site is running here, so the staging checklist in the PR is the browser pass.

## Remaining errors

None.
