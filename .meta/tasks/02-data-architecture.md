<!-- STATUS: COMPLETED -->

# Phase 2 - Data Architecture

Goal: implement durable, HPOS-safe, non-PII storage for offers, attribution, settings, analytics, sessions, import/export, and cleanup.

## UB-P2-001 - Offer CPT Registration

- Objective: Register the private `upsellbay_offer` custom post type.
- Scope definition: Add CPT registration in the runtime bootstrap and activation path.
- Dependencies: UB-P1-002, UB-P1-005, UB-P1-007.
- Implementation notes: CPT is private, admin-managed through custom UpsellBay pages, revision-capable where useful, and supports title, status, menu order, and metadata. Do not create a top-level WordPress menu from the CPT.
- Acceptance criteria: Offers can be stored as posts without exposing a public archive, REST leakage, or top-level admin menu.
- Validation and testing requirements: Activation test confirms CPT exists before rewrite flush; admin menu test confirms no top-level CPT menu.
- Estimated complexity: Medium.
- Suggested execution order: 23.

## UB-P2-002 - Offer Meta Schema

- Objective: Define and validate all `_ub_` offer configuration metadata.
- Scope definition: Implement schema for `_ub_offer_type`, `_ub_status`, `_ub_offer_product_id`, triggers, discount fields, headline/body/button text, rules, placement config, show image, schedule, and priority.
- Dependencies: UB-P2-001, UB-P1-010.
- Implementation notes: Centralize schema in `app/Domain/Offers/OfferValidator.php` or a dedicated schema class. Enforce max lengths: headline 80 chars, body 240 chars with limited HTML.
- Acceptance criteria: Invalid meta is rejected or normalized before save; empty rules mean eligible in applicable contexts.
- Validation and testing requirements: Unit tests cover valid values, invalid placement, missing product, invalid discount, bad schedule range, overlong text, and malformed rules.
- Estimated complexity: High.
- Suggested execution order: 24.

## UB-P2-003 - Offer Repository

- Objective: Encapsulate all CPT and meta access for offers.
- Scope definition: Create `app/Data/OfferRepository.php`.
- Dependencies: UB-P2-001, UB-P2-002.
- Implementation notes: Repository methods should support create, update, duplicate, trash/delete, pause, activate, get by ID, query by placement/status/priority, and load normalized meta.
- Acceptance criteria: Domain and admin code do not call raw post/meta APIs for offers except through the repository.
- Validation and testing requirements: Unit/integration tests for CRUD, query filters, priority ordering, duplicate behavior, and invalid product filtering.
- Estimated complexity: High.
- Suggested execution order: 25.

## UB-P2-004 - Stats Table Migration

- Objective: Create and version the aggregate stats table.
- Scope definition: Add migration for `{$wpdb->prefix}upsellbay_offer_stats_daily`.
- Dependencies: UB-P1-007, UB-P1-010.
- Implementation notes: Use `dbDelta`, store DB version in `upsellbay_db_version`, and create unique key `(stat_date, offer_id, placement)`. Columns must match PRD section 10.4.3 exactly.
- Acceptance criteria: Table is created on activation and upgraded idempotently without losing existing rows.
- Validation and testing requirements: Migration test runs twice; inspect table schema; verify unique key prevents duplicate date/offer/placement rows.
- Estimated complexity: High.
- Suggested execution order: 26.

## UB-P2-005 - Stats Repository

- Objective: Encapsulate aggregate stats reads and writes.
- Scope definition: Create `app/Data/StatsRepository.php`.
- Dependencies: UB-P2-004.
- Implementation notes: Provide atomic increment methods for views, accepts, dismissals, orders, revenue, and discount totals. Provide bounded read methods by date range, offer, and placement.
- Acceptance criteria: Analytics code never scans orders for dashboard totals during normal page load.
- Validation and testing requirements: Integration tests for atomic upsert, date range queries, zero rows, decimal revenue precision, and placement filtering.
- Estimated complexity: High.
- Suggested execution order: 27.

## UB-P2-006 - Cart Session State Store

- Objective: Track offer acceptance and dismissal in the WooCommerce session.
- Scope definition: Create `app/Data/CartSession.php`.
- Dependencies: UB-P1-014.
- Implementation notes: Store only non-PII session state: accepted offer IDs, dismissed offer IDs, cart item keys, source placement, and generated guest/session tokens for REST validation.
- Acceptance criteria: Session data survives cart refreshes but does not leak into analytics as PII.
- Validation and testing requirements: Integration tests with WC session for set/get/remove state and guest token validation.
- Estimated complexity: Medium.
- Suggested execution order: 28.

