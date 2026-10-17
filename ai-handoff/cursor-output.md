# Cursor output

## Status

needs-review

## Summary

0.4.1 fixes three live 0.4.0 bugs. Variable and variable-subscription From/range HTML uses price-book amounts only when every variation has that currency; otherwise the store currency and its symbol are shown. REST (except Store API storefront), cron, WP-CLI, wp-admin, and variation sync stay on the store currency. Block checkout writes `_rwgcm_price_book_currency` and `_rwgcm_price_book_source`.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-price-book.php | Range decision and per-currency variation price hash |
| includes/class-rwgcm-price-book-apply.php | From/range HTML and subscription price strings |
| includes/class-rwgcm-price-book-currency.php | Force the store currency on writes and sync |
| includes/class-rwgcm-price-book-order.php | Store API checkout meta |
| includes/class-rwgcm-catalog-price.php | Do not geo-adjust prices while a write forces the store currency |
| includes/class-rwgcm-catalog-price-variable.php | Same guard for variation prices |
| tests/test-price-book.php | Range, hash, write-context, and Store API meta tests |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.4.1 |

## What was not changed

- Gateway country rules
- Geo Core detection
- No tag until the tests on this branch pass and the release is merged

## Commands run

```bash
php -l includes/class-rwgcm-price-book.php
php -l includes/class-rwgcm-price-book-currency.php
php -l includes/class-rwgcm-price-book-apply.php
php -l includes/class-rwgcm-price-book-order.php
php -l includes/class-rwgcm-catalog-price.php
php -l includes/class-rwgcm-catalog-price-variable.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — `tests/test-price-book.php` 86 passed; `tests/test-rules-unified.php` 8 passed.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
