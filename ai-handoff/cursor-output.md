# Cursor output

## Status

done

## Summary

Geo Commerce 0.9.1 dedupes the exchange-rate cache purge and applies a 0.8.0 rule-UI price adjustment after currency conversion. Not tagged and not merged.

## Root cause

A rule saved by the 0.8.0 rule UI is a generic rule: scope `global`, priority 100, condition `visitor.country` / operator `is` / value `ZA` (label South Africa), action `price_adjustment` mode `percent` value `-15`. Conversion asked the generic evaluator for that adjustment. Geo Core does not treat operator `is` as valid, so the adapter rewrote it to `in` and left the country as the string `ZA`. That portable match failed, the adjustment was null, and the REST price was the book amount times the rate with no 15% decrease. The legacy `country`/`type`/`value` row used in staging never hit this path. A fixed ZAR book price does not enter conversion, so R1,299 stayed correct. `should_apply_base_adjustment()` is still false when a GBP book exists; the discount has to be applied on the converted amount, which only happens once the UI rule resolves.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-cache-purge.php | One exchange-rate purge per request; silence when a refresh says the rates did not change |
| includes/class-rwgcm-exchange.php | Compare rate tables at 6 decimal places; skip the saved action when a refresh is unchanged |
| includes/class-rwgcm-targeting-adapter.php | Fill a missing visitor country; send `is` to Geo Core as `in` with a list; fall back to the commerce rows |
| includes/class-rwgcm-condition-evaluator.php | Match the UI operators `is` / `in` and a country code or name |
| includes/class-rwgcm-pricing-resolution.php | Resolve a generic price rule even when the context snapshot function is empty |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | 0.9.1 |
| tests/test-cache-purge.php, tests/test-exchange.php | Purge dedupe, and the 0.8.0 UI rule on a converted price |
| tests/test-product-price.php, tests/test-cache-safe-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.9.1 |

## What was not changed

Price-book and pricing-rule purges. A fixed visitor-currency book price. No tag and no merge for 0.9.1.

## Commands run

```bash
php tests/test-exchange.php
php tests/test-cache-purge.php
for f in tests/test-*.php; do php "$f"; done
```

`tests/test-exchange.php`: 89 passed, 0 failed.
`tests/test-cache-purge.php`: 47 passed, 0 failed.
Every `tests/test-*.php` script passed.

## Remaining errors

None.
