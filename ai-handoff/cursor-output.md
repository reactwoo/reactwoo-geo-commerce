# Cursor output

## Status

needs-review

## Summary

Staging QA on 0.4.2 and 0.4.3 failed because Payments by country only listed enabled gateways, so Paystack (enabled false on a GBP store) could not be mapped, and saving then replaced the stored rules with the gateways the form did show. 0.4.3 now lists every registered gateway, merges saves, returns to the Payments screen, and puts the WooCommerce Payments link on surfaces WooCommerce 11.2 still renders. Version stays 0.4.3. Not merged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-gateway-availability.php | List every registered gateway, disabled badge data, merge country rules, Paystack disabled hint |
| includes/class-rwgcm-gateway-currency.php | Merge currency lists instead of replacing them |
| includes/class-rwgcm-price-book-admin.php | Render disabled gateways, post the rows that were shown, redirect from the form’s return field |
| includes/class-rwgcm-admin.php | Payments link via checkout settings, admin footer banner, and classic gateway title field |
| admin/css/rwgcm-admin.css | Disabled-in-WooCommerce badge |
| tests/test-price-book.php, tests/test-gateway-currency.php, tests/test-admin-nav.php | List, merge, and redirect coverage |
| CHANGELOG.md, readme.txt | 0.4.3 notes for the QA fixes |

## What was not changed

- Version stays 0.4.3
- Store currency is not forced on the WooCommerce Payments overview
- 0.4.1 write lock and the 0.4.2 scoped currency override
- No merge and no tag

## Commands run

```bash
php -l includes/class-rwgcm-gateway-availability.php
php -l includes/class-rwgcm-gateway-currency.php
php -l includes/class-rwgcm-price-book-admin.php
php -l includes/class-rwgcm-admin.php
php tests/test-admin-nav.php
php tests/test-gateway-currency.php
php tests/test-price-book.php
php tests/test-rules-unified.php
```

## Build / test result

pass — admin nav 19 passed; gateway currency 36 passed; price book 96 passed; rules 8 passed.

## Remaining errors

```
(none)
```

## If still blocked (do not guess again)

1. **Suspected root cause:**
2. **Evidence:**
3. **Next file to inspect:**
4. **Exact log needed:**
