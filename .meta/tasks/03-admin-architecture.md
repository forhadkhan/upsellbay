<!-- STATUS: PENDING -->

# Phase 3 - Admin Architecture

Goal: build a WooCommerce-native admin experience under WooCommerce -> UpsellBay without a custom app shell or CartBay recovery features.

## UB-P3-001 - Admin Menu and Page Routing

- Objective: Register the UpsellBay admin area under WooCommerce only.
- Scope definition: Add submenu pages for Offers, Add Offer, Analytics, Settings, Tools, and Help.
- Dependencies: UB-P1-005, UB-P1-010.
- Implementation notes: Use `add_submenu_page()` with `manage_woocommerce`. Do not create a top-level WordPress admin menu. Use screen IDs to scope assets.
- Acceptance criteria: WooCommerce -> UpsellBay appears and all pages enforce `manage_woocommerce`.
- Validation and testing requirements: Manual admin navigation test; static scan confirms no `add_menu_page()` for UpsellBay.
- Estimated complexity: Medium.
- Suggested execution order: 36.

## UB-P3-002 - Offers List Table

- Objective: Provide native offer management.
- Scope definition: Create `app/Admin/Offers/OfferListTable.php` and the Offers page controller.
- Dependencies: UB-P2-003, UB-P3-001.
- Implementation notes: Use `WP_List_Table` patterns with search, placement/status filters, sortable priority, bulk pause/delete, duplicate action, delete/trash action, preview link, and performance columns.
- Acceptance criteria: Merchant can create, edit, pause, duplicate, delete, preview, reorder, search, and filter offers.
- Validation and testing requirements: Admin integration test for list table actions and nonce/capability checks; manual keyboard navigation check.
- Estimated complexity: High.
- Suggested execution order: 37.

## UB-P3-003 - Offer Editor Shell

- Objective: Build the Add/Edit Offer admin screen.
- Scope definition: Create `app/Admin/Offers/OfferEditPage.php` with native meta boxes or Woo-style form sections.
- Dependencies: UB-P2-002, UB-P2-003, UB-P3-001.
- Implementation notes: Fields include offer type, status, offered product, trigger products/categories/tags, headline, body, button text, image toggle, discount, rules match mode, schedule, priority, and placement config.
- Acceptance criteria: Offer save uses nonce and capability checks, sanitizes all fields, validates through `OfferValidator`, and returns clear Woo admin notices.
- Validation and testing requirements: Integration tests for valid save, invalid product, invalid discount, missing nonce, insufficient capability, and overlong text.
- Estimated complexity: High.
- Suggested execution order: 38.

## UB-P3-004 - Rule Builder UI

- Objective: Let merchants configure P0 targeting rules without hand-editing JSON.
- Scope definition: Add admin UI and JS for cart product, category/tag, subtotal, viewed product, user role, customer order count, lifetime spend, stock status, and exclude-if-product-in-cart.
- Dependencies: UB-P3-003, UB-P4-003.
- Implementation notes: UI should store normalized rule arrays. Use native controls, Select2 product/category search, and concise `wc_help_tip()` guidance.
- Acceptance criteria: Rules saved in the editor match the normalized schema consumed by the server-side evaluator.
- Validation and testing requirements: Browser test for adding/removing rule rows and saving; unit test server rejects malformed submitted rules.
- Estimated complexity: High.
- Suggested execution order: 52.

## UB-P3-005 - Admin Overview Summary

- Objective: Provide a quick operational snapshot without creating a separate marketing dashboard.
- Scope definition: Add a compact summary band on the Offers or Analytics landing view with active offers, test mode state, recent attributed revenue, and warnings.
- Dependencies: UB-P2-005, UB-P3-002, UB-P3-008.
- Implementation notes: Use native Woo cards/metabox-like panels sparingly. Keep the primary admin workflow focused on offers and analytics.
- Acceptance criteria: Merchant can see whether the plugin is enabled, test mode is on, offers are active, and compatibility warnings exist.
- Validation and testing requirements: Manual admin layout check on desktop and mobile admin widths; data loading under 500ms on seeded stats.
- Estimated complexity: Medium.
- Suggested execution order: 65.

## UB-P3-006 - Settings Page and Sections

- Objective: Implement native settings management.
- Scope definition: Create `app/Admin/Settings/SettingsPage.php`, section interface/base class, `GeneralSection`, `StyleSection`, `DataSection`, and licensing/settings rows as needed.
- Dependencies: UB-P1-010, UB-P1-012, UB-P3-001.
- Implementation notes: Use WordPress/WooCommerce settings table patterns, nonces, `manage_woocommerce`, WP color picker for P1 style controls, and no custom admin app shell.
- Acceptance criteria: Settings save persists normalized values and shows Woo admin notices.
- Validation and testing requirements: Integration tests for save permissions, nonce failure, checkbox normalization, and invalid retention.
- Estimated complexity: High.
- Suggested execution order: 39.

## UB-P3-007 - Test Mode Controls