## UB-P2-007 - Attribution Data Contract

- Objective: Define order item and follow-on order attribution keys.
- Scope definition: Document and implement constants for `_ub_offer_id`, `_ub_offer_type`, `_ub_offer_placement`, `_ub_discount_type`, `_ub_discount_amount`, `_ub_source_context`, `_ub_source_order_id`, `_ub_source_offer_id`, and `_ub_follow_on_order`.
- Dependencies: UB-P1-002, UB-P0-004.
- Implementation notes: Attribution must use WooCommerce order/order-item CRUD APIs only. No direct SQL or postmeta writes are allowed.
- Acceptance criteria: Attribution keys are stable and documented for developer use.
- Validation and testing requirements: Static scan rejects direct order postmeta usage; unit tests confirm key names come from constants.
- Estimated complexity: Medium.
- Suggested execution order: 29.

## UB-P2-008 - Data Retention Model

- Objective: Define retention settings and cleanup behavior.
- Scope definition: Implement retention fields for aggregate stats, transient/session cleanup, logs, and uninstall cleanup preference.
- Dependencies: UB-P1-010, UB-P2-004, UB-P2-006.
- Implementation notes: Preserve merchant configuration by default. Retention cleanup must not delete order attribution meta during routine pruning.
- Acceptance criteria: Scheduled prune removes only configured stats/log/session artifacts and never deletes active offers or orders.
- Validation and testing requirements: Unit tests for retention normalization; integration test for prune job on aged stats rows.
- Estimated complexity: Medium.
- Suggested execution order: 30.

## UB-P2-009 - Import/Export JSON Schema

- Objective: Define portable offer export and import data.
- Scope definition: Create `app/Utils/ImportExporter.php` or `app/Domain/Offers/ImportExportService.php` plus a documented JSON schema.
- Dependencies: UB-P2-002, UB-P2-003.
- Implementation notes: Export offer definitions without site-specific product IDs unless SKU/name mapping metadata is included. Import must require admin capability, nonce, file type checks, schema validation, and product mapping.
- Acceptance criteria: Agencies can export an offer template from staging and import it on another store with product SKU mapping.
- Validation and testing requirements: Unit tests for schema validation, invalid JSON, missing products, SKU mapping, and safe error messages.
- Estimated complexity: High.
- Suggested execution order: 31.

## UB-P2-010 - Settings Repository and Migration Helpers

- Objective: Keep option reads/writes and future upgrades consistent.
- Scope definition: Extend `app/Core/Settings.php` with versioned defaults and migration helpers.
- Dependencies: UB-P1-010.
- Implementation notes: Store settings in one option `upsellbay_settings`; keep license key storage and display masking separate enough to avoid accidental frontend exposure.
- Acceptance criteria: Old or partial settings arrays are normalized without warnings and retain valid merchant choices.
- Validation and testing requirements: Unit tests for missing option, legacy option shape, invalid booleans, and license state masking.
- Estimated complexity: Medium.
- Suggested execution order: 32.

## UB-P2-011 - Analytics Reconciliation Data Flow

- Objective: Define how aggregate stats are reconciled without live dashboard order scans.
- Scope definition: Implement data contract for `AnalyticsRecorder`, `StatsRepository`, and `StatsReconciler`.
- Dependencies: UB-P2-005, UB-P2-007.
- Implementation notes: Runtime writes should increment stats at render/accept/dismiss/order events. Reconciler should repair aggregates from attribution where feasible, with bounded date ranges.
- Acceptance criteria: Dashboard reads stats table; reconciliation is idempotent and safe to run more than once.
- Validation and testing requirements: Test duplicate reconciliation does not double-count; test missing aggregate row repair.
- Estimated complexity: High.
- Suggested execution order: 33.

## UB-P2-012 - Data Architecture Tests

- Objective: Create a dedicated test suite for persistence behavior.
- Scope definition: Add tests under `tests/TestCases/Data/`.
- Dependencies: UB-P2-003, UB-P2-005, UB-P2-006, UB-P2-010.
- Implementation notes: Tests should cover offer CRUD, stats upserts, settings normalization, cart session state, import/export schema, and retention pruning.
- Acceptance criteria: Data tests can run independently and fail on schema drift.
- Validation and testing requirements: Run `composer test -- --testsuite=data` or the project equivalent.
- Estimated complexity: Medium.
- Suggested execution order: 34.
