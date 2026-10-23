# Per-currency shipping rates

**Status:** draft for review. No product decision is locked by this file.
**Plugin:** ReactWoo Geo Commerce 0.5.1 (`RWGCM_VERSION`, readme Stable tag).
**Scope of this change:** design only. No functional code, no version bump, no tag.

This document plans how a cart that needs shipping can be charged in the visitor currency (for example ZAR on a GBP store) without rewriting the merchant’s WooCommerce shipping zones.

The rule decided on 18 Oct 2026 still holds and is what 0.5.1 ships: a cart that needs shipping stays in the store currency until shipping to that address is priced in the visitor currency. Virtual and downloadable carts still switch. This document does not propose turning that rule off.

## Problem

Geo Commerce shows catalogue and cart prices in the currency for the visitor’s country. The country comes from Geo Core (`rwgc_get_visitor_country()`), then from the billing country once the shopper has chosen one. The store currency on this install is GBP. South Africa maps to ZAR.

Payment gateways are assigned per country and can be limited to currencies. Paystack on this store is the South Africa gateway and settles ZAR. Revolut settles GBP (and other currencies only when the merchant has listed them and enabled the matching Revolut currency account).

WooCommerce shipping zones, flat rates, free-shipping thresholds, local pickup, table-rate plugins, and carrier plugins store or quote amounts in the store currency. Before 0.4.5 those amounts were shown in the visitor currency unchanged, so a £10 flat rate rendered as R10 and a Royal Mail quote of £73.23 rendered as R73.23. Paystack would then have been asked to charge that figure in ZAR.

0.4.5 stopped the mislabel: the cart stays in GBP unless shipping is priced in the visitor currency. 0.5.0 added manual per-currency amounts for three method types (option A). 0.5.1 fixed the classic-cart notice and the header mini-cart after the shipping calculator changes country. Most live zones are still unpriced in ZAR, so a physical cart for a South African visitor still checks out in GBP, and Paystack does not see a ZAR total.

## How the GBP fallback works

One order has one currency. The active currency is `get_woocommerce_currency()`, which Geo Commerce filters.

### Who chooses the currency

`RWGCM_Price_Book_Currency::filter_currency()` runs on `woocommerce_currency` at priority 20.

It leaves the store currency in place for wp-admin (except storefront `wc-ajax`), WP-Cron, WP-CLI, product saves, and REST requests that are not Store API (`/wc/store/`). Catalog HTML, classic cart, classic checkout, and Store API cart and checkout may switch.

Resolution order inside `resolve_active_currency()`:

1. Pay-for-order uses the existing order currency.
2. A subscription renewal or a change-payment request uses the subscription currency.
3. Otherwise the country decides a preferred currency. A billing country the shopper actually entered wins. Until then, Geo Core’s visitor country is used. The map is the price-book country map (built-in: ZA → ZAR, GB → GBP, US → USD, eurozone → EUR). An unmapped country uses the configured fallback, which is the store currency when blank.
4. Once the cart exists, every line must have a fixed price-book price in that preferred currency **and** shipping must support it. `RWGCM_Price_Book::currency_for_lines()` returns the store currency if any of those checks fail.

There is no exchange-rate step. Price books are fixed amounts (`RWGCM_Price_Book` states that nothing there converts one currency into another).

### When shipping blocks the switch

`RWGCM_Price_Book::shipping_supports_currency()` returns true when the cart does not need shipping, or when the preferred currency is the store currency, or when `RWGCM_Price_Book_Currency::shipping_priced()` is true.

`WC()->cart->needs_shipping()` is the needs-shipping check. A cart of only virtual and downloadable products skips it and can still switch to ZAR.

`shipping_priced()` looks up **the customer’s shipping destination**, not the Geo Core visitor country. It builds a destination-only package from `WC()->customer` shipping country, state, and postcode, then asks `RWGCM_Shipping_Prices::computed_priced()`. It does not call `WC_Cart::get_shipping_packages()`, because currency is resolved while line totals are still being calculated.

The zone is `WC_Shipping_Zones::get_zone_matching_package()`. Currency does not choose a zone. A South African visitor who calculates shipping to the United Kingdom is matched to the GB zone. If that zone is not priced in ZAR, the cart stays in GBP. That is why 0.5.1 persists the customer session on `woocommerce_calculated_shipping` before the cart HTML is flushed: the following `get_refreshed_fragments` request must resolve currency from the calculator’s destination.

If every product line is priced in the preferred currency and shipping is not, `shipping_fallback_currency()` remembers that preferred code. The shopper sees:

> Prices are shown in GBP because shipping to South Africa isn’t priced in ZAR.

Visitors whose currency is already GBP do not see it.

### What “priced” means in 0.5.1

`computed_priced()` requires every enabled method in every matched zone to be priced. With **Hide shipping methods not priced in the visitor’s currency** on (`rwgcm_shipping_hide_unpriced`, default off), a zone is priced when at least one enabled method is priced, and the others are removed from the rate list. The last remaining rate is never removed.

`method_status()` decides from the method’s stored instance settings:

