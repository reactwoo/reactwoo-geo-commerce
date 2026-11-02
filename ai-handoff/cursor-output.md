# Cursor output

## Status

done

## Summary

QA16's Checkout notice stick and loading placeholders come from a second `GET /wc/store/v1/cart` plus `receiveCart` racing the block's own update-customer request. Setting `#shipping-country` with `element.value` alone does not start that request. The Checkout block no longer refetches. The notice is reconciled when `isCustomerDataUpdating`, `isApplyingCoupon`, and checkout `isCalculating` are clear, and when `lastCartUpdate` changes. Draft only. Not tagged and not merged.

## Files changed

| File | Why |
|------|-----|
| assets/js/shipping-currency-notice.js | Wait for the address update to settle, then clear or add the notice by id. Do not refetch on the Checkout block. |
| tests/test-shipping-prices.php | Source check that the checkout guard is before `refetchCart()` and that the settle selectors are read. |
| CHANGELOG.md | 0.10.0 note. |

## What was not changed

- Fallback calculation, zone data, and the Shipping admin layout
- Cart block refetch after a settled country change
- No merge and no `v*` tag

## Commands run

```bash
for f in tests/test-*.php; do php "$f" || exit 1; done
```

All passed. No WordPress site is running here. The manual steps are in the PR.

## Remaining errors

None.
