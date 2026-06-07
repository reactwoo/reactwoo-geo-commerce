# Changelog

All notable changes to **reactwoo-geo-commerce** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Rules UX (0.3.16 development):** Unified Pricing Rules and Product Overlays under shared Rules model with guided condition builder, multi-action rule builder, and human-readable summaries; legacy overlays/pricing deprecated with migration.

## [0.3.15] - 2026-06-06

### Fixed
- **i18n (WP 6.7):** Defer legacy pricing migration (which builds a translated label) from `plugins_loaded` boot to `init`, fixing `_load_textdomain_just_in_time` notices.

## [0.3.14] - 2026-06-06

### Fixed
- **i18n:** Queue textdomain via Geo Core `RWGC_I18n` on `plugins_loaded` priority 6 (WP 6.7 JIT fix with Geo Core 1.8.29).

## [0.3.13] - 2026-06-06

### Changed
- **Suite release:** Aligned with Geo Core 1.7.9 contextual admin shell (unified Targeting rules index includes commerce rules).

## [0.3.12] - 2026-06-06

### Changed
- **Admin IA:** Commerce section nav (Pricing rules, Offers, Merchandising, Availability); attribution under Insights; diagnostics under Settings.

## [0.3.11] - 2026-06-06

### Added
- **Phase 2:** Portable visibility rule builder on generic commerce rule edit (Geo Core evaluator).

## [0.3.8] - 2026-06-06

### Changed
- **Admin hub:** Submenus via `rw_geo_register_admin_submenu`, in-page nav via `rw_geo_render_inner_nav`.

## [0.3.7] - 2026-06-06

### Changed
- **Admin:** Register all Geo Commerce screens as submenus under Geo Core (`rwgc-dashboard`).

## [0.3.5] - 2026-06-06

### Changed
- **Targeting:** Commerce rule and overlay conditions evaluate through Geo Core `RWGC_Rule_Evaluator` and portable schema; GeoCore Pro hooks apply to pricing and overlays.

## [0.3.4.0] - 2026-06-06

### Added
- **Product overlays & rules:** Per-product overlay definitions; generic rules list/edit; diagnostics admin view.

## [0.3.3.0] - 2026-06-06

### Changed
- **Independent licensing:** Own platform client, JWT cache, and update-auth callback; explicit one-time import from other ReactWoo plugins.

## [0.2.22.0] - 2026-06-06

### Fixed
- **Pricing:** `RWGCM_Pricing_Calc::get_base_unit_price()` uses WooCommerce 3.2+ price APIs (fixes internal meta key notices).

## [0.2.20.0] - 2026-06-06

### Added
- **License:** Geo Commerce → License screen; filters `rwgc_reactwoo_license_key` / `rwgc_reactwoo_api_base`.

## [0.2.19.0] - 2026-06-06

### Added
- **UX:** Card-based rule builders for Pricing and Cart fees; preview panels; Attribution submenu.

## [0.2.8.0] - 2026-06-06

### Added
- **Coupon geo:** Allowed countries multiselect on coupons.

## [0.2.7.0] - 2026-06-06

### Added
- **Shipping:** Filter `rwgcm_package_rates` on `woocommerce_package_rates`.

## [0.2.5.0] - 2026-06-06

### Added
- **UTM / click-id attribution:** First-touch and last-touch cookies; order meta at checkout.

## [0.2.0.0] - 2026-06-06

### Added
- **Commerce pricing** screen: country-based percent or fixed rules applied to cart line unit prices.

## [0.1.1.0] - 2026-06-06

### Added
- Initial dashboard; merges cart context into `rwgc_geo_data`.

---

Full history: `readme.txt`.