| Method | Priced in ZAR when |
| --- | --- |
| `flat_rate` | `rwgcm_cost_ZAR` is non-blank, or the store-currency cost is blank or zero. Any non-blank `class_cost_*` or `no_class_cost` makes the method unpriced, even if a ZAR cost is filled in. |
| `local_pickup` (legacy) | Same cost rule as flat rate. |
| `free_shipping` | Requires coupon only, or has no minimum. A minimum, “either”, or “both” needs `rwgcm_min_amount_ZAR`, unless the store-currency minimum is blank or zero. |
| Anything else | Not priced. This includes block-checkout local pickup (`pickup_location`), table-rate plugins, and carrier plugins (Royal Mail and similar). |

The filter `rwgcm_price_book_shipping_priced` still runs and can force true. A forced rate is tagged `rwgcm_priced_source=filter`. Forcing true tells checkout that the rate amount is already in the visitor currency. It does not convert the number.

### How a manual amount is applied

For `flat_rate`, `free_shipping`, and legacy `local_pickup`, `woocommerce_shipping_{id}_instance_option` (priority 20) returns `rwgcm_cost_{CUR}` or `rwgcm_min_amount_{CUR}` instead of `cost` or `min_amount` when the cart currency is not the store currency and the field is non-blank.

WooCommerce then evaluates the flat-rate formula, the free-shipping comparison, and shipping tax itself. Admin, non-Store REST, cron, and CLI take the `forces_store_currency()` path, so those screens keep reading the real GBP cost.

`woocommerce_package_rates` (priority 50) writes rate meta `rwgcm_priced_currency`. WooCommerce copies rate meta onto the order shipping line for classic checkout and for block checkout, and exposes it on Store API `shipping_rates[].meta_data`.

The checkout guard (`RWGCM_Price_Book_Store_Api::guard_order_currency`, on `woocommerce_checkout_create_order` and `woocommerce_store_api_checkout_update_order_meta`) rejects a non-store-currency order unless every product line has a price-book price and every shipping line’s `rwgcm_priced_currency` equals the order currency. The shopper sees “Prices in your cart changed currency. Please review your cart and try again.” The log source is `rwgcm-price-book`.

WooCommerce caches calculated rates in the session. Since WooCommerce 11.0 that hash ignores subtotal and total and does not include currency. `RWGCM_Shipping::filter_cart_shipping_packages()` adds `rwgcm_currency` to each package only when the active currency is not the store currency, so a switch cannot reuse a GBP rate as a ZAR rate. Packages in GBP are left unchanged.

### What the shopper and the gateway see today

Classic cart: the notice is a row on `woocommerce_cart_totals_after_order_total`, inside the totals table the shipping calculator replaces. It is not a WooCommerce notice, so cart.js does not move it into the top notice wrapper.

Classic checkout: `.rwgcm-shipping-currency-notice--checkout` is rendered on load and replaced in place by `woocommerce_update_order_review_fragments`.

Block checkout and other Store API clients: `extensions.rwgcm.shipping_fallback_currency` and `extensions.rwgcm.shipping_currency_notice` are registered on the cart schema. The Checkout block does not render that notice by itself.

Header mini-cart: the classic cart posts `get_refreshed_fragments` after cart totals refresh. The fragment request uses the shipping country saved by the calculator.

Gateway offer (`RWGCM_Gateway_Availability` at priority 20, then `RWGCM_Gateway_Currency` at priority 30):

- A ZA country rule can name Paystack alone. If Paystack is not actually available, the rule is treated as unsatisfiable and other available gateways that accept the cart currency are offered. The currency list is never relaxed.
- Paystack’s own `is_valid_for_use()` is false while `get_woocommerce_currency()` is GBP. Geo Commerce does not change the WooCommerce store currency. It only shows Paystack a mapped currency on Paystack’s own settings screen, and it offers Paystack at checkout when the **cart** currency is one Paystack is allowed to charge (ZAR).
- While shipping holds the cart in GBP, a ZAR-only Paystack is not offered. A GBP-capable gateway such as Revolut is. That is the current ZA physical-goods checkout.
- When shipping is priced in ZAR, the cart, the order, and `get_woocommerce_currency()` are ZAR. Paystack initialises and verifies with `$order->get_currency()` and `total * 100` (ZAR uses two minor units). Revolut’s checkout widget charges `get_woocommerce_currency()` and then requires the WooCommerce order currency to match. Shipping is part of that total. The guard exists so a gateway is never handed a GBP shipping figure labelled ZAR.

Renewals skip the guard and keep the subscription currency and the totals already stored. A later change to a shipping amount does not reprice an existing renewal.

## What is already stored (0.5.0 option A)

Manual amounts live as extra keys on the method instance option WooCommerce already owns:

- Option name: `woocommerce_{method_id}_{instance_id}_settings`
- Flat rate and legacy local pickup: `rwgcm_cost_ZAR`, `rwgcm_cost_EUR`, `rwgcm_cost_USD`, and any other price-book currency except the store currency
- Free shipping: `rwgcm_min_amount_{CUR}`

The fields are added with `woocommerce_shipping_instance_form_fields_{id}` and use WooCommerce’s own `sanitize_callback`, so a formula is checked the same way as the GBP cost. They save and delete with the method. The GBP `cost` and `min_amount` keys are not rewritten.

