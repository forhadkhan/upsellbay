<!-- STATUS: COMPLETED -->

# Phase 0 - Project Foundation

Goal: prove the product, architecture, and technical constraints from PRD v4 before implementation starts.

## UB-P0-001 - Repository and PRD Baseline Audit

- Objective: Establish the current repository state and confirm PRD v4 is the definitive specification.
- Scope definition: Review `.meta/PRDs/UpsellBay-PRD-v4.md`, `.meta/notes/folder-structure-plan.md`, `.meta/notes/plugin-development-blueprint.md`, `.meta/architecture/index.md`, and existing files in the repository.
- Dependencies: None.
- Implementation notes: Record findings in `.meta/architecture/initial-audit.md`. Include current branch, existing tracked/untracked files, missing app scaffold, existing `.meta/tasks`, and any mismatch between notes and PRD v4.
- Acceptance criteria: Audit document exists, names PRD v4 as source of truth, and lists every implementation area that is not yet present.
- Validation and testing requirements: Re-read the audit file and verify all referenced paths exist or are explicitly marked as planned.
- Estimated complexity: Low.
- Suggested execution order: 1.

## UB-P0-002 - Identifier Contract and Product Isolation ADR

- Objective: Convert PRD section 10.5 and section 2 isolation rules into a binding architecture decision.
- Scope definition: Define plugin slug, main file, namespace, text domain, option prefix, meta prefix, hook prefix, nonce prefix, REST namespace, CPT, stats table, Action Scheduler group, asset prefix, and license slug.
- Dependencies: UB-P0-001.
- Implementation notes: Create `.meta/architecture/identifier-contract.md`. Include a "never use" list for `cartbay_`, `_cartbay_`, `cartbay-`, `CartBay`, and `WPAnchorBay\CartBay` outside docs and coexistence notices.
- Acceptance criteria: ADR maps every identifier to the PRD value and states that all runtime constants must live in `app/Core/Constants.php`.
- Validation and testing requirements: Static text scan of planned docs confirms no conflicting UpsellBay identifiers are introduced.
- Estimated complexity: Low.
- Suggested execution order: 2.

## UB-P0-003 - Block Checkout Proof Plan

- Objective: Define the Week 1 proof needed before claiming Block Checkout support.
- Scope definition: Plan a minimal proof for rendering a rich checkout bump card in Block Checkout, toggling the offer, updating totals, and preserving checkout submission.
- Dependencies: UB-P0-001, UB-P0-002.
- Implementation notes: Create `.meta/architecture/block-checkout-poc.md`. Include APIs to evaluate: Additional Checkout Fields API, WooCommerce Blocks extension points, Store API cart mutation, dependency extraction, and E2E acceptance path with WooPayments active.
- Acceptance criteria: POC plan identifies supported APIs only, states unsupported private APIs are prohibited, and defines a pass/fail decision gate for launch scope.
- Validation and testing requirements: Architecture review confirms the plan covers render, accept, unaccept, totals refresh, attribution, keyboard access, and mobile display.
- Estimated complexity: Medium.
- Suggested execution order: 3.

## UB-P0-004 - HPOS and CRUD Compliance Proof Plan

- Objective: Prevent direct order table/postmeta usage before order attribution work begins.
- Scope definition: Define rules for order, order item, coupon, and product access through WooCommerce CRUD APIs.
- Dependencies: UB-P0-002.
- Implementation notes: Create `.meta/architecture/hpos-crud-compliance.md`. Include approved APIs such as `wc_get_order()`, `WC_Order_Item_Product`, `$order->update_meta_data()`, `$item->add_meta_data()`, `wc_get_product()`, and `WC_Coupon` for P1 next-order coupons only.
- Acceptance criteria: Document includes prohibited direct access patterns and required static scan patterns.
- Validation and testing requirements: Planned scan commands cover `wp_postmeta`, `_order_itemmeta`, direct SQL on order tables, and direct CartBay identifiers.
- Estimated complexity: Low.
- Suggested execution order: 4.

## UB-P0-005 - Dependency and Tooling Plan

