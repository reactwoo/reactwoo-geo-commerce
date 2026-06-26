# Decisions — ReactWoo Geo Commerce

| Date | Decision | Rationale |
|------|----------|-----------|
| — | Eligibility via Core evaluator | `RWGCM_Targeting_Adapter` → `RWGC_Rule_Evaluator::matches()` |
| — | Commerce applies outcomes only | Pricing, fees, overlays — not a second rules engine |
| — | Requires WooCommerce + Geo Core | `rwgcm_loaded` after Core |
| — | File handoff for cross-tool debug | `ai-handoff/` + `reactwoo-geocore/docs/ai-handoff-workflow.md` |

## AI handoff defaults

- Cart/checkout bugs: trace `rwgcm_cart_fees`, pricing adapters, and Core geo context before adding fallbacks.
