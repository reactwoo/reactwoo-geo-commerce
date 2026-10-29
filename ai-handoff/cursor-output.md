# Cursor output

## Status

done

## Summary

Geo Commerce 0.9.0 is tagged `v0.9.0` on `52ed619` and published. Geo Commerce 0.9.1 dedupes the exchange-rate cache purge. Not tagged and not merged.

## Root cause

Saving rates updated several options and fired `rwgcm_exchange_rates_saved`, and each signal purged. A cron refresh also rewrote `fetched_at`, so the cache option change purged even when the rates were the same.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-cache-purge.php | One exchange-rate purge per request; silence when a refresh says the rates did not change |
| includes/class-rwgcm-exchange.php | Compare rate tables at 6 decimal places; skip the saved action when a refresh is unchanged |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.9.1 |
| tests/test-cache-purge.php, tests/test-exchange.php | One purge per save; cron purges only when a rate changes |
| tests/test-product-price.php, tests/test-cache-safe-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.9.1 |

## What was not changed

Price-book and pricing-rule purges. Conversion behaviour. No tag and no merge for 0.9.1.

## Commands run

```bash
php tests/test-exchange.php
php tests/test-cache-purge.php
for f in tests/test-*.php; do php "$f"; done
```

`tests/test-exchange.php`: 82 passed, 0 failed.
`tests/test-cache-purge.php`: 47 passed, 0 failed.
Every `tests/test-*.php` script passed.

## Remaining errors

None.
