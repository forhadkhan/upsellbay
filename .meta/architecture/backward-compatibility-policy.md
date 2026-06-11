# Backward Compatibility Policy

UpsellBay v1 public contracts include documented hooks, REST namespace `upsellbay/v1`, offer schema keys, attribution meta keys, settings option shape, and the aggregate stats table schema.

## Versioning

- Patch releases may fix behavior without changing public signatures.
- Minor releases may add optional fields, hooks, filters, routes, or response fields.
- Breaking changes require a major version or a documented migration path with deprecation warnings.

## Deprecation Rules

- Public hooks must keep argument order and meaning through the current major version.
- Deprecated hooks should remain as wrappers for at least one minor release.
- Schema fields must not be removed without a migration note and fallback reader.
- REST routes stay under `upsellbay/v1`; incompatible route behavior requires a new namespace.

## Data Rules

- Offer meta, attribution meta, options, and stats tables preserve merchant data by default.
- Cleanup remains opt-in through data-retention settings.
- Migrations must be idempotent and must not depend on CartBay state.

Release preparation must check this policy before packaging.
