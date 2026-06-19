# Developer Extensibility

Phase 6 exposes public hooks at stable inputs and outputs without making private internals part of the public contract.

Runtime hook calls are centralized through `Core\Hooks` and use the `upsellbay_` prefix from `Core\Constants`. Hooks are placed around:

- Offer schema defaults and placement labels.
- Offer repository query arguments.
- Rule context and per-rule result evaluation.
- Eligible offer lists after validation.
- Escaped offer HTML after renderer output.
- Server-calculated offer price and discount amount.
- Attribution meta before WooCommerce CRUD writes.
- Aggregate analytics event payloads.
- Offer lifecycle, render, accept, dismiss, attribution, follow-on order, and stats reconciliation actions.

Import/export filters support portable agency templates while preserving schema validation. They cannot bypass capability, nonce, session-token, file-upload, or server-side product validation.

## Block Checkout Integration Points

Block Checkout renders offers through supported WooCommerce Blocks extension APIs:

- `ExperimentalOrderMeta` — renders offer cards in the order meta section of the checkout block
- `extensionCartUpdate({ namespace: 'upsellbay' })` — triggers block totals refresh after cart mutation
- Store API endpoint data via `woocommerce_store_api_register_endpoint_data` — exposes offer payloads under `extensions.upsellbay`
- `woocommerce_register_additional_checkout_field` — registers `upsellbay/accepted_offer` field for checkout data persistence
- `window.upsellbayStorefront` — localized config with `token`, `restUrl`, `cartUrl`, `checkoutUrl` for REST authentication

These are all documented public APIs. No private block internals, DOM scraping, or checkout replacement is used.
