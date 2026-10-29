# Changelog

All notable changes to **reactwoo-geo-commerce** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.0] - 2026-10-29

### Added
- **Localised product price.** `[rwgcm_price]` (optional `product_id` and `variation_id`; otherwise the current product), the `rwgcm/product-price` block, and Elementor elements `rwgcm-product-price` and `rwgcm-product-price-atomic` share `RWGCM_Product_Price`. Output uses WooCommerce's filtered `get_price()` / `get_regular_price()` and `wc_get_price_to_display()`, so `_rwgcm_price_book` and `rwgcm_pricing` percent/fixed rules apply as they do on the product page. Tax follows the shop display setting.
- Format `amount`, `symbol`, or `code`. Optional billing-period suffix (`period`, for example `/year`), or the subscription period when the product has one. `from` shows the lowest variation price. `sale` strikes through the regular price when the product is on sale. `fallback` (default "Unavailable") is used when the product is missing, not purchasable, or out of stock.
- Colour presets map to Elementor global colours and `theme.json` presets. Typography and colour inherit when no preset is chosen. The shortcode does not read `rwgc_cc`, set `DONOTCACHEPAGE`, or send nocache headers.

### Fixed
- **ZA rule on a GBP store.** When a product has no price in the visitor currency, the cart charges the store currency and applies the country rule. Catalog prices, price HTML, and the product price element now use that same discounted amount (ZA −15% of £100 is £85), instead of the undiscounted store price. A fixed ZAR book price is still shown in ZAR and is not discounted again.
- **Elementor widgets missing.** Commerce boots after Elementor has fired `elementor/loaded`, so the widgets and the editor preview route never registered. Registration now runs immediately when that action has already fired, and again on `elementor/widgets/register` if it is still pending. Weather widgets use the same path. Atomic elements register only while Elementor's atomic experiment is on.
- **Atomic product price on Elementor 4.3.** `Atomic_Widget_Base` is not loaded when widget registration starts, so a `class_exists` check without autoload skipped `rwgcm-product-price-atomic`. The V4 experiment is checked first, then the base class is autoloaded. Registration also runs later on `elementor/elements/elements_registered`.
- **Editor preview country.** Gutenberg and Elementor previews use Geo Core's `rwgc_preview_country` (request or the editor page referer) or the rule-tester preview country when one is set. With no simulated country they stay on the store currency. Weather products and the weather strip show a placeholder in the editor when visitor weather is missing.

## [0.6.0] - 2026-10-28

### Added
- **Per-currency shipping-class costs.** Flat rate instance settings gain `rwgcm_class_cost_{id}_{CUR}` and `rwgcm_no_class_cost_{CUR}` for each price-book currency except the store currency. The fields are on the WooCommerce method modal, under the existing currency heading, and use WooCommerce's own cost sanitizer. A blank field still means "not priced".
- **Block-checkout local pickup amounts.** `rwgcm_pickup_location_costs` maps a location id to a currency amount. Price books → Shipping edits them as stacked cards (location name and store-currency cost are read-only). Saving posts back to `admin.php?page=rwgcm-price-books&tab=shipping` and bumps the shipping rate cache. An id WooCommerce no longer has is an unused row the merchant can delete. Amounts are not copied onto another location, and `pickup_location_pickup_locations` is not written.
- **Checkout and cart block notice.** The blocks read `extensions.rwgcm.shipping_currency_notice` into a `role="status"` region. An empty string renders nothing, so the notice clears when the cart is priced in the visitor currency.

### Changed
- A flat rate is priced in a visitor currency only when every non-zero component (base cost and each shipping-class cost) has a manual amount. Until then the cart stays in the store currency and no component is swapped, so a visitor-currency base is never added to a store-currency class cost. Blank and numeric-zero class costs still need no amount.
- Block local pickup counts in that same decision when the method is enabled. Each enabled location needs an amount, unless its store-currency cost is zero. The manual amount is applied in `woocommerce_shipping_method_add_rate_args`, before WooCommerce calculates shipping tax. The location's own cost is left as stored.
- Currency inputs (flat rate, free-shipping minimum, legacy local pickup, and class costs) show helper text under the field and use `inputmode="decimal"`. Below 782px the Shipping matrix shows one currency column, chosen with a labelled select that does not save. The first column stays readable without sideways scrolling.
- Shipping-tab reasons: "class costs need a ZAR amount" and "carrier, store currency only".

