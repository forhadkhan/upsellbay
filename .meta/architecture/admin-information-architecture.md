# Admin Information Architecture

UpsellBay admin surfaces live under WooCommerce -> UpsellBay. Runtime code must not register a top-level WordPress admin menu.

## Surfaces

- Dashboard / Overview: default landing tab with operational status, primary next actions, and aggregate offer performance loaded from `StatsRepository`.
- Offers: primary offer list and offer management actions.
- Add/Edit Offer: internal Offers-tab action for native offer configuration and P0 rule rows.
- Settings: general enablement, test mode, placements, style tokens, retention, and cleanup preferences.
- Tools: import/export validation, compatibility scan entry points, diagnostics, log controls, reconciliation trigger, and cleanup preview.
- Help: concise routing to UpsellBay merchant, compatibility, data retention, developer, and support docs.
- Setup: first-run setup belongs to Phase 5 and remains a Woo-native admin surface.

## Runtime Boundaries

- Admin registration uses one `add_submenu_page()` call with parent slug `woocommerce`, menu slug `upsellbay`, and capability `manage_woocommerce`.
- Internal navigation uses WooCommerce-style tabs. The default dashboard route and explicit `tab=dashboard` route render the same dashboard content and dashboard-scoped analytics assets.
- Asset loading is scoped by WooCommerce UpsellBay screen IDs and the active internal tab/action.
- Settings and offer saves require capability and nonce checks before persistence.
- Analytics dashboards read aggregate, non-PII stats. Normal dashboard load must not scan live orders.
- CartBay detection is allowed only for optional coexistence guidance and must not read CartBay options, sessions, metadata, routes, scheduled jobs, recovery notifications, or recovery settings.

## Exclusion Guard

UpsellBay admin IA includes dashboard/overview, offers, offer editor, dashboard analytics, settings, tools, help, setup wizard, compatibility notices, coexistence guidance, diagnostics, and admin-only test mode controls. UpsellBay does not include a separate Analytics tab in v1.

UpsellBay admin IA does not include recovery sequences, recovery notifications, recovery email templates, abandoned-cart sessions, unsubscribe flows, restore links, SMS recovery, WhatsApp recovery, popup lead capture, CRM automation, or funnel-builder modules.
