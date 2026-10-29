# Cursor output

## Status

done

## Summary

Staging fixes on the 0.7.0 product-price branch. A GBP-store ZA visitor with no ZAR book price now sees the −15% rule on the catalog and in the price element, matching the cart. Elementor classic widgets (including weather) and the atomic element register after Elementor has already loaded. Atomic widgets stay unloaded while the experiment is off. Not merged and not tagged.

## Root cause

1. Catalog currency stays on the visitor currency (ZAR) even when the product has no ZAR book. `allow_base_adjustment()` then skips the geo rule, and price HTML printed the raw GBP amount. The cart falls back to GBP for that line and applies the rule.
2. `elementor/loaded` fires on `plugins_loaded` before Geo Commerce boots, so `boot_elementor()` never ran. No widgets and no preview route.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-catalog-price.php | Apply the rule when the cart will charge the store currency |
| includes/class-rwgcm-catalog-price-variable.php | Same guard for variation prices |
| includes/class-rwgcm-price-book-apply.php | Price HTML uses that discounted store-currency amount |
| includes/class-rwgcm-product-price.php | Element shows the same amount |
| includes/class-rwgcm-plugin.php | Boot Elementor if `elementor/loaded` already fired |
| includes/integrations/elementor/class-rwgcm-elementor.php | Register classic widgets if `elementor/widgets/register` already fired |
| includes/integrations/elementor/class-rwgcm-elementor-atomic.php | Same for atomic widgets and the preview route; inactive experiment returns before loading atomic classes |
| tests/test-product-price.php | GBP-store ZA −15% case |
| tests/test-elementor-widgets.php | Late Elementor boot, weather widgets, atomic on/off |
| CHANGELOG.md, readme.txt | Note the fixes under 0.7.0 |

## What was not changed

- A fixed ZAR book price is still shown in ZAR and is not discounted again.
- Version stays 0.7.0. No merge and no tag.

## Commands run

```bash
php tests/test-product-price.php
php tests/test-elementor-widgets.php
php tests/test-*.php
python3 scripts/package_zip.py
```

`tests/test-product-price.php`: 74 passed, 0 failed.
`tests/test-elementor-widgets.php`: 14 passed, 0 failed.
Every `tests/test-*.php` script passed.
`reactwoo-geo-commerce-0.7.0.zip` rebuilt (130 entries, gitignored) and contains the updated catalog, price, and Elementor classes.

## Remaining errors

None.