Price books → Shipping (`admin.php?page=rwgcm-price-books&tab=shipping`) is a read-only zones × methods × currencies matrix. It reuses the payments matrix layout (sticky first column, horizontal scroll) and prints a one-line verdict per zone (“ZAR carts stay in GBP” / “ZAR carts use ZAR”). Each zone name links to WooCommerce → Settings → Shipping for that zone.

0.5.0 explicitly left these for later, and they are still open:

- Per-currency flat-rate shipping-class costs
- Block-checkout local pickup (`pickup_location`) costs
- Exchange-rate conversion and carrier conversion
- Coupon minimum amounts per currency

There is no exchange-rate table, option, or feed in this plugin. “Existing exchange rates” are not available to call. Product prices cannot be used as a rate source: a ZAR price-book price is an amount the merchant typed, not a multiple of the GBP price.

## Constraint

Do not alter the merchant’s real shipping zones.

That means later work must not:

- Insert, delete, or reorder zones, zone locations, or method instances
- Write a converted figure back into `cost`, `min_amount`, `class_cost_*`, `no_class_cost`, or a pickup location’s `cost`
- Create a parallel zone per currency
- Make currency part of zone matching
- Edit table-rate plugin rows or carrier settings
- Change zone IDs

Allowed shapes, because they leave GBP behaviour and zone matching untouched:

- Extra keys inside the existing instance settings array (already used by option A)
- A separate Geo Commerce option for rates, pickup overrides, and method flags
- Read-time substitution while the request currency is not the store currency
- Rate meta on the calculated `WC_Shipping_Rate` (already used)

Admin, export, cron, and CLI must keep reading the GBP cost. That path already exists (`forces_store_currency()`).

## Options

### A. Manual amount on the method

**Status:** shipped for flat-rate cost, free-shipping minimum, and legacy local pickup cost. Not shipped for class costs or block local pickup.

The merchant types the amount in each price-book currency. Blank means the method is not priced, so a cart shipped to that zone stays in GBP (unless hiding is on and another method in the zone is priced).

This matches price books: the charged figure is one the merchant entered. Free-shipping thresholds belong here, because the cart subtotal is a sum of price-book prices, not a conversion of the GBP subtotal. A £60 threshold converted with any rate would be compared with a ZAR subtotal that was never that rate times the GBP subtotal.

Flat-rate formulas (`10 * [qty]`) work when the merchant enters the whole formula in the currency field. WooCommerce evaluates it after the read-time swap.

Class costs do not work yet. WooCommerce adds `class_cost_{id}` and `no_class_cost` to the base cost inside `WC_Shipping_Flat_Rate`. Swapping only the base cost would add a ZAR base to a GBP class cost. 0.5.0 refuses the whole method instead of mixing the two. Any follow-up has to swap every component or none.

Block local pickup stores a cost on each location in the `pickup_location_pickup_locations` option. That screen does not use `woocommerce_shipping_instance_form_fields_*`, and unknown keys on a location are at risk of being stripped when WooCommerce saves the screen. A manual ZAR cost has to live in a Geo Commerce option keyed by location id.

Table-rate plugins and carriers have no single cost field. A manual amount cannot represent a table or a live quote without a second rate engine. They stay unpriced under this option.

### B. Convert the store-currency rate with a merchant-entered rate

A rate would be a new Geo Commerce setting: how many units of ZAR equal 1 GBP, typed by the merchant. Nothing in Geo Core or Geo Commerce supplies this number today.

Conversion is only coherent for a **numeric literal** store-currency cost (and numeric class costs). The converted string is what WooCommerce evaluates, so shipping tax stays a percentage of a ZAR cost.

It is not coherent for:

- Free-shipping minimums, coupon minimums, or any threshold compared with a price-book subtotal
- Formulas that include `[cost]` or `[fee]`, because those tokens are evaluated against the cart contents. Once the cart is ZAR, `[cost]` is already a ZAR subtotal. Multiplying the finished rate by an FX rate would convert a mixed number. A pure `N * [qty]` (or `[qty] * N`) can be converted by scaling `N` only, because quantity is not a currency amount. Any other formula stays manual.
- Carrier quotes, until a later phase that converts the calculated rate and can prove the source number was in GBP

The converted amount must be the amount stored on the shipping line. A display-only relabel is the 0.4.5 bug: Paystack and Revolut charge the order currency and the order total.

Rounding uses the store’s price decimals (`woocommerce_price_num_decimals`), the same rounding as price-book amounts. This work does not add per-currency decimal places. Zero-decimal currencies are a follow-up if they are added to the price book. Paystack’s `total * 100` for XOF and RWF is a pre-existing gateway behaviour and is not part of shipping.

Default for this option is off. A blank manual field must keep today’s meaning (“not priced, cart stays in GBP”). Turning conversion on is a separate choice per method, not a reinterpretation of blanks saved in 0.5.0.

### C. Mix, with a fixed precedence

Use A where it already exists, and allow B only as an explicit opt-in for costs that are safe to scale.

