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
