=== ReactWoo Geo Commerce ===
Contributors: reactwoo
Requires at least: 6.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.9.1

WooCommerce rules and personalization on ReactWoo Geo Core.

== Description ==

Separate plugin for commerce-specific personalization. Requires **ReactWoo Geo Core** and **WooCommerce**. See Geo Core `docs/phases/phase-7.md` for integration checklist.

Cache-safe price mode (Settings) defaults to Auto. With no page-cache plugin, prices stay in the page HTML. See the FAQ for what AJAX mode does and does not cover.

== Installation ==

1. Install WooCommerce and ReactWoo Geo Core.
2. Activate this plugin.

== Frequently Asked Questions ==

= What does cache-safe price mode do? =

* **Off** leaves prices in the page HTML and does not set the `rwgc_country` vary cookie.
* **Auto** (default) matches Off when no known page cache is active. LiteSpeed is treated as varied: Geo Commerce registers `rwgc_country` on `litespeed_vary_cookies` and sends `X-LiteSpeed-Vary`, so each country can have its own cached page and the price stays in the HTML. Auto switches to AJAX when a known page cache is active and is not varied by `rwgc_country`.
* **AJAX** prints a placeholder for the price element and for catalog price HTML. The browser loads the localised price from `GET /wp-json/rwgcm/v1/prices` (and `/price`).

The cookie value is the Geo Core visitor country. The REST route does not trust the cookie or a `country` query argument. It is public, does not use a nonce, and sends `Cache-Control: no-store`. One batched request covers the placeholders on the page. Requests are limited per IP (filter `rwgcm_price_rate_limit`, default 40 a minute).

= Which caches count as varied? =

LiteSpeed varies when cache-safe mode is not Off. WP Rocket varies only when `rwgc_country` is already on `rocket_cache_dynamic_cookies`. W3 Total Cache varies when that cookie is in its configuration, or listed on `rwgcm_w3tc_vary_cookies`. WP Super Cache and Cloudflare APO are not varied.

= What if the visitor currency has no price-book amount? =

Auto (the default) converts the store-currency price when a rate exists: the price-book amount in the store currency if one is set, otherwise the regular or sale price. The geo pricing rule runs after that conversion. A fixed price in the visitor currency wins and is not discounted again. The same amount is shown on the catalog, the price shortcode, the block, the Elementor widgets, and the cache-safe price route, and it is what the cart, checkout, and Store API charge. The order stores the currency and the rate.

Off, or Auto with no rate, keeps the store currency. A fixed store-currency book price is then not run through the geo percent rule. Rates are entered under Geo Commerce → Settings (manual, or ECB / open.er-api.com). They refresh every 12 hours. A failed fetch uses the last saved provider rates, then manual rates. Saving rates clears the geo cache.

A cart that needs shipping still uses the store currency when shipping is not priced in the visitor currency. On that request the displayed total is the store-currency total.

= What stays server-rendered? =

Cart, checkout, the mini-cart, cart fragments, order emails, wp-admin, and the block editor. Catalog AJAX covers the shop, the product page, and product taxonomy archives. Homepage loops, the Store API, and product-collection blocks stay server-rendered. Variation dropdown prices (`woocommerce_available_variation`) stay server-rendered, so a cached product page that is not varied can still show the cached country's variation price after a variation is chosen. JSON-LD is left as the server rendered it. With JavaScript disabled the placeholder does not show a country price.

= What does Clear Geo cache purge? =

The product (with its parent and variations), the shop page, product category archives, and posts that use `[rwgcm_price]`, the `rwgcm/product-price` block, or the Elementor widgets `rwgcm-product-price` and `rwgcm-product-price-atomic`. That usage list is updated on `save_post`. The same purge runs when a price book (`_rwgcm_price_book`), the `rwgcm_pricing` option, or exchange rates are saved (`rwgcm_exchange_rates`, the `rwgcm_exchange_rate_options` filter, or the `rwgcm_exchange_rates_saved` action).

Adapters run only when that plugin is active: LiteSpeed (`litespeed_purge_post`, `litespeed_purge_url`), WP Rocket (`rocket_clean_post`, and `rocket_clean_files` for archive URLs), W3 Total Cache (`w3tc_flush_post`, and `w3tc_flush_url` for archive URLs), WP Super Cache (`wpsc_delete_post_cache`; archive URLs have no purge call), and Cloudflare APO (`cloudflare_purge_by_url`, `cloudflare_purge_url`, `cloudflare_purge_everything`) when those hooks are registered. Elementor CSS is regenerated for each affected Elementor post. The last purge time and counts are stored in `rwgcm_last_cache_purge`. Extensions can use the `rwgcm_cache_purged` action. The button requires the `manage_woocommerce` capability and a nonce.