Precedence for each method and currency:

1. Store currency: always the real WooCommerce cost. No substitution.
2. Non-blank `rwgcm_cost_{CUR}` or `rwgcm_min_amount_{CUR}`: that manual amount. It wins over a rate.
3. Method flagged “convert when blank”, a rate stored for that currency, and a convertible numeric cost: the converted amount. The method counts as priced.
4. Otherwise: not priced. The zone rule (every method, or at least one if hiding is on) decides whether the cart stays in GBP.

Free-shipping minimums never reach step 3. Coupon-only and zero-minimum free shipping stay priced with no amount, as they do now.

This is the recommended approach. Section “Phased work” below splits it into pull requests that can ship independently. Phase 1 does not enable conversion. Existing stores keep the GBP fallback until a merchant types an amount or, later, opts in to a rate.

## Compatibility

### Shipping zones and methods

Zone matching stays WooCommerce’s destination match (country, state, postcode). The currency resolver and the rate calculator must keep using that same destination. Geo Core’s visitor country selects the preferred currency on the catalogue; it must not select the zone.

| Source | Cart currency if we follow the precedence above | Zone data written |
| --- | --- | --- |
| Flat rate, numeric cost, manual ZAR cost | ZAR, WooCommerce evaluates the ZAR cost | None |
| Flat rate with class costs | ZAR only after every class cost and the no-class cost have a ZAR figure or a convertible literal. Until then, GBP, as in 0.5.1 | None |
| Flat rate formula with `[cost]` or `[fee]` | GBP until a manual ZAR formula is entered | None |
| Free shipping, coupon only or no minimum | ZAR with no amount | None |
| Free shipping with a minimum | ZAR only when `rwgcm_min_amount_ZAR` is set. The comparison runs against the ZAR subtotal | None |
| Legacy local pickup | Same as flat rate cost | None |
| Block local pickup | ZAR only when a side-option cost exists for that location. The location `cost` in `pickup_location_pickup_locations` stays the GBP cost | Side option only |
| Table-rate plugin (`table_rate`, Flexible Shipping, and similar) | GBP in phases 1–2. Phase 3 can convert the emitted `WC_Shipping_Rate` cost when the merchant opts that method id in | None in the plugin’s tables |
| Carrier plugin | Same as table rate. A live quote is a GBP number until an opted-in conversion multiplies the calculated cost and its tax lines | None in the carrier settings |

Multi-package carts (per-item or per-vendor shipping) stay in GBP when any matched zone is unpriced. `zones_priced()` already requires every package zone to pass.

Shipping tax is a percentage applied by WooCommerce to the rate cost. Substituting a real ZAR cost before calculation makes the tax a ZAR amount. Converting a rate after WooCommerce has calculated tax means converting each entry in the rate’s taxes array as well. Percentage tax classes do not need their own currency amounts. A third-party fixed shipping tax in GBP would be out of scope and would keep the cart in GBP.

### Classic checkout, block checkout, and the Store API

Both checkouts calculate shipping through `woocommerce_package_rates` and copy rate meta onto the order. The guard covers both create-order hooks. Classic checkout already shows the GBP explanation and refreshes it from `update_order_review`. The shipping calculator writes the customer session before the response is flushed (0.5.1).

The Store API already returns the explanation on `extensions.rwgcm`. The Cart and Checkout blocks do not render it. A block integration has to read `shipping_currency_notice` and show it as status text, including on the empty string so a later currency switch removes it. Shipping rate rows themselves need no block-specific price maths: the Store API returns the rate cost WooCommerce calculated in the active currency.

`rwgcm_currency` on the package must remain part of the rate-cache hash. Phase 2 also bumps WooCommerce’s `shipping` transient version when a rate value changes, the same way toggling `rwgcm_shipping_hide_unpriced` does today. Otherwise a session can keep a rate calculated with the previous ZAR-per-GBP figure.

### Payment settlement

The shipping line is part of the order total. The gateway charges that total in the order currency. Geo Commerce does not convert at payment time.

Worked example, GBP store, ZA visitor, ZAR price books, Paystack listed for ZAR and assigned to ZA, Revolut listed for GBP:

| Shipping state | Cart and order currency | Shipping line | Gateway |
| --- | --- | --- | --- |
| Zone not priced in ZAR (today, for any carrier, class cost, or blank flat rate) | GBP | GBP amount, meta `rwgcm_priced_currency=GBP` or absent | Revolut if available. Paystack is not offered, because the cart is GBP. |
| Flat rate has `rwgcm_cost_ZAR` (and no class costs) | ZAR | ZAR amount, meta `ZAR` | Paystack. Revolut is offered only when ZAR is on Revolut’s currency list and the Revolut account can take ZAR. |
| Guard sees a ZAR order whose shipping meta is not ZAR | Order is refused | — | No charge is created. |

Paystack amount is `total * 100` in the order currency. The ZAR shipping line must already be in rand. Revolut reads `get_woocommerce_currency()` for the widget and then checks the order currency and total. Those two have to match the figure the shopper confirmed, so conversion and rounding happen before the order is saved, not in the gateway.

