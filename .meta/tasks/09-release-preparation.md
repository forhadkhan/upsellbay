<!-- STATUS: PENDING -->

# Phase 9 - Release Preparation

Goal: package, audit, submit, and launch UpsellBay v1 with evidence for every PRD launch gate.

## UB-P9-001 - Versioning and Release Branch

- Objective: Establish release version and branch discipline.
- Scope definition: Set plugin version, constants version, package metadata, changelog version, asset versions, and release branch name.
- Dependencies: UB-P7-011, UB-P8-012.
- Implementation notes: Use semantic versioning starting at `1.0.0` unless business decides a pre-release tag. Keep version in one source of truth or verify all mirrored locations match.
- Acceptance criteria: Plugin header, constants, package metadata, changelog, and built assets agree on version.
- Validation and testing requirements: Static scan for version strings and package metadata.
- Estimated complexity: Low.
- Suggested execution order: 107.

## UB-P9-002 - Final Launch Gate Audit

- Objective: Confirm no PRD launch blocker remains.
- Scope definition: Run through `.meta/qa/launch-gates.md`, PRD section 15, and PRD section 17.
- Dependencies: UB-P7-004 through UB-P7-013.
- Implementation notes: Block release for incomplete Block Checkout claim, incorrect totals, HPOS failure, direct order postmeta, primary order mutation, subscription discount leakage, license outage disabling offers, client-sent pricing trust, QIT high-severity failures, non-native admin shell, or CartBay dependency.
- Acceptance criteria: Every launch gate is passed with evidence or release is stopped.
- Validation and testing requirements: Save final audit in `.meta/qa/final-launch-gate-audit.md`.
- Estimated complexity: High.
- Suggested execution order: 108.

## UB-P9-003 - Marketplace Listing Draft

- Objective: Prepare accurate Woo Marketplace submission content.
- Scope definition: Draft product name, short description, long description, screenshots list, features, compatibility, pricing, support policy, refund policy, and reviewer notes.
- Dependencies: UB-P8-001 through UB-P8-009, UB-P7-013.
- Implementation notes: Position as "Native WooCommerce order bumps and offers that increase AOV without replacing checkout." Do not claim funnels, abandoned cart recovery, or tokenized one-click upsells.
- Acceptance criteria: Listing copy matches tested features and does not overclaim Block Checkout or AOV lift.
- Validation and testing requirements: Copy review against PRD sections 11, 12, 15, and final QA evidence.
- Estimated complexity: Medium.
- Suggested execution order: 109.

## UB-P9-004 - License Server and Update Metadata Verification

- Objective: Confirm direct-sales licensing and updates work independently for UpsellBay.
- Scope definition: Verify WP Anchor Bay product slug `upsellbay`, activation/deactivation, staging/local behavior, cached valid state, update-check URL, package metadata, and outage fail-open behavior.
- Dependencies: UB-P1-012, UB-P7-009.
- Implementation notes: Never reuse CartBay license keys, slugs, transients, updater URLs, or product metadata.
- Acceptance criteria: License server can serve UpsellBay updates and license outages do not disable live offers.
- Validation and testing requirements: Manual license activation/check/deactivation test; simulated outage test; masked key inspection.
- Estimated complexity: High.
- Suggested execution order: 110.

## UB-P9-005 - Production Package Build

- Objective: Create the installable release artifact.
- Scope definition: Build assets, install production dependencies, generate translation files, exclude development-only files, include required docs/assets/templates, and package as ZIP.
- Dependencies: UB-P9-001, UB-P7-011.
- Implementation notes: Package must include compiled assets and language template. Exclude `.git`, local environment files, node modules, tests if marketplace package policy excludes them, and hidden metadata not meant for release.
- Acceptance criteria: ZIP installs on a clean WordPress/WooCommerce site and includes all runtime files.
- Validation and testing requirements: Install ZIP on clean site, activate, run smoke tests, and compare package contents to release manifest.
- Estimated complexity: Medium.
- Suggested execution order: 111.

## UB-P9-006 - Release Candidate Smoke Test

- Objective: Validate the packaged artifact, not just the working tree.
- Scope definition: On a clean site, install release ZIP and test activation, license, wizard, first checkout bump, classic checkout, Block Checkout, product/cart/thank-you offers, analytics, import/export, settings, and uninstall preservation.
- Dependencies: UB-P9-005.
- Implementation notes: Use the same paths a merchant or reviewer would use. Record environment versions.
- Acceptance criteria: Packaged plugin passes critical flows with no fatal errors, checkout breakage, or missing assets.
- Validation and testing requirements: Save smoke test notes in `.meta/qa/release-candidate-smoke-test.md`.
- Estimated complexity: High.
- Suggested execution order: 112.

## UB-P9-007 - Demo Store and Reviewer Data

- Objective: Prepare a deterministic environment for demos, QA, and marketplace review.
- Scope definition: Create seed products, offers, rules, discounts, carts/orders, and reviewer credentials or instructions.
- Dependencies: UB-P8-006, UB-P9-006.
- Implementation notes: Include at least one offer for each P0 placement, one disabled offer, one expired offer, one conflict notice scenario where possible, and analytics seed data.
- Acceptance criteria: Reviewer can follow the guide and see all major features without creating data from scratch.
- Validation and testing requirements: Run reviewer guide end-to-end on demo data.
- Estimated complexity: Medium.
- Suggested execution order: 113.

## UB-P9-008 - Beta Release Plan

- Objective: Launch safely before broad marketplace exposure.
- Scope definition: Define beta cohort, feedback channels, rollback plan, support triage, metrics collection, and release decision criteria.
- Dependencies: UB-P9-006, UB-P8-004.
- Implementation notes: Target at least five beta stores for attributed revenue validation. Do not collect external telemetry unless explicitly approved and documented.
- Acceptance criteria: Beta plan protects checkout stability and defines stop-ship triggers.
- Validation and testing requirements: Review plan against success metrics in PRD section 14.
- Estimated complexity: Medium.
- Suggested execution order: 114.

## UB-P9-009 - Submission and Launch Checklist

- Objective: Execute final submission or direct launch actions.
- Scope definition: Submit marketplace package or publish direct sales package, upload docs, confirm pricing, configure support routing, and archive final QA evidence.
- Dependencies: UB-P9-002 through UB-P9-008.
- Implementation notes: Marketplace price must not exceed direct channel price. Keep CartBay and UpsellBay support categories distinct.
- Acceptance criteria: Launch is complete only when package, docs, pricing, support, and QA artifacts are all present.
- Validation and testing requirements: Final human review of package, listing, docs, and support routing.
- Estimated complexity: Medium.
- Suggested execution order: 115.

## UB-P9-010 - Post-Launch Monitoring and Patch Triage

- Objective: Prepare the first production maintenance loop.
- Scope definition: Define monitoring windows, support severity levels, hotfix branch process, patch release checklist, and known-issue documentation.
- Dependencies: UB-P9-009.
- Implementation notes: Checkout-critical bugs, incorrect totals, duplicate orders, payment failures, and license lockouts are severity 1. Recovery-feature requests should be routed as out-of-scope or CartBay-related.
- Acceptance criteria: Support and engineering know how to respond to launch issues without ad hoc process.
- Validation and testing requirements: Review triage plan against PRD success metrics and launch blockers.
- Estimated complexity: Low.
- Suggested execution order: 116.

