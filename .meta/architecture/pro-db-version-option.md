# ADR: Pro keeps its own schema version option

Status: accepted, 2026-10-22 (card PRO-3, UB-8).

## Context
Free and Pro both wrote `upsellbay_db_version`. Free stamps it on activation and deletes it on
uninstall; Pro used it to decide when to run its own schema upgrades. Whichever plugin wrote last
masked the other's version, so a Pro upgrade could be skipped (free stamped a newer value) or
re-run, and uninstalling free removed Pro's stamp.

## Decision
- Pro stores its schema version in `upsellbay_pro_db_version` (`Constants::DB_VERSION_OPTION`).
  `Constants::DB_VERSION` is now `'2'`.
- `Installer::maybe_upgrade()` calls `migrate_version_stamp()`: one-time, idempotent. When Pro's own
  option is below `2` it unschedules retired Action Scheduler hooks (`upsellbay_pro_refresh_analytics`)
  and writes `upsellbay_pro_db_version = 2`.
- Pro never reads or writes the shared `upsellbay_db_version` row. It belongs to free. Reading it as a
  migration source was rejected: free rewrites it, so it would reintroduce the masking.
- Pro's uninstall and "clear all data" delete only `upsellbay_pro_db_version`.

## Consequences
- An existing Pro install with no Pro option runs the stamp migration once on its next load; the
  shared row is left in place for free.
- Free's uninstall no longer affects Pro's upgrade state.
- Tests: `installer stamps its own db version option and leaves the host stamp` in
  `tests/test-free-seam.php`.