### Fixed
- **Cart shipping calculator.** Calculating shipping to a country whose currency is the store currency (GB on a GBP store) clears the "prices are shown in GBP because shipping isn’t priced in ZAR" line. The calculator country is remembered the same way a checkout billing country is, and a fallback left by the previous address is dropped when the visitor currency is the store currency. Checkout already did this.

### Not in this release
- Opt-in conversion of numeric costs (phase 2), carrier and table-rate quotes (phase 3), coupon minimums, geo fee conversion, an exchange-rate feed, and per-currency decimals. No shipping zone, zone location, method order, or store-currency cost is migrated or rewritten.

## [0.5.1] - 2026-10-19

### Fixed
- **Classic cart shipping-currency notice.** The "Prices are shown in GBP because shipping to … isn't priced in ZAR." line is a row on `woocommerce_cart_totals_after_order_total`, inside the totals markup the shipping calculator replaces. It is not a WooCommerce notice, so cart.js no longer moves it to the top notice wrapper. It is rendered once while the cart is held in the store currency, and omitted when the cart is priced in the visitor currency again. Checkout still uses the `.rwgcm-shipping-currency-notice--checkout` fragment.
- **Header mini-cart after a currency switch.** The classic cart posts `get_refreshed_fragments` itself after `updated_cart_totals`, `updated_shipping_method`, `updated_wc_div`, and after the shipping-calculator request finishes. The script is bound with jQuery on `document.body` and does not depend on `wc-cart-fragments` (WooCommerce does not enqueue that file on the cart). A refresh in flight queues one more send, and the fragment response does not reset the debounce. The calculator writes the customer session before the cart HTML is flushed, so the fragment request resolves currency from that shipping country — the same destination the totals just used — instead of the geo visitor country still in the previous session. `woocommerce_cart_hash` and the fragments include the active currency, and a currency change drops cached `wc_fragments_*` session data. Themes whose header amount is not `div.widget_shopping_cart_content` get a fragment for `a.cart-contents` (filter `rwgcm_header_cart_fragment_selectors`); a selector the theme already filled is left as the theme rendered it.

## [0.5.0] - 2026-10-18

### Added
- **Per-currency shipping amounts** (option A of `qa/2026-10-18-per-currency-shipping-scope.md`). `RWGCM_Shipping_Prices` adds `rwgcm_cost_{CUR}` (flat_rate, legacy local_pickup) and `rwgcm_min_amount_{CUR}` (free_shipping) instance fields for every price-book currency except the store currency, via `woocommerce_shipping_instance_form_fields_{id}`. They save and delete with the method and reuse WooCommerce's own `sanitize_callback`, so a formula is validated like the store-currency cost.
- **Read-time swap** on `woocommerce_shipping_{id}_instance_option`: on the storefront and Store API, when the cart currency is not the store currency and an amount exists, `cost` / `min_amount` read as that amount. WooCommerce's formula, free-shipping check and shipping tax run unchanged. wp-admin, non-Store REST (exports), cron and CLI force the store currency and never see swapped values.
- **Computed "shipping priced"**: `rwgcm_price_book_shipping_priced` defaults to a decision from the zone configuration (every enabled method in each matched zone priced; coupon-only / no-minimum free shipping and zero costs count as priced; flat rates with class costs and other or carrier methods do not). The filter gets `$packages` as a third argument and can still force the answer.
- **Optional setting** `rwgcm_shipping_hide_unpriced` (Price books → "Hide shipping methods not priced in the visitor's currency", default off; filter `rwgcm_shipping_hide_unpriced`): a zone counts as priced when at least one method is priced, and unpriced rates are hidden in that currency (never all of them). Changing it flushes WooCommerce's rate cache.
- **Rate and order line meta** `rwgcm_priced_currency` (and `rwgcm_priced_source=filter` when the site filter declared an otherwise unpriced rate). WooCommerce copies rate meta to the order shipping line for classic and block checkout; it also appears in Store API `shipping_rates[].meta_data`.
- **Shipping-currency notice**: while a cart is held in the store currency because shipping to its address is not priced in the visitor's currency, the classic cart and checkout show "Prices are shown in GBP because shipping to … isn't priced in ZAR." It is rendered from the current state on every page load and checkout refresh (`update_order_review` fragment `.rwgcm-shipping-currency-notice--checkout`; on the cart inside the totals), so it shows on first load, never duplicates, and disappears when the cart is priced again. Visitors whose currency is the store currency never see it. Store API carts expose `extensions.rwgcm.shipping_fallback_currency` and `shipping_currency_notice`.
- **Price books → Shipping tab** (read-only): zones × methods × currencies, with amounts, reasons and a per-zone verdict; uses the Payments matrix layout (sticky first column, horizontal scroll).