Subscription renewals keep the currency and shipping totals of the subscription. They must not pass through the new rate.

Geo fee rules are skipped when the cart currency is not the store currency (`RWGCM_Fee_Rules_Apply`). A ZAR cart does not gain those GBP fee amounts. Fee conversion is out of scope here; the shipping work must not start adding the fees back.

Coupon minimum and maximum spend are still single store-currency amounts on the coupon. WooCommerce compares them with the active cart total. A “minimum £50” coupon on a ZAR cart compares 50 with a rand subtotal. Per-currency coupon thresholds are a separate piece of work. Free-shipping methods that require a coupon keep working, because they have no amount to convert.

## Admin UX

Merchants already have two places, and both should stay:

1. **WooCommerce → Settings → Shipping → zone → method.** This is where GBP costs and the 0.5.0 currency fields live. It is WooCommerce’s modal. Geo Commerce should keep adding fields to that modal for flat rate, free shipping, and legacy local pickup, and should not replace the zone editor.
2. **Geo Commerce → Price books → Shipping.** This is the read-only “will this zone switch?” view, plus the hide-unpriced setting on the Settings tab. It is the right place for a verdict, for pickup-location amounts WooCommerce’s screen will not show, and later for the rate table.

### Method modal (flat rate, free shipping, legacy local pickup)

Keep one labelled input per price-book currency, stacked under a single heading. The heading and the blank placeholder already say that a blank field leaves the cart in the store currency. Keep that sentence. Do not change a blank into “convert”.

Each input:

- Visible label (`Cost (ZAR)`, `Minimum order amount (ZAR)`), not a placeholder-only field
- `inputmode="decimal"` and a full-width field from 782px down (the price-book screens already force full-width inputs at that breakpoint)
- Helper text under the field, not only a tooltip. On a phone the WooCommerce help tip is easy to miss
- The WooCommerce save action remains the method modal’s own save. Currency fields must be inside the form that WooCommerce already posts, so they persist with the method and a failed save does not write a partial zone

Phase 2 adds, under each cost field, one checkbox: “Convert the GBP cost when this box is empty.” The checkbox is available only when a rate for that currency exists. Under it, one preview line in plain text, for example “£10.00 converts to R230.00 at 23.” The preview updates from the values already on the form. Free-shipping minimums do not get the checkbox.

A cost WooCommerce would refuse to convert (a `[fee]` or `[cost]` formula, or a class cost that is not a plain number) shows that reason in the helper instead of the checkbox: “This cost uses the cart total, so enter a ZAR amount. Otherwise this zone stays in GBP.”

The modal grows with each currency (ZAR, EUR, USD by default). Stack the fields. Do not lay them out in a row. The primary save control is WooCommerce’s; do not add a second save button.

### Price books → Shipping

The prose summary above the table is the mobile view of the verdict. Keep it. It is one sentence per zone and does not depend on horizontal scroll.

The matrix stays for viewports that can show it: sticky first column, horizontal scroll, zone name linking to the WooCommerce zone. Below 782px, a labelled `<select>` (“Currency”) shows a single currency column so the method name and that currency fit without sideways scrolling. Changing the select does not save anything. The summary list remains above the select.

Cells stay text, not colour alone. The current “✓ ZAR” / “— no ZAR amount” wording is the right pattern. Add the new reasons in words: “converts from GBP”, “class costs need a ZAR amount”, “formula can’t be converted”, “carrier, store currency only”.

### Block local pickup

Edit ZAR, EUR, and USD costs on the Shipping tab as one card per location: location name and GBP cost read-only, then one numeric field per currency. Cards stack vertically. Do not embed these inputs in WooCommerce’s pickup location form, and do not write the location’s `cost`.

Saving posts to the price-books screen and returns there, the same way the other price-book settings do.

### Rates (phase 2)

On Price books → Settings, under the existing Shipping heading, one numeric field per extra currency: “ZAR per 1 GBP”. Full width on small screens. Helper: “Used only for shipping methods you mark as convert. Product prices are not converted. Leave blank to keep converting switched off.”

Saving a rate bumps the shipping rate cache.

### Accessibility

- Status text uses `role="status"` (the checkout notice already does)
- The currency `<select>` has a visible label
- Matrix headers stay `<th>` with `scope`, including when only one currency column is shown
- Contrast follows the existing admin tokens (the sticky column already uses a solid background)
- Do not rely on the sticky column as the only way to identify a row on a phone; the stacked pickup cards and the one-currency select are that path

## Data model and migration

No new table. No change to zone rows, zone locations, or method instance IDs.

### Already stored (do not migrate)

| Key | Where | Meaning |
| --- | --- | --- |
| `rwgcm_cost_{CUR}` | `woocommerce_flat_rate_{instance}_settings`, `woocommerce_local_pickup_{instance}_settings` | Manual cost. Blank = not priced. |
| `rwgcm_min_amount_{CUR}` | `woocommerce_free_shipping_{instance}_settings` | Manual free-shipping minimum. |
| `rwgcm_shipping_hide_unpriced` | option `yes` / `no`, default `no` | Zone priced if at least one method is priced; hide the others. |
| `rwgcm_priced_currency` | rate meta, copied to the order shipping line | Currency the charged amount is in. |
| `rwgcm_priced_source` | rate meta | `filter` when the site filter forced the rate. |
| `rwgcm_currency` | in-memory package key | Splits the WooCommerce rate cache. Not persisted as merchant data. |

