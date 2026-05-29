# Dependency and Tooling Plan

Status: accepted for Phase 0.

Task: `UB-P0-005`.

## Platform Baseline

| Dependency | Minimum or target |
| --- | --- |
| PHP | 8.1 minimum; test against 8.1 through 8.4 where supported. |
| WordPress | 6.9 minimum; WordPress 7.0-ready admin styling. |
| WooCommerce | 10.8.x minimum target. |
| JavaScript build | WordPress scripts and dependency extraction. |
| Marketplace validation | QIT managed tests before submission. |

## Composer Plan

Phase 1 should add `composer.json` with:

- PSR-4 autoload for `WPAnchorBay\\UpsellBay\\` from `app/`.
- PHP platform constraint `>=8.1`.
- WordPress Coding Standards and WooCommerce sniffs.
- PHPStan.
- PHPUnit or the chosen WordPress test harness.
- Plugin Check command wiring where available.

Expected scripts:

```bash
composer phpcs
composer phpcbf
composer phpstan
composer test
composer plugin-check
```

## JavaScript Plan

Phase 1 should add `package.json` and build configuration for:

- `@wordpress/scripts`.
- `@wordpress/dependency-extraction-webpack-plugin` or the equivalent dependency extraction configured by WordPress scripts.
- Multiple entry points for admin, classic checkout, Block Checkout, product page, cart, and thank-you assets.
- JS i18n extraction using the `upsellbay` text domain.

Expected scripts:

```bash
bun run build
bun run start
bun run i18n:make-pot
```

`npm` compatibility may be documented later if release tooling requires it, but current project commands use `bun`.

## QA and E2E Plan

Quality gates by task type:

| Change type | Required validation |
| --- | --- |
| PHP runtime | `composer phpcs`, `composer phpstan`, targeted `composer test`. |
| JavaScript or assets | `bun run build`. |
| Strings | `bun run i18n:make-pot`. |
| Checkout/cart/product/thank-you | Relevant Playwright E2E and manual tests from `.meta/tasks/07-quality-assurance.md`. |
| HPOS/order attribution | HPOS enabled and disabled integration coverage plus CRUD static scan. |
| Marketplace readiness | `composer plugin-check` and QIT managed tests. |

## Tooling Guardrails

- Do not add Laravel, Symfony, a custom React admin shell, or broad frameworks.
- Do not add external telemetry or SaaS dependencies unless PRD v4 is revised.
- Do not add direct browser-to-license-server calls.
- Keep built runtime assets committed under `assets/`.
- Keep authored JS under `src/`.