== Changelog ==

= 0.9.1 =
* **Exchange-rate cache purge.** Saving rates, settings, and the provider cache in one request clears the geo cache once. A provider or cron refresh clears it only when a stored rate changes. Rates are compared to 6 decimal places, so a new fetch time alone does not purge.

= 0.9.0 =
* **Visitor currency when the price book has no amount.** A visitor whose currency has no fixed price-book price is charged the store-currency price (the book price in the store currency when one is set, otherwise the regular or sale price) converted at the current rate, then the matching geo pricing rule. £49 at 22.1 ZAR per GBP with ZA −15% is R920.47 before any rounding or markup. A fixed price in the visitor currency is unchanged and the percent rule is not stacked on it.
* **Where it applies.** Catalog price HTML, `[rwgcm_price]`, the `rwgcm/product-price` block, both Elementor price widgets, `GET /wp-json/rwgcm/v1/prices`, the cart, checkout, and the Store API use that amount. The order stores the currency and the rate that was used (`_rwgcm_exchange_rate`).
* **Rates.** Geo Commerce → Settings. Source is manual rates or an automatic provider (ECB reference rates, or open.er-api.com with an optional API key). Rates are cached for 12 hours and refreshed by WP-Cron. If the fetch fails, the last saved provider rates are used, then manual rates. With no rate the store currency is kept. Rounding can be none, whole units, or .99. An optional markup percent is applied to the rate. Saving rates clears the geo cache.
* **Default.** Conversion is Auto: it runs only when a rate exists. The source starts on Manual with no rates, so an upgrade does not change prices until a rate is entered or a provider fetch succeeds. Off keeps the 0.8.0 store-currency fallback even when rates exist. A cart that needs shipping still stays in the store currency when shipping is not priced in the visitor currency.

= 0.8.0 =
* **Cache purge.** Saving a price book, country pricing rules, or exchange rates purges the product (parent and variations), the shop, product category archives, and posts that use the price shortcode, block, or Elementor price widgets. A usage index tracks those posts on save. Adapters run only when LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, or the Cloudflare plugin is active. Elementor CSS is regenerated for affected Elementor posts.
* **Clear Geo cache.** Settings includes the button for users who can `manage_woocommerce`. The last purge stores the time and counts. The `rwgcm_cache_purged` action fires for extensions.
* **Cache-safe prices.** A `rwgc_country` vary cookie follows the Geo Core visitor country and is registered with LiteSpeed (`litespeed_vary_cookies` and `X-LiteSpeed-Vary`). The cookie is not set on REST responses. Cache-safe price mode is Off, Auto, or AJAX. Auto uses AJAX when a known page cache is active and not varied by country. AJAX placeholders load the price from a public, nonce-free REST route with `Cache-Control: no-store`. That route applies the visitor-country price book and pricing rules. Cart and checkout stay server-rendered. With no cache plugin, Auto keeps today's server-rendered prices and does not set the cookie. Limits are in the FAQ.

= 0.7.0 =
* **Product price:** Shortcode `[rwgcm_price]`, Gutenberg block `rwgcm/product-price`, and Elementor widgets `rwgcm-product-price` (classic) and `rwgcm-product-price-atomic` (V4) share one renderer. The amount comes from WooCommerce's filtered price getters, so price books and pricing rules match the product page, including tax display. A country rule such as ZA −15% is included when the cart would charge the store currency. Options cover the format (amount, symbol, or currency code), a billing-period suffix, a From prefix for the lowest variation, the struck-through regular price when on sale, and fallback text when the product is missing, not purchasable, or out of stock. Colour controls use site colour variables. The shortcode follows Geo Core's country cache vary and does not mark the page uncacheable.
* **Elementor:** Widgets register even when Elementor has already loaded. Atomic widgets register only while the atomic experiment is on.

