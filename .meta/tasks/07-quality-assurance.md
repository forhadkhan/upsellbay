<!-- STATUS: IN_PROGRESS -->

# Phase 7 - Quality Assurance

Goal: prove UpsellBay is secure, stable, performant, Woo-native, HPOS-safe, Block Checkout-safe, and marketplace-ready before release.

## UB-P7-001 - PHPUnit and Test Harness

- Objective: Establish automated PHP test execution.
- Scope definition: Configure PHPUnit bootstrap, WordPress/WooCommerce test environment, factories, and test suites.
- Dependencies: UB-P1-003.
- Implementation notes: Separate suites for Core, Data, Domain, REST, Admin, Storefront, Extensibility, and Integration where practical.
- Acceptance criteria: `composer test` runs and produces deterministic results in local development.
- Validation and testing requirements: Run the test suite from a clean checkout after dependencies are installed.
- Estimated complexity: High.
- Suggested execution order: 81.

## UB-P7-002 - Unit Test Coverage

- Objective: Cover deterministic business logic before broad integration testing.
- Scope definition: Test settings normalization, offer validation, rule parsing/evaluation, discount calculation, prioritization, import/export schema, logger masking, rate limiter, and token helper.
- Dependencies: UB-P1-014, UB-P2-002, UB-P4-003, UB-P4-004, UB-P4-002, UB-P2-009.
- Implementation notes: Prefer focused unit tests with minimal WordPress state where possible.
- Acceptance criteria: Core logic has tests for success and failure paths, including malformed inputs.
- Validation and testing requirements: Run targeted unit suite and full `composer test`.
- Estimated complexity: High.
- Suggested execution order: 82.

## UB-P7-003 - Integration Tests

- Objective: Verify WordPress/WooCommerce storage and lifecycle behavior.
- Scope definition: Test activation, deactivation, CPT registration, settings persistence, stats table migration, offer repository, cart session, REST routes, attribution writer, and scheduler jobs.
- Dependencies: UB-P1-007, UB-P2-003, UB-P2-005, UB-P4-014, UB-P4-015, UB-P1-011.
- Implementation notes: Include HPOS enabled and disabled coverage for order attribution where the test environment supports it.
- Acceptance criteria: Integration tests catch lifecycle, storage, and permission failures.
- Validation and testing requirements: Run integration suite locally and in CI.
- Estimated complexity: High.
- Suggested execution order: 83.

## UB-P7-004 - Classic Checkout E2E Suite

- Objective: Prove classic checkout order bump stability.
- Scope definition: Add E2E tests for classic checkout render, accept, unaccept, totals refresh, place order, attribution, invalid offer, and out-of-stock behavior.
- Dependencies: UB-P4-009, UB-P4-015.
- Implementation notes: Use Playwright or QIT-compatible E2E tooling. Test `[woocommerce_checkout]` with a simple product cart and at least one payment method.
- Acceptance criteria: Classic checkout E2E passes with no incorrect totals or payment submission failures.
- Validation and testing requirements: Run tests with HPOS enabled and WooCommerce debug logging enabled.
- Estimated complexity: High.
- Suggested execution order: 84.

## UB-P7-005 - Block Checkout E2E Suite

- Objective: Prove Block Checkout support before claiming compatibility.
- Scope definition: Add E2E tests for Block Checkout render, accept, unaccept, Store API totals update, place order, attribution, keyboard interaction, and WooPayments active path.
- Dependencies: UB-P4-010, UB-P4-015.
- Implementation notes: This is a launch gate. If tests fail due to unsupported APIs or incomplete UI, do not declare Block Checkout compatibility.
- Acceptance criteria: Block Checkout E2E passes and product metadata/marketplace copy can truthfully claim support.
- Validation and testing requirements: Run with WooPayments active and HPOS enabled.
- Estimated complexity: High.
- Suggested execution order: 85.

## UB-P7-006 - Storefront Placement E2E Suite

- Objective: Verify product, cart, and thank-you offer flows.
- Scope definition: Add E2E tests for product-page offer, cart cross-sell, thank-you follow-on checkout, dismissal, mobile layout, and no primary-order mutation.
- Dependencies: UB-P4-011, UB-P4-012, UB-P4-013.
- Implementation notes: Include simple product paths and explicit unsupported variation/bundle behavior.
- Acceptance criteria: All P0 placements render, accept/dismiss, attribute, and degrade safely.
- Validation and testing requirements: Run desktop and mobile viewport tests; inspect order meta for follow-on linkage.
- Estimated complexity: High.
- Suggested execution order: 87.

## UB-P7-007 - WooCommerce Compatibility Matrix Tests

- Objective: Validate required plugin compatibility and documented limitations.
- Scope definition: Test WooPayments, Stripe for WooCommerce, PayPal Payments, WooCommerce Subscriptions, CartBay active/inactive, known checkout replacement notices, multicurrency limitation docs, and simple/variable product support.
- Dependencies: UB-P0-008, UB-P4-017, UB-P7-004, UB-P7-005.
- Implementation notes: For each matrix row, record supported, warning-only, deferred, or blocked behavior in `.meta/qa/compatibility-matrix.md` and `docs/compatibility-matrix.md`.
- Acceptance criteria: Compatibility docs match observed behavior.
- Validation and testing requirements: Manual or automated matrix run with screenshots/logs where available.
- Estimated complexity: High.
- Suggested execution order: 88.

