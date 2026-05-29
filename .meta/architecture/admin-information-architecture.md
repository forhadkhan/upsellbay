# Admin Information Architecture

UpsellBay admin surfaces live under WooCommerce -> UpsellBay. Runtime code must not register a top-level WordPress admin menu.

## Surfaces

- Offers: primary offer list and offer management actions.
- Add Offer: native editor shell for offer configuration and P0 rule rows.
- Analytics: aggregate offer performance loaded from `StatsRepository`.
- Settings: general enablement, test mode, placements, style tokens, retention, and cleanup preferences.
- Tools: import/export validation, compatibility scan entry points, diagnostics, log controls, reconciliation trigger, and cleanup preview.
- Help: concise routing to UpsellBay merchant, compatibility, data retention, developer, and support docs.
- Wizard: first-run setup belongs to Phase 5 and remains a Woo-native admin surface.

## Runtime Boundaries

- Admin registration uses `add_submenu_page()` with parent slug `woocommerce` and capability `manage_woocommerce`.
- Asset loading is scoped by WooCommerce UpsellBay screen IDs.
- Settings and offer saves require capability and nonce checks before persistence.
- Analytics dashboards read aggregate, non-PII stats. Normal dashboard load must not scan live orders.
- CartBay detection is allowed only for optional coexistence guidance and must not read CartBay options, sessions, metadata, routes, scheduled jobs, recovery notifications, or recovery settings.

## Exclusion Guard

UpsellBay admin IA includes offers, offer editor, analytics, settings, tools, help, wizard, compatibility notices, coexistence guidance, diagnostics, and admin-only test mode controls.

UpsellBay admin IA does not include recovery sequences, recovery notifications, recovery email templates, abandoned-cart sessions, unsubscribe flows, restore links, SMS recovery, WhatsApp recovery, popup lead capture, CRM automation, or funnel-builder modules.
