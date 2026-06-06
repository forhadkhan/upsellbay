# Admin Heading Hierarchy

**Date:** 2026-06-02
**Context:** UB-P1-5 tab heading cleanup and onboarding tab label behavior

## Change

Replaced the static `<h1>UpsellBay</h1>` pattern with a dynamic `<h1>` that reflects the current tab. Operational tabs may also render Woo-native `<h2 class="wp-heading-inline">` content headings where the page has list-table, editor, or dashboard content that benefits from a local section anchor.

## Current hierarchy

| Tab / View | Visible `<h1>` | Browser `<title>` |
|---|---|---|
| Dashboard | `UpsellBay` | `UpsellBay` |
| Offers list | `UpsellBay › Offers` | `UpsellBay › Offers` |
| Offer edit | `UpsellBay › Add Offer` | `UpsellBay › Add Offer` |
| Settings | `UpsellBay › Settings` | `UpsellBay › Settings` |
| Tools | `UpsellBay › Tools` | `UpsellBay › Tools` |
| Setup tab (hidden from nav) | `UpsellBay › Get started` | Matches visible `<h1>` |

## Rationale

- Tab navigation indicates which tab is active, while the `<h1>` gives bookmarks and browser history a descriptive page title.
- Woo-native list and editor surfaces can still use inline `<h2>` headings such as `Offers`, `Add UpsellBay Offer`, and `Dashboard` to anchor the local content below tabs and notices.
- The setup tab is hidden from the tab navigation (`is_visible = false`). The `Get Started` link is rendered as an action button in the page header (`upsellbay-layout-header__actions`) alongside `Add Offer`, making it a secondary action rather than a primary navigation destination.
- Section-level `<h2>`s within pages (Settings' Basic/Style/Data/License routed by active section via `current_section()`, Tools' System diagnostics/Import offers) are preserved as real content dividers.

## Implementation

- **`app/Admin/AdminPage.php`** — `page_heading()` method generates the heading from the active tab's label and request context. `filter_admin_title()` hooks `admin_title` to sync the browser tab title.
- **`app/Admin/Navigation/TabFactory.php`** — Keeps setup tab routing centralized. The setup tab has `is_visible` set to `false` so it does not appear in the tab navigation bar. Setup tab label always reads as `Get started`.
- **`app/Admin/AdminPage.php`** — The `render_header()` method renders both `Get Started` and `Add Offer` action buttons in the `.upsellbay-layout-header__actions` area.
- **Tab pages** — Dashboard, Offers, and Offer editor render Woo-native inline content headings where tests and admin layout expect local operational anchors.
- **`templates/admin/wizard.php`** — Removed its own `<h1>` and `.wrap` div since `AdminPage` now provides them.
