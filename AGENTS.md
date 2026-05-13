# Agent workflow — ReactWoo Geo Commerce

Geo Commerce is a **Geo Core satellite** (WooCommerce layer). It is not a standalone geo engine.

## Defaults

- Prefer **one coherent thread** (read → change → verify). Match the sequential style described in Geo Core `docs/AGENTS.md` for suite work.
- **Do not** duplicate visitor detection, MaxMind stacks, or portable targeting evaluation — use Geo Core APIs and **`RWGC_Rule_Evaluator`**.

## Build and release (parity with Geo AI / Geo Optimise)

- **`package.json`** defines `reactwooBuild.pluginFolder`, `reactwooBuild.zipFile`, and `reactwooBuild.geoCoreDependencySlug` (`reactwoo-geocore`).
- **Distribution zip:** `npm run package:zip` (runs `python scripts/package_zip.py`). Includes `admin/`, `assets/`, `includes/`, main PHP, and `readme.txt`.
- **CI:** `.github/workflows/publish-update.yml` runs the same script on tag / dispatch.
- **Git:** do not commit `*.zip` (see `.gitignore`). Prefer **`git archive`** for versioned drops when not using the Python packager.
- **Cursor:** shared repo rules live under **`.cursor/rules/`** (committed); local IDE state stays untracked by default.

## Targeting architecture

- Visitor conditions for generic rules and product overlays are bridged by **`RWGCM_Targeting_Adapter`** into Geo Core’s portable schema and evaluated with **`RWGC_Rule_Evaluator::matches()`** so Geo Core Pro hooks (`rwgc_targeting_evaluate_condition`) apply.
- Commerce code should **apply outcomes** (pricing, overlays, fees, meta); eligibility is the shared evaluator’s job.

## References

- Geo Core product context: `reactwoo-geocore/docs/geo-core-cursor-master-plan.md`, `docs/phases/phase-7.md`, `docs/releases-and-git-tags.md`.
- Constants: **`RWGCM_VERSION`** in `reactwoo-geo-commerce.php` must match the shipped release and readme **Stable tag**.
