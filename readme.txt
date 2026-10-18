=== ReactWoo Geo Commerce ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.4.8

WooCommerce rules and personalization on ReactWoo Geo Core.

== Description ==

Separate plugin for commerce-specific personalization. Requires **ReactWoo Geo Core** and **WooCommerce**. See Geo Core `docs/phases/phase-7.md` for integration checklist.

== Installation ==

1. Install WooCommerce and ReactWoo Geo Core.
2. Activate this plugin.

== Changelog ==

= 0.4.8 =
* **Payments by country:** The rwgcm-gateways log records each distinct fallback warning at most once a day (and once per request) instead of on every gateway check.
* **Admin:** New "Log payment-rule fallbacks" checkbox on Payments by country (on by default), and a `rwgcm_log_gateway_warnings` filter, to turn these warnings off.
* **i18n:** Fixed the text domain on four strings added in 0.4.5 and 0.4.7.

= 0.4.7 =
* **Payments by country:** Rules only narrow the gateways WooCommerce already offers. A country rule whose gateways are all disabled or unavailable no longer empties checkout: the available gateways allowed for the cart currency are offered instead. Currency lists are never relaxed. If no gateway can take the currency, classic checkout says so.
* **Block checkout:** Uses the same billing country as classic checkout for country rules, so a GBP cart with a South African address gets the same methods in both.
* **Admin:** Payments by country warns when a rule names a gateway that is disabled or not registered in WooCommerce.

= 0.4.6 =
* **Shipping:** Shipping rates are recalculated when the visitor's currency changes. WooCommerce could reuse rates cached in the other currency.
* **Docs:** The `rwgcm_price_book_shipping_priced` filter warns that free-shipping minimum amounts are compared in the visitor's currency.

= 0.4.5 =
* **Shipping:** A cart that needs shipping stays in the store currency, because shipping rates are store-currency amounts. Use the `rwgcm_price_book_shipping_priced` filter if your rates are entered in another currency.
* **Rank Math:** Product JSON-LD from Rank Math reports the store currency for products without a price-book entry in the visitor's currency.
* **Checkout currency:** A visitor's checkout billing and shipping country default to their geo country when the store sells there, and a pre-filled billing country no longer switches the cart to the store currency. Only a billing country the customer chose (or a logged-in customer's saved one) changes the currency.
* **Currency:** Store API product data (block product grids, single product, cart cross-sells, batch) and product JSON-LD no longer label a store-currency amount with the visitor's currency. A product without a price-book entry in the active currency reports the store currency, matching the classic price HTML. Checkout refuses an order whose currency has no price-book amount for one of its lines.

= 0.4.4 =
* **Gateways:** Payments by country lists a gateway even when WooCommerce’s own registry missed it, and warns that checkout cannot offer that gateway until WooCommerce registers it. A summary above the table names every gateway, and the country column stays visible while the table scrolls.

= 0.4.3 =
* **Navigation:** Price books and Payments by country are their own Commerce screens. The Commerce hub leads with Rules, Price books, Configure payment gateways, and Settings. WooCommerce → Settings → Payments links to the per-country screen from the checkout settings output, because WooCommerce 11.2 removes third-party admin notices on the React Payments page.
* **Gateways:** Payments by country lists every registered gateway, including ones WooCommerce has disabled (Paystack on a GBP store). Saving keeps mappings for gateways the form did not show, and returns to the Payments screen.
* **Mobile:** Rule steps are no longer cards inside cards. Inputs use the full width, and padding is tighter at 782px and 600px.

= 0.4.2 =
* **Gateway currencies:** Optional per-gateway currency list. A gateway’s own settings screen can see a mapped currency when the store currency is not one it accepts. Checkout offers that gateway only when the cart or order currency is in the list. Paystack keeps charging the order currency.

= 0.4.1 =
* **Variable prices:** The From/range on variable and variable-subscription products uses price-book amounts only when every variation has that currency. Otherwise it keeps the store-currency amount and the store-currency symbol.
* **Saved prices:** REST (except the Store API storefront), cron, WP-CLI, wp-admin, and variation sync stay on the store currency, so a visitor currency is not written into variation or subscription price meta.
* **Block checkout:** Store API orders store the price-book currency and source meta.

