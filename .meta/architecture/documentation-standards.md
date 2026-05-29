# Documentation and Decision Logging Standards

Status: accepted for Phase 0.

Task: `UB-P0-009`.

## Decision

Implementation decisions must stay synchronized across `.meta/architecture/`, `.meta/tasks/`, `docs/`, changelog or release notes, and inline PHPDoc. PRD v4 remains the source of truth. Deviations from PRD v4 require an architecture note before implementation continues.

## Required Updates by Change Type

| Change type | Required documentation |
| --- | --- |
| New subsystem or service | `.meta/architecture/` entry or update; task checkbox/status update. |
| Storage entity, option, table, meta schema, or migration | Architecture note, data retention docs, migration notes, tests. |
| REST route | Architecture note, developer docs, permission/security notes, tests. |
| Scheduler job | Architecture note, Action Scheduler group, idempotence notes, tests. |
| Hook/filter/action | Developer reference with name, arguments, return expectations, and backward compatibility note. |
| Admin screen | Architecture note and merchant docs when user-facing. |
| Storefront placement behavior | Merchant docs, compatibility docs, accessibility and mobile QA notes. |
| Public schema or import/export format | Developer docs, JSON examples, backward compatibility policy. |
| Compatibility finding | `.meta/qa/compatibility-matrix.md` and public compatibility docs when relevant. |
| PRD deviation | New architecture decision explaining reason, risk, and product impact before code continues. |

## Task Status Rules

- Set a phase file to `<!-- STATUS: IN_PROGRESS -->` when work starts.
- Set it to `<!-- STATUS: COMPLETED -->` only after all sub-tasks meet acceptance criteria.
- Update `.meta/tasks/index.md` checkboxes as each sub-task is completed.
- Do not mark later phases complete from earlier phase work.

## Release Documentation Rules

Release notes must not overclaim:

- Block Checkout support before E2E proof.
- HPOS compatibility before tests and declaration proof.
- AOV lift before measured evidence.
- Cart recovery, recovery emails, restore links, unsubscribe flows, SMS, WhatsApp, CRM automation, or funnel-builder behavior.

## AGENTS.md Relationship

`AGENTS.md` is the operational guide for agents. It must reference these standards so future implementation work updates the correct docs before claiming completion.

## Review Checklist

Before closing a task, re-read changed docs from disk and confirm:

- Paths referenced by the task exist or are explicitly marked planned.
- The PRD source of truth is not contradicted.
- Public claims match tested behavior.
- No CartBay runtime coupling was introduced.
- Task status and checkboxes match the actual evidence.
