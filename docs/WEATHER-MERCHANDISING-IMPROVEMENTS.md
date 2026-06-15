# Weather merchandising — improvements & roadmap

Operational guide and suggested next steps after Phases 1–5. Suite contract: **`reactwoo-geocore/docs/WEATHER-FACETS-MERCHANDISING-PLAN.md`**.

## Implemented (v2 admin)

| Feature | Where |
|---------|--------|
| Products list **Weather** column | WooCommerce → Products |
| **Bulk edit** — set / merge / clear facets | Products → Bulk edit panel |
| **Quick edit** — comma-separated facets | Products → Quick edit |
| **Bulk actions** — Suggest weather facets / Apply category defaults | Products → Bulk actions |
| **Untagged filter** | Products list via Merchandising → View untagged |
| **Category default facets** | Products → Categories (add/edit) |
| **Auto-apply category defaults** (setting) | Merchandising → Product tagging |
| **Catalog coverage report** | Merchandising screen |
| **Apply category defaults** on product | Product editor → Good for this weather |
| **Diagnostics** weather summary | Geo Commerce → Diagnostics |
| **Overlap score cache** per request | Catalog boost / widgets |
| **Visitor facet cache** per request | `RWGCM_Weather_Affinity::get_visitor_facets()` |
| **Storefront preview** on product edit | Product editor → Storefront preview panel |
| **Meta weather badge** on loops | Merchandising → Storefront badges |
| **Weather strip shop link** | Merchandising + `[rwgcm_weather_strip link="shop"]` |
| **Category filter chips** | `[rwgcm_weather_filter]` shortcode |
| Geo AI suggester uses **category defaults** | Product editor suggest button |
| **Remote AI facet suggestions** | Geo AI workflow `weather_facet_suggest` when engine is remote |
| **SQL batch overlap scoring** | `RWGCM_Weather_Affinity::batch_overlap_scores()` |
| **Geo Optimise weather targeting** | Create/Edit Test → Shopping weather |
| **Geo AI catalog audit** | Merchandising → weekly cron + manual run |
| **Elementor Visitor Weather Facets** | Dynamic tag in Elementor (WooCommerce group) |
| **AQI / pollen facets** | `poor_air`, `high_pollen` (WeatherAPI Pro+ pollen) |
| **Store weather coordinates** | Merchandising → fallback for GeoCore Pro store mode |

## Merchant workflow (recommended)

1. **Connect weather** — GeoCore Pro → Weather → test connection + warm cache (cron or manual).
2. **Set category defaults** — e.g. “Rain gear” category → `wet`, `windy`.
3. **Tag products** — per product checkboxes, **Suggest (Geo AI)**, or **Apply category defaults**.
4. **Bulk tag** — filter untagged products → Bulk edit → Set weather facets.
5. **Rules** — Commerce rule: Shopping weather `wet` → badge “Great for rainy days”.
6. **Surfaces** — `[rwgcm_weather_products]`, Weather Strip, shop boost on Merchandising screen.
7. **Preview** — Geo Core → Visitor test (advanced) → simulate `wet` / `cold` before go-live.
8. **Export/import** — CSV column `weather_facets` for spreadsheet workflows.

## Hooks for customisation

| Hook | Use |
|------|-----|
| `rwgcp_weather_facet_thresholds` | Adjust hot/cold/windy/sunny/**high_uv**/**poor_air**/**high_pollen** cutoffs |
| `rwgcp_weather_derived_facets` | Add/remove visitor facets after derivation |
| `rwgcm_weather_catalog_boost_enabled` | Disable boost on specific queries |
| `rwgcm_weather_catalog_boost_posts` | Custom reorder after boost |
| `rwgcm_weather_products_query_args` | Widget/block query tuning |
| `rwgcm_weather_product_match` | Stricter product/visitor match (e.g. require 2+ facets) |
| `rwga_weather_facet_suggestions` | Override Geo AI keyword suggestions |

## Suggested future improvements

### High value
- ~~**Bulk “Suggest facets”** action on products list~~ ✅
- ~~**Auto-tag on save** option: category defaults~~ ✅
- ~~**Facet coverage report**~~ ✅ (Merchandising screen)
- ~~**Remote AI suggestions** via Geo AI workflow~~ ✅ (`weather_facet_suggest` on API + Geo AI remote engine)

### Storefront / UX
- ~~**Weather strip** link to filtered shop view or Weather Products anchor~~ ✅ (shop/custom link on Merchandising + shortcode `link` attr)
- ~~**Badge on loop** from product meta (“Good for rain”) independent of rules~~ ✅
- ~~**Filter widget** — “Show products for today’s weather” on category pages (filter mode + facet chips)~~ ✅ (`[rwgcm_weather_filter]` + `?rwgcm_wf=` query arg)

### Performance
- ~~**Object cache** for visitor facet list per request~~ ✅
- ~~**SQL facet overlap** for large catalogs~~ ✅ (`batch_overlap_scores`, `query_tagged_product_ids`)

### Integrations
- ~~**Geo Optimise** — weather as experiment dimension~~ ✅ (`weather_facets` targeting mode)
- ~~**Geo AI intelligence** — periodic catalog audit workflow~~ ✅ (weekly cron + Merchandising report)
- ~~**Elementor loop** — dynamic tag `{rwgcm_visitor_weather_facets}`~~ ✅ (Visitor Weather Facets dynamic tag)

### Platform
- ~~**Pollen / air quality** facets when provider supports (WeatherAPI roadmap)~~ ✅ (`poor_air`, `high_pollen` via WeatherAPI `aqi=yes` & `pollen=yes`)
- ~~**Store-location vs visitor** weather toggle for click-and-collect merchants~~ ✅ (GeoCore Pro **Weather location** + Commerce store coordinate fallback)

## QA checklist (extended)

1. Category defaults appear on product editor; **Apply to product** checks boxes.
2. Bulk edit **Add to existing** merges without wiping prior facets.
3. Quick edit saves `wet,cold` and column updates.
4. Diagnostics shows visitor facets when weather cache warm.
5. CSV export/import round-trip preserves facet order.
6. Suggest button picks up category defaults when keywords sparse.
7. Product Collection block respects **boost_collection** setting.

## Known limits

- Category defaults are **hints** only — storefront matching uses **product meta** tags.
- **Filter** catalog mode hides non-matching products (use **boost** to keep full catalog visible).
- Open-Meteo UV requires updated API params; re-test connection after Pro update.
- **AQI / pollen** facets require WeatherAPI.com with `aqi=yes` / `pollen=yes` (pollen on Pro+ plans). Open-Meteo does not supply these signals.
- **Store vs visitor** weather: configure **GeoCore Pro → Weather → Weather location**; optional Commerce store lat/lon as fallback coordinates.
- Keyword suggester is English-biased; extend `$keyword_map` or use `rwga_weather_facet_suggestions` for locales.