### Changed
- **Checkout guard**: a non-store-currency order passes only when every shipping line is tagged with the order currency, instead of the site-wide `shipping_priced()` yes/no.
- The currency resolver looks up the zone from the customer's shipping destination instead of `WC_Cart::get_shipping_packages()`, which read `line_total` before WooCommerce had calculated it.

### Not in this release (see the scope doc)
- Per-currency flat-rate shipping-class costs, block-checkout "Local pickup" (pickup_location) costs, exchange-rate conversion and carrier conversion, coupon minimum amounts per currency.

## [0.4.8] - 2026-10-18

### Changed
- Payment-rule warnings in the `rwgcm-gateways` log are de-duplicated: each distinct warning (type, country, currency, gateway set) is logged at most once per 24 hours and once per request.

### Added
- "Log payment-rule fallbacks" checkbox on Payments by country (default on) and the `rwgcm_log_gateway_warnings` filter (default `true`).

### Fixed
- Payments by country no longer warns about a disabled gateway that appears only in a currency list; it shows a soft info line ("… is disabled in WooCommerce; its currency list has no effect"). Country/group rule warnings are unchanged.
- The store-currency explanation is only shown for gateways whose saved `enabled` is `yes` but which are disabled at runtime; plainly disabled gateways say "disabled in WooCommerce".
- No more "get_cart was called incorrectly … before the wp_loaded action" notices: the cart item count merged into Geo Core visitor data is only read after `wp_loaded` (Geo Core resolves the visitor earlier, during `init`).
- Text domain on four strings added in 0.4.5 and 0.4.7.

## [0.4.7] - 2026-10-18

### Fixed
- **Country rules emptied checkout when their gateway was unavailable (live: ZA → Paystack only, Paystack disabled).** `RWGCM_Gateway_Availability::filter_gateways()` (`woocommerce_available_payment_gateways`, priority 20) intersected the rule with WooCommerce's available list. When the rule named only an unavailable gateway, nothing was left. Commerce never added a gateway: it only filters the list WooCommerce passes in, which already excludes disabled or `is_available() === false` gateways. Now `apply_country_rule()` treats a rule with no usable gateway as unsatisfiable. "Usable" means available **and** allowed for the cart currency by the currency lists. The available gateways allowed for that currency are offered instead, and a warning is logged to `rwgcm-gateways`. The currency lists (`RWGCM_Gateway_Currency`, priority 30) still apply and are never relaxed: a ZAR cart is not offered Revolut when Revolut is listed for GBP/EUR only.
- **Nothing can take the currency.** The list stays empty (no gateway is offered for a currency the owner did not allow). Classic checkout replaces WooCommerce's "no available payment methods" text with "No payment method can take payments in ZAR right now…" (`woocommerce_no_available_payment_methods_message`), and the event is logged.
- **Block checkout vs classic (GBP cart, ZA address: no methods in blocks, Revolut in classic).** The rule country fell back to `WC()->customer->get_billing_country()` only on Store API cart routes. So the classic page (no billing typed yet) skipped the rules, while blocks applied the ZA rule and emptied the list. Both now use the chosen billing country, then the customer billing country WooCommerce shows. Combined with the unsatisfiable-rule fallback, both offer Revolut for that cart.