- Objective: Define Composer, JavaScript, WordPress, WooCommerce, QIT, and local test dependencies.
- Scope definition: Plan `composer.json`, `package.json`, PHPCS, PHPStan, PHPUnit, WordPress scripts, dependency extraction, i18n generation, QIT, and optional Playwright setup.
- Dependencies: UB-P0-001.
- Implementation notes: Create `.meta/architecture/development-tooling.md`. Use PHP 8.1 minimum, WordPress 6.9 minimum, WooCommerce 10.8.x minimum, and test PHP 8.1 through 8.4 where supported.
- Acceptance criteria: Tooling plan lists exact scripts to add and when each script must be run.
- Validation and testing requirements: Peer review verifies the plan supports WPCS, PHPStan, unit tests, build, i18n, plugin check, QIT, and E2E.
- Estimated complexity: Medium.
- Suggested execution order: 5.

## UB-P0-006 - Folder Structure Acceptance Plan

- Objective: Convert the folder structure plan into a file ownership map for implementation.
- Scope definition: Confirm directories under `app/`, `assets/`, `src/`, `templates/`, `languages/`, `tests/`, and `docs/`.
- Dependencies: UB-P0-001, UB-P0-002.
- Implementation notes: Create `.meta/architecture/file-ownership-map.md`. For each planned class, list responsibility, owning phase, and dependencies.
- Acceptance criteria: File map covers all services from `.meta/notes/folder-structure-plan.md` and excludes CartBay email/recovery/agent subsystems.
- Validation and testing requirements: Cross-check against PRD section 10.6 and the tasks in phases 1 through 9.
- Estimated complexity: Medium.
- Suggested execution order: 6.

## UB-P0-007 - Launch Gate and Risk Register

- Objective: Turn PRD sections 15 and 16 into a release-blocking checklist.
- Scope definition: Capture launch blockers for Block Checkout, totals, HPOS, attribution, follow-on offers, subscription discounts, license failures, public REST pricing, QIT, native admin UI, and CartBay isolation.
- Dependencies: UB-P0-001.
- Implementation notes: Create `.meta/qa/launch-gates.md` and `.meta/qa/risk-register.md`. Each risk must have severity, owner role, mitigation, verification command or manual test, and release decision.
- Acceptance criteria: Every PRD launch blocker appears as an explicit checklist item.
- Validation and testing requirements: Review against PRD sections 15, 16, and 17.
- Estimated complexity: Low.
- Suggested execution order: 7.

## UB-P0-008 - Competitor and Conflict Plugin Matrix

- Objective: Convert PRD competitor/conflict research into engineering inputs.
- Scope definition: Create a compatibility target list for checkout/funnel/recovery/payment/subscription plugins and a copy boundary for marketplace positioning.
- Dependencies: UB-P0-001.
- Implementation notes: Create `.meta/qa/compatibility-matrix.md`. Include CheckoutWC, CartFlows, FunnelKit, WooPayments, Stripe for WooCommerce, PayPal Payments, WooCommerce Subscriptions, CartBay, multicurrency plugins, bundles/composites, and page builders.
- Acceptance criteria: Matrix defines detect, warn, support, defer, or block behavior for each plugin category.
- Validation and testing requirements: Confirm each matrix row maps to a QA task in `07-quality-assurance.md`.
- Estimated complexity: Medium.
- Suggested execution order: 8.

## UB-P0-009 - Documentation and Decision Logging Standards

- Objective: Define how implementation decisions stay synchronized with docs.
- Scope definition: Establish required updates for `.meta/architecture/`, `.meta/tasks/`, `docs/`, changelog, and developer reference.
- Dependencies: UB-P0-001.
- Implementation notes: Create `.meta/architecture/documentation-standards.md`. Include rules for documenting deviations from PRD v4, public hook changes, schema changes, migrations, and compatibility findings.
- Acceptance criteria: Standards document is referenced by `AGENTS.md` and release checklist.
- Validation and testing requirements: Re-read `AGENTS.md` after creation to ensure it enforces these standards.
- Estimated complexity: Low.
- Suggested execution order: 9.
