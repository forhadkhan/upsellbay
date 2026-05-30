<!-- STATUS: IN_PROGRESS -->

# UpsellBay Tasks Index

Source of truth: `.meta/PRDs/UpsellBay-PRD-v4.md`.
Full task details in each phase file. Update status by changing the `<!-- STATUS: -->` comment at the top of each file.

---

## [Phase 0 — Project Foundation](00-project-foundation.md)
Goal: prove the product, architecture, and technical constraints from PRD v4 before implementation starts.

- [x] UB-P0-001 — Repository and PRD Baseline Audit
- [x] UB-P0-002 — Identifier Contract and Product Isolation ADR
- [x] UB-P0-003 — Block Checkout Proof Plan
- [x] UB-P0-004 — HPOS and CRUD Compliance Proof Plan
- [x] UB-P0-005 — Dependency and Tooling Plan
- [x] UB-P0-006 — Folder Structure Acceptance Plan
- [x] UB-P0-007 — Launch Gate and Risk Register
- [x] UB-P0-008 — Competitor and Conflict Plugin Matrix
- [x] UB-P0-009 — Documentation and Decision Logging Standards

## [Phase 1 — Core Plugin Bootstrap](01-plugin-bootstrap.md)
Goal: build the minimal, stable plugin foundation that every later subsystem can depend on.

- [x] UB-P1-001 — Main Plugin Entrypoint
- [x] UB-P1-002 — Core Constants
- [x] UB-P1-003 — Composer Autoload and PHP Tooling
- [x] UB-P1-004 — Minimal Service Container
- [x] UB-P1-005 — Bootstrap Coordinator
- [x] UB-P1-006 — Platform Dependency Guards
- [x] UB-P1-007 — Installer, Deactivation, and Upgrade Shell
- [x] UB-P1-008 — Uninstall and Data Retention Foundation
- [x] UB-P1-009 — WooCommerce Feature Compatibility Declarations
- [x] UB-P1-010 — Settings Foundation
- [x] UB-P1-011 — Scheduler Foundation
- [x] UB-P1-012 — License and Updater Foundation
- [x] UB-P1-013 — Asset Build Foundation
- [x] UB-P1-014 — Base Utilities

## [Phase 2 — Data Architecture](02-data-architecture.md)
Goal: implement durable, HPOS-safe, non-PII storage for offers, attribution, settings, analytics, sessions, import/export, and cleanup.

- [x] UB-P2-001 — Offer CPT Registration
- [x] UB-P2-002 — Offer Meta Schema
- [x] UB-P2-003 — Offer Repository
- [x] UB-P2-004 — Stats Table Migration
- [x] UB-P2-005 — Stats Repository
- [x] UB-P2-006 — Cart Session State Store
- [x] UB-P2-007 — Attribution Data Contract
- [x] UB-P2-008 — Data Retention Model
- [x] UB-P2-009 — Import/Export JSON Schema
- [x] UB-P2-010 — Settings Repository and Migration Helpers
- [x] UB-P2-011 — Analytics Reconciliation Data Flow
- [x] UB-P2-012 — Data Architecture Tests

## [Phase 3 — Admin Architecture](03-admin-architecture.md)
Goal: build a WooCommerce-native admin experience under WooCommerce -> UpsellBay without a custom app shell or CartBay recovery features.

- [x] UB-P3-001 — Admin Menu and Page Routing
- [x] UB-P3-002 — Offers List Table
- [x] UB-P3-003 — Offer Editor Shell
- [x] UB-P3-004 — Rule Builder UI
- [x] UB-P3-005 — Admin Overview Summary
- [x] UB-P3-006 — Settings Page and Sections
- [x] UB-P3-007 — Test Mode Controls
- [x] UB-P3-008 — Compatibility and Coexistence Notices
- [x] UB-P3-009 — Dashboard Analytics Section
- [x] UB-P3-010 — Tools and Diagnostics Page
- [x] UB-P3-011 — Help Page and Support Routing
- [x] UB-P3-012 — Admin Asset Scoping
- [x] UB-P3-013 — Recovery Module Exclusion Guard

## [Phase 4 — Core Business Logic](04-core-business-logic.md)
Goal: implement the offer engine that evaluates rules, renders eligible placements, mutates carts safely, applies discounts, writes attribution, records analytics, and keeps checkout stable.

- [x] UB-P4-001 — Offer Service
- [x] UB-P4-002 — Offer Prioritizer
- [x] UB-P4-003 — Rule Parser and Evaluator
- [x] UB-P4-004 — Discount Calculator
- [x] UB-P4-005 — Cart Validator
- [x] UB-P4-006 — Cart Mutator
- [x] UB-P4-007 — Discount Applier
- [x] UB-P4-008 — Placement Renderer Coordinator
- [x] UB-P4-009 — Classic Checkout Bump
- [x] UB-P4-010 — Block Checkout Bump
- [x] UB-P4-011 — Product Page Offer
- [x] UB-P4-012 — Cart Cross-Sell Offer
- [x] UB-P4-013 — Thank-You Follow-On Offer
- [x] UB-P4-014 — Public REST Routes
- [x] UB-P4-015 — Attribution Writer and Reader
- [x] UB-P4-016 — Analytics Recorder and Reconciler
- [x] UB-P4-017 — Conflict Scanner
- [x] UB-P4-018 — Core Business Logic Tests

## [Phase 5 — Merchant Experience](05-merchant-experience.md)
Goal: help merchants create, preview, trust, and improve offers without needing custom checkout knowledge.

