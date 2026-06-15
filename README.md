# ReactWoo Geo Commerce

**Version:** 0.3.15  
**Plugin slug:** `reactwoo-geo-commerce`

## Overview

Geo Commerce adds WooCommerce-specific geo personalization on top of **ReactWoo Geo Core**: pricing rules, cart fees, product overlays, shipping and coupon restrictions, and order attribution. Rule **eligibility** is evaluated through Geo Core's `RWGC_Rule_Evaluator` (via `RWGCM_Targeting_Adapter`); this plugin applies **commerce outcomes** only. Commercial licensing uses **react-license** and updates via **reactwoo-api**.

## Position in family

```
Geo Core (detection + evaluator)
    ↓
Geo Commerce (+ WooCommerce) — pricing, fees, overlays, shipping, coupons
    ↓ optional
GeoCore Pro — portable campaign/audience/time/weather on commerce conditions
```

Requires **Geo Core** and **WooCommerce**. Does not implement a second geo engine.

## Key Features

### Available

- Unified **Rules** model: conditions + multi-action builder (pricing, badges, overlays, notices)
- Geo-based **pricing** (percent or fixed per unit; category-aware; first match wins)
- **Cart fees** by visitor country (optional tax class)
- **Product overlays** and per-product rule assignments
- **Coupon** allowed-countries restriction
- **Shipping** rate filtering via `rwgcm_package_rates`
- **Order attribution**: visitor ISO2, UTM/click-id first/last touch
- **Variable product** geo pricing with correct transients per region
- Portable visibility rule builder on commerce rule edit (Geo Core evaluator)
- Independent license screen and `RWGC_Satellite_Updater` (JWT via **reactwoo-api**)
- Commerce section in Geo Core platform shell (Rules, Products, Settings, Logs)

### In Progress

- Migration from legacy country rows and product overlays into unified Rules table (automatic on upgrade; legacy fallback at runtime)
- Admin IA consolidation under **Rules** primary nav (0.3.16+ work in development)

### Planned

- **Weather facets merchandising** — product “good for this weather” tags, shop boost, `[rwgcm_weather_products]` / Gutenberg block / Elementor widget; see **`docs/WEATHER-MERCHANDISING.md`** and Geo Core **`docs/WEATHER-FACETS-MERCHANDISING-PLAN.md`**
- Expanded cart/checkout condition types
- Deeper HPOS reporting exports

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| ReactWoo Geo Core | 1.7+ (1.8.x for platform shell) |
| WooCommerce | 3.2+ (HPOS supported for order columns) |
| react-license | Valid Geo Commerce product key |
| reactwoo-api | JWT login and `/api/v5/updates/check` |

## Installation

1. Install and activate **WooCommerce** and **ReactWoo Geo Core**.
2. Configure MaxMind in Geo Core Integrations.
3. Install and activate **reactwoo-geo-commerce**.
4. Enter license key under **Geo → Commerce → Settings** (or License).

```bash
npm run package:zip   # development packaging
```

## Configuration

| Area | Location |
|------|----------|
| License | Commerce → Settings |
| Rules | Commerce → Rules (unified builder) |
| Product assignments | Commerce → Products |
| Attribution | Insights (when shell active) or Commerce settings |
| Diagnostics | Commerce → Logs / Settings |

Filters: `rwgcm_apply_catalog_price`, `rwgcm_cart_fees`, `rwgcm_package_rates`, `rwgcm_coupon_allowed_for_visitor`, `rwgcm_skip_pricing_for_cart_item`.

## Feature Entitlements

| Feature | Requires |
|---------|----------|
| Geo pricing & fees | Geo Commerce license |
| Portable campaign/audience on commerce rules | GeoCore Pro + Commerce license |
| Core visitor country | Geo Core (free MaxMind) |
| Plugin updates | Commerce license JWT via reactwoo-api |

## Integrations

| Integration | Purpose |
|-------------|---------|
| **Geo Core** | Visitor country, `RWGC_Rule_Evaluator`, REST `/capabilities` |
| **GeoCore Pro** | Portable condition hooks on commerce rule rows |
| **react-license** | Product key, domain binding |
| **reactwoo-api** | Auth, commercial updates (R2 signed `download_url`) |
| **WooCommerce** | Products, cart, checkout, coupons, shipping |

## Developer Notes

- Constant: `RWGCM_VERSION`; fires `rwgcm_loaded`.
- Targeting bridge: `RWGCM_Targeting_Adapter` → portable schema → `RWGC_Rule_Evaluator::matches()`.
- Pricing: `RWGCM_Pricing_Calc`, `RWGCM_Catalog_Price_Variable`.
- Suite docs: `AGENTS.md`, Geo Core `docs/phases/phase-7.md`.

## Known Limitations

- First matching pricing rule wins per line item — order rules carefully.
- Bundle/composite child lines skipped by default for pricing (filterable).
- Legacy overlay/pricing options migrate automatically but remain as fallback if unified rules unavailable.
- Shipping and coupon rules are country-based; city-level rules require Geo Elementor or portable Pro conditions where applicable.

## Release Readiness

| Area | Status |
|------|--------|
| Pricing, fees, coupons, shipping | **Shipped** |
| Order attribution | **Shipped** |
| Unified Rules UX | **In Progress** (0.3.16+) |
| Portable rules + GeoCore Pro | **Shipped** |

## Compatibility

| Component | Version |
|-----------|---------|
| WordPress | 6.2+ |
| PHP | 7.4+ |
| Geo Core | 1.7.x – 1.8.x |
| WooCommerce | 3.2+ with HPOS |
| GeoCore Pro | Optional 0.1.x |
| react-license | 1.0.x |
| reactwoo-api | 0.1.x |

## Roadmap

- Complete Rules-first admin (deprecate separate pricing/overlay screens)
- Merchandising and availability hub cards
- Export attribution reports

## Support

- In-plugin Help and Commerce dashboard
- [ReactWoo support](https://reactwoo.com/support)

## License

GPLv2 or later.
