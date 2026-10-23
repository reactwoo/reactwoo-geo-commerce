# Cursor output

## Status

needs-review

## Summary

Scoping only. Draft design for per-currency shipping rates is in `docs/scoping/per-currency-shipping.md`. No functional code, no version bump, no tag. Recommendation: keep the 18 Oct 2026 GBP fallback, finish manual amounts for class costs and block local pickup, then add opt-in conversion of numeric GBP costs using a merchant-entered rate. Manual amounts win. Free-shipping thresholds stay manual. Carriers and table-rate plugins are a later opt-in and must not be relabelled. Zone locations and GBP costs are never written.

## Files changed

| File | Reason |
|------|--------|
| docs/scoping/per-currency-shipping.md | Design and scoping document |
| ai-handoff/cursor-output.md | Handoff for this pass |

## What was not changed

- Plugin PHP, admin assets, tests, readme, changelog
- `RWGCM_VERSION` (stays 0.5.1)
- Shipping zones, price books, gateway behaviour
- No tag and no merge

## Commands run

None. Documentation only.

## Remaining errors

None in this pass. The design is a draft until review.