- [x] UB-P5-001 — First-Run Wizard Controller
- [x] UB-P5-002 — Sensible Defaults
- [x] UB-P5-003 — Empty States
- [x] UB-P5-004 — Preview Links and Test Mode Flow
- [x] UB-P5-005 — Guidance UX and Help Tips
- [x] UB-P5-006 — Progressive Configuration
- [x] UB-P5-007 — Product Recommendation Assistant Baseline
- [x] UB-P5-008 — Accessibility and Mobile UX Pass
- [x] UB-P5-009 — Merchant Copy Boundary Review

## [Phase 6 — Developer Extensibility](06-developer-extensibility.md)
Goal: expose stable, documented customization points without turning internal implementation details into accidental public contracts.

- [x] UB-P6-001 — Public Hook Contract
- [x] UB-P6-002 — Offer Schema Developer Contract
- [x] UB-P6-003 — REST Endpoint Contracts
- [x] UB-P6-004 — Internal Service API Boundaries
- [x] UB-P6-005 — Backward Compatibility Policy
- [x] UB-P6-006 — Import/Export Extension Points
- [x] UB-P6-007 — WP-CLI Utility Plan
- [x] UB-P6-008 — Developer Extensibility Tests

## Phase 6.5 — Admin UX and Product Independence Remediation
Goal: correct the post-Phase 6 admin UX gap before QA by replacing placeholder admin shells with Woo-native operational surfaces and ensuring UpsellBay does not appear dependent on CartBay.

- [x] UB-P6.5-001 — Remove visible CartBay global admin banner
- [x] UB-P6.5-002 — Render Woo-native operational admin screens instead of heading-only placeholders
- [x] UB-P6.5-003 — Add regression tests for product independence and admin page rendering

## [Phase 7 — Quality Assurance](07-quality-assurance.md)
Goal: prove UpsellBay is secure, stable, performant, Woo-native, HPOS-safe, Block Checkout-safe, and marketplace-ready before release.

- [ ] UB-P7-001 — PHPUnit and Test Harness
- [ ] UB-P7-002 — Unit Test Coverage
- [ ] UB-P7-003 — Integration Tests
- [ ] UB-P7-004 — Classic Checkout E2E Suite
- [ ] UB-P7-005 — Block Checkout E2E Suite
- [ ] UB-P7-006 — Storefront Placement E2E Suite
- [ ] UB-P7-007 — WooCommerce Compatibility Matrix Tests
- [ ] UB-P7-008 — Performance Tests
- [ ] UB-P7-009 — Security Review
- [ ] UB-P7-010 — Accessibility Review
- [ ] UB-P7-011 — WordPress and WooCommerce Standards Gates
- [x] UB-P7-012 — Product Isolation Scan
- [ ] UB-P7-013 — Marketplace Compliance Review

## [Phase 8 — Documentation](08-documentation.md)
Goal: ship complete merchant, developer, architecture, QA, migration, and marketplace documentation that matches the implemented product.

- [ ] UB-P8-001 — Merchant Setup Guide
- [ ] UB-P8-002 — First Offer Tutorial
- [ ] UB-P8-003 — Offer Placement and Rules Guide
- [ ] UB-P8-004 — Analytics and Attribution Guide
- [ ] UB-P8-005 — Compatibility Matrix
- [ ] UB-P8-006 — Marketplace Reviewer Guide
- [ ] UB-P8-007 — Developer Hook Reference
- [ ] UB-P8-008 — Import/Export Guide
- [ ] UB-P8-009 — Data Retention and Uninstall Guide
- [ ] UB-P8-010 — Architecture Documentation
- [ ] UB-P8-011 — Upgrade and Migration Notes
- [ ] UB-P8-012 — Changelog and Release Notes Draft

## [Phase 9 — Release Preparation](09-release-preparation.md)
Goal: package, audit, submit, and launch UpsellBay v1 with evidence for every PRD launch gate.

- [ ] UB-P9-001 — Versioning and Release Branch
- [ ] UB-P9-002 — Final Launch Gate Audit
- [ ] UB-P9-003 — Marketplace Listing Draft
- [ ] UB-P9-004 — License Server and Update Metadata Verification
- [ ] UB-P9-005 — Production Package Build
- [ ] UB-P9-006 — Release Candidate Smoke Test
- [ ] UB-P9-007 — Demo Store and Reviewer Data
- [ ] UB-P9-008 — Beta Release Plan
- [ ] UB-P9-009 — Submission and Launch Checklist
- [ ] UB-P9-010 — Post-Launch Monitoring and Patch Triage

## Summary

| Phase | Tasks | Status |
|-------|-------|--------|
| 0 — Project Foundation | 9 | COMPLETED |
| 1 — Core Plugin Bootstrap | 14 | COMPLETED |
| 2 — Data Architecture | 12 | COMPLETED |
| 3 — Admin Architecture | 13 | COMPLETED |
| 4 — Core Business Logic | 18 | COMPLETED |
| 5 — Merchant Experience | 9 | COMPLETED |
| 6 — Developer Extensibility | 8 | COMPLETED |
| 6.5 — Admin UX and Product Independence Remediation | 3 | COMPLETED |
| 7 — Quality Assurance | 13 | IN_PROGRESS |
| 8 — Documentation | 12 | PENDING |
| 9 — Release Preparation | 10 | PENDING |
| **Total** | **121** | |
