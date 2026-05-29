# Competitor and Conflict Plugin Matrix

Status: accepted for Phase 0.

Task: `UB-P0-008`.

## Behavior Legend

| Behavior | Meaning |
| --- | --- |
| Detect | Identify plugin or environment where possible. |
| Warn | Show dismissible Woo-native admin guidance. |
| Support | Include in normal QA matrix. |
| Defer | Document as not guaranteed for v1 unless later tasks add support. |
| Block | Do not claim compatibility or release a broken integration. |

## Compatibility Targets

| Plugin or category | Engineering behavior | QA mapping | Copy boundary |
| --- | --- | --- | --- |
| WooPayments | Support | Phase 7 Block Checkout and payment E2E. | Required for Block Checkout proof path; do not claim gateway-specific one-click upsells. |
| Stripe for WooCommerce | Support | Phase 7 gateway compatibility tests. | Normal checkout compatibility only. |
| PayPal Payments | Support | Phase 7 gateway compatibility tests. | Normal checkout compatibility only. |
| WooCommerce Subscriptions | Detect, warn, support safe paths | Phase 7 compatibility matrix; discount leakage tests. | No recurring discount leakage claims until proven. |
| WooCommerce Blocks / Block Checkout | Block claim until proven | Phase 0 POC; Phase 7 Block Checkout E2E. | No Blocks compatibility claim before tests pass. |
| CheckoutWC | Detect, warn | Phase 7 compatibility review; Phase 4 conflict scanner. | UpsellBay preserves existing checkout but cannot guarantee replacement checkout surfaces. |
| CartFlows | Detect, warn | Phase 7 compatibility review; Phase 4 conflict scanner. | Position as non-funnel alternative; do not copy funnel behavior. |
| FunnelKit | Detect, warn | Phase 7 compatibility review; Phase 4 conflict scanner. | Position as additive AOV layer, not funnel suite. |
| CartBay | Detect, warn only for coexistence guidance | Phase 7 product isolation scan; Phase 3 coexistence notice. | Explain separation only; never present recovery features as UpsellBay. |
| Multicurrency plugins | Detect, defer broad support | Phase 7 compatibility matrix. | Support only tested configurations; server-side totals remain authoritative. |
| Product Bundles and Composite Products | Detect, defer advanced behavior | Phase 7 compatibility matrix. | Product/cart offers should skip invalid or unsupported offer products. |
| Page builders | Warn if checkout/product template conflicts are detected | Phase 7 storefront compatibility review. | UpsellBay uses native hooks/templates and cannot guarantee arbitrary builder overrides. |
| Dynamic pricing/discount plugins | Detect, warn | Phase 7 totals and discount matrix. | Server-side totals and Woo cart state are authoritative. |
| Recovery/email automation plugins | Detect, warn only if checkout/cart conflict is likely | Phase 7 compatibility matrix. | UpsellBay is not recovery automation. |

## Product Positioning Boundaries

- Do not claim checkout replacement.
- Do not claim funnel-builder features.
- Do not claim abandoned-cart recovery, recovery email, SMS, WhatsApp, restore-link, or unsubscribe behavior.
- Do not claim Block Checkout support before POC and E2E pass.
- Do not claim guaranteed compatibility with replacement checkouts or heavily customized checkout templates.
