=== ReactWoo Geo Commerce ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.2.16.0

WooCommerce overlays and pricing rules on ReactWoo Geo Core.

== Description ==

Separate plugin for commerce-specific personalization. Requires **ReactWoo Geo Core** and **WooCommerce**. See Geo Core `docs/phases/phase-7.md` for integration checklist.

== Installation ==

1. Install WooCommerce and ReactWoo Geo Core.
2. Activate this plugin.

== Changelog ==

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
