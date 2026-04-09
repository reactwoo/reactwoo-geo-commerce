=== ReactWoo Geo Commerce ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.3.4.0

WooCommerce overlays and pricing rules on ReactWoo Geo Core.

== Description ==

Separate plugin for commerce-specific personalization. Requires **ReactWoo Geo Core** and **WooCommerce**. See Geo Core `docs/phases/phase-7.md` for integration checklist.

== Installation ==

1. Install WooCommerce and ReactWoo Geo Core.
2. Activate this plugin.

== Changelog ==

= 0.3.4.0 =
* **Product overlays & rules:** Per-product overlay definitions with sanitization and storefront resolution; **generic rules** (list/edit) with condition evaluation, actions, and migration from legacy pricing options where applicable; **diagnostics** admin view; catalog/pricing apply path updates alongside existing commerce pricing.

= 0.3.3.1 =
* **Updates:** Harden update publish workflow.

= 0.3.3.0 =
* **Independent licensing:** Geo Commerce now uses its own platform client, JWT cache, and update-auth callback. Automatic cross-plugin license migration and runtime fallback are removed; importing a key from another ReactWoo plugin is now an explicit one-time admin action.

= 0.2.22.0 =
* **Pricing:** `RWGCM_Pricing_Calc::get_base_unit_price()` uses `WC_Product::get_regular_price( 'edit' )` and `get_price( 'edit' )` instead of `get_meta( '_regular_price' )` / `_price`, fixing WooCommerce 3.2+ `is_internal_meta_key` notices and critical errors when storefront price filters run (e.g. after Elementor exit on product-related templates).

= 0.2.21.0 =
* **Updates:** Registers **`RWGC_Satellite_Updater`** (Geo Core 1.3.4+) — update checks use the ReactWoo API + license JWT; **`download_url`** is R2-signed.

= 0.2.20.0 =
* **License:** **Geo Commerce → License** screen to save a ReactWoo product key (`rwgcm_settings`); filters `rwgc_reactwoo_license_key` / `rwgc_reactwoo_api_base` (priority 16); migration from Geo AI, Geo Optimise, or Geo Core keys; **License** link on Geo Core dashboard card.

= 0.2.19.0 =
* **UX (Phase 3):** **Pricing** and **Cart fees** use **card-based rule builders** (name, enabled, move up/down, duplicate, remove, plain-English summary) plus **preview panels** (pricing simulator; fee list by country). **`rwgcm-rule-cards.js`** renumbers indices after reorder.
* **Data:** Rules support optional **`label`**, **`active`** (pricing + fees). Inactive rows are stored but skipped at runtime. Backward compatible with existing options.
* **Simulator:** **`RWGCM_Simulator`** helpers for explanations and previews (uses existing pricing math).
* **Attribution:** New **Attribution** submenu — UTM/click-id toggle, recent orders with visitor country; overview dashboard uses **Geo Core `RWGC_Admin_UI`** stat cards when available.
* **Styles:** **`rwgc-suite.css`** enqueued with Geo Core admin; expanded **`rwgcm-admin.css`** for builder layout.

= 0.2.18.0 =
* **Admin:** **Top-level Geo Commerce menu** (cart icon) — own Overview, Pricing rules, Cart fees, and **Help** (MaxMind vs product keys, merchant glossary). No longer nested under Geo Core’s sidebar.
* **Geo Core dashboard:** When Geo Commerce is active, a **Geo Commerce** summary card links into this plugin.
* **UX:** Merchant-first overview (steps, status), developer JSON/hooks under **Technical details**; enqueue **rwgcm-admin.css** plus Geo Core admin styles.

= 0.2.16.0 =
* **Admin UI:** **Geo Commerce** dashboard, **Commerce pricing**, and **Commerce fees** screens use **`rwgc-wrap`**, shared inner nav, **`rwgc-card`**, and a **grid** for pricing/fee status cards (aligned with Geo Core / Geo Elementor-style admin).
* **Navigation:** Registers **Geo Commerce**, **Commerce pricing**, and **Commerce fees** on **`rwgc_inner_nav_items`**.
* **Fees screen:** **Tax class** column header added to match fee rule rows (Woo tax class when taxable).

= 0.2.15.0 =
* **WooCommerce orders list:** **Visitor country** column is **sortable** (HPOS + legacy orders table) by **`_rwgcm_visitor_country_iso2`**.