= 0.6.0 =
* **Shipping-class costs:** A flat rate with shipping-class costs can be priced in another currency when every class cost and the base cost that will be charged has an amount in that currency (Cost (ZAR), and one field per class). If any of those amounts is blank, the method stays in the store currency and none of the amounts are swapped.
* **Block local pickup:** Price books → Shipping has one card per WooCommerce local pickup location. The store-currency cost is shown and is not saved. Amounts are stored in Geo Commerce (`rwgcm_pickup_location_costs`). A location id WooCommerce no longer uses is listed so it can be deleted, and it is not copied onto another location. Saving returns to the Shipping tab.
* **When the cart switches:** A cart that needs shipping uses the visitor currency only when every applicable rate, including block local pickup, has an amount in that currency. Otherwise it stays in the store currency, as before. Free-shipping minimums are unchanged.
* **Checkout blocks:** The Cart and Checkout blocks show the same "prices are shown in …" notice as classic checkout, and remove it when shipping is priced in the visitor currency.
* **Admin:** Currency fields have a visible helper and a decimal keypad. On a narrow screen the Shipping tab shows one currency column at a time.
* **Cart:** The shipping calculator clears the "prices are shown in the store currency" notice when the new address uses the store currency.

= 0.5.1 =
* **Cart notice:** On the classic cart, "Prices are shown in GBP because shipping to … isn't priced in ZAR." is a row inside the cart totals. The shipping calculator replaces that totals block, so the notice stays there, shows once, and goes away when the cart is priced in the visitor currency again. Checkout is unchanged.
* **Mini-cart:** After the shipping calculator updates the cart, the page posts `get_refreshed_fragments` itself (jQuery on `document.body`, debounced, including when a refresh is already in flight). The fragment request uses the shipping country the calculator just saved, so the header amount follows the cart totals. A theme header that is not the WooCommerce mini-cart widget is refreshed too (`rwgcm_header_cart_fragment_selectors`). A currency change drops cached `wc_fragments_*` session data.

= 0.5.0 =
* **Shipping in the visitor's currency:** Flat rate, Free shipping and (legacy) Local pickup methods get "Cost (ZAR)" / "Minimum order amount (ZAR)" fields for each price-book currency, under "Prices in other currencies (Geo Commerce)" in the method settings. WooCommerce's own cost formula ([qty], [cost], [fee]) and shipping tax run on the amount for the cart's currency. Admin screens and exports keep showing the store-currency values.
* **Which carts switch:** A shipped cart uses the visitor's currency when every enabled method in its shipping zone has an amount in that currency (coupon-only free shipping and zero costs need none). Otherwise it stays in the store currency, as before. Flat rates with shipping-class costs, carrier and other methods stay store-currency only in this release. The `rwgcm_price_book_shipping_priced` filter now defaults to this computed answer and receives the packages as a third argument.
* **Optional:** "Hide shipping methods not priced in the visitor's currency" (Price books) uses the visitor's currency when at least one method is priced and hides the others.
* **Checkout guard:** Each order shipping line records the currency its amount was entered in (`rwgcm_priced_currency`); an order whose shipping was not priced in the order currency is refused.
* **Address changes:** When the shipping address is in a zone not priced in the visitor's currency, the cart uses the store currency and the classic cart and checkout show "Prices are shown in GBP because shipping to … isn't priced in ZAR." for as long as that is the case. The Store API cart carries the same text in `extensions.rwgcm.shipping_currency_notice`.
* **Admin:** New read-only Shipping tab on Price books: zones × methods × currencies, with the reason a zone stays in the store currency.
* **Upgrade note:** Free shipping "Minimum order amount" is now compared with a per-currency minimum. If your site returned true from `rwgcm_price_book_shipping_priced`, its rates are still treated as priced (tagged `rwgcm_priced_source=filter`); enter per-currency amounts to drop the filter.

= 0.4.8 =
* **Payments by country:** The rwgcm-gateways log records each distinct fallback warning at most once a day (and once per request) instead of on every gateway check.
* **Admin:** New "Log payment-rule fallbacks" checkbox on Payments by country (on by default), and a `rwgcm_log_gateway_warnings` filter, to turn these warnings off.
* **Admin:** A disabled gateway that only has a currency list (no country or group rule) gets a soft info line instead of the warning. The "disabled because the store currency is …" hint is only shown for gateways saved as enabled that switch themselves off at runtime (such as Paystack in a GBP store). Other disabled gateways say "disabled in WooCommerce".
* **PHP notices:** Fixed the "get_cart was called incorrectly" notice logged since 0.4.5 when Geo Core resolved the visitor before `wp_loaded`.
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
