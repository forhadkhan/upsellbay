# Risk Register

Status: accepted for Phase 0.

Task: `UB-P0-007`.

| Risk | Severity | Owner role | Mitigation | Verification | Release decision |
| --- | --- | --- | --- | --- | --- |
| Block Checkout rich UI cannot be implemented with supported APIs. | High | Engineering lead | Run Week 1 proof before compatibility claims. Keep unsupported private APIs prohibited. | Block Checkout POC and Playwright E2E with WooPayments active. | Block launch claim or re-scope product before release. |
| Checkout totals become incorrect after accept/unaccept. | High | Checkout engineer | Server-side cart mutation and discount calculation only. Unit and E2E tests around totals. | Classic and Block checkout E2E; discount unit tests. | Block release until fixed. |
| HPOS attribution fails or uses legacy order storage. | High | Data engineer | Use Woo CRUD only and static scans for direct order storage. | HPOS enabled/disabled tests and CRUD scan commands. | Block release until fixed. |
| Follow-on offer mutates the primary order. | High | Checkout engineer | Thank-you offers start a separate cart/checkout flow linked by metadata. | Thank-you E2E and source order immutability assertion. | Block release until fixed. |
| Subscription discount leaks into renewals. | High | Discounts engineer | Detect subscription products and fail closed unless safe. | Subscription compatibility tests and manual gateway review. | Block release for affected support claim. |
| License outage disables live offers. | High | Licensing engineer | Cache last-known valid state and fail open for live offers. | License outage simulation. | Block release until live offers remain active. |
| Public REST endpoint trusts client-sent price or discount. | High | API engineer | Validate offer, product, discount, and totals server-side. | REST negative tests and code review. | Block release until fixed. |
| QIT high-severity failure. | High | Release owner | Run QIT before submission and treat failures as release blockers. | QIT managed test result. | Block marketplace submission. |
| Admin UI becomes a custom app shell. | Medium | Admin engineer | Use Woo/WP native tables, notices, settings tables, and help tips. | Admin UX review against AGENTS.md and PRD v4. | Block marketplace copy and release polish until corrected. |
| CartBay feature bleed or private data coupling. | High | Architecture reviewer | Enforce identifier contract, static scans, and coexistence-only integration. | CartBay coupling scan and code review. | Block release until removed. |
| Product scope expands into funnels or recovery. | Medium | Product owner | PRD v4 boundaries; deviations require architecture decision before implementation. | Documentation and admin-copy review. | Defer scope or revise PRD before continuing. |
| Analytics migration causes database issues. | Medium | Data engineer | Use versioned `dbDelta`, bounded aggregate table, and migration tests. | Migration test and rollback review. | Block analytics release if unsafe. |
| Compatibility conflicts with checkout/funnel plugins. | High | QA owner | Detect known plugins, warn rather than hard-fail, document matrix. | Compatibility matrix tests from Phase 7. | Release may proceed only with documented limitations. |
