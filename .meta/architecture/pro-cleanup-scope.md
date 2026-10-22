# Pro cleanup scope (PRO-4)

Pro shares `upsellbay_settings` and the `upsellbay_offer` CPT with the free host. Free creates no tables.

- Pro owns: tables `*_upsellbay_pro_offer_stats_daily`, `_logs`, `_fbt_index`; options `upsellbay_pro_db_version`, `upsellbay_pro_license_data`; Action Scheduler group `upsellbay-pro`; inside the shared option the keys in `Settings::pro_only_keys()` and the placement keys in `Settings::pro_placement_keys()`.
- `Installer::uninstall()` (only when `cleanup_on_delete`) and `Installer::clear_all_data()` call `remove_pro_data()`: unschedule, `Settings::remove_pro_keys()` (read, unset Pro keys, write back through Settings' own-write path), drop Pro tables, delete Pro's version stamp. Uninstall also deletes the license data option.
- Never deleted by Pro: the `upsellbay_settings` row, host keys, offer posts and their meta, the shared `upsellbay_db_version`. Pro offers survive both actions (copy in Settings > Data says so).
- `Settings::seed_defaults()` adds only missing Pro keys, overwrites nothing, writes only when something is missing.
- Tests: `tests/test-free-seam.php` (pro cleanup removes only pro keys...; seed defaults adds only missing pro keys...).
