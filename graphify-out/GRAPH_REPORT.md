# Graph Report - .  (2026-05-30)

## Corpus Check
- Corpus is ~2,582 words - fits in a single context window. You may not need a graph.

## Summary
- 34 nodes · 43 edges · 6 communities (5 shown, 1 thin omitted)
- Extraction: 63% EXTRACTED · 37% INFERRED · 0% AMBIGUOUS · INFERRED: 16 edges (avg confidence: 0.81)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Workflow Guardrails|Workflow Guardrails]]
- [[_COMMUNITY_Project Authority|Project Authority]]
- [[_COMMUNITY_Architecture and Data|Architecture and Data]]
- [[_COMMUNITY_WooCommerce Offers|WooCommerce Offers]]
- [[_COMMUNITY_Scope Boundaries|Scope Boundaries]]
- [[_COMMUNITY_PHP Standards|PHP Standards]]

## God Nodes (most connected - your core abstractions)
1. `UpsellBay` - 10 edges
2. `AI Agent Workflow` - 10 edges
3. `Security Requirements` - 8 edges
4. `WooCommerce Native AOV Offer Engine` - 7 edges
5. `Planned Repository Layout` - 4 edges
6. `Repository Storage Boundary` - 4 edges
7. `Product Constraints` - 3 edges
8. `Admin UI Rules` - 3 edges
9. `Storefront UX Rules` - 3 edges
10. `Checkout Bumps` - 2 edges

## Surprising Connections (you probably didn't know these)
- `Gemini Delegates To AGENTS` --references--> `UpsellBay`  [EXTRACTED]
  GEMINI.md → AGENTS.md

## Hyperedges (group relationships)
- **Offer Placement Surface** — agents_product_page_offers, agents_cart_offers, agents_checkout_bumps, agents_thank_you_offers [EXTRACTED 1.00]
- **Implementation Guardrails** — agents_product_constraints, agents_architecture_constants, agents_php_standards, agents_security_requirements, agents_validation_commands [EXTRACTED 1.00]
- **WooCommerce Native Boundaries** — agents_woocommerce_rules, agents_admin_ui_rules, agents_storefront_ux_rules, agents_hpos_crud_order_attribution [INFERRED 0.85]

## Communities (6 total, 1 thin omitted)

### Community 0 - "Workflow Guardrails"
Cohesion: 0.33
Nodes (9): Admin UI Rules, AI Agent Workflow, JavaScript CSS Asset Rules, Internationalization Rules, Overlay Metadata Strategy, Performance Requirements, REST API Rules, Security Requirements (+1 more)

### Community 1 - "Project Authority"
Cohesion: 0.25
Nodes (8): Architecture Constants, Marketplace Requirements, Phase Task Execution, UpsellBay PRD v4, Source Of Truth Order, UpsellBay, Validation Commands, Gemini Delegates To AGENTS

### Community 2 - "Architecture and Data"
Cohesion: 0.29
Nodes (7): Aggregate Non PII Analytics, Core Plugin Initialization, HPOS CRUD Order Attribution, Planned Repository Layout, Private Offer CPT, Repository Storage Boundary, Small Service Container

### Community 3 - "WooCommerce Offers"
Cohesion: 0.4
Nodes (6): Cart Offers, Checkout Bumps, Product Page Offers, Thank You Offers, WooCommerce Native AOV Offer Engine, WooCommerce Rules

### Community 4 - "Scope Boundaries"
Cohesion: 0.67
Nodes (3): No CartBay Coupling, No Recovery Scope, Product Constraints

## Knowledge Gaps
- **15 isolated node(s):** `UpsellBay PRD v4`, `Product Page Offers`, `Cart Offers`, `Source Of Truth Order`, `Phase Task Execution` (+10 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `UpsellBay` connect `Project Authority` to `Workflow Guardrails`, `Architecture and Data`, `WooCommerce Offers`, `Scope Boundaries`?**
  _High betweenness centrality (0.530) - this node is a cross-community bridge._
- **Why does `AI Agent Workflow` connect `Workflow Guardrails` to `Project Authority`?**
  _High betweenness centrality (0.309) - this node is a cross-community bridge._
- **Why does `WooCommerce Native AOV Offer Engine` connect `WooCommerce Offers` to `Workflow Guardrails`, `Project Authority`?**
  _High betweenness centrality (0.290) - this node is a cross-community bridge._
- **Are the 4 inferred relationships involving `Security Requirements` (e.g. with `REST API Rules` and `Admin UI Rules`) actually correct?**
  _`Security Requirements` has 4 INFERRED edges - model-reasoned connections that need verification._
- **Are the 2 inferred relationships involving `WooCommerce Native AOV Offer Engine` (e.g. with `Admin UI Rules` and `Storefront UX Rules`) actually correct?**
  _`WooCommerce Native AOV Offer Engine` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Are the 3 inferred relationships involving `Planned Repository Layout` (e.g. with `Core Plugin Initialization` and `Small Service Container`) actually correct?**
  _`Planned Repository Layout` has 3 INFERRED edges - model-reasoned connections that need verification._
- **What connects `UpsellBay PRD v4`, `Product Page Offers`, `Cart Offers` to the rest of the system?**
  _15 weakly-connected nodes found - possible documentation gaps or missing edges._