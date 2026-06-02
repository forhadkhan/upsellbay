# Admin Heading Hierarchy

**Date:** 2026-06-02
**Context:** UB-P1-5 tab heading cleanup

## Change

Replaced static `<h1>UpsellBay</h1>` + redundant `<h2>{Tab Name}</h2>` pattern with a single dynamic `<h1>` that reflects the current tab.

## Current hierarchy

| Tab / View | Visible `<h1>` | Browser `<title>` |
|---|---|---|
| Dashboard | `UpsellBay` | `UpsellBay` |
| Offers list | `UpsellBay › Offers` | `UpsellBay › Offers` |
| Offer edit | `UpsellBay › Add Offer` | `UpsellBay › Add Offer` |
| Settings | `UpsellBay › Settings` | `UpsellBay › Settings` |
| Tools | `UpsellBay › Tools` | `UpsellBay › Tools` |
| Setup Wizard | `UpsellBay › Setup` | `UpsellBay › Setup` |

## Rationale

- Tab navigation already indicates which tab is active — the `<h2>` was redundant.
- Previously all tabs shared the same `<h1>` and browser `<title>`, making it hard to distinguish tabs in bookmarks or browser history.
- WordPress admin convention uses a single descriptive heading per page, not h1 + repeating h2.
- Section-level `<h2>`s within pages (Settings' General/Style/Data/License, Tools' System diagnostics/Import offers) are preserved as real content dividers.

## Implementation

- **`app/Admin/AdminPage.php`** — `page_heading()` method generates the heading from the active tab's label and request context. `filter_admin_title()` hooks `admin_title` to sync the browser tab title.
- **Tab pages** — Removed h2 tab-name headings from `DashboardPage`, `SettingsPage`, `ToolsPage`, `WizardController`, `OfferEditPage`, `OffersPage`.
- **`templates/admin/wizard.php`** — Removed its own `<h1>` and `.wrap` div since `AdminPage` now provides them.
