# UpsellBay Compatibility Matrix

UpsellBay is built as an additive WooCommerce AOV offer layer. It preserves the store's existing checkout and uses WooCommerce APIs for order, product, cart, and order-item behavior.

## Current Compatibility Position

| Area | Status | Notes |
| --- | --- | --- |
| HPOS | Supported by architecture | Attribution uses WooCommerce order and order-item object methods. Final HPOS enabled and disabled QA evidence is required before release. |
| Classic checkout | Implemented, QA required | Classic checkout bump support must pass Phase 7 E2E before release. |
| Block Checkout | Supported | Runtime Block Checkout integration code is verified via Phase 7 E2E suite using supported WooCommerce Store APIs. |
| WooPayments | QA required | Required for the Block Checkout proof path. |
| Stripe for WooCommerce | QA required | Normal checkout compatibility only; no gateway-tokenized one-click upsell claim. |
| PayPal Payments | QA required | Normal checkout compatibility only. |
| WooCommerce Subscriptions | Limited until tested | Subscription products must not receive recurring discount leakage. |
| Multicurrency plugins | Deferred broad support | v1 attribution reports store currency only unless a specific integration is tested and documented. |
| Product bundles/composites | Deferred advanced behavior | v1 focuses on simple and variable product offer paths. |
| Checkout replacement plugins | Warning-only | UpsellBay detects known replacement checkout plugins where possible and avoids unsafe injection. |
| CartBay | Coexistence guidance only | UpsellBay does not require CartBay and must not read or write CartBay options, sessions, metadata, routes, jobs, or recovery state. |

## Review Rule

Do not market or submit a compatibility claim unless the matching Phase 7 evidence is current in `.meta/qa/release-validation.md`.