= 0.4.0 =
* **Price books:** Fixed prices per currency (ZAR, GBP, EUR, USD, filterable) on simple products and variable-subscription variations, including recurring price, sale price, and subscription sign-up fee.
* **Currency:** Before checkout, Geo Core’s visitor country selects the currency. Once a billing country is entered, that country decides. No exchange-rate conversion. Missing prices fall back to the store currency.
* **Country map:** Editable under Geo Commerce → Settings. Defaults: ZA ZAR, GB GBP, US USD, eurozone EUR. Other countries use the store currency or a configured fallback.
* **Payment gateways:** Assign enabled gateways per billing country, or per Geo Core country group. Unassigned countries keep every enabled gateway. Renewals keep the gateway already on the subscription.
* **Orders:** Cart, checkout, and order currency follow that choice. Subscription renewals keep the original currency and price.
* **Cache:** Catalog prices follow Geo Core’s `rwgc_cc` / `rwgc_cc_{ISO2}` vary. Cart and checkout are not cached.
* **Revolut:** Active WooCommerce currency matches the order currency so Revolut Gateway for WooCommerce charges the order currency.
* **Pricing rules:** Existing store-currency geo adjustments stay on unless turned off under Geo Commerce → Settings.

= 0.3.26 =
* Elementor Atomic: register Weather Products and Weather Strip twins beside the classic widgets.

= 0.3.25 =
* **Release:** CI dual-uploads mutable R2 `latest/{slug}.zip` + `latest.json` for My Account store downloads.

= 0.3.21 =
* **Product editor:** Unregister legacy General-tab weather output; Geo Core tab now invokes the weather renderer directly.

= 0.3.20 =
* **Product editor:** Move weather facet tagging into the GeoCore product data tab (checkbox grid); show facets even when live weather is not connected.
* **Catalogue boost:** Honour per-product boost exclude meta from the GeoCore tab.

= 0.3.19 =
* **Fix:** PHP parse error in product weather admin inline script (jQuery `$` in double-quoted PHP string).

= 0.3.18 =
* **Geo AI snapshot:** Append `geo_commerce` rule metadata and commerce_rule→product relationships to Geo Core site intelligence snapshots (no order PII or prices).

= 0.3.16 =
* **Rules UX:** Unified Pricing Rules and Product Overlays under a shared Rules model with guided condition builder, multi-action rule builder, and human-readable summaries.
* **Admin IA:** Primary nav uses Rules; Products screen for rule assignments; legacy overlays/pricing deprecated with migration.
* **Runtime:** Unified display rules apply through rule evaluator with legacy overlay fallback.

= 0.3.15 =
* **i18n (WP 6.7):** Defer legacy pricing migration (which builds a translated label) from `plugins_loaded` boot to `init`, fixing `_load_textdomain_just_in_time` notices for the `reactwoo-geo-commerce` domain.

= 0.3.14 =
* **i18n:** Queue textdomain via Geo Core `RWGC_I18n` on `plugins_loaded` priority 6 (WP 6.7 JIT fix with Geo Core 1.8.29).

= 0.3.13 =
* **Suite release:** Aligned with Geo Core 1.7.9 contextual admin shell (unified Targeting rules index includes commerce rules).

= 0.3.12 =
* **Admin IA:** Commerce section nav (Pricing rules, Offers, Merchandising, Availability); attribution under Insights; diagnostics under Settings; dashboard hidden from section tabs.

= 0.3.11 =
* **Phase 2:** Portable visibility rule builder on generic commerce rule edit (Geo Core evaluator); platform shell titles and no duplicate inner nav on Commerce screens.

= 0.3.8 =
* **Admin hub (reference):** Submenus via `rw_geo_register_admin_submenu`, in-page nav via `rw_geo_render_inner_nav` + `rwgcm_inner_nav_items`, hub breadcrumb to Geo Core, and CSS to hide detail screens from the wp-admin sidebar (use inner nav).

= 0.3.7 =
* **Admin:** Register all Geo Commerce screens as **submenus under Geo Core** (`rwgc-dashboard`) instead of a separate top-level admin menu, for a unified ReactWoo Geo hub experience. Screen URLs (`?page=rwgcm-*`) are unchanged.

= 0.3.6 =
* **Build / repo:** `package_zip.py` now bundles the `assets/` tree (icons) like other Geo satellites; expanded `.gitignore` (`*.zip`, `.cursor/`, PHPUnit cache); added `AGENTS.md` and `.cursor/rules` for suite-aligned workflow and shared-targeting reminders.

= 0.3.5 =
* **Targeting:** Commerce rule and overlay condition groups now evaluate through Geo Core `RWGC_Rule_Evaluator` and shared portable schema (`RWGC_Targeting_Rule_Set_Schema`), so Geo Core Pro campaign/audience/time hooks apply to pricing and overlays; legacy flat evaluation remains only when Geo Core classes are unavailable.

= 0.3.4.1 =
* **License login:** `RWGCM_Platform_Client::get_access_token()` now applies `rwgc_auth_login_body`; `RWGCM_Settings::filter_auth_login_body` registers on init for parity with Geo Core / Geo AI / Geo Optimise.

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
