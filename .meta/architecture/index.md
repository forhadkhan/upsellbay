# Architecture

This directory records the architecture that is already implemented in the current UpsellBay codebase.

## Implemented Product Architecture

Runtime application architecture is implemented through Phase 6.5 (86 of 121 tasks). Phase 7 (QA) is in progress. The following architecture areas are implemented and documented below:

- Bootstrap foundation and service container (Phase 1)
- Data architecture: offer CPT, stats table, session state, logs (Phase 2)
- Admin information architecture and surfaces (Phase 3)
- Core business logic: offer engine, rules, cart, discounts, storefront, REST, attribution, analytics (Phase 4)
- Merchant experience and wizard (Phase 5)
- Service boundaries, developer extensibility, logging system (Phase 6)
- Block Checkout E2E proof passed; `cart_checkout_blocks` compatibility declared `true` (Phase 7)

## Phase 0 Foundation Decisions

- [Initial repository and PRD baseline audit](./initial-audit.md)
- [Identifier contract and product isolation ADR](./identifier-contract.md) — **note: hook/nonce/asset identifiers have since diverged from this ADR by design; see the divergence note inside**
- [Block Checkout proof plan](./block-checkout-poc.md) — **E2E proof passed, compatibility enabled**
- [HPOS and CRUD compliance proof plan](./hpos-crud-compliance.md) — **HPOS compatibility declared**
- [Dependency and tooling plan](./development-tooling.md)
- [File ownership map](./file-ownership-map.md) — updated for the Phase 6.5 admin integration refactor
- [Documentation and decision logging standards](./documentation-standards.md)

## Free Plugin and Free↔Pro Coupling

Pro (`upsellbay`) requires and extends the free plugin
(`upsellbay-order-bump-for-woocommerce`), which owns the CPT/postmeta schema and the
entire admin page shell. These two documents cover the host plugin's own architecture and
the exact, verified mechanism by which Pro attaches to it — required reading before
adding any Pro feature that touches admin UI, offers, or storefront rendering:

- [Free plugin architecture](./free-plugin-architecture.md)
- [Free↔Pro coupling](./free-pro-coupling.md) — full hook inventory, the admin-integration pattern that replaced Pro's deleted duplicate admin pages, a confirmed dead-hook-wiring bug, and a double-registration risk

## Phase 1–6 Implemented Architecture

- [Bootstrap foundation](./bootstrap-foundation.md)
- [Data architecture](./data-architecture.md)
- [Core business logic](./core-business-logic.md)
- [Service boundaries](./service-boundaries.md)
- [Admin information architecture](./admin-information-architecture.md)
- [Admin heading hierarchy](./admin-heading-hierarchy.md)
- [Merchant experience](./merchant-experience.md)
- [Developer extensibility](./developer-extensibility.md) — documents Pro's own (`upsellbay_pro_`-prefixed) hooks; see [free-pro-coupling.md](./free-pro-coupling.md) for the host's hooks Pro actually attaches to
- [Logging system](./logging-system.md)
- [Backward compatibility policy](./backward-compatibility-policy.md)
- [WP-CLI utilities](./wp-cli-utilities.md) — planned, not registered in v1

## Git Architecture: Orphan Overlay
This project uses an **Orphan Overlay** strategy to manage dot-prefixed directories independently from the main application codebase. Here is the [detailed explanation](./git-orphan-overlay.md)

## WooCommerce Compatibility
UpsellBay follows WooCommerce HPOS and Block Checkout integration guidance. Both compatibility declarations are now active:

- **HPOS**: `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` declared `true` in `Plugin.php`.
- **Block Checkout**: `FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ... )` declared `true` in `Plugin.php` after E2E proof passed (2026-06-19).

Proof plans are documented in [HPOS and CRUD compliance proof plan](./hpos-crud-compliance.md) and [Block Checkout proof plan](./block-checkout-poc.md).