## UB-P7-008 - Performance Tests

- Objective: Verify PRD section 10.13 budgets.
- Scope definition: Test checkout overhead under 150ms p95 with 50 active offers and object cache disabled, rule evaluation under 10ms p95, analytics dashboard under 500ms p95 with generated data representing 100,000 orders and 500 offers, and scoped asset loading.
- Dependencies: UB-P4-002, UB-P4-003, UB-P3-009.
- Implementation notes: Create repeatable seed and benchmark scripts under `tests/performance/` or documented QA scripts.
- Acceptance criteria: Performance targets pass or release notes document a blocker and mitigation before launch.
- Validation and testing requirements: Run benchmark suite and save results in `.meta/qa/performance-results.md`.
- Estimated complexity: High.
- Suggested execution order: 89.

## UB-P7-009 - Security Review

- Objective: Validate input handling, output escaping, permissions, rate limits, and secret masking.
- Scope definition: Review admin forms, REST routes, import uploads, logs, license handling, discount calculation, uninstall cleanup, and CartBay isolation.
- Dependencies: UB-P3-003, UB-P3-006, UB-P3-010, UB-P4-014, UB-P1-012.
- Implementation notes: Confirm no client-sent pricing is trusted, no license keys exposed, no PII in analytics, no remote code loading, and no direct CartBay data reads/writes.
- Acceptance criteria: No high or critical findings remain.
- Validation and testing requirements: Run WPCS security sniffs, PHPStan, manual code review, REST negative tests, and static scans for sensitive identifiers.
- Estimated complexity: High.
- Suggested execution order: 90.

## UB-P7-010 - Accessibility Review

- Objective: Verify WCAG 2.1 AA for P0 admin and storefront paths.
- Scope definition: Review offer widgets, checkout toggle, cart/product/thank-you templates, wizard, editor, list table, settings, analytics, and tools.
- Dependencies: UB-P5-008.
- Implementation notes: Focus on keyboard access, labels, focus management, color contrast, screen reader text, responsive layout, and no horizontal scroll.
- Acceptance criteria: Accessibility blockers are resolved before marketplace submission.
- Validation and testing requirements: Keyboard walkthrough, automated accessibility scan, screen reader spot checks, and mobile screenshots.
- Estimated complexity: High.
- Suggested execution order: 91.

## UB-P7-011 - WordPress and WooCommerce Standards Gates

- Objective: Pass local static and marketplace-aligned checks.
- Scope definition: Run PHPCS, PHPStan, i18n generation, build, plugin check, and QIT managed tests.
- Dependencies: UB-P1-003, UB-P1-013, UB-P8-001 through UB-P8-008.
- Implementation notes: QIT managed tests include activation, security, PHPStan, compatibility, API compatibility, and E2E packages where available.
- Acceptance criteria: Zero WPCS violations, zero PHPStan errors at configured level, successful build, updated POT, and QIT passes or documented platform issue.
- Validation and testing requirements: Save command outputs or summaries in `.meta/qa/release-validation.md`.
- Estimated complexity: High.
- Suggested execution order: 92.

## UB-P7-012 - Product Isolation Scan

- Objective: Prove UpsellBay is independent from CartBay.
- Scope definition: Scan runtime code, assets, templates, tests, settings, routes, schedules, and docs for accidental CartBay coupling.
- Dependencies: UB-P3-013, all implementation tasks.
- Implementation notes: Runtime code must not contain `cartbay_`, `_cartbay_`, `cartbay-`, `WPAnchorBay\CartBay`, CartBay sessions, CartBay notification records, recovery sequences, restore links, or unsubscribe behavior. Docs may mention CartBay only for comparison/coexistence.
- Acceptance criteria: Scan report shows no runtime coupling and only documented, intentional references.
- Validation and testing requirements: Add a repeatable grep/ripgrep script or command list to `.meta/qa/product-isolation-scan.md`.
- Estimated complexity: Medium.
- Suggested execution order: 93.

## UB-P7-013 - Marketplace Compliance Review

- Objective: Prepare the product for Woo Marketplace submission.
- Scope definition: Review marketplace requirements, product headers, licensing, readme, screenshots, QIT outputs, reviewer guide, data retention docs, and compatibility claims.
- Dependencies: UB-P7-011, UB-P8-006, UB-P9-003.
- Implementation notes: Marketplace price must not exceed direct channel price. Do not claim Block Checkout support unless UB-P7-005 passed.
- Acceptance criteria: Submission package has no avoidable quality failures and all claims are backed by tests/docs.
- Validation and testing requirements: Run pre-submission checklist from PRD section 17 and save results.
- Estimated complexity: High.
- Suggested execution order: 94.