### Added
- **Payments by country warning** when a country/group rule or a currency list names a gateway that is disabled or not registered in WooCommerce (`disabled_rule_warning()`). A gateway saved as enabled but switched off only because of the store currency (Paystack in a GBP store) is not flagged, because it can still appear at a ZAR checkout (`saved_enabled_flags()`).
- `tests/test-gateway-rules.php` (22 tests).

## [0.4.6] - 2026-10-18

### Fixed
- **Shipping rate cache ignored the currency.** WooCommerce caches rates per package in the session (`WC_Shipping::calculate_shipping_for_package()`). Since WC 11.0 the cache hash ignores `subtotal` and `total`, and it never included the currency, so rates calculated in one currency could be reused after a switch (for example when `rwgcm_price_book_shipping_priced` is true and the visitor's currency changes). `woocommerce_cart_shipping_packages` now adds `rwgcm_currency` to each package when the active currency is not the store currency. Base-currency packages are unchanged, so normal stores keep their cache.

### Changed
- `rwgcm_price_book_shipping_priced` docblock: free shipping `min_amount` is one store-currency number, but WooCommerce compares it with the subtotal in the active currency. Per-currency minimums are planned for 0.5.0.

## [0.4.5] - 2026-10-17

### Fixed
- **Checkout flipped a geo visitor to the store currency (live, classic checkout).** A ZA guest saw R699 on `/cart/` and £39 on `/checkout/`. WooCommerce pre-fills billing with the store base country (GB), and `entered_billing_country()` fell back to `WC()->customer->get_billing_country()`, so the pre-filled default overrode the geo currency. Now:
  - Checkout billing and shipping (`default_checkout_billing_country`, `default_checkout_shipping_country`) and a new guest's default location (`woocommerce_customer_default_location_array`) default to the Geo Core visitor country when the customer has chosen none and the store sells there (`get_allowed_countries()` / `get_shipping_countries()`). Otherwise WooCommerce's default is kept.
  - Only a billing country the customer actually set overrides geo: posted this request (`update_order_review`, checkout), remembered from a checkout or Store API cart/checkout update-customer, or a logged-in customer's saved billing country. The WooCommerce customer-object fallback is removed (`RWGCM_Price_Book::chosen_billing_country()`).
  - A ZA guest who switches billing to GB still gets GBP. The whole cart still follows `currency_for_lines()`, and the order currency and checkout guard are unchanged.
- **Store API relabel did not apply on real WooCommerce (staging QA of 62f5061).** WooCommerce 11.2's `ProductSchema::get_item_response()` returns `prices` as a `stdClass` (`(object) prepare_product_price_response()`), and the walker only matched arrays, so nothing was relabelled. The hook is now `rest_request_after_callbacks` (it runs inside `WP_REST_Server::dispatch()`, which also covers batch and `rest_do_request()` preloading) plus `woocommerce_hydration_request_after_callbacks` (block hydration calls the Store API controller directly). Both arrays and objects are walked and keep their shape. Verified on a throwaway WordPress 7.1.3 + WooCommerce 11.2.0 site (SQLite, PHP 8.4) with `tests/integration/real-wc-check.sh`: an unpriced product goes from ZAR 800 to GBP 800 for a ZA visitor.
- **Rank Math JSON-LD.** Rank Math replaces WooCommerce's structured data, and its Product entity takes `priceCurrency` from `get_woocommerce_currency()`. A `rank_math/json_ld` filter (priority 99) relabels `Product`/`ProductGroup` entities for the viewed product when it has no book price in the active currency. Verified with Rank Math (free) on the same site: ZAR 8.00 becomes GBP 8.00.
- **Shipping charged as the visitor currency.** WooCommerce shipping rates (flat rate, Royal Mail, and so on) are store-currency amounts, and Commerce has no shipping price book. A ZA cart in ZAR showed R73.23 Royal Mail shipping on staging, and a £10 flat rate showed as R10 on the test site. Both are GBP numbers labelled ZAR. A cart that needs shipping now stays in the store currency unless `rwgcm_price_book_shipping_priced` returns true for that currency (`RWGCM_Price_Book::shipping_supports_currency()`, used inside `currency_for_lines()` support). The checkout guard also refuses a non-store-currency order with a shipping total above zero when shipping is not priced in it. Virtual and downloadable carts still switch.
- **Store API currency mislabel.** In 0.4.4, `/wc/store/v1/products` (and block product grids, cart `cross_sells`, and batch sub-responses) built `prices` from the unconverted store-currency amount but took `currency_code` and the symbol from `get_woocommerce_currency()`. A ZA visitor therefore got `currency_code=ZAR, price=2300` for a £23 product with no ZAR price book, which a block storefront renders as R23.00. A new `RWGCM_Price_Book_Store_Api` (`rest_post_dispatch`, `/wc/store/` routes only) rewrites the currency fields of any product node that has no book price in the active currency to the store currency. Amounts are unchanged. A variable product counts as priced only when every variation has a book price. Cart line items are not rewritten, because the whole cart already follows `currency_for_lines()`.
- **Product JSON-LD.** `offers[].priceCurrency` (including `priceSpecification`) reports the store currency for products without a book price in the active currency.

### Added
- **Checkout currency guard (defence in depth).** On `woocommerce_checkout_create_order` and `woocommerce_store_api_checkout_update_order_meta`, the plugin refuses an order whose currency is not the store currency while a line has no book price in that currency, and logs to `rwgcm-price-book`. `currency_for_lines()` should make this unreachable. The guard stops a gateway from ever charging a store-currency number as another currency.
- `tests/integration/real-wc-check.sh`: curl checks against a real WooCommerce site (Store API single and list, JSON-LD).
- `tests/test-checkout-country.php`: a ZA guest defaults to ZA and ZAR, an explicit GB choice gives GBP (posted and Store API), a saved GB account gives GBP, a geo country not sold to keeps the WooCommerce default, and mixed carts stay GBP while the guard agrees.
- `tests/test-store-api-currency.php`: Store API product and cart payloads for ZA, with and without a ZAR book, plus a mixed cart, cross-sells, the guard, and JSON-LD.

## [0.4.4] - 2026-10-17

### Fixed
- **Payments by country lists a gateway WooCommerce’s registry missed.** The screen re-reads the `woocommerce_payment_gateways` filter when it renders, skips gateways already loaded, and instantiates any missing gateway inside try/catch. Checkout still uses WooCommerce’s own list. A warning names each gateway found only that way, because checkout cannot offer it until WooCommerce registers it.
- **The last gateway column is easier to find.** A summary above the country table names every gateway, and the country column stays on screen while the table scrolls. Unconfigured Paystack is usually that last column.

## [0.4.3] - 2026-10-17

### Changed
- **Commerce navigation:** Price books (`admin.php?page=rwgcm-price-books`) and Payments by country (`admin.php?page=rwgcm-payments`) are first-class Commerce screens, not sections buried on Settings and not rules in the IF/THEN builder. The Commerce hub (`admin.php?page=rwgc-commerce-hub`) leads with Rules, Price books, Configure payment gateways, and Settings. The Commerce overview links to the same screens.
- **WooCommerce payments:** WooCommerce → Settings → Payments links to Payments by country. WooCommerce 11.2 strips third-party admin notices on the React Payments screen, so the link is a checkout settings field and a banner placed above `#experimental_wc_settings_payments_main`. Classic gateway settings (such as Paystack) get the same link as a title field, which WooCommerce does not save into the gateway option.
- **Mobile admin:** Rule-builder steps no longer draw a second card inside the page card. Commerce screens use border-box, full-width inputs, and less padding at 782px and 600px so the page introduction is not clipped under the sticky shell header.

### Fixed
- **Payments by country lists every registered gateway**, including ones WooCommerce has switched off. Paystack sets `enabled` to false while the store currency is GBP, so it was missing from the screen and could not be mapped. The list now comes from `payment_gateways()` and marks those gateways “disabled in WooCommerce”, including `paystack-one` through `paystack-five` when they are registered. A hint says Paystack shows as disabled because the store currency is GBP, and ReactWoo Geo enables it for the currencies listed (for example ZAR). The store currency is not changed globally, so WooCommerce’s Payments list can still show Action needed.
- **Saving no longer wipes mappings the form did not show.** Currency lists and country rules are merged. A blank currency field clears only that gateway, and an unchecked box removes only a gateway that was on the form.
- **Saving Payments by country** returns to `admin.php?page=rwgcm-payments` and shows the saved notice. The form posts to itself, so the HTTP referer is not a usable redirect target.

## [0.4.2] - 2026-10-17

### Added
- **Gateway currencies:** Geo Commerce → Settings can list the currencies a payment gateway is allowed to charge. Leave a gateway blank and nothing about it changes. Paystack WooCommerce Payment Gateway 5.8.5 (`paystack`) accepts NGN, GHS, USD, KES, RWF, ZAR, XOF, and EGP. Its `woocommerce_paystack_supported_currencies` filter only widens that list, so a GBP store still fails `is_valid_for_use()` until the gateway’s own settings screen (`page=wc-settings&tab=checkout&section=paystack`) is shown a mapped currency such as ZAR. Product saves, REST, cron, and WP-CLI stay on the store currency.
- **Checkout:** A configured gateway is offered only when the cart or order currency is one of its currencies. Paystack’s payment init and verify already send `$order->get_currency()` and the amount in minor units (`total * 100` for ZAR).

## [0.4.1] - 2026-10-17

### Fixed
- **Variable price HTML:** Variable and variable-subscription From/range text uses the price book when every variation has the visitor currency. Otherwise the range stays in the store currency, with the store-currency symbol. The variation price hash is per currency, and product saves do not cache a visitor currency.
- **Product saves:** WooCommerce REST (except Store API storefront routes), cron, WP-CLI, wp-admin, and variation sync no longer run in the visitor currency. Subscription variation sync cannot write a switched amount into `_subscription_price` or the min/max variation price meta.
- **Block checkout orders:** `woocommerce_store_api_checkout_update_order_meta` stores `_rwgcm_price_book_currency` and `_rwgcm_price_book_source`, the same meta classic checkout already stored.

## [0.4.0] - 2026-10-16

### Added
- **Price books:** Admins can store fixed regular, sale, and (for subscriptions) sign-up prices per currency on simple products and variable-subscription variations. Default currencies are ZAR, GBP, EUR, and USD; `rwgcm_price_book_currencies` and `rwgcm_price_book_country_currencies` extend the lists.
- **Currency selection:** Billing country decides the currency. Before checkout, Geo Core’s visitor country (`rwgc_get_visitor_country`) selects it. Cart, checkout, and the order use that currency and the fixed price, with no conversion. A currency with no price falls back to the store currency.
- **Country map:** Geo Commerce → Settings edits the country-to-currency map. Defaults are South Africa ZAR, United Kingdom GBP, United States USD, and the eurozone EUR. Any other known country uses the store currency, or a fallback currency set on that screen.
- **Payment gateways:** The same screen assigns which enabled WooCommerce gateways are offered for each billing country, and for a Geo Core country group when that registry has groups. A country with no assignment keeps every enabled gateway. `woocommerce_available_payment_gateways` applies the rule at checkout and when changing a subscription payment method.
- **Subscription renewals:** Renewal orders keep the subscription currency, the price already stored on the subscription, and the gateway already on the subscription.
- **Settings:** “Keep store-currency geo price adjustments” is migrated on, so existing GBP (store-currency) pricing rules still apply when a product has no fixed price in the store currency.

### Fixed
- **Payment gateways (block checkout):** The country rule also applies to Store API cart and batch responses. The block checkout renders the `payment_methods` list from those responses, so it used to offer every enabled gateway even though the classic checkout and the Store API checkout route were already narrowed.

### Notes
- Revolut Gateway for WooCommerce reads `get_woocommerce_currency()` for the checkout charge and then requires the WooCommerce order currency to match. Price books set both. Card payments accept ZAR, GBP, EUR, and USD when the merchant has those currency accounts.
- Catalog HTML follows Geo Core’s server-resolved country. Geo Core varies full-page caches with the `rwgc_cc` cookie and the LiteSpeed vary group `rwgc_cc_{ISO2}`. A cache that ignores that vary serves one country’s prices to everyone. Cart and checkout are marked uncacheable. This plugin does not do its own IP lookup.

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
