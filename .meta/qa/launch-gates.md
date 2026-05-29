# Launch Gates

Status: accepted for Phase 0.

Task: `UB-P0-007`.

Launch is blocked until every applicable item is checked with current evidence.

## Release-Blocking Checklist

- [ ] Block Checkout support is either fully proven by E2E tests or not claimed in product, marketplace, plugin, or docs copy.
- [ ] Checkout bump cart mutations produce correct order totals.
- [ ] HPOS compatibility tests pass.
- [ ] Attribution uses WooCommerce order and order-item CRUD APIs only.
- [ ] Thank-you follow-on offers create a separate checkout flow and never mutate the primary order.
- [ ] Subscription product discounts cannot leak into recurring renewals.
- [ ] License server outage does not disable live offers.
- [ ] Public REST endpoints never accept client-sent pricing, discount, totals, or product validity.
- [ ] QIT has no unresolved high-severity security, compatibility, static analysis, or activation failures.
- [ ] Admin UI is WooCommerce-native and does not introduce a top-level WordPress menu or custom app shell.
- [ ] UpsellBay activates and operates without CartBay.
- [ ] UpsellBay does not read from or write to CartBay options, sessions, metadata, REST routes, scheduled jobs, recovery email settings, or private runtime state.
- [ ] Static scan confirms identifiers match the contract in `.meta/architecture/identifier-contract.md`.
- [ ] Marketplace and docs copy avoid unsupported AOV lift, Block Checkout, or recovery claims.
- [ ] Documentation updates follow `.meta/architecture/documentation-standards.md`.

## Evidence Expectations

Each release candidate must attach or link:

- PHPCS result.
- PHPStan result.
- PHPUnit or project test result.
- Build result.
- POT generation result when strings changed.
- Plugin Check result.
- QIT result.
- Classic checkout E2E result.
- Block Checkout E2E result before any Block compatibility claim.
- HPOS enabled and disabled attribution result.
- Static scans for CartBay coupling and direct order storage access.
