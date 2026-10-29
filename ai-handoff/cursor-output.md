# Cursor output

## Status

done

## Summary

Elementor 4.3 atomic product price registers after the V4 experiment autoloads Atomic_Widget_Base. Editor previews use Geo Core's simulated country when one is set, and stay on the store currency otherwise. Weather widgets show an editor placeholder when weather data is missing.

## Root cause

1. `is_active()` called `class_exists( Atomic_Widget_Base, false )` before Elementor had loaded that class, so `rwgcm-product-price-atomic` never registered.
2. Gutenberg block preview and the Elementor editor are REST or builder requests that force the store currency. `rwgc_preview_country` on the editor URL was not applied to the pricing rule, which still read `rwgc_get_visitor_country()`.

## Files changed

| File | Reason |
|------|--------|
| includes/integrations/elementor/class-rwgcm-elementor-atomic.php | Experiment check first, autoload the base class, register again on `elementor/elements/elements_registered` |
| includes/class-rwgcm-price-book-currency.php | Editor preview country from the request, referer, or rule-tester payload |
| includes/class-rwgcm-pricing-calc.php | Pricing rules use that preview country |
| includes/class-rwgcm-weather-strip.php | Editor placeholder when weather is missing |
| includes/class-rwgcm-weather-products-display.php | Editor placeholder when weather is missing |
| tests/test-elementor-atomic-autoload.php | Autoload test |
| tests/test-product-price.php | Editor country and weather placeholder |
| CHANGELOG.md | Notes under 0.7.0 |

## Commands run

```bash
php tests/test-product-price.php
php tests/test-elementor-widgets.php
php tests/test-elementor-atomic-autoload.php
php tests/test-*.php
python3 scripts/package_zip.py
```

`tests/test-product-price.php`: 81 passed, 0 failed.
`tests/test-elementor-widgets.php`: 14 passed, 0 failed.
`tests/test-elementor-atomic-autoload.php`: 9 passed, 0 failed.
Every `tests/test-*.php` script passed.
`reactwoo-geo-commerce-0.7.0.zip` rebuilt.

## Remaining errors

None.