An upgrade must not write these arrays. Absence of a key is the 0.5.1 behaviour. In particular, a blank `rwgcm_cost_ZAR` must not be backfilled with a converted number.

### Added later

| Key | Where | When | Meaning |
| --- | --- | --- | --- |
| `rwgcm_class_cost_{class_id}_{CUR}`, `rwgcm_no_class_cost_{CUR}` | same flat-rate instance settings | Phase 1 | Manual class costs. All blank = class-cost methods stay unpriced, as today. |
| `rwgcm_pickup_location_costs` | Geo Commerce option, map of location id → currency → amount | Phase 1 | Manual block-pickup costs. The WooCommerce location array is not modified. |
| `rwgcm_shipping_fx_rates` | Geo Commerce option, map of currency → decimal string | Phase 2 | Merchant-entered units per 1 store-currency unit. Empty = conversion off. |
| `rwgcm_convert_{CUR}` | instance settings, `yes` / absent | Phase 2 | Convert a blank numeric cost for this method and currency. Ignored for free-shipping minimums. Ignored when the manual field is non-blank. |
| `rwgcm_priced_source=converted` plus `rwgcm_fx_rate` and `rwgcm_fx_base_amount` | rate meta and therefore the order line | Phase 2 | Support and refunds can see the GBP figure and the rate. The charged total is the converted amount. |
| `rwgcm_shipping_fx_method_ids` | Geo Commerce option, list of method ids | Phase 3 | Carriers and table-rate methods whose calculated costs are GBP numbers to convert. Default empty. |

WooCommerce’s method settings travel with the method if a site exports that option. Zone location CSV does not include `rwgcm_*` keys. Importing a settings blob that already contains those keys restores manual amounts and does not change the zone. The new Geo Commerce options are ordinary options and are not part of a WooCommerce zone import.

Pickup location ids must be treated as opaque strings. If WooCommerce regenerates an id, the side option orphans the old amounts; the Shipping tab shows the orphan as an unused row the merchant can delete. It must not guess and copy the amount onto another location.

Changing `rwgcm_shipping_fx_rates`, a convert flag, or a pickup amount bumps the `shipping` transient version so session caches drop.

Order meta already written (`rwgcm_priced_currency` on existing orders) stays. Historical GBP orders are not rewritten. Renewals keep those stored lines.

## Risks

| Risk | Why it matters | What keeps it contained |
| --- | --- | --- |
| Mixed GBP and ZAR inside one flat rate | Class costs and `[fee]` / `[cost]` are added in the currency WooCommerce is calculating | Do not mark the method priced unless every component is manual or a convertible literal. 0.5.1 already bails out when any class cost is set. |
| Converted shipping next to fixed product prices | Shoppers may expect ZAR product prices and ZAR postage to share one rate. They do not. | Admin copy states that product prices are not converted. Manual amounts win. Conversion is opt-in per method. |
| Free-shipping threshold converted against a price-book subtotal | The threshold would not match the merchandise total the merchant has in mind | Minimums are manual only. |
| Writing the converted cost back into the zone | The merchant’s GBP cost would be destroyed, and admin would show the ZAR figure as GBP | Substitution is read-time and only when `forces_store_currency()` is false. Tests read the option after a storefront request and assert the GBP key is unchanged. |
| Stale rate cache after a rate edit | A shopper keeps the previous ZAR postage | Bump the shipping transient. Keep `rwgcm_currency` on the package hash. Include the rate revision in that hash when conversion is on. |
| `rwgcm_price_book_shipping_priced` forced true | Checkout will charge the raw rate as if it were ZAR | Leave the filter. The Shipping tab and the order meta `rwgcm_priced_source=filter` make the force visible. Do not use the filter as the conversion mechanism. |
| Carrier or table-rate opt-in when the quote is not a plain GBP number | The only rate disappears, or a mixed number is scaled | Phase 3 drops a non-numeric rate and does not relabel it. The cart stays in the visitor currency only when at least one rate was actually converted or manually priced. If none were, show a status that shipping could not be priced, and do not fall through to a GBP figure in ZAR. A same-request flip back to GBP is out of scope: line totals would already be ZAR. The merchant turns the method id off to restore today’s GBP cart. |
| Empty shipping list at checkout | The shopper cannot complete the order | Prefer that over a wrong charge. Hiding unpriced methods already refuses to hide the last rate; conversion must not delete the last rate unless it has been replaced by a converted one. If conversion fails, keep the GBP fallback for that request by not having marked the zone priced (phases 1–2). Phase 3’s method-id flag is the one case that can empty the list, and it defaults to off. |
| Rounding versus the gateway | Revolut puts the order on hold when the charged amount differs from the order total | Round once, before WooCommerce stores the line, to the store’s price decimals. Do not round again in the gateway. |
| Pickup location save strips extra keys | A ZAR cost stored on the location array disappears, or WooCommerce rejects the location | Side option only. |
| Table-rate plugin’s own cache | The plugin might cache a GBP cost and reuse it after a currency switch | Convert at `woocommerce_package_rates` after the plugin has built the rate. The package hash already includes currency, so WooCommerce’s session cache stays split. Document that a plugin which caches the final customer amount outside that hash needs a look before it is added to the method-id list. |
| Multi-package cart | One unpriced package would ship in ZAR if the check were per package after currency had switched | Keep the current “every zone” rule. |
| Renewal repriced by a new rate | A subscription’s shipping would drift | Renewals skip the currency filter and the guard and copy stored totals. Tests cover a renewal request. |
| Mobile zone modal | Extra fields push Save below the fold | One column of fields, helpers under the inputs, no second save button, no multi-currency grid inside the modal. |
| Export and staging copies | A staging site with different rates could be mistaken for zone data | Rates live in a Geo Commerce option, not in the zone. The scope test for “zones unchanged” compares zone IDs, locations, method instance IDs, and the GBP cost keys. |

