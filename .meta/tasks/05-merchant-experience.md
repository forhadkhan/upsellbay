<!-- STATUS: PENDING -->

# Phase 5 - Merchant Experience

Goal: help merchants create, preview, trust, and improve offers without needing custom checkout knowledge.

## UB-P5-001 - First-Run Wizard Controller

- Objective: Guide merchants to the first offer quickly.
- Scope definition: Create `app/Admin/Wizard/WizardController.php` and wizard templates under `templates/admin/`.
- Dependencies: UB-P3-001, UB-P3-003, UB-P4-001.
- Implementation notes: Wizard steps: offer type, product to offer, placement, headline, optional discount, one targeting rule, preview/test mode. Store completion in `upsellbay_settings`.
- Acceptance criteria: A merchant can create and preview the first checkout bump in under 15 minutes.
- Validation and testing requirements: Browser test for full wizard path and settings persistence; manual timing check on a clean store.
- Estimated complexity: High.
- Suggested execution order: 63.

## UB-P5-002 - Sensible Defaults

- Objective: Reduce setup friction while keeping offers explicit.
- Scope definition: Define defaults for headline, body, button text, placement config, image display, status, priority, and discount.
- Dependencies: UB-P2-002, UB-P4-001.
- Implementation notes: Do not create live offers automatically on activation. Defaults should populate new offer forms and wizard steps only.
- Acceptance criteria: New offer forms are usable without blank critical copy, but no shopper-facing offer appears until the merchant saves/enables it.
- Validation and testing requirements: Unit tests for default generation and admin test for new offer prefilled fields.
- Estimated complexity: Medium.
- Suggested execution order: 64.

## UB-P5-003 - Empty States

- Objective: Make zero-data states actionable without marketing clutter.
- Scope definition: Add empty states for Offers, Analytics, Tools import, and Help.
- Dependencies: UB-P3-002, UB-P3-009, UB-P3-010.
- Implementation notes: Empty states should use native admin styling and link to Add Offer, Wizard, setup docs, or test mode preview as appropriate.
- Acceptance criteria: Empty states clearly explain next action and avoid recovery/funnel language.
- Validation and testing requirements: Copy review and admin screenshot check for no layout overlap.
- Estimated complexity: Low.
- Suggested execution order: 68.

## UB-P5-004 - Preview Links and Test Mode Flow

- Objective: Let merchants validate offers before shoppers see them.
- Scope definition: Add preview links from list table, editor, wizard, and settings.
- Dependencies: UB-P3-007, UB-P4-008, UB-P4-009, UB-P4-010, UB-P4-011, UB-P4-012, UB-P4-013.
- Implementation notes: Preview links should route to product/cart/checkout/thank-you contexts when enough data exists and explain when preview is unavailable.
- Acceptance criteria: Admin can preview each placement in test mode and sees a visible admin-only indicator.
- Validation and testing requirements: Browser tests for each placement preview path as admin and guest.
- Estimated complexity: Medium.
- Suggested execution order: 69.

## UB-P5-005 - Guidance UX and Help Tips

- Objective: Keep forms understandable without adding bulky instructional copy.
- Scope definition: Add `wc_help_tip()` or concise inline descriptions for product selection, rules, discounts, display limits, test mode, and compatibility warnings.
- Dependencies: UB-P3-003, UB-P3-006.
- Implementation notes: Use WooCommerce-native help tips. Avoid long paragraphs inside dense settings tables.
- Acceptance criteria: Merchants can understand risky settings such as discounts and test mode without leaving the page.
- Validation and testing requirements: Copy review for clarity, i18n scan for wrapped strings, and accessibility check for tooltip controls.
- Estimated complexity: Low.
- Suggested execution order: 70.

## UB-P5-006 - Progressive Configuration

- Objective: Keep simple setup simple while supporting growth use cases.
- Scope definition: Organize offer editor sections into required basics, targeting rules, discount, placement display, schedule/priority, and advanced metadata.
- Dependencies: UB-P3-003, UB-P3-004.
- Implementation notes: Advanced sections should be collapsible or visually secondary using native admin patterns. No custom app shell.
- Acceptance criteria: A basic checkout bump can be created without touching advanced rules, but all P0 fields remain available.
- Validation and testing requirements: Manual UX walkthrough for small merchant and agency personas from PRD section 8.1.
- Estimated complexity: Medium.
- Suggested execution order: 71.

## UB-P5-007 - Product Recommendation Assistant Baseline

- Objective: Prepare a P1-ready recommendation surface without blocking P0.
- Scope definition: Add optional suggestions in offer editor from Woo upsells/cross-sells, same category, low-priced accessories, and historically accepted offers when data exists.
- Dependencies: UB-P4-016, UB-P3-003.
- Implementation notes: Ship only if P0 is stable. Suggestions must be explainable, local, and optional; do not use external AI or SaaS in v1.
- Acceptance criteria: If shipped, recommendations can be inserted into the offer product field and never auto-create live offers.
- Validation and testing requirements: Unit tests for recommendation source ranking and no-result behavior; feature can be disabled without affecting P0.
- Estimated complexity: Medium.
- Suggested execution order: P1 after release candidate unless explicitly pulled into v1.

## UB-P5-008 - Accessibility and Mobile UX Pass

- Objective: Make admin and storefront workflows usable by keyboard, screen reader, and mobile shoppers.
- Scope definition: Review offer widgets, checkout bump, product/cart/thank-you templates, wizard, editor, list table, and analytics.
- Dependencies: UB-P3-002 through UB-P3-012, UB-P4-009 through UB-P4-013.
- Implementation notes: Ensure labels, focus order, keyboard toggles, ARIA where needed, readable mobile layouts, and no horizontal scroll.
- Acceptance criteria: WCAG 2.1 AA blockers are resolved for P0 admin and storefront paths.
- Validation and testing requirements: Keyboard test, screen reader spot check, mobile viewport screenshots, and automated accessibility scan where available.
- Estimated complexity: High.
- Suggested execution order: 72.

## UB-P5-009 - Merchant Copy Boundary Review

- Objective: Keep product language aligned with PRD positioning.
- Scope definition: Review admin copy, notices, docs links, onboarding, help text, and empty states.
- Dependencies: UB-P3-011, UB-P5-003, UB-P5-005.
- Implementation notes: Use AOV, offer, checkout bump, product offer, cart offer, thank-you follow-on language. Do not use abandoned cart recovery, recovery sequence, funnel builder, or CartBay upgrade language.
- Acceptance criteria: Copy is product-specific, Woo-native, and free of unsupported AOV lift claims.
- Validation and testing requirements: Static scan plus manual copy review against PRD sections 7, 12, and 15.
- Estimated complexity: Low.
- Suggested execution order: 73.

