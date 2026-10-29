# Cursor output

## Status

done

## Summary

Localised product price for Geo Commerce 0.7.0. Shortcode, Gutenberg block, and both Elementor widgets share `RWGCM_Product_Price`. Amounts come from WooCommerce's filtered price getters, so price books and pricing rules match the product page. Not merged and not tagged.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-product-price.php | Shared renderer, shortcode, cache notes |
| includes/class-rwgcm-product-price-block.php | Server-rendered `rwgcm/product-price` block |
| blocks/product-price/block.json, blocks/product-price/index.js | Block metadata and editor controls |
| assets/css/product-price.css | BEM styles, inherited type, site colour variables |
| includes/integrations/elementor/class-rwgcm-elementor-product-price.php | Classic widget `rwgcm-product-price` |
| includes/integrations/elementor/class-rwgcm-elementor-atomic-product-price.php | Atomic element `rwgcm-product-price-atomic` |
| includes/integrations/elementor/templates/product-price.html.twig | Atomic editor shell |
| includes/integrations/elementor/class-rwgcm-elementor.php | Register the classic widget |
| includes/integrations/elementor/class-rwgcm-elementor-atomic.php | Register the atomic element and preview |
| includes/class-rwgcm-plugin.php | Boot the renderer and block |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md | Version 0.7.0 |
| tests/test-product-price.php | Country, rule, tax, sale, From, fallback, cache |
| tests/test-gateway-fallback.php | Version assertion is now 0.7.0 |

## What was not changed

- Pricing math (`RWGCM_Pricing_Calc`, price-book filters). A fixed book price still replaces a percent rule, same as the product page.
- No merge and no tag.

## Commands run

```bash
php -l includes/class-rwgcm-product-price.php
php -l includes/class-rwgcm-product-price-block.php
php -l includes/integrations/elementor/class-rwgcm-elementor-product-price.php
php -l includes/integrations/elementor/class-rwgcm-elementor-atomic-product-price.php
php tests/test-product-price.php
php tests/test-gateway-fallback.php
python3 scripts/package_zip.py
```

`tests/test-product-price.php`: 68 passed, 0 failed.
`tests/test-gateway-fallback.php`: 17 passed, 0 failed.
`python3 scripts/package_zip.py` wrote `reactwoo-geo-commerce-0.7.0.zip` (130 entries). The zip contains the renderer, block, both Elementor classes, the Twig shell, and `assets/css/product-price.css`. The zip is gitignored.
Every `tests/test-*.php` script passed (0 failed).

## Remaining errors

None.
