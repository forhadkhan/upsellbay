# Phase 7 Quality Assurance Runbook

Status: in progress for Phase 7.

This runbook defines the repeatable evidence required before UpsellBay can be marked marketplace-ready. It does not replace the hard launch gates in `.meta/qa/launch-gates.md`; each gate must be backed by current command output, screenshots, logs, or reviewer notes.

## Local Automated Gates

Run from the plugin root:

```bash
composer test
composer phpcs
composer phpstan
bun run build
bun run i18n:make-pot
composer qa:static
composer qa:isolation
composer qa:performance
composer plugin-check
```

Record results in `.meta/qa/release-validation.md`.

## Task Coverage

| Task | Evidence required |
| --- | --- |
| UB-P7-001 | `composer test` runs deterministically through `tests/run.php`; full PHPUnit/WP test environment remains a launch hardening item if adopted later. |
| UB-P7-002 | Unit coverage for settings, offer validation, rules, discounts, prioritization, import/export, logger masking, rate limiting, and tokens passes through `composer test`. |
| UB-P7-003 | Integration coverage includes activation schema, CPT registration, settings persistence, stats repositories, attribution CRUD objects, scheduler jobs, and route permission behavior where local isolated tests can verify it. WordPress/WooCommerce integration runs must be captured before release. |
| UB-P7-004 | Classic Checkout E2E scenario output must be captured from `.meta/qa/e2e-test-plan.md`. |
| UB-P7-005 | Block Checkout E2E scenario output must be captured before any Blocks compatibility claim. |
| UB-P7-006 | Product, cart, and thank-you placement E2E scenario output must be captured from desktop and mobile runs. |
| UB-P7-007 | `.meta/qa/compatibility-matrix.md` and `docs/compatibility-matrix.md` must match observed compatibility behavior. |
| UB-P7-008 | `php scripts/qa-performance-bench.php` covers repeatable rule-evaluation timing; checkout overhead and analytics dashboard timings require a seeded WooCommerce environment. |
| UB-P7-009 | `composer phpcs`, `composer phpstan`, `php scripts/qa-static-gates.php`, REST negative tests, and manual review must show no high or critical security findings. |
| UB-P7-010 | Keyboard, screen reader spot checks, automated accessibility scan, and mobile screenshots must be captured for admin and storefront paths. |
| UB-P7-011 | PHPCS, PHPStan, build, POT generation, plugin check, and QIT outputs must be attached or summarized. |
| UB-P7-012 | `php scripts/qa-product-isolation.php` must pass, and any allowed CartBay references must remain coexistence-only. |
| UB-P7-013 | Marketplace checklist, reviewer guide, data retention docs, compatibility claims, screenshots, and QIT outputs must be reviewed together before submission. |

## Completion Rule

Phase 7 can move to `COMPLETED` only after every task above has current evidence. If external tooling is unavailable locally, leave the task in progress and document the blocker in `.meta/qa/release-validation.md`.
