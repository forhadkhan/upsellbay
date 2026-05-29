<!-- STATUS: PENDING -->

# Phase 6 - Developer Extensibility

Goal: expose stable, documented customization points without turning internal implementation details into accidental public contracts.

## UB-P6-001 - Public Hook Contract

- Objective: Implement the public filters and actions from PRD section 10.15.
- Scope definition: Add filters `upsellbay_offer_schema`, `upsellbay_available_placements`, `upsellbay_offer_query_args`, `upsellbay_rule_context`, `upsellbay_rule_result`, `upsellbay_eligible_offers`, `upsellbay_render_offer_html`, `upsellbay_offer_price`, `upsellbay_discount_amount`, `upsellbay_attribution_meta`, and `upsellbay_analytics_event`; add actions `upsellbay_offer_created`, `upsellbay_offer_updated`, `upsellbay_offer_rendered`, `upsellbay_offer_accepted`, `upsellbay_offer_dismissed`, `upsellbay_attribution_written`, `upsellbay_follow_on_order_created`, and `upsellbay_daily_stats_reconciled`.
- Dependencies: UB-P4-001 through UB-P4-016.
- Implementation notes: Place hooks around stable inputs/outputs only. Prefix all names with `upsellbay_`. Document parameter types at the call site and in developer docs.
- Acceptance criteria: Every PRD hook exists exactly once at the intended boundary and has a documented purpose.
- Validation and testing requirements: Static scan for hook names; unit tests for representative filters/actions receiving expected parameters.
- Estimated complexity: Medium.
- Suggested execution order: 74.

## UB-P6-002 - Offer Schema Developer Contract

- Objective: Make offer definitions predictable for agencies and integrations.
- Scope definition: Document and expose normalized offer fields, allowed values, defaults, and validation errors.
- Dependencies: UB-P2-002, UB-P2-009.
- Implementation notes: Store contract in `docs/developer-hooks.md` or `docs/offer-schema.md` and align import/export schema with internal validation.
- Acceptance criteria: Developers can construct a valid offer payload without reading implementation internals.
- Validation and testing requirements: Schema examples are used in import/export tests.
- Estimated complexity: Medium.
- Suggested execution order: 75.

## UB-P6-003 - REST Endpoint Contracts

- Objective: Stabilize request/response behavior for admin and public REST routes.
- Scope definition: Document `/offer-preview`, `/bump-toggle`, `/cart-offer-add`, `/dismiss`, `/analytics/summary`, and `/import`.
- Dependencies: UB-P4-014, UB-P3-009, UB-P2-009.
- Implementation notes: Include method, auth, nonce/session requirements, request args, response shape, error codes, rate limits, and sensitive-value rules.
- Acceptance criteria: REST docs match route args and validation in code.
- Validation and testing requirements: REST tests compare required args and representative error responses against docs.
- Estimated complexity: Medium.
- Suggested execution order: 76.

## UB-P6-004 - Internal Service API Boundaries

- Objective: Keep services independently testable and prevent admin/REST/rendering layers from bypassing domain logic.
- Scope definition: Document service responsibilities for OfferService, OfferPrioritizer, RuleEvaluator, CartMutator, DiscountApplier, AttributionWriter, AnalyticsRecorder, LicenseClient, CompatibilityScanner, ImportExportService, Scheduler, and Logger.
- Dependencies: UB-P4-001 through UB-P4-017.
- Implementation notes: Add `.meta/architecture/service-boundaries.md` and keep it updated as services are implemented.
- Acceptance criteria: Boundary doc names allowed callers and prohibited responsibilities for each service.
- Validation and testing requirements: Code review checklist confirms controllers/routes/renderers delegate to services rather than duplicating logic.
- Estimated complexity: Low.
- Suggested execution order: 77.

## UB-P6-005 - Backward Compatibility Policy

- Objective: Define how public hooks, schema, options, meta, and tables evolve after v1.
- Scope definition: Create `.meta/architecture/backward-compatibility-policy.md`.
- Dependencies: UB-P2-002, UB-P2-004, UB-P6-001.
- Implementation notes: Include semantic versioning rules, deprecation notices, migration requirements, public hook stability, REST versioning, and data cleanup policy.
- Acceptance criteria: Any future breaking change requires a documented migration or major version decision.
- Validation and testing requirements: Release checklist references this policy before package creation.
- Estimated complexity: Low.
- Suggested execution order: 78.

## UB-P6-006 - Import/Export Extension Points

- Objective: Let agencies adapt portable offer templates without editing plugin internals.
- Scope definition: Add filters for export payload, import mapping, SKU matching, validation errors, and post-import status where needed.
- Dependencies: UB-P2-009, UB-P6-001.
- Implementation notes: Keep filters narrow and documented. Do not allow import filters to bypass capability, nonce, file, or schema validation.
- Acceptance criteria: Developers can map products or adjust labels during import while security checks remain enforced.
- Validation and testing requirements: Unit tests for filtered SKU mapping and validation preservation.
- Estimated complexity: Medium.
- Suggested execution order: 79.

## UB-P6-007 - WP-CLI Utility Plan

- Objective: Prepare P1 WP-CLI commands without delaying P0.
- Scope definition: Define commands for stats rollup, offer export/import, and compatibility diagnostics.
- Dependencies: UB-P2-009, UB-P4-016, UB-P4-017.
- Implementation notes: Add architecture plan first; implement only if P0 and release schedule allow. Commands must use the same services as admin tools.
- Acceptance criteria: P1 plan exists and does not create dead code in v1 if deferred.
- Validation and testing requirements: If implemented, run CLI command tests for success and failure paths.
- Estimated complexity: Medium.
- Suggested execution order: P1 after marketplace readiness unless explicitly approved.

## UB-P6-008 - Developer Extensibility Tests

- Objective: Protect public extension points from silent regressions.
- Scope definition: Add tests under `tests/TestCases/Extensibility/`.
- Dependencies: UB-P6-001 through UB-P6-006.
- Implementation notes: Tests should assert hook firing, filter result handling, REST contract basics, and import/export extension behavior.
- Acceptance criteria: Changing or removing public hook parameters causes test failure.
- Validation and testing requirements: Run targeted extensibility suite and full unit suite.
- Estimated complexity: Medium.
- Suggested execution order: 80.

