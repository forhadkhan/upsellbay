# Phase 7 Product Isolation Scan

Status: in progress.

## Command

```bash
composer qa:isolation
```

## Scope

The scanner checks runtime code, assets, source entries, templates, and tests for accidental CartBay coupling:

- `cartbay_`
- `_cartbay_`
- `cartbay-`
- `WPAnchorBay\\CartBay`
- Recovery-only product language such as restore links, unsubscribe flows, recovery email templates, and abandoned cart recovery.

Allowed runtime references are limited to optional coexistence guidance in `app/Admin/Coexistence.php` and conflict messaging in `app/Domain/Compatibility/CompatibilityScanner.php`.

## Latest Result

2026-05-30:

```text
UpsellBay product isolation scan passed.
Scanned: app, assets, src, templates, tests
Allowed coexistence files: app/Admin/Coexistence.php, app/Domain/Compatibility/CompatibilityScanner.php, tests/test-admin-architecture.php, tests/test-core-business-logic.php, tests/test-merchant-experience.php, tests/test-quality-assurance.php
```
