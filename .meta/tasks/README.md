<!-- STATUS: PENDING -->

# UpsellBay Development Roadmap

Source of truth: `.meta/PRDs/UpsellBay-PRD-v4.md`.

These task files convert PRD v4 into an execution-ready development roadmap. They are written for developers and AI agents who may have no project context beyond this repository.

## Execution Rules

- Read PRD v4 before starting any task.
- Read `AGENTS.md` before changing code, docs, tests, or release assets.
- Execute tasks in suggested order unless a dependency explicitly allows parallel work.
- Keep UpsellBay independent from CartBay. Reuse engineering lessons only, not CartBay code, state, routes, options, schedules, or recovery concepts.
- Use native WordPress and WooCommerce APIs first. Add abstractions only when they protect plugin boundaries or reduce meaningful repetition.
- Update architecture docs whenever a task changes storage, service topology, hooks, REST routes, lifecycle behavior, or public extension points.
- Run the validation listed on the task before marking it complete.
- Keep docs synchronized with implementation decisions. If implementation deviates from PRD v4, record the reason in `.meta/architecture/` before continuing.

## Phase Map

| Phase | File | Purpose |
| --- | --- | --- |
| 0 | `00-project-foundation.md` | Confirm PRD scope, architecture, standards, dependencies, and launch gates before coding. |
| 1 | `01-plugin-bootstrap.md` | Build plugin entrypoint, constants, container, lifecycle, settings foundation, license/update base, and asset tooling. |
| 2 | `02-data-architecture.md` | Implement offer storage, metadata, aggregate stats, repositories, migrations, attribution, and retention. |
| 3 | `03-admin-architecture.md` | Build WooCommerce-native admin IA: offers, add/edit offer, analytics, settings, tools, help, diagnostics, and coexistence notices. |
| 4 | `04-core-business-logic.md` | Implement offer validation, rules, priority, discounts, cart mutation, placements, checkout integrations, and attribution flows. |
| 5 | `05-merchant-experience.md` | Add onboarding, defaults, empty states, preview/test mode guidance, and progressive configuration. |
| 6 | `06-developer-extensibility.md` | Stabilize public hooks, schemas, REST contracts, import/export contracts, and backward compatibility policy. |
| 7 | `07-quality-assurance.md` | Add unit, integration, E2E, compatibility, performance, accessibility, security, and marketplace validation. |
| 8 | `08-documentation.md` | Produce merchant, developer, architecture, migration, compatibility, and reviewer documentation. |
| 9 | `09-release-preparation.md` | Package, version, audit, submit, launch, and prepare production support. |

## Non-Goals For V1

- No checkout replacement.
- No funnel builder.
- No abandoned cart recovery.
- No recovery email sequences.
- No SMS, WhatsApp, popup lead capture, or CRM automation.
- No CartBay dependency.
- No direct order postmeta writes.
- No tokenized one-click post-purchase charge flow.
- No external SaaS requirement.

## Task Field Contract

Every implementation task includes:

- Unique task ID.
- Objective.
- Scope definition.
- Dependencies.
- Implementation notes.
- Acceptance criteria.
- Validation and testing requirements.
- Estimated complexity.
- Suggested execution order.

## Standard Validation Commands

Run only the commands that are available for the current repository stage and relevant to the files changed:

```bash
composer phpcs
composer phpstan
composer test
bun run build
bun run i18n:make-pot
composer plugin-check
```

For checkout, Block Checkout, HPOS, gateway, and QIT tasks, also run the explicit runtime or managed tests defined in the relevant task file.
