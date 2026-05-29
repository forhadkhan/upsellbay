# Architecture

This directory records the architecture that is already implemented in the current UpsellBay codebase.

## Implemented Product Architecture

No runtime application architecture is implemented yet. Phase 0 records the binding architecture proof and launch-gate plans that Phase 1+ implementation must follow.

## Phase 0 Foundation Decisions

- [Initial repository and PRD baseline audit](./initial-audit.md)
- [Identifier contract and product isolation ADR](./identifier-contract.md)
- [Block Checkout proof plan](./block-checkout-poc.md)
- [HPOS and CRUD compliance proof plan](./hpos-crud-compliance.md)
- [Dependency and tooling plan](./development-tooling.md)
- [File ownership map](./file-ownership-map.md)
- [Documentation and decision logging standards](./documentation-standards.md)

## Git Architecture: Orphan Overlay
This project uses an **Orphan Overlay** strategy to manage dot-prefixed directories independently from the main application codebase. Here is the [detailed explanation](./git-orphan-overlay.md)

## WooCommerce Compatibility
UpsellBay follows WooCommerce HPOS and Block Checkout integration guidance. The Phase 0 proof plans are documented in [HPOS and CRUD compliance proof plan](./hpos-crud-compliance.md) and [Block Checkout proof plan](./block-checkout-poc.md).

`woocommerce-compatibility.md` is planned for the implementation and QA phases once runtime compatibility evidence exists.
