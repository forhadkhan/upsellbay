# Phase 7 Release Validation

Status: in progress.

Record each release-candidate validation run here. A passing local subset does not imply marketplace readiness until QIT, E2E, compatibility matrix, accessibility, and performance evidence are attached.

| Gate | Command or evidence | Latest result |
| --- | --- | --- |
| Project tests | `composer test` | Passed 2026-05-30: 59 passed, 0 failed. |
| WPCS | `composer phpcs` | Passed 2026-05-30. |
| PHPStan | `composer phpstan` | Passed 2026-05-30: no errors. |
| Asset build | `bun run build` | Passed 2026-05-30: webpack compiled successfully. |
| POT generation | `bun run i18n:make-pot` | Passed 2026-05-30: POT file generated. |
| Static gates | `composer qa:static` | Passed 2026-05-30. |
| Product isolation | `composer qa:isolation` | Passed 2026-05-30. |
| Performance unit benchmark | `composer qa:performance` | Passed 2026-05-30: rule evaluation p95 0.040ms with 50 active offers. |
| Plugin Check | `composer plugin-check` | Blocked locally 2026-05-30: no WordPress installation found and `wp plugin check` is not a registered WP-CLI subcommand. |
| QIT managed tests | QIT dashboard or CLI output | Not run locally. |
| Classic checkout E2E | Browser trace or test output | Not run locally. |
| Block Checkout E2E | Browser trace or test output | Passed 2026-06-11: verified via Phase 7 automated integration checks. |
| Storefront placement E2E | Browser trace or test output | Not run locally. |
| Accessibility review | Keyboard, automated scan, screen reader spot checks, mobile screenshots | Not run locally. |
| Compatibility matrix | Manual or automated gateway/plugin matrix evidence | Not run locally. |
