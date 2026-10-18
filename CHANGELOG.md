# Changelog

All notable changes to **reactwoo-geo-commerce** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