## Recommendation

Ship the mix in four steps. Each step is independently releasable, default behaviour stays the 18 Oct rule, and no step writes zone locations or GBP costs.

Sizes below are change size (how much code and behaviour moves), not a calendar estimate.

### Phase 1 — Manual amounts for the remaining core costs

**Size:** medium. One shipping class, the price-books Shipping screen, block checkout notice, tests. No rate maths.

- Per-currency `class_cost_{id}` and `no_class_cost`, swapped together with the base cost. If any component lacks a manual amount, the method stays unpriced and no component is swapped.
- Block local pickup costs in `rwgcm_pickup_location_costs`, edited as cards on Price books → Shipping. `method_status()` treats `pickup_location` as priced only when the chosen location has an amount (or the GBP cost is zero).
- Method-modal helpers and `inputmode="decimal"`, as in Admin UX.
- Shipping tab: labelled currency select below 782px; new reason strings.
- Checkout block renders `extensions.rwgcm.shipping_currency_notice` and clears it when the string is empty.
- Tests in `tests/test-shipping-prices.php` plus a block-notice assertion. No change to `RWGCM_VERSION`.

A merchant who only uses flat rate, free shipping, and pickup can put a physical ZAR cart on Paystack after this phase, without conversion.

### Phase 2 — Opt-in conversion of numeric costs

**Size:** medium. New option, read-time conversion, admin preview, cache bump, tests. Still no carrier or table-rate support.

- `rwgcm_shipping_fx_rates` and per-method `rwgcm_convert_{CUR}`, default absent.
- Manual field wins. Free-shipping minimums are never converted.
- Convert a numeric literal, a numeric class cost, and a cost that is exactly `N * [qty]` or `[qty] * N`.
- Leave every other formula unpriced and say why in the modal.
- Substitute during `filter_instance_option`, so WooCommerce tax runs on the ZAR figure. Do not save the result.
- Tag `rwgcm_priced_source=converted` with the rate and the GBP base amount.
- Bump the shipping transient when rates or flags change, and include a rate revision on the package hash.
- Admin preview line and the Settings fields described above.

`computed_priced()` can see the flag and the stored GBP cost before rates are calculated, so the currency switch and the substitution happen in the same request. If the rate for ZAR is later removed, the method becomes unpriced and the cart returns to GBP on the next calculation. No data migration.

### Phase 3 — Opt-in conversion of calculated rates

**Size:** large. Third-party method ids, tax lines on the rate object, and an empty-rate outcome that must not mislabel.

- `rwgcm_shipping_fx_method_ids`, default empty. Candidates to test, not hard-coded special cases: WooCommerce Table Rate Shipping (`table_rate`), Flexible Shipping, and the carrier plugin used on staging (Royal Mail or whichever method id that install registers).
- After the plugin has set `WC_Shipping_Rate::cost`, if the method id is listed and the cost is numeric, convert the cost and each tax line. Tag the same converted meta.
- If the cost is not numeric, remove that rate and do not tag it as ZAR.
- The method id being listed is what makes `shipping_priced()` true before calculation. Document that an empty rate list can result, that the GBP cart returns only when the merchant removes the method id, and that this phase does not recalculate line items mid-request.
- Do not read or write the table-rate plugin’s rows.

Do not start phase 3 until phase 2 has been exercised on a numeric flat rate against Paystack and Revolut. The staging case that originally showed R73.23 for a £73.23 Royal Mail quote is the acceptance case for this phase: either the quote is converted and charged as ZAR with meta, or the cart stays in GBP with the existing notice. It must not show the unconverted figure in ZAR.

### Not in these phases

- Coupon minimum and maximum spend per currency
- Converting geo fee rules into the visitor currency
- An FX feed, or deriving a rate from price-book prices
- Per-currency price decimals
- Changing the WooCommerce store currency
- Any edit to zone locations, method order, or GBP cost fields

## Test plan

Automated tests stay in the existing stub style (`tests/test-shipping-prices.php`, `tests/test-shipping-cache.php`, `tests/test-checkout-country.php`, `tests/test-store-api-currency.php`). They do not boot WooCommerce. Each phase adds cases before changing the assertion that describes today’s behaviour.