= 0.2.14.0 =
* **WooCommerce orders list:** **Visitor country** column (HPOS + legacy) from **`_rwgcm_visitor_country_iso2`** (`RWGCM_Admin_Orders_List`).

= 0.2.13.0 =
* **Variable products:** storefront min/max and variation prices use the same geo rules as simple products (`RWGCM_Catalog_Price_Variable` — **`woocommerce_variation_prices_*`**, **`woocommerce_get_variation_prices_hash`** with visitor country so WooCommerce price transients stay correct per region).

= 0.2.12.0 =
* **Dashboard:** **REST discovery** — open **Geo Core capabilities JSON** when REST is enabled (`rwgc_get_rest_capabilities_url`).

= 0.2.11.0 =
* **Cart fees:** optional **`tax_class`** per row (WooCommerce **Tax class** when **Taxable** is checked) — passed to **`WC_Cart::add_fee()`** for correct fee VAT. **`rwgcm_cart_fees`** filter rows may include **`tax_class`** when **`taxable`** is true.

= 0.2.10.0 =
* **Pricing:** also skip WooCommerce Composite Products–style lines (`composite_parent` / `composite_item`) by default; same **`rwgcm_skip_pricing_for_cart_item`** filter.

= 0.2.9.0 =
* **Cart fee rules** screen (Geo Core → **Commerce fees**): country + fee name + amount + taxable; merged into **`rwgcm_cart_fees`** via **`RWGCM_Fee_Rules_Apply`** (priority 5). Filter **`rwgcm_fee_rule_rows`**. Dashboard **Fee rules status**.
* **Pricing:** skip bundle child lines by default (`bundled_by`); filter **`rwgcm_skip_pricing_for_cart_item`**.

= 0.2.8.0 =
* **Coupon geo:** Usage restriction — **Allowed countries (Geo Commerce)** multiselect (WooCommerce country list). Meta **`_rwgcm_allowed_countries`**. Filters **`rwgcm_coupon_allowed_for_visitor`**, **`rwgcm_coupon_valid_when_country_unknown`** (if visitor country unknown).

= 0.2.7.0 =
* **Shipping:** filter **`rwgcm_package_rates`** on **`woocommerce_package_rates`** (priority 99) — adjust rates using visitor ISO2 from Geo Core.

= 0.2.6.0 =
* Dashboard **Pricing status** (rules active, saved rule count).

= 0.2.5.0 =
* **UTM / click-id attribution:** optional first-touch and last-touch cookies from query string; merged into order meta at checkout (`_rwgcm_*_ft` / `_rwgcm_*_lt`). Dashboard toggle. Filters **`rwgcm_store_utm_on_orders`**, **`rwgcm_attribution_query_keys`**, **`rwgcm_attribution_cookie_ttl`**.

= 0.2.4.0 =
* **`rwgcm_cart_fees`** filter → `woocommerce_cart_calculate_fees` (add_fee rows). **`rwgcm_checkout_order_meta`** for extra `_rwgcm_*` order meta at checkout.

= 0.2.3.0 =
* **Order attribution:** on checkout, store visitor **ISO2** and capture time on the order; **`rwgcm_order_attributed`** action; filter **`rwgcm_order_visitor_geo`**. Order admin shows geo line when present.

= 0.2.2.0 =
* **Storefront price parity** (hooks only): `woocommerce_product_get_price` matches cart rules; **`RWGCM_Pricing_Calc`**; filter **`rwgcm_apply_catalog_price`** (default true). Skips variable parent products.

= 0.2.1.0 =
* Pricing rules: optional **product category** multi-select (Woo `product_cat`, first 200 terms). **First matching rule wins** per line item — list more specific rows above catch-all (empty categories = all products in country).

= 0.2.0.0 =
* **Commerce pricing** screen: country select (WooCommerce list), percent or fixed-per-unit rules, enable toggle. Applies to cart line unit prices via Geo Core visitor country; filter **`rwgcm_adjusted_unit_price`**.

= 0.1.2.0 =
* Action **`rwgcm_before_cart_totals`** on `woocommerce_before_calculate_totals` (priority 5) — extension point for deterministic, rule-based cart pricing (no default price changes).

= 0.1.1.0 =
* **Geo Core → Geo Commerce** dashboard; merges `rwgc_commerce_cart_items` into `rwgc_geo_data` when cart exists; filter `rwgcm_geo_data`.
