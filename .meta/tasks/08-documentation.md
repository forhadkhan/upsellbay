<!-- STATUS: PENDING -->

# Phase 8 - Documentation

Goal: ship complete merchant, developer, architecture, QA, migration, and marketplace documentation that matches the implemented product.

## UB-P8-001 - Merchant Setup Guide

- Objective: Help merchants install, activate, license, and configure UpsellBay.
- Scope definition: Create `docs/README.md` or `docs/setup-guide.md`.
- Dependencies: UB-P1-001, UB-P1-012, UB-P3-006, UB-P5-001.
- Implementation notes: Cover requirements, install, license activation, first-run wizard, global enable, test mode, first offer, and support links.
- Acceptance criteria: A merchant can complete setup without reading developer docs.
- Validation and testing requirements: Follow the guide on a clean local store and confirm steps match UI labels.
- Estimated complexity: Medium.
- Suggested execution order: 95.

## UB-P8-002 - First Offer Tutorial

- Objective: Provide a concise path to create and preview the first checkout bump.
- Scope definition: Add `docs/first-offer-tutorial.md`.
- Dependencies: UB-P3-003, UB-P5-001, UB-P5-004.
- Implementation notes: Use native admin paths: WooCommerce -> UpsellBay -> Add Offer. Include test mode and preview steps. Avoid unsupported ROI promises.
- Acceptance criteria: Tutorial completes with a working test checkout bump and disabled test mode before going live.
- Validation and testing requirements: Run the tutorial on a clean store and record any mismatched UI text.
- Estimated complexity: Low.
- Suggested execution order: 96.

## UB-P8-003 - Offer Placement and Rules Guide

- Objective: Explain product, cart, checkout, and thank-you placements plus targeting rules.
- Scope definition: Add `docs/placements-and-rules.md`.
- Dependencies: UB-P4-003, UB-P4-009 through UB-P4-013.
- Implementation notes: Include examples for each P0 rule type and safe behavior when products are out of stock or unsupported.
- Acceptance criteria: Merchants can choose the correct placement and rule set for common AOV use cases.
- Validation and testing requirements: Copy review against PRD sections 8.2, 8.3, 9.5, and 9.7.
- Estimated complexity: Medium.
- Suggested execution order: 97.

## UB-P8-004 - Analytics and Attribution Guide

- Objective: Explain what metrics mean and how attribution is calculated.
- Scope definition: Add `docs/analytics-and-attribution.md`.
- Dependencies: UB-P2-011, UB-P3-009, UB-P4-015, UB-P4-016.
- Implementation notes: Define views, accepts, dismissals, orders, attributed revenue, discount total, accept rate, and AOV lift estimate. Clarify that aggregate stats contain no PII.
- Acceptance criteria: Merchants understand reporting limits and do not expect raw event logs in v1.
- Validation and testing requirements: Verify every displayed dashboard metric is documented.
- Estimated complexity: Medium.
- Suggested execution order: 98.

## UB-P8-005 - Compatibility Matrix

- Objective: Publish supported, warning-only, and deferred compatibility behavior.
- Scope definition: Add `docs/compatibility-matrix.md`.
- Dependencies: UB-P0-008, UB-P7-007.
- Implementation notes: Include HPOS, classic checkout, Block Checkout, WooPayments, Stripe, PayPal, Subscriptions, multicurrency, bundles/composites, CheckoutWC, CartFlows, FunnelKit, CartBay, and page builders.
- Acceptance criteria: Compatibility claims match actual QA results.
- Validation and testing requirements: Review against `.meta/qa/compatibility-matrix.md` and E2E/gateway test results.
- Estimated complexity: Medium.
- Suggested execution order: 99.

## UB-P8-006 - Marketplace Reviewer Guide

