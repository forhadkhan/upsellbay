# Initial Repository and PRD Baseline Audit

Status: accepted for Phase 0.

Source of truth: `.meta/PRDs/UpsellBay-PRD-v4.md`.

## Scope

This audit satisfies `UB-P0-001`. It records the current repository state before application implementation begins and confirms the PRD v4 priority over notes, task files, and future implementation.

## Current Repository State

| Area | Current state | Phase impact |
| --- | --- | --- |
| Branch | `config-assets` for metadata work; application branch is `main`. | Phase 0 changes belong to the overlay branch because they are `.meta/` and agent documentation. |
| Source of truth | `.meta/PRDs/UpsellBay-PRD-v4.md` exists and is final production-ready PRD v4.0. | All implementation decisions must map to this PRD or a documented architecture decision. |
| Task plan | `.meta/tasks/index.md` and phase files `00` through `09` exist. | Work proceeds sequentially by phase. Phase 0 must complete before plugin bootstrap. |
| Architecture notes | `.meta/architecture/index.md` and `.meta/architecture/git-orphan-overlay.md` exist. | Phase 0 adds the missing product architecture decisions. |
| Planning notes | `.meta/notes/folder-structure-plan.md` and `.meta/notes/plugin-development-blueprint.md` exist. | These are implementation inputs only; PRD v4 wins on conflict. |
| App scaffold | No runtime app scaffold is present yet. | Phase 1 must create `upsellbay.php`, `app/`, Composer tooling, and bootstrap code. |
| QA metadata | `.meta/qa/` did not exist before Phase 0 work. | Phase 0 creates launch gate, risk, and compatibility planning files. |
| Main branch files | `scripts/sync-dots.sh` and `.gitignore` are application-branch concerns. | Do not mix overlay metadata commits with app code commits. |

## Implementation Areas Not Yet Present

The following PRD-backed implementation areas are planned but not yet present in runtime code:

- `upsellbay.php` plugin entrypoint.
- `uninstall.php` data retention and opt-in cleanup handler.
- `app/Core/Constants.php` identifier contract implementation.
- `app/Core/Container.php` minimal service container.
- `app/Core/Plugin.php` bootstrap coordinator.
- `app/Core/Installer.php` activation, upgrade, scheduler, and migration shell.
- `app/Core/Settings.php` settings normalization helpers.
- Admin pages under `app/Admin/` for offers, settings, tools, wizard, analytics, coexistence, compatibility notices, and admin bar test-mode state.
- REST routes under `app/Api/Routes/`.
- Data repositories under `app/Data/`.
- Domain services under `app/Domain/` for offers, rules, cart, discounts, attribution, analytics, and storefront rendering.
- WooCommerce integrations under `app/Integrations/WooCommerce/`.
- Licensing and updater integrations under `app/Integrations/Licensing/`.
- Utilities under `app/Utils/`.
- Authored JavaScript under `src/`.
- Built runtime assets under `assets/`.
- Storefront and admin templates under `templates/`.
- Translation template under `languages/`.
- Test harness and test suites under `tests/`.
- Merchant, developer, compatibility, reviewer, migration, and release docs under `docs/`.
- Composer, PHPCS, PHPStan, PHPUnit, package, build, and i18n tooling files.

## PRD and Notes Alignment

The current notes align with PRD v4 on the major architecture constraints:

- UpsellBay is a standalone WooCommerce AOV offer engine, not a CartBay module.
- Admin entry must be WooCommerce-native and must not use a top-level WordPress admin menu.
- Runtime code must centralize identifiers in `app/Core/Constants.php`.
- Offer configuration must use private CPT `upsellbay_offer`.
- Analytics must use one aggregate, non-PII table named `{$wpdb->prefix}upsellbay_offer_stats_daily`.
- Order attribution must use WooCommerce order and order-item CRUD APIs only.
- Block Checkout support is a launch gate, not a marketing claim before E2E proof.

One note requires careful interpretation: `.meta/notes/folder-structure-plan.md` says the folder structure is based partly on CartBay implementation patterns. That means implementation may reuse lessons and design patterns, but must not reuse CartBay options, meta keys, tables, sessions, routes, scheduled jobs, recovery state, or recovery modules.

## Baseline Decision

Implementation can begin only after Phase 0 architecture proof documents are present. Runtime code must not be created during Phase 0 unless a task explicitly requires a prototype; the current Phase 0 task file requests plans and ADRs, not production runtime implementation.
