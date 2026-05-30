# Phase 7 Performance Results

Status: in progress.

## Targets

| Area | Target | Current evidence |
| --- | --- | --- |
| Rule evaluation | Less than 10ms p95 for 50 active offers. | Use `php scripts/qa-performance-bench.php`. |
| Checkout overhead | Less than 150ms p95 added server time with 50 active offers and object cache disabled. | Requires seeded WooCommerce checkout benchmark. |
| Analytics dashboard | Less than 500ms p95 with generated data representing 100,000 orders and 500 offers. | Requires seeded stats table benchmark. |
| Asset loading | Separate bundles load only on relevant placement pages. | Covered by asset scoping tests and browser/network review. |

## Latest Local Run

2026-05-30:

```text
Rule evaluation p95 with 50 active offers: 0.040ms
Target: less than 10.000ms
```

Checkout overhead and analytics dashboard benchmarks still require a seeded WooCommerce environment.
