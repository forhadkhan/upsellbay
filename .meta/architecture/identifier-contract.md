# Identifier Contract and Product Isolation ADR

Status: accepted for Phase 0.

Task: `UB-P0-002`.

## Decision

All UpsellBay runtime identifiers must be defined once in `app/Core/Constants.php` during Phase 1 and reused everywhere. Runtime code must not invent parallel prefixes, product slugs, namespaces, asset handles, scheduled-action groups, REST namespaces, or storage keys.

UpsellBay is a standalone product. It may coexist with CartBay, but it must not require CartBay or depend on CartBay private state.

**Note — divergence from this ADR in the shipped Pro plugin**: this table below was
written to apply identically to both the free and Pro plugins. The implementation has
since diverged for hook/nonce/asset identifiers to avoid Pro colliding with free at
runtime. Pro's actual values (`app/Core/Constants.php`) are: namespace root
`WPAnchorBay\UpsellBayPro`, hook prefix `upsellbay_pro_`, nonce prefix `upsellbay_pro_`,
asset handle prefix `upsellbay-pro-`, admin menu slug `upsellbay-pro`. Only the option
prefix, settings option, meta prefix, offer CPT, and REST namespace are genuinely shared
between the two plugins as this ADR originally intended. This is treated as an accepted,
intentional divergence (it avoids hook-namespace collisions between two active plugins)
rather than a defect — see [free-pro-coupling.md](./free-pro-coupling.md) for the full
identifier-by-identifier comparison and its consequences for hook wiring.

## Required Identifiers

| Identifier | Required value | Runtime constant expectation |
| --- | --- | --- |
| PHP namespace root | `WPAnchorBay\UpsellBay` | `Constants::NAMESPACE_ROOT` or equivalent. |
| PSR-4 source directory | `app/` | Composer autoload configuration. |
| Text domain | `upsellbay` | `Constants::TEXT_DOMAIN`. |
| Plugin slug | `upsellbay` | `Constants::PLUGIN_SLUG`. |
| Plugin entry file | `upsellbay.php` | `Constants::PLUGIN_FILE` or entry-file constant. |
| REST namespace | `upsellbay/v1` | `Constants::REST_NAMESPACE`. |
| Option prefix | `upsellbay_` | `Constants::OPTION_PREFIX`. |
| Main settings option | `upsellbay_settings` | Derived from option prefix. |
| Offer/order attribution meta prefix | `_ub_` | `Constants::META_PREFIX`. |
| Hook prefix | `upsellbay_` | `Constants::HOOK_PREFIX`. |
| Nonce prefix | `upsellbay_` | `Constants::NONCE_PREFIX`. |
| CPT | `upsellbay_offer` | `Constants::OFFER_POST_TYPE`. |
| Stats table suffix | `upsellbay_offer_stats_daily` | `Constants::STATS_TABLE_SUFFIX`. |
| Action Scheduler group | `upsellbay` | `Constants::ACTION_SCHEDULER_GROUP`. |
| Asset handle prefix | `upsellbay-` | `Constants::ASSET_HANDLE_PREFIX`. |
| License product slug | `upsellbay` | `Constants::LICENSE_PRODUCT_SLUG`. |

## Never Use in Runtime Code

These identifiers are prohibited outside documentation, historical notes, and explicit coexistence notices:

- `cartbay_`
- `_cartbay_`
- `cartbay-`
- `CartBay`
- `WPAnchorBay\CartBay`

Runtime code must not read from, write to, query, enqueue, schedule, or branch behavior on CartBay private data. CartBay can be detected only for optional coexistence guidance and conflict messaging.

## Product Boundary

UpsellBay runtime code must not add abandoned-cart capture, recovery sequences, recovery notifications, recovery email templates, unsubscribe flows, restore links, SMS, WhatsApp, popup lead capture, CRM automation, or funnel-builder behavior.

Allowed reuse is limited to engineering lessons, WordPress/WooCommerce patterns, and future documented public hooks. Private CartBay state and CartBay runtime code are not part of the UpsellBay dependency graph.

## Enforcement

Phase 1 must create `app/Core/Constants.php` before other runtime classes depend on identifiers.

Static scans before release must include:

```bash
rg -n "cartbay_|_cartbay_|cartbay-|WPAnchorBay\\\\CartBay|CartBay" app assets src templates tests docs .meta
rg -n "upsellbay_|_ub_|upsellbay-|upsellbay/v1|upsellbay_offer" app assets src templates tests docs .meta
```

Reviewers must confirm any CartBay match is documentation or coexistence copy only.