- Objective: Give Woo Marketplace reviewers a repeatable test path.
- Scope definition: Add `docs/marketplace-reviewer-guide.md`.
- Dependencies: UB-P3-010, UB-P5-004, UB-P7-004, UB-P7-005.
- Implementation notes: Include setup, license/test mode notes, classic checkout test, Block Checkout test, product/cart/thank-you tests, compatibility notes, and support contact.
- Acceptance criteria: Reviewer can validate P0 claims without guessing test data or setup order.
- Validation and testing requirements: Follow the guide on the release candidate package.
- Estimated complexity: Medium.
- Suggested execution order: 100.

## UB-P8-007 - Developer Hook Reference

- Objective: Document public hooks, schema, and examples.
- Scope definition: Add `docs/developer-hooks.md`.
- Dependencies: UB-P6-001, UB-P6-002, UB-P6-003.
- Implementation notes: Include filter/action names, parameters, return values, examples, and stability notes.
- Acceptance criteria: Developers can alter eligibility, rendering, pricing, attribution, and analytics events through documented hooks.
- Validation and testing requirements: Run examples as snippets or unit tests where practical; verify hook names match code.
- Estimated complexity: High.
- Suggested execution order: 101.

## UB-P8-008 - Import/Export Guide

- Objective: Help agencies move offer templates safely between sites.
- Scope definition: Add `docs/import-export.md`.
- Dependencies: UB-P2-009, UB-P3-010, UB-P6-006.
- Implementation notes: Document JSON schema, product SKU/name mapping, validation errors, unsupported site-specific fields, and security limits.
- Acceptance criteria: Agency user can export from staging, import into client site, map products, and enable test mode.
- Validation and testing requirements: Run export/import flow on two local stores or two seeded environments.
- Estimated complexity: Medium.
- Suggested execution order: 102.

## UB-P8-009 - Data Retention and Uninstall Guide

- Objective: Explain what data UpsellBay stores and how cleanup works.
- Scope definition: Add `docs/data-retention-uninstall.md`.
- Dependencies: UB-P1-008, UB-P2-008.
- Implementation notes: Cover offers, settings, stats table, attribution meta, transients, logs, scheduled jobs, and cleanup preference. State that data is preserved by default.
- Acceptance criteria: Merchant and marketplace reviewer understand opt-in deletion behavior.
- Validation and testing requirements: Verify guide matches uninstall implementation and settings labels.
- Estimated complexity: Medium.
- Suggested execution order: 103.

## UB-P8-010 - Architecture Documentation

- Objective: Keep internal implementation notes current.
- Scope definition: Update `.meta/architecture/index.md` and add notes for lifecycle, admin IA, data model, services, REST routes, checkout integrations, scheduler, and product isolation.
- Dependencies: UB-P0-009, implementation tasks as completed.
- Implementation notes: Architecture docs must describe actual implementation, not aspirational design. Update when code changes system shape.
- Acceptance criteria: New contributor can navigate the system from `.meta/architecture/index.md`.
- Validation and testing requirements: Cross-check architecture docs against current file tree and service registration.
- Estimated complexity: Medium.
- Suggested execution order: Continuous; final pass at 104.

## UB-P8-011 - Upgrade and Migration Notes

- Objective: Document versioned upgrade behavior for future releases.
- Scope definition: Add `docs/upgrade-migrations.md`.
- Dependencies: UB-P1-007, UB-P2-004, UB-P6-005.
- Implementation notes: Include DB version, settings version, schema migration rules, rollback expectations, and backup recommendation.
- Acceptance criteria: Support can explain what changes during activation or upgrade.
- Validation and testing requirements: Compare migration notes to installer code and migration tests.
- Estimated complexity: Low.
- Suggested execution order: 105.

## UB-P8-012 - Changelog and Release Notes Draft

- Objective: Prepare release communication for v1.0.
- Scope definition: Add `CHANGELOG.md` or release notes under `docs/release-notes.md` depending on final package policy.
- Dependencies: UB-P9-001.
- Implementation notes: Include user-facing features, compatibility, known limitations, and support routing. Do not include unsupported claims or internal-only task IDs unless helpful for maintainers.
- Acceptance criteria: Release notes accurately describe the tested product.
- Validation and testing requirements: Review against final QA results and marketplace listing copy.
- Estimated complexity: Low.
- Suggested execution order: 106.

