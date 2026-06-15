# Geo Commerce — weather merchandising

Commerce-side checklist for **weather facets** (product tagging, catalog boost, display widgets). Suite contract and phases: **`reactwoo-geocore/docs/WEATHER-FACETS-MERCHANDISING-PLAN.md`**.

## Role

Geo Commerce does **not** fetch weather. It consumes `rwgc_get_context_snapshot()['weather']['facets']` from GeoCore Pro and:

1. Stores **product affinity** (which facets a SKU is good for)
2. Evaluates **visitor.weather_facet** in Commerce rules (via `RWGCM_Targeting_Adapter`)
3. **Surfaces products** in WooCommerce loops, shortcode, Gutenberg block, and Elementor widget
4. Optionally **boosts** shop/category sort order

## Facet vocabulary (same as Core)

`wet`, `dry`, `hot`, `cold`, `mild`, `windy`, `sunny`, `high_uv` — multi-select checkboxes only; no numeric inputs.

## Implementation checklist

### Phase 2 — Product tagging & rules

- [x] `includes/class-rwgcm-weather-affinity.php` — read/write `_rwgcm_weather_facets`, `product_matches_visitor()`
- [x] Product editor tab “Good for this weather” (checkbox group)
- [x] `RWGCM_Condition_Library` field `visitor.weather_facet` → portable type `weather_facet`
- [x] `admin/js/rwgcm-condition-builder.js` — facet picker from `weather_facets` context
- [x] Products list column, bulk edit, quick edit
- [x] Category default facets (term meta) + Apply on product editor

### Phase 3 — Display widgets

- [x] `includes/class-rwgcm-weather-product-query.php` — shared query + scoring
- [x] `includes/class-rwgcm-weather-products-shortcode.php` — `[rwgcm_weather_products]`
- [x] `blocks/weather-products/block.json` + `index.js` + `render.php`
- [x] `includes/integrations/elementor/class-rwgcm-elementor-weather-products.php`
- [x] `templates/weather-products-loop.php`
- [x] `assets/css/weather-products.css`

#### Shortcode attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | `8` | Max products |
| `columns` | `4` | Grid columns |
| `category` | `` | Category slug or ID |
| `ids` | `` | Comma-separated product IDs (manual pool) |
| `orderby` | `relevance` | `relevance`, `date`, `menu_order` |
| `fallback` | `hide` | `hide`, `category`, `message` |
| `fallback_category` | `` | Slug when `fallback=category` |
| `class` | `` | Extra wrapper class |

#### Gutenberg block `rwgcm/weather-products`

Inspector panels mirror shortcode settings; uses same `RWGCM_Weather_Product_Query`.

#### Elementor widget `rwgcm-weather-products`

Controls: heading, product source, limit, columns, carousel on/off, empty state, style tab (spacing). Category: **ReactWoo Geo** or **WooCommerce**.

### Phase 4 — Catalog boost

- [x] `includes/class-rwgcm-catalog-weather-boost.php`
- [x] Settings: shop, category, Product Collection block (Merchandising screen)
- [x] Hook `woocommerce_product_query` + `query_loop_block_query_vars`
- [x] Modes: off / boost / filter
- [x] Filter `rwgcm_weather_catalog_boost_enabled`

### Phase 5 — Polish & extensions

- [x] GeoCore Pro: UV index on providers + `high_uv` facet
- [x] Geo Core: shopping weather facet overrides in Visitor test (advanced)
- [x] Geo Commerce: Weather Strip `[rwgcm_weather_strip]` + Elementor widget
- [x] Geo Commerce: Product Collection block boost setting + query hook
- [x] Geo Commerce: CSV `weather_facets` import/export column
- [x] Geo AI: keyword facet suggester + product editor button + REST

## WooCommerce touchpoints

| Hook / API | Use |
|------------|-----|
| `woocommerce_product_query` | Catalog boost |
| `woocommerce_before_shop_loop_item_title` | Badges (existing `RWGCM_Product_Display_Apply`) |
| `WC_Product` + post meta | Facet storage |
| `wc_get_products()` / `WP_Query` `product` | Widget query |

## Elementor & Gutenberg

- **Visibility rules** on content (show/hide sections) use Geo Core `rwgc-rule-builder.js` + `weather_facet` — not implemented in Commerce.
- **Product listing widgets** are Commerce-owned (Phase 3).
- Register Elementor widget on `elementor/widgets/register` from `RWGCM_Plugin` when Elementor active.
- Register block on `init` from `blocks/weather-products/`.

## Dependencies

- `reactwoo-geocore` + `reactwoo-geocore-pro` (weather connected)
- `woocommerce`
- Elementor optional (widget only)

## Manual QA (Commerce)

1. Product tagged `wet` + `windy` appears in Weather Products block when visitor facets include either.
2. Commerce rule: condition wet → badge on loop card.
3. Shop boost enabled → relevant products move up on wet day.
4. Weather unavailable → shortcode `fallback=hide` removes output; no notices for shoppers.

See also **`docs/WEATHER-MERCHANDISING-IMPROVEMENTS.md`** for merchant workflow, hooks, and roadmap.