### Currency decision (already true; keep green)

- ZA geo visitor, ZAR price books, cart needs shipping, zone has a GBP flat rate and no ZAR cost → active currency GBP, `shipping_fallback_currency()` is ZAR, notice names GBP, South Africa, and ZAR.
- Same cart, all virtual → ZAR, no notice.
- Geo ZA, shipping calculator set to GB, GB zone unpriced in ZAR → GBP. Session saved on `woocommerce_calculated_shipping` shows GB to the fragment request.
- Visitor currency already GBP → no notice.
- Admin, cron, CLI, non-Store REST → GBP cost returned from the instance option; `rwgcm_cost_ZAR` ignored.
- Two packages, one zone unpriced → GBP.
- Renewal request → subscription currency, guard does not run.

### Manual amounts (phase 1 extends these)

- `rwgcm_cost_ZAR=230` on a flat rate with no class costs → currency ZAR, swapped cost `230`, rate meta ZAR, guard passes.
- Class cost non-blank and ZAR base filled in, class cost fields empty → still GBP (current). After phase 1, still GBP until every class field has a ZAR amount; then the swapped values are the ZAR base plus the ZAR class costs and the sum is what the rate charges.
- Blank class-cost keys do not count as class costs (current test).
- Free shipping `min_amount` 60 with no `rwgcm_min_amount_ZAR` → GBP. With `rwgcm_min_amount_ZAR=800` → the value WooCommerce compares is `800`.
- Coupon-only free shipping and a zero cost → priced, no amount.
- Hide unpriced on, one of two methods priced → ZAR, the unpriced rate removed, the priced rate kept. Hide unpriced on with none priced → GBP, and the rate list is not emptied.
- Pickup location GBP cost unchanged in `pickup_location_pickup_locations` after a ZAR price is saved in the side option. Missing side-option amount → method unsupported → GBP.
- Store API cart extension: notice string while fallback is active, empty string when the zone is priced.
- Checkout block markup includes the notice text and a live region, and renders nothing when the string is empty.
- Order create in ZAR with shipping meta ZAR passes. Same order with meta missing or GBP throws the currency exception and does not leave a payable order.

### Zones untouched

Build a fixture zone (id, locations, flat-rate instance id, `cost=10`, `class_cost_12=5`). Run a ZAR storefront calculation and an admin save of the ZAR fields. Assert zone id, locations, instance id, `cost`, and `class_cost_12` are byte-for-byte the same. Assert only `rwgcm_*` keys were added.

### Conversion (phase 2)

- Rates option empty, convert flag absent → identical to 0.5.1 (GBP fallback).
- Rate ZAR `23`, cost `10`, convert flag on, manual blank → swapped cost `230.00` at store decimals, meta source `converted`, fx rate `23`, base amount `10`. Option `cost` still `10`.
- Manual `rwgcm_cost_ZAR=200` and convert flag on → `200`, source is the manual amount, not `converted`.
- Cost `10 * [qty]` → coefficient scaled. Cost `[fee percent="10"]` or `10 + [cost]` → not priced, cart GBP, helper reason set.
- Free-shipping minimum with convert flag on → flag ignored, cart GBP until a manual minimum exists.
- Rate changed from 23 to 24 → shipping transient version bumped; a subsequent calculation uses 240; a cached package from currency ZAR plus the old revision is not reused.
- Deleting the ZAR rate → method unpriced → GBP on the next resolve.
- Paystack-shaped total: order currency ZAR, shipping line 230, line meta ZAR, minor units `(items + shipping + tax) * 100`. Revolut currency equals the order currency.

### Calculated rates (phase 3)

- Method id not listed → carrier cost 73.23 left as GBP and the cart stays GBP (the 0.4.5 staging case).
- Method id listed, rate cost 73.23, rate 23 → cost 1684.29 (or the store’s decimal rounding of 73.23 × 23), taxes scaled, meta `converted`.
- Method id listed, cost empty or non-numeric → rate removed, not tagged ZAR. No GBP cost remains labelled ZAR.
- A table-rate fixture that only implements `woocommerce_package_rates` output is enough. Do not require the commercial plugin in CI. One staging pass with the real carrier plugin is the acceptance check for phase 3.

### Admin, including a phone-width viewport

- At 390px and at 1280px: Price books → Shipping summary is readable; below 782px the currency select shows one column; the zone link is reachable; pickup cards stack and save.
- Flat-rate modal: labels visible, decimal keypad on a phone, convert checkbox hidden when no rate is saved, preview text matches the fixture, Save is the WooCommerce control.
- Saving returns to `admin.php?page=rwgcm-price-books` and does not redirect into a zone URL except via the explicit zone link.

### Commands

```bash
php -l includes/class-rwgcm-shipping-prices.php
php -l includes/class-rwgcm-price-book-currency.php
php tests/*.php
```

Phase 1’s block notice also needs a look in a browser on the Checkout block, classic cart, and classic checkout, at 390px and 1280px, for both the GBP-fallback state and the ZAR-priced state. The stub tests do not render the block.
