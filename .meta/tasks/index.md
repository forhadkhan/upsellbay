<!-- STATUS: IN_PROGRESS -->

# UpsellBay Tasks Index

Source of truth: `.meta/PRDs/UpsellBay-PRD-v4.md`.
Full task details in each phase file. Update status by changing the `<!-- STATUS: -->` comment at the top of each file.

---

## Phase 0 — Project Foundation
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

## Phase 1 — Core Plugin Bootstrap
Goal: build the minimal, stable plugin foundation that every later subsystem can depend on.

- [ ] UB-P1-001 — Main Plugin Entrypoint
- [ ] UB-P1-002 — Core Constants
- [ ] UB-P1-003 — Composer Autoload and PHP Tooling
- [ ] UB-P1-004 — Minimal Service Container
- [ ] UB-P1-005 — Bootstrap Coordinator
- [ ] UB-P1-006 — Platform Dependency Guards
- [ ] UB-P1-007 — Installer, Deactivation, and Upgrade Shell
- [ ] UB-P1-008 — Uninstall and Data Retention Foundation
- [ ] UB-P1-009 — WooCommerce Feature Compatibility Declarations
- [ ] UB-P1-010 — Settings Foundation
- [ ] UB-P1-011 — Scheduler Foundation
- [ ] UB-P1-012 — License and Updater Foundation
- [ ] UB-P1-013 — Asset Build Foundation
- [ ] UB-P1-014 — Base Utilities

## Phase 2 — Data Architecture
Goal: implement durable, HPOS-safe, non-PII storage for offers, attribution, settings, analytics, sessions, import/export, and cleanup.

- [ ] UB-P2-001 — Offer CPT Registration
- [ ] UB-P2-002 — Offer Meta Schema
- [ ] UB-P2-003 — Offer Repository
- [ ] UB-P2-004 — Stats Table Migration
- [ ] UB-P2-005 — Stats Repository
- [ ] UB-P2-006 — Cart Session State Store
- [ ] UB-P2-007 — Attribution Data Contract
- [ ] UB-P2-008 — Data Retention Model
- [ ] UB-P2-009 — Import/Export JSON Schema
- [ ] UB-P2-010 — Settings Repository and Migration Helpers
- [ ] UB-P2-011 — Analytics Reconciliation Data Flow
- [ ] UB-P2-012 — Data Architecture Tests

## Phase 3 — Admin Architecture
Goal: build a WooCommerce-native admin experience under WooCommerce -> UpsellBay without a custom app shell or CartBay recovery features.

- [ ] UB-P3-001 — Admin Menu and Page Routing
- [ ] UB-P3-002 — Offers List Table
- [ ] UB-P3-003 — Offer Editor Shell
- [ ] UB-P3-004 — Rule Builder UI
- [ ] UB-P3-005 — Admin Overview Summary
- [ ] UB-P3-006 — Settings Page and Sections
- [ ] UB-P3-007 — Test Mode Controls
- [ ] UB-P3-008 — Compatibility and Coexistence Notices
- [ ] UB-P3-009 — Analytics Admin Page
- [ ] UB-P3-010 — Tools and Diagnostics Page
- [ ] UB-P3-011 — Help Page and Support Routing
- [ ] UB-P3-012 — Admin Asset Scoping
- [ ] UB-P3-013 — Recovery Module Exclusion Guard

## Phase 4 — Core Business Logic
Goal: implement the offer engine that evaluates rules, renders eligible placements, mutates carts safely, applies discounts, writes attribution, records analytics, and keeps checkout stable.

- [ ] UB-P4-001 — Offer Service
- [ ] UB-P4-002 — Offer Prioritizer
- [ ] UB-P4-003 — Rule Parser and Evaluator
- [ ] UB-P4-004 — Discount Calculator
- [ ] UB-P4-005 — Cart Validator
- [ ] UB-P4-006 — Cart Mutator
- [ ] UB-P4-007 — Discount Applier
- [ ] UB-P4-008 — Placement Renderer Coordinator
- [ ] UB-P4-009 — Classic Checkout Bump
- [ ] UB-P4-010 — Block Checkout Bump
- [ ] UB-P4-011 — Product Page Offer
- [ ] UB-P4-012 — Cart Cross-Sell Offer
- [ ] UB-P4-013 — Thank-You Follow-On Offer
- [ ] UB-P4-014 — Public REST Routes
- [ ] UB-P4-015 — Attribution Writer and Reader
- [ ] UB-P4-016 — Analytics Recorder and Reconciler
- [ ] UB-P4-017 — Conflict Scanner
- [ ] UB-P4-018 — Core Business Logic Tests

## Phase 5 — Merchant Experience
Goal: help merchants create, preview, trust, and improve offers without needing custom checkout knowledge.

- [ ] UB-P5-001 — First-Run Wizard Controller
- [ ] UB-P5-002 — Sensible Defaults
- [ ] UB-P5-003 — Empty States
- [ ] UB-P5-004 — Preview Links and Test Mode Flow
- [ ] UB-P5-005 — Guidance UX and Help Tips
- [ ] UB-P5-006 — Progressive Configuration
- [ ] UB-P5-007 — Product Recommendation Assistant Baseline
- [ ] UB-P5-008 — Accessibility and Mobile UX Pass
- [ ] UB-P5-009 — Merchant Copy Boundary Review

## Phase 6 — Developer Extensibility
Goal: expose stable, documented customization points without turning internal implementation details into accidental public contracts.

- [ ] UB-P6-001 — Public Hook Contract
- [ ] UB-P6-002 — Offer Schema Developer Contract
- [ ] UB-P6-003 — REST Endpoint Contracts
- [ ] UB-P6-004 — Internal Service API Boundaries
- [ ] UB-P6-005 — Backward Compatibility Policy
- [ ] UB-P6-006 — Import/Export Extension Points
- [ ] UB-P6-007 — WP-CLI Utility Plan
- [ ] UB-P6-008 — Developer Extensibility Tests

## Phase 7 — Quality Assurance
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
- [ ] UB-P7-012 — Product Isolation Scan
- [ ] UB-P7-013 — Marketplace Compliance Review

## Phase 8 — Documentation
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

## Phase 9 — Release Preparation
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
| 1 — Core Plugin Bootstrap | 14 | PENDING |
| 2 — Data Architecture | 12 | PENDING |
| 3 — Admin Architecture | 13 | PENDING |
| 4 — Core Business Logic | 18 | PENDING |
| 5 — Merchant Experience | 9 | PENDING |
| 6 — Developer Extensibility | 8 | PENDING |
| 7 — Quality Assurance | 13 | PENDING |
| 8 — Documentation | 12 | PENDING |
| 9 — Release Preparation | 10 | PENDING |
| **Total** | **118** | |