- Objective: Make preview/testing safe and visible to admins.
- Scope definition: Add test mode toggle in settings, admin bar indicator, and preview links for eligible contexts.
- Dependencies: UB-P1-010, UB-P3-003, UB-P4-008.
- Implementation notes: Test mode must affect admins only and must not show forced offers to shoppers. Admin bar notice should be visible while browsing storefront as an admin.
- Acceptance criteria: Admins can force eligible offers for preview and disable test mode before launch.
- Validation and testing requirements: Manual test as admin and guest; verify guest sessions never inherit admin test mode.
- Estimated complexity: Medium.
- Suggested execution order: 58.

## UB-P3-008 - Compatibility and Coexistence Notices

- Objective: Warn about known conflicts without blocking stores unnecessarily.
- Scope definition: Create `app/Admin/CompatibilityNotice.php` and `app/Admin/Coexistence.php`.
- Dependencies: UB-P0-008, UB-P1-010.
- Implementation notes: Detect checkout/funnel/recovery plugins where possible. CartBay detection must show optional guidance only and must never read CartBay private data.
- Acceptance criteria: Notices are dismissible, stored in UpsellBay settings, and explain risk with links to compatibility docs.
- Validation and testing requirements: Manual plugin-active simulations; static scan confirms no CartBay options/meta/session reads.
- Estimated complexity: Medium.
- Suggested execution order: 40.

## UB-P3-009 - Analytics Admin Page

- Objective: Display offer performance from aggregate stats.
- Scope definition: Create `app/Admin/Analytics/AnalyticsPage.php`.
- Dependencies: UB-P2-005, UB-P2-011, UB-P3-001.
- Implementation notes: Show views, accepts, dismissals, accept rate, orders, attributed revenue, discount total, AOV lift estimate, date range filter, placement filter, and per-offer table.
- Acceptance criteria: Dashboard loads from `StatsRepository` and does not scan live orders.
- Validation and testing requirements: Performance test with generated data representing 100,000 orders and 500 offers; target under 500ms p95 for dashboard query/render.
- Estimated complexity: High.
- Suggested execution order: 66.

## UB-P3-010 - Tools and Diagnostics Page

- Objective: Provide operational tools for agencies, support, and marketplace review.
- Scope definition: Add Tools page with import/export, compatibility scan, system info, log controls, stats reconciliation trigger, and safe cleanup preview.
- Dependencies: UB-P2-009, UB-P2-011, UB-P3-008, UB-P8-006.
- Implementation notes: Use native forms, nonces, capability checks, file validation, and masked sensitive values in system info.
- Acceptance criteria: Support can gather useful diagnostics without exposing license keys, emails, tokens, or payment identifiers.
- Validation and testing requirements: Security test for nonce/capability failures; manual export/import flow; verify logs mask sensitive values.
- Estimated complexity: High.
- Suggested execution order: 67.

## UB-P3-011 - Help Page and Support Routing

- Objective: Guide merchants without overloading admin screens.
- Scope definition: Add Help page links to setup guide, first offer tutorial, compatibility matrix, data retention guide, developer docs, and support distinction between UpsellBay and CartBay.
- Dependencies: UB-P8-001, UB-P8-002, UB-P8-005.
- Implementation notes: Keep admin copy concise. Do not describe abandoned cart recovery, recovery email sequences, or CartBay-only concepts as UpsellBay features.
- Acceptance criteria: Help page routes AOV offer questions to UpsellBay docs and recovery-session/email questions to CartBay docs/support.
- Validation and testing requirements: Copy review against PRD section 2 and section 7.7.
- Estimated complexity: Low.
- Suggested execution order: 86.

## UB-P3-012 - Admin Asset Scoping

- Objective: Load admin CSS/JS only where needed.
- Scope definition: Add `assets/admin/css/upsellbay-admin.css`, `assets/admin/js/upsellbay-admin.js`, `assets/admin/js/upsellbay-offer-editor.js`, and `assets/admin/js/upsellbay-analytics.js`.
- Dependencies: UB-P1-013, UB-P3-001, UB-P3-003.
- Implementation notes: Use screen IDs for enqueueing. Use WordPress dependencies for Select2, color picker, i18n, and API fetch where appropriate.
- Acceptance criteria: No UpsellBay admin assets load outside UpsellBay admin screens unless the admin bar test mode indicator requires a tiny scoped asset.
- Validation and testing requirements: Browser devtools asset check on WooCommerce Orders, Products, and UpsellBay pages.
- Estimated complexity: Medium.
- Suggested execution order: 41.

## UB-P3-013 - Recovery Module Exclusion Guard

- Objective: Prevent generic task examples from becoming accidental product scope.
- Scope definition: Explicitly document that recovery sequences, recovery notifications, recovery email templates, abandoned-cart sessions, unsubscribe flows, and restore links are not UpsellBay admin modules.
- Dependencies: UB-P0-002, UB-P3-001.
- Implementation notes: Add the guard to `.meta/architecture/admin-information-architecture.md` and `AGENTS.md`.
- Acceptance criteria: Admin IA includes Offers, Add Offer, Analytics, Settings, Tools, Help, Wizard, and diagnostics only; no recovery admin pages exist.
- Validation and testing requirements: Static scan for `recovery`, `abandoned`, `sequence`, `unsubscribe`, and `restore` in runtime code; allowed only in docs/guardrails or compatibility copy.
- Estimated complexity: Low.
- Suggested execution order: 42.

