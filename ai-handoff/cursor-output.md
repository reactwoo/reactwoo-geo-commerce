# Cursor output

## Status

done

## Summary

0.10.0 still keeps phase 1 shipping on the GBP fallback. QA on `7b526f3` found the Checkout block was not rendering the shipping-currency notice, and the Price books → Shipping table overflowed the admin page by about 20px at 1280px. The notice now goes into the Cart and Checkout block notice lists from `extensions.rwgcm.shipping_currency_notice`, and the shipping matrix is contained in the page. Draft only. Not tagged and not merged.

## Files changed

| File | Why |
|------|-----|
| assets/js/shipping-currency-notice.js | Dispatch an info notice into `wc/cart` and `wc/checkout`. Remove it when the string is empty. A `role="status"` region is inserted only when the block notice list is not showing the text. The order-meta slot is not used, because that fill is not mounted. |
| assets/css/shipping-currency-notice.css | The notice wraps and stays within the screen, including below 782px. |
| includes/class-rwgcm-shipping-prices.php | Load the script on block cart and checkout, including when `has_block()` misses a template. Dependencies include `wp-notices`. |
| includes/class-rwgcm-shipping-block.php | Same script dependencies. |
| admin/css/rwgcm-admin.css, includes/class-rwgcm-price-book-admin.php | The shipping matrix cannot widen the price books page. |
| CHANGELOG.md, readme.txt | 0.10.0 notes for the notice and the table. |
| tests/test-shipping-prices.php | Source checks for the notice dispatch and the contained matrix. |

## What was not changed

- Shipping amounts, zone data, exchange rates, the cache purge, country-code normalisation, and the satellite updater
- The classic cart and classic checkout notice
- No merge and no `v*` tag

## Commands run

```bash
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-shipping-block.php
php -l includes/class-rwgcm-price-book-admin.php
php tests/test-shipping-prices.php
```

`tests/test-shipping-prices.php`: 211 passed, 0 failed. `php -l` reported no syntax errors. No WordPress site is running here, so the block notice and the 1280px table need a browser pass.

## Remaining errors

None.
