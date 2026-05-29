# UpsellBay Final PRD v4.0

Native AOV, order bump, and post-purchase offer engine for WooCommerce

| Field | Value |
| --- | --- |
| Document status | Final production-ready PRD |
| Version | 4.0, supersedes UpsellBay PRD v1.0, v2.0, and v3.0 |
| Prepared | May 29, 2026 |
| Research window | Current public market and platform research as of May 29, 2026 |
| Target product | Premium WooCommerce extension for direct sale and Woo Marketplace submission |
| Primary audience | Product, engineering, QA, support, licensing, marketplace submission |
| Product decision | Build a WooCommerce-native AOV offer layer, not a checkout replacement, funnel builder, CartBay module, or cart recovery email platform |
| Internal dependency policy | UpsellBay must be independently installable and operable without CartBay |
| Internal reference inputs | CartBay feature guide and WordPress/WooCommerce plugin development blueprint in `.meta/notes/` |

## 1. Executive Decision

UpsellBay should launch as a premium WooCommerce-native average order value engine that adds relevant offers to the existing buying journey: product page, cart, checkout, and thank-you page. The product should not replace checkout, not require page builders, not depend on payment gateway tokenization for v1, not become a broad abandoned-cart email suite, and not become a feature module inside CartBay.

The validated wedge is:

> Increase WooCommerce average order value with native order bumps and offers that work on the merchant's existing checkout, including Block Checkout and HPOS, without funnel-builder lock-in.

This positioning is commercially viable because the market already pays for AOV, order bump, checkout optimization, and recovery tools. The gap is that most strong competitors either replace checkout, bundle the feature inside a larger funnel suite, focus on popup/email recovery, or solve only one offer placement. UpsellBay wins by being additive, native, reliable, measurable, and easier to adopt.

## 2. Internal Product Separation: UpsellBay vs CartBay

CartBay already exists as a WooCommerce-native abandoned cart recovery platform. It captures checkout sessions with consent, detects abandonment, sends recovery email sequences, restores abandoned carts, applies recovery incentives, tracks recovery performance, and supports recovery-oriented automation. UpsellBay must remain a separate commercial plugin with a separate product promise, code namespace, data model, admin IA, license identity, analytics model, and roadmap.

### 2.1 Separation Decision

UpsellBay is not "CartBay offers." UpsellBay is a standalone AOV offer engine that can coexist with CartBay on the same store, but it must not require CartBay, share CartBay persistence keys, reuse CartBay session state, or present CartBay recovery behavior as an UpsellBay feature.

The practical separation is:

| Area | CartBay | UpsellBay |
| --- | --- | --- |
| Primary job | Recover revenue after checkout abandonment. | Increase value of active and just-completed orders. |
| Shopper timing | After checkout activity stalls or a recovery link is used. | During product evaluation, cart review, checkout, and thank-you follow-on. |
| Core object | Captured checkout/recovery session. | Offer definition and accepted offer attribution. |
| Main workflow | Capture -> detect abandonment -> schedule/send recovery emails -> restore cart -> attribute recovery. | Create offer -> evaluate rules -> render placement -> accept/dismiss -> mutate cart or start follow-on checkout -> attribute offer revenue. |
| Email ownership | WooCommerce recovery emails and sequence templates. | No recovery emails in v1; optional next-order coupon messaging only when triggered from thank-you context. |
| Coupon ownership | Recovery incentives tied to abandoned sessions. | Offer discounts tied to active cart items or follow-on checkout, preferably without persistent coupon creation. |
| Analytics promise | Abandoned, recovered, email, and recovery revenue metrics. | Offer views, accepts, dismissals, attributed offer revenue, and AOV impact. |
| External automation | Recovery-session and mail-status automation. | Offer eligibility, acceptance, attribution, and analytics export hooks. |

### 2.2 Non-Negotiable Isolation Rules

- Separate plugin main file: `upsellbay.php`.
- Separate PHP namespace root: `WPAnchorBay\UpsellBay`.
- Separate text domain: `upsellbay`.
- Separate REST namespace: `/wp-json/upsellbay/v1`.
- Separate option prefix: `upsellbay_`.
- Separate internal short prefix: `_ub_` for offer/order attribution meta only.
- Separate hook prefix: `upsellbay_`.
- Separate Action Scheduler group: `upsellbay`.
- Separate license product slug and update identity.
- Separate admin entry under WooCommerce -> UpsellBay, never nested under CartBay.
- No reads or writes to `cartbay_*`, `_cartbay_*`, `cartbay-` assets, CartBay sessions, CartBay notification records, or CartBay recovery sequence settings.
- No shared tables with CartBay. If a future shared WP Anchor Bay framework exists, it must contain platform infrastructure only, not product state.
- If both plugins are active, each must activate, deactivate, uninstall, and upgrade independently.

### 2.3 Allowed Integration Between Products

UpsellBay and CartBay may integrate only through explicit, documented public hooks or future integration adapters. Allowed examples:

- CartBay may include accepted UpsellBay offer attribution in a recovered-order report if UpsellBay exposes a public read API.
- UpsellBay may suppress an offer during a CartBay recovery restore only if CartBay exposes a documented context flag and the merchant enables that behavior.
- Both plugins may use the same WP Anchor Bay licensing infrastructure, but each must have its own product slug, license screen state, update package, cache keys, and failure behavior.
- Both plugins may follow the same internal engineering blueprint, but product-specific services, options, REST routes, templates, assets, and analytics must remain separate.

### 2.4 Disallowed Integration and Scope Creep

The following are out of scope for UpsellBay v1 and should be rejected during implementation unless this PRD is explicitly revised:

- Reusing CartBay's captured checkout sessions as UpsellBay targeting data.
- Adding abandoned cart detection, recovery email scheduling, unsubscribe flows, recovery restore links, or recovery session lifecycle states to UpsellBay.
- Sharing CartBay's recovery coupon generation model for checkout bumps.
- Writing UpsellBay offer events into CartBay notification or session metadata.
- Adding SMS, WhatsApp, or abandoned-cart automation to UpsellBay.
- Presenting UpsellBay as a CartBay upgrade path or bundled module in admin copy.
- Making CartBay activation a requirement, soft requirement, or hidden dependency.

## 3. Audit of Existing PRDs

### 3.1 PRD v1 Review

| Area | Finding |
| --- | --- |
| Strengths to preserve | Clear AOV problem framing; focused merchant personas; practical offer placements; early recognition that traffic monetization is often cheaper than traffic acquisition; correct identification of checkout bumps, product upsells, cart cross-sells, thank-you offers, rules, and revenue dashboard as the core product surface. |
| Weak assumptions | Treated post-purchase one-click upsells as a must-have despite acknowledging gateway risk; assumed a custom tracking table without defining privacy, scale, or reporting model; deferred Block Checkout despite WooCommerce Block Checkout being default for new stores since WooCommerce 8.3; did not define commercial packaging, marketplace standards, security, HPOS, licensing, or native admin constraints. |
| Missing opportunities | No competitor analysis; no pricing strategy; no Woo Marketplace positioning; no agency segment detail; no developer extensibility model; no clear boundary against funnel builders or cart recovery tools. |
| UX risks | Standalone "WP Admin -> UpsellBay" flow would feel less Woo-native; styling customizer could become theme-breaking if over-scoped; no preview/test mode for merchants or marketplace reviewers. |
| Implementation risks | Tokenized post-purchase charges, attribution edge cases, and checkout block support were unresolved; no security/rate-limit requirements; no lifecycle plan for analytics data. |

### 3.2 PRD v2 Review

| Area | Finding |
| --- | --- |
| Strengths to preserve | Strong strategic pivot to "additive, never replacement"; clear rejection of gateway-tokenized one-click upsells for v1; HPOS and Block Checkout treated as launch requirements; Woo-native admin direction; CPT-based offer configuration; line-item attribution; license resilience; QIT and marketplace gates. |
| Weak assumptions | Pricing references are now partially stale: UpsellWP lists from $75/year, Woo Marketplace Order Bump is $79/year, CartFlows and CheckoutWC pricing has shifted; platform baseline should reflect WooCommerce 10.8.x and WordPress 7.0 readiness; PHP 8.3 minimum is commercially too restrictive for many Woo stores; transient counters are not strong enough for premium analytics accuracy under traffic. |
| Missing opportunities | Competitor analysis is too shallow for a final PRD; no direct competitor-by-competitor product response; no explicit customer segment validation table; no merchant objection handling; no source-backed market evidence; no clear "not cart recovery" boundary despite including cart recovery competitors. |
| UX risks | Block Checkout rich offer rendering is described as if Additional Checkout Fields alone can render a full product card; the API supports field registration, but a polished offer card also needs a blocks-side UI strategy. This needs a Phase 0 proof before launch claims. |
| Implementation risks | Phase names contain "[object Object]" artifacts; Part B labels the document "v4.0" while the file is v2; `woocommerce_loaded` is not the ideal HPOS compatibility declaration timing; no uninstall/data retention policy; no explicit developer hooks contract. |

### 3.3 PRD v3 Review

| Area | Finding |
| --- | --- |
| Strengths to preserve | v3 correctly positions UpsellBay as a premium Woo-native AOV layer, includes detailed competitor analysis, treats Block Checkout and HPOS as launch gates, defines aggregate analytics, and avoids tokenized one-click post-purchase charges in v1. |
| Weak assumptions | v3 references recovery competitors but does not explicitly describe how UpsellBay stays separate from the existing CartBay plugin. This creates implementation risk because CartBay already has sessions, coupons, recovery analytics, Woo emails, REST routes, Action Scheduler jobs, and license behavior that could be accidentally reused. |
| Missing opportunities | v3 does not turn the plugin-development blueprint into a concrete identifier contract, module layout, lifecycle flow, or anti-coupling checklist for UpsellBay. |
| Required v4 correction | Keep v3's market and product strategy, then add a hard CartBay separation model and blueprint-driven engineering contract before implementation begins. |

### 3.4 Decisions Carried Forward

- Preserve the core product: checkout bumps, product-page offers, cart offers, thank-you page offers, rules, attribution, analytics.
- Preserve the strategic boundary: no checkout replacement, no page-builder dependency, no gateway-tokenized one-click upsell in v1, no CartBay dependency.
- Preserve Woo-native admin and marketplace readiness as product requirements, not engineering preferences.
- Preserve HPOS, Block Checkout, and QIT as launch gates.
- Improve analytics with one aggregate stats table instead of transient-only counters.
- Update commercial positioning to a premium $79/year entry point with higher multi-site tiers.
- Apply the internal plugin-development blueprint: small subsystems coordinated by one bootstrap layer, focused services and repositories, native Woo admin surfaces, Action Scheduler where scheduled work is needed, strict security/localization/asset scoping, and explicit identifier constants.

## 4. Market Validation

### 4.1 Market Signals

| Signal | Evidence | Product implication |
| --- | --- | --- |
| Checkout and cart abandonment remain large merchant pain points | Baymard reports an average documented cart abandonment rate around 70.22% in its 2026 cart abandonment statistics. Source: [Baymard cart abandonment statistics](https://baymard.com/blog/cart-abandonment-statistics). | Merchants already understand checkout/cart revenue leakage and will pay for measurable improvements, but the product must avoid adding checkout friction. |
| Block Checkout compatibility is no longer optional | WooCommerce announced Cart and Checkout Blocks as default for new stores starting WooCommerce 8.3. Source: [WooCommerce Cart and Checkout Blocks FAQ](https://developer.woocommerce.com/2023/11/06/faq-extending-cart-and-checkout-blocks/). | UpsellBay cannot launch as "premium" unless it supports both classic and Block Checkout or clearly gates launch until Block support passes QA. |
| WooCommerce platform baseline moved forward | WooCommerce 10.8 was released May 26, 2026, is ready for WordPress 7.0 styling, and requires WordPress 6.9+. Source: [WooCommerce 10.8 release notes](https://developer.woocommerce.com/2026/05/26/woocommerce-10-8-0-release/). WordPress 7.0 was released May 20, 2026. Source: [WordPress 7.0 documentation](https://wordpress.org/documentation/wordpress-version/version-7-0/). | v4 should target WooCommerce 10.8.x, WordPress 6.9+ minimum, WordPress 7.0 readiness, and native admin styling. |
| Woo Marketplace quality expectations are explicit | WooCommerce developer docs position QIT as the marketplace quality gate with managed tests, security checks, PHPStan, compatibility, and E2E support. Sources: [Woo extension docs](https://developer.woocommerce.com/docs/extensions/getting-started-extensions/) and [QIT docs](https://qit.woo.com/docs/). | QIT must be in the definition of done, not a late-stage task. |
| HPOS remains a hard extension requirement | WooCommerce HPOS docs require WooCommerce CRUD APIs instead of direct order post/postmeta access and document compatibility declarations. Source: [HPOS extension recipe book](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/). | Order attribution must use WC order and order-item APIs only. |
| Merchants pay for AOV tools | UpsellWP starts at $75/year; Woo Marketplace Order Bump is $79/year; WP Swings starts at $89/year; CheckoutWC starts at $149/year and includes bumps in higher plans; FunnelKit and CartFlows charge substantially more for complete funnel suites. | A focused $79/year premium entry point is validated, while higher tiers can monetize agencies and multi-store merchants. |
| Merchant frustration is practical, not theoretical | Public support/review patterns include checkout takeover problems, scheduled recovery emails not firing, completed orders being marked abandoned, limited templates, Elementor conflicts, and Block Checkout confusion. Sources include [CartFlows support/reviews on WordPress.org](https://wordpress.org/plugins/cartflows/), [Cart Abandonment Recovery support topics](https://wordpress.org/support/plugin/woo-cart-abandonment-recovery/), [UpsellWP review complaint](https://wordpress.org/support/topic/disappointed-123/), and WooCommerce Block Checkout support discussions. | Product promise must be reliability, native behavior, clean admin workflows, and understandable conflict handling. |

### 4.2 What Merchants Are Actively Paying For

- Relevant order bumps at checkout with simple accept/remove behavior.
- Product-page "frequently bought together" and accessory offers.
- Cart and side-cart offers, free shipping threshold prompts, and add-on recommendations.
- Post-purchase or thank-you page offers.
- Conditional targeting by cart contents, categories, customer role, purchase history, and order value.
- AOV, accept-rate, and attributed revenue reporting.
- Checkout improvement suites, even at $149-$349/year, when they promise measurable conversion lift.
- Cart recovery automation, especially email/SMS/WhatsApp, but this is a separate product category from UpsellBay's best wedge.

### 4.3 Market Gaps and Product Opportunities

| Gap | Evidence pattern | UpsellBay opportunity |
| --- | --- | --- |
| Checkout replacement fatigue | Funnel/checkout products often require custom checkout templates or global checkout replacement. | "Additive checkout bumps" that work on the current checkout and never replace the checkout page. |
| Block Checkout support gap | Many legacy extensions still rely on classic PHP hooks and do not support block checkout cleanly. | Block Checkout support becomes a core moat, validated through a Phase 0 prototype and QIT/E2E tests. |
| Analytics are fragmented or too suite-specific | Funnel suites report funnel steps; recovery suites report abandoned carts; simple bump plugins report little or no business impact. | Offer-level analytics: views, accepts, dismissals, revenue, AOV lift, per-placement performance, and recommendations. |
| Merchants do not know which offer to create | Existing tools expose forms but provide little guidance on product selection and pricing. | Native recommendation assistant based on Woo upsells/cross-sells, same-category products, low-priced accessories, and prior accepted offers. |
| Post-purchase one-click upsells create gateway risk | Competitors market one-click flows, but support complexity depends on gateway tokenization and order mutation behavior. | Launch with reliable follow-on checkout for all gateways; defer tokenized one-click to a tested gateway pack. |
| Plugin stacking causes conflicts | Stores combine checkout, cart, discount, recovery, payment, and page builder plugins. | Conflict detection, safe fallbacks, test mode, marketplace reviewer mode, and documented compatibility matrix. |
| Developer extensibility is often weak | Agencies need hooks and stable schemas for client-specific logic. | Public hooks, filters, REST endpoints, schema docs, and no dependency on private Woo internals. |
| Native Woo admin experience is inconsistent | Many competitors use custom app-like dashboards that feel separate from WooCommerce. | Use WooCommerce admin patterns, settings tables, WP list tables, Select2 product search, help tips, and Woo notices. |

## 5. Client Need Validation

| Segment | Primary pain points | Desired outcomes | Purchase motivations | Adoption objections | Premium features that justify payment |
| --- | --- | --- | --- | --- | --- |
| Small WooCommerce stores | Low AOV, limited time, no developer, fear of breaking checkout, too many plugin choices. | Add one relevant checkout bump quickly and see whether it increases revenue. | Low annual price, fast setup, no checkout replacement, native UI. | "Will this break checkout?", "Will it look ugly?", "Will it slow the site?" | First-run wizard, test mode, native styling, one-click product selection, simple rules, $79/year entry price. |
| Growth-stage merchants | Paid traffic pressure, need higher AOV, multiple product categories, more orders, more plugin conflicts. | Run targeted offers across product, cart, checkout, and thank-you pages with reliable analytics. | Measurable AOV lift, rule targeting, attribution, Block Checkout/HPOS support. | Concern about performance, data accuracy, subscriptions/payment compatibility. | Full analytics, offer priority, multi-placement rules, compatibility matrix, performance budgets, revenue dashboard. |
| Agencies | Need repeatable tools for client stores, low conflict risk, easy offboarding, hooks for customization. | Install one dependable AOV plugin across stores and customize without lock-in. | Multi-site license, developer docs, predictable architecture, Woo-native admin. | Support burden, client-specific checkout stacks, licensing friction. | Agency tier, import/export, hooks/filters, JSON offer definitions, test mode, conflict notices. |
| Enterprise WooCommerce operators | HPOS, performance, accessibility, security, QA, reporting, and operational governance. | Safe offer layer that does not compromise checkout, data integrity, or compliance. | QIT readiness, no PII-heavy external SaaS dependency, performance profile, controlled rollout. | Need proof of scale, auditability, staging, rollback, and support SLAs. | Aggregate stats table, Action Scheduler jobs, feature flags, role/capability controls, logs, WP-CLI utilities, documented data retention. |

## 6. Competitor Analysis

Pricing and feature notes are based on public pages crawled during research on May 29, 2026. Prices can change, so launch copy must be verified again before publication.

### 6.1 Direct and Adjacent Competitors

| Competitor | Official link | Core feature set | Pricing model | Strengths | Weaknesses and UX limitations | Missing capabilities | Market positioning | UpsellBay response |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| CartFlows | [cartflows.com/pricing](https://cartflows.com/pricing/) | Funnels, checkout replacement, order bumps, one-click upsells/downsells, smart routing, A/B testing, analytics, companion cart/recovery tools. | Plus listed at $189 today then $249/year; Pro $299 today then $449/year; annual and lifetime options. | Mature funnel suite, broad feature set, strong marketing, templates, analytics, payment gateway support. | Over-scoped for merchants who only need native offers; checkout replacement and funnel model can create lock-in and theme/plugin conflict risk; higher price. | Lightweight additive mode; minimal native Woo admin path; narrow AOV-only purchase option. | Full funnel platform for growth marketers. | Position UpsellBay as the simpler, safer $79/year AOV layer for merchants who do not want funnel architecture. |
| FunnelKit | [funnelkit.com FAQ](https://funnelkit.com/frequently-asked-questions/) and [order bump page](https://funnelkit.com/woocommerce-order-bump/) | Funnel builder, optimized checkout, order bumps, one-click upsells/downsells, automations, rules, A/B testing, analytics. | Basic $99.50/year; Plus $179.50/year; Professional $249.50/year; Elite $399.50/year. Order bumps and deeper funnel features are in higher tiers. | Powerful, high-trust brand, deep funnel feature set, strong rules and templates. | More complex than needed for simple AOV improvements; checkout/funnel page workflow adds learning curve and lock-in. | Native additive offer layer; Marketplace-first positioning; no-funnel admin path. | Premium Woo funnel and automations suite. | UpsellBay should not compete on funnel depth; compete on native simplicity, reliability, and lower total cost. |
| WooCommerce Cart Abandonment Recovery by CartFlows | [WordPress.org plugin](https://wordpress.org/plugins/woo-cart-abandonment-recovery/) | Captures abandoned carts and sends timed recovery emails. | Free plugin; active installs listed at 300,000+. | Massive install base, free, validates recovery demand. | Public support shows operational pain: email retry loops, orders marked abandoned after completion, scheduling issues, webhook/capture issues. | AOV offer placement; checkout bumping; product/cart/thank-you offers; rich revenue attribution. | Free recovery utility. | Treat as adjacent, not direct. UpsellBay should integrate via hooks but not become a recovery email product. |
| CheckoutWC | [checkoutwc.com/pricing](https://www.checkoutwc.com/pricing/) | Shopify-style checkout replacement, order bumps, side cart, thank-you page offers, A/B testing, abandoned cart recovery, templates. | Basic $149/year; Plus $249/year; Pro $349/year; Agency $1499/year. Bumps start in Plus; unlimited bumps in Pro. | Strong checkout UX pitch, advanced checkout features, A/B testing, side cart, post-purchase offer features. | Requires adopting a replacement checkout experience; not ideal for stores that want to preserve the current Woo checkout. Higher price. | Additive offer layer for current checkout; low-risk install for stores not ready to replace checkout. | Premium checkout optimization platform. | UpsellBay is the non-replacement AOV alternative. |
| UpsellWP | [upsellwp.com/pricing](https://upsellwp.com/pricing/) | Checkout upsells, cart upsells, frequently bought together, next order coupons, one-click offers, conditions, A/B testing, popups. | Starter $75/year 1 site; Professional $135/year 5 sites; Agency $295/year 25 sites; multi-year discounts. | Closest direct competitor, strong breadth, reasonable pricing, Flycart credibility. | Not a Woo Marketplace product; support/review evidence includes template customization limits and page builder conflicts; unclear Block Checkout/HPOS depth in public positioning. | Marketplace-first trust; stricter native Woo admin; documented Block/HPOS support; safer post-purchase flow. | Direct upsells-only WooCommerce plugin. | Match price band but win on Woo-native quality, Block Checkout proof, QIT, marketplace credibility, and developer docs. |
| Order Bump for WooCommerce by Flintop | [Woo Marketplace listing](https://woocommerce.com/products/order-bump-for-woocommerce/) | Checkout order bumps, multiple bumps, upsell funnels, quantity controls, reports. | $79/year; 2-year discounted plan; 400+ active installs; 3.3 rating from 3 reviews. | Official Woo Marketplace presence, clear order bump use case, native distribution trust. | Narrower feature scope, small install base, modest public rating. | Multi-placement AOV architecture; richer rules; product/cart/thank-you offers; stronger analytics. | Marketplace order bump extension. | UpsellBay can be the premium marketplace alternative with broader but still focused AOV coverage. |
| WP Swings Upsell Funnel Builder | [WP Swings product page](https://wpswings.com/product/upsell-order-bump-offer-for-woocommerce-pro/) | Upsell/downsell funnels, order bumps, post-purchase upsells, A/B testing, reports, popups, scheduling, subscriptions support. | $89/year 1 site; $149/year 5 sites; $289/year 10 sites. | Broad feature list at accessible price, free/pro model, reports and testing. | Complex surface area; public complaints around technical issues and refund/support dissatisfaction exist; UX can feel feature-heavy. | Simpler native Woo admin, fewer risky features in v1, marketplace-grade QA. | Budget broad upsell/funnel plugin. | UpsellBay should be less cluttered, more reliable, and easier to trust. |
| YITH WooCommerce Frequently Bought Together | [YITH product page](https://yithemes.com/themes/plugins/yith-woocommerce-frequently-bought-together/) | Product-page frequently bought together bundles, discounts, variation selection, placement customization. | $79.99/year for 1 year updates/support. | YITH brand trust, clear product-page AOV use case, native Woo ecosystem familiarity. | Product-page focused; not a complete cart/checkout/thank-you AOV engine. | Checkout bumps, cart offers, post-purchase offers, unified analytics. | Focused product-page upsell extension. | UpsellBay should include product-page offers but differentiate with full journey attribution. |
| YITH WooCommerce Dynamic Pricing & Discounts | [YITH product page](https://yithemes.com/themes/plugins/yith-woocommerce-dynamic-pricing-and-discounts/) | Promotions, BOGO, quantity discounts, cart discounts, gifts, scheduling, cart/checkout deals. | Annual per-extension premium license with 1 year support/updates; exact crawled page did not expose a visible price. | Powerful promotion engine, broad discount rules, YITH framework. | Discount-focused, not primarily an offer-placement and attribution product; can become complex. | Offer acceptance analytics; placement-specific AOV reporting; checkout bump UX. | Promotion and discount rule engine. | UpsellBay should integrate with discounts lightly but stay focused on offer placement and AOV attribution. |
| Retainful | [retainful.com/pricing](https://www.retainful.com/pricing) | Abandoned cart recovery, email/SMS/WhatsApp automation, popups/forms, segmentation, templates, reporting. | Free up to 500 emails/month; Pro starts at $14/month for 10,000 emails/month; SMS/WhatsApp pass-through costs. | Strong omnichannel recovery value, low entry price, automation breadth. | SaaS-like marketing automation, not a native checkout offer engine; contact/email volume model. | Native Woo checkout bumps and product/cart/thank-you AOV placements. | Omnichannel recovery automation. | Adjacent integration target, not product direction. UpsellBay can expose events to Retainful via webhooks later. |
| ShopMagic Abandoned Carts | [ShopMagic Abandoned Carts](https://shopmagic.app/products/shopmagic-abandoned-carts/) | Free abandoned cart add-on, guest/registered cart capture, recovery automations, charts, optional Pro add-ons. | Free add-on; ShopMagic Pro bundle Personal GBP 69/year, Professional GBP 99/year, Lifetime GBP 299. | Woo-focused automation tool, lower cost, useful add-ons. | Recovery workflow focus; not checkout bump/AOV engine; delayed actions and advanced filters require add-ons. | Offer placements, native checkout bump UX, AOV attribution. | Woo automation and cart recovery. | Keep UpsellBay focused; offer integration hooks for automation tools. |
| Metorik | [metorik.com/pricing](https://metorik.com/pricing) and [abandoned cart emails](https://metorik.com/engage/abandoned-cart-emails) | Woo/Shopify analytics, segmentation, abandoned carts, email automation, profit reporting, cohorts, exports. | Scales by monthly orders; example public pricing shows $75/month for 100-500 orders/month; all features included by order level. | Best-in-class analytics reputation, real-time cart visibility, deep reporting. | Higher monthly SaaS cost; not a native offer rendering plugin. | On-site offer acceptance UX; checkout bumps; product/cart/thank-you placements. | Premium analytics and engagement platform. | UpsellBay should not try to replace Metorik; provide clean attributed data and optional export hooks. |
| OptinMonster | [optinmonster.com/pricing](https://optinmonster.com/pricing/) and [Exit Intent feature](https://optinmonster.com/features/exit-intent/) | Lead capture, popups, exit intent, cart/form abandonment campaigns, targeting, A/B testing, revenue attribution. | Intro annual pricing: Basic $7/month, Plus $19/month, Pro $29/month, Growth $49/month; renewals at full annual rates shown on pricing page. | Powerful targeting, templates, mature conversion SaaS, strong popup/lead capture. | External SaaS, popup-first UX, not Woo-native checkout/order item attribution. | Native Woo offer placement, checkout-safe bumps, HPOS-aware attribution. | Lead generation and conversion optimization SaaS. | UpsellBay should avoid popup bloat and win on native checkout/cart relevance. |
| CartPulse for WooCommerce | [Woo Marketplace listing](https://woocommerce.com/products/cartpulse/) | Cart tracking, abandoned/recovered cart reporting, automated recovery emails, coupons, guest lead capture. | AUD $140/year on Woo Marketplace. | Official marketplace listing, reporting, recovery email automation. | Recovery-only; early version 1.0.5 in listing; not AOV offer engine. | Checkout/product/cart/thank-you offers and attribution. | Marketplace cart recovery extension. | Adjacent category; useful benchmark for analytics and marketplace pricing. |
| Abandoned Cart for WooCommerce by WPExperts | [Woo Marketplace listing](https://woocommerce.com/products/abandoned-cart/) | Abandoned cart tracking, reminders, coupons, guest tracking, logs, Twilio/web push. | $49/year. | Low-cost marketplace recovery tool. | Recovery-only; lower technical baseline in listing; no AOV offers. | Native offer engine and checkout bumping. | Budget marketplace recovery extension. | Confirms recovery is crowded and low-priced; UpsellBay should not compete here directly. |

### 6.2 Direct Comparison

| Capability | UpsellBay target | Funnel suites | Recovery suites | Single-feature bump plugins |
| --- | --- | --- | --- | --- |
| Existing checkout preserved | Yes, non-negotiable | Often no or secondary | Usually yes, but they do not render bumps | Usually yes |
| Classic checkout support | Yes | Yes | Usually yes | Yes |
| Block Checkout support | Yes, launch gate | Mixed and suite-specific | Often not core | Often weak or unclear |
| HPOS-safe attribution | Yes | Varies by product maturity | Varies | Varies |
| Product, cart, checkout, thank-you offers | Yes | Yes, but inside funnel/checkouts | No | Usually no |
| Cart recovery emails | No, integration only | Often yes in suite | Yes | No |
| Native Woo admin | Yes | Often custom app/funnel UI | Mixed | Mixed |
| Advanced funnels | No | Yes | No | No |
| A/B testing | P1/v1.1 | Yes | Often campaign tests | Rare |
| Entry price | $79/year target | $149-$349+ for comparable features | Free to $75/month+ | $49-$89/year |
| Best buyer | Merchant who wants AOV gains without checkout replacement | Growth marketer wanting full funnel suite | Merchant focused on abandoned cart emails | Merchant needing only one bump |

### 6.3 Internal Portfolio Comparison

CartBay should be treated as an internal adjacent product, not a competitor and not a dependency.

| Dimension | CartBay | UpsellBay v4 implication |
| --- | --- | --- |
| Category | Abandoned cart recovery. | AOV offer placement and attribution. |
| Primary admin mental model | Recovery sessions, sequences, email templates, restore links, recovery analytics. | Offers, placements, rules, discounts, acceptance analytics. |
| Shopper data sensitivity | Captures checkout identity and cart snapshot after consent. | Should avoid shopper identity unless Woo order/cart context requires it; analytics aggregate table must remain non-PII. |
| Woo email usage | First-class recovery email classes and templates. | Do not add Woo recovery emails; use normal Woo order/customer emails only when core Woo behavior already sends them. |
| Scheduling | Abandonment detection, email sequence jobs, cleanup, analytics refresh, license checks. | Only analytics reconciliation, cleanup, license checks, and optional future testing jobs. No abandonment scanner. |
| Coupons | Recovery-session-linked incentives. | Session-scoped cart item discount where possible; persistent coupons only for P1 next-order coupon feature. |
| Shared lessons | Woo-native admin, HPOS-safe CRUD, Action Scheduler idempotence, license resilience, PII-safe logs. | Reuse lessons and patterns, not CartBay state or feature code. |

## 7. Product Foundation

### 7.1 Product Vision

UpsellBay helps WooCommerce merchants increase revenue from the traffic they already have by placing relevant, measurable, low-friction offers at the moments shoppers are most likely to add value to an order.

### 7.2 Problem Statement

WooCommerce merchants want higher average order value, but many AOV tools are bundled inside complex funnel builders, require checkout replacement, break theme expectations, lack Block Checkout support, or provide weak attribution. Merchants need a reliable native extension that lets them create targeted offers quickly, prove incremental revenue, and keep checkout stable.

### 7.3 Market Opportunity

The market is validated by paid competitor categories:

- Funnel and checkout optimization suites from $149/year to $349+/year.
- Focused upsell/order bump plugins from $75/year to $89/year.
- Woo Marketplace single-purpose conversion extensions from $49/year to AUD $140/year.
- Analytics/recovery SaaS tools from $14/month to $75/month+.

UpsellBay should occupy the premium focused middle: richer than single-feature bump tools, safer and cheaper than funnel suites, more Woo-native than popup/SaaS tools.

### 7.4 Value Proposition

For WooCommerce merchants who want higher AOV without replacing checkout, UpsellBay provides native order bumps, cart/product offers, thank-you offers, and offer-level analytics that work with WooCommerce's current checkout architecture, HPOS, and marketplace standards.

### 7.5 Product Positioning

UpsellBay is the native WooCommerce AOV offer engine.

It is:

- Native, not a standalone marketing app.
- Additive, not a checkout replacement.
- Focused, not a funnel suite.
- Measurable, not a design-only widget.
- Reliable, not tokenization-dependent.
- Extensible, not a closed workflow.

### 7.6 Unique Selling Proposition

The first premium WooCommerce AOV plugin built around three practical promises:

1. Works on the checkout the merchant already has.
2. Supports modern WooCommerce architecture: Block Checkout, HPOS, QIT, and WordPress 7.0 admin styling.
3. Shows exactly which offers generate revenue, without forcing a funnel builder or external SaaS dependency.

### 7.7 Product Boundaries

UpsellBay v1 is not:

- A funnel builder.
- A checkout replacement.
- A cart recovery email/SMS platform.
- A popup lead capture suite.
- A dynamic pricing replacement.
- An AI recommendation platform.
- A tokenized one-click post-purchase charge engine.
- A CartBay add-on, CartBay settings section, or CartBay recovery-offer feature.

These boundaries protect implementation reliability and make the product easier to sell.

## 8. User Strategy

### 8.1 Target Personas

| Persona | Profile | Jobs to be done | UpsellBay promise |
| --- | --- | --- | --- |
| Sarah, store operator | Runs a small or growth-stage Woo store, handles marketing and ops, no developer. | Add one relevant offer to checkout without breaking anything. | Create a tested checkout bump in 15 minutes using native Woo admin. |
| Mike, agency optimizer | Manages multiple client Woo stores, optimizes AOV and paid traffic ROI. | Roll out targeted offers and prove revenue impact across stores. | Multi-site license, import/export, rules, analytics, and hooks. |
| Priya, Woo developer | Builds custom Woo stores and maintains checkout/payment compatibility. | Add client-specific offer logic without fighting a closed plugin. | Stable schema, documented hooks, HPOS-safe APIs, no private Woo internals. |
| Daniel, enterprise operator | Oversees a high-order-volume Woo store with QA and compliance needs. | Add revenue optimization without adding operational risk. | QIT-gated, performance-tested, no PII-heavy analytics, predictable rollback. |

### 8.2 Core Use Cases

1. Show a relevant low-priced accessory as a checkout order bump.
2. Show a product-page bundle or "frequently bought together" offer.
3. Show cart cross-sells or free-shipping-threshold add-ons.
4. Show a safe thank-you page follow-on offer after the primary order is complete.
5. Track attributed offer revenue and accept rate by placement.
6. Let agencies export/import working offer configurations.
7. Let developers alter eligibility and rendering through documented hooks.

### 8.3 Customer Journey Alignment

| Journey stage | Shopper intent | UpsellBay placement | Offer strategy | Success metric |
| --- | --- | --- | --- | --- |
| Product page | Evaluating a product | Product-page offer | Complementary accessory, bundle, warranty, upgrade comparison. | Add-to-cart rate and bundled add rate. |
| Cart | Reviewing order | Cart offer | Add-on products, threshold prompts, small accessory bundles. | Cart offer accept rate and cart AOV. |
| Checkout | High purchase intent | Checkout bump | One small relevant item with clear value and no interruption. | Checkout bump accept rate and checkout completion impact. |
| Thank-you page | Purchase complete | Follow-on offer | Low-friction second purchase or next-order incentive. | Follow-on conversion and attributed revenue. |
| Post-launch analysis | Merchant evaluating ROI | Analytics | Identify best/worst offers and next action. | Attributed revenue, accept rate, AOV lift. |

## 9. Feature Architecture

### 9.1 Priority Model

- P0: Required for v1 launch and Woo Marketplace submission.
- P1: Ship in v1 only if P0 is stable; otherwise v1.1.
- P2: Backlog, not required for initial launch.

### 9.2 P0 Launch Features

| Feature | Functional specification | Definition of done |
| --- | --- | --- |
| Offer management | Private `upsellbay_offer` CPT with native Woo admin list table, statuses, placement, target product, priority, and performance columns. | Merchant can create, edit, pause, duplicate, delete, preview, and reorder offers under WooCommerce -> UpsellBay. No top-level WP admin menu. |
| Checkout order bump - classic checkout | Inject one highest-priority eligible checkout bump using classic checkout hooks. Accept/unaccept via AJAX/REST updates cart and totals. | Works on `[woocommerce_checkout]`; updates totals; writes attribution to order item meta; does not break payment submission. |
| Checkout order bump - Block Checkout | Register compatible order-field/slot UI for Block Checkout using WooCommerce's Additional Checkout Fields API plus blocks UI integration where needed. | Phase 0 prototype proves full offer card rendering and cart update behavior. Final E2E test passes on Block Checkout with WooPayments active. |
| Product-page offer | Render a native "Frequently bought together" or "Recommended add-on" module on product pages. | Merchant can target by current product, category, tag, or manually selected product. Add-to-cart works for simple and variable products where supported. |
| Cart offer | Render up to three eligible cross-sell/add-on offers in cart using Woo-native cart patterns. | Offers respect rules, stock, visibility, and theme layout; no layout shift on mobile; accepted items get attribution. |
| Thank-you follow-on offer | Render a post-purchase offer on the order-received page after primary order completion. | Clicking "Add to order" creates a new cart/checkout flow linked to the source order. Primary order is never mutated. |
| Rules engine | AND/OR rules for cart product, cart category/tag, cart subtotal, product being viewed, user role, customer order count, customer lifetime spend, stock status, and exclude-if-product-in-cart. | Rules are validated on save, evaluated server-side, and exposed to filters for developers. Empty rules mean "eligible for all applicable contexts." |
| Discount model | Offer discounts may be none, fixed amount, percentage, or fixed offer price. | Discount is applied without persistent coupon creation. Subscription products are protected from recurring discount leakage. |
| Attribution | Track accepted offer product, placement, offer ID, source order, discount amount, and follow-on order linkage. | Uses WC order/order-item CRUD APIs only; zero direct order postmeta writes. |
| Analytics dashboard | Views, accepts, dismissals, accept rate, attributed revenue, AOV lift estimate, and per-offer table with date range. | Dashboard loads in under 500ms on test data set with 100,000 orders because it reads aggregate stats, not live order scans. |
| Test mode | Admin-only mode that forces eligible offers to render for preview/testing without affecting shoppers. | Admin bar notice appears; test mode can be disabled; marketplace reviewer instructions use this mode. |
| Conflict detection | Detect known checkout/funnel/recovery plugins and custom checkout replacements where possible. | Dismissible Woo admin notice explains compatibility risk and links to docs. No hard failure. |
| CartBay coexistence guard | Detect CartBay only to show optional coexistence guidance and to avoid confusing duplicate recovery/AOV language. | UpsellBay works normally with CartBay active, never reads CartBay data, never writes CartBay data, and never adds recovery features to UpsellBay admin. |
| Licensing and updates | WP Anchor Bay license activation, deactivation, update checks, masked key UI, staging domain handling. | License outages never disable live offers; last-known valid state is cached; staging/local domains do not consume slots. |
| Import/export | JSON export/import for offer definitions, excluding site-specific product IDs unless mapped during import. | Agencies can move offer templates between sites using product SKU/name mapping. |
| Documentation and reviewer guide | README, setup guide, compatibility matrix, developer hook reference, marketplace reviewer steps. | Docs are complete before submission. |

### 9.3 P1 Features

| Feature | Specification |
| --- | --- |
| Basic style controls | Native WP color picker for accent color, border color, background, and badge color. Defaults inherit theme/Woo styles. No custom UI framework. |
| Offer recommendations | In the offer editor, suggest products from Woo cross-sells/upsells, same category, low-priced accessories, and historically accepted offers. |
| Variant A/B testing for checkout bump | Two variants for headline/body/discount. Cookie or session split. Report accept rate and revenue per variant. |
| Multi-bump display | Growth/Agency tiers can show up to three checkout bumps if eligible. Core tier shows one highest-priority bump. |
| Next-order coupon offer | Thank-you page can offer a next-order coupon, but coupon generation must use Woo coupon APIs and auto-expiry. |
| WP-CLI utilities | Commands for stats rollup, offer export/import, and compatibility diagnostics. |

### 9.4 P2 Backlog

- Tokenized one-click post-purchase upsells for vetted gateways only, starting with WooPayments/Stripe after a separate technical discovery.
- Downsell chains.
- AI-generated offer suggestions.
- Deep CRM integrations.
- SMS/WhatsApp abandoned-cart recovery is not an UpsellBay feature. If pursued, it belongs in CartBay or a separate automation integration.
- Visual template marketplace.
- Headless WooCommerce support.

### 9.5 Offer Types

| Offer type | Placement | Primary shopper action | Typical offer | P0 support |
| --- | --- | --- | --- | --- |
| `checkout_bump` | Checkout | Checkbox/toggle add-on | Warranty, sample, gift wrap, accessory. | Yes |
| `product_upsell` | Product page | Add bundle/add-on | Matching accessory, upgraded product, bundle. | Yes |
| `cart_crosssell` | Cart | Add item | Threshold helper, complementary product. | Yes |
| `thankyou_offer` | Thank-you page | Start follow-on checkout | Low-cost add-on, replenishment, next-order incentive. | Yes |

### 9.6 Admin Workflows

#### Workflow A: First offer setup

1. Merchant installs and activates UpsellBay.
2. Merchant goes to WooCommerce -> UpsellBay.
3. First-run wizard asks for offer type, product to offer, placement, headline, optional discount, and one targeting rule.
4. Merchant enables test mode.
5. Merchant opens preview checkout/cart/product link.
6. Merchant confirms visual placement and disables test mode.
7. Offer goes live.

#### Workflow B: Growth merchant optimization

1. Merchant opens Analytics.
2. Sees per-offer accept rate, attributed revenue, and AOV lift estimate.
3. Identifies low-performing offer.
4. Duplicates offer and adjusts product/discount/copy.
5. Optionally enables A/B test if P1 shipped.
6. After enough traffic, merchant keeps the winner and pauses the loser.

#### Workflow C: Agency rollout

1. Agency creates a tested offer template on staging.
2. Exports JSON template.
3. Imports into client site.
4. Maps source product SKUs to client products.
5. Enables test mode and runs a checkout test.
6. Activates offer after client approval.

### 9.7 Shopper Experience Requirements

- Offers must look like part of WooCommerce, not injected advertising.
- Checkout bump must not obscure the "Place order" button or payment methods.
- Offer price, discount, and added item must be unambiguous.
- Checkbox/toggle state must be accessible by keyboard and screen reader.
- Mobile layouts must keep product image, copy, price, and CTA readable without horizontal scroll.
- Dismissal must be available but visually secondary.
- If an offered product becomes out of stock, the offer must not render.
- If an offer cannot be added, show a Woo notice and preserve checkout state.

## 10. Technical Product Requirements

### 10.1 Platform Baseline

| Component | Requirement |
| --- | --- |
| WordPress | Minimum 6.9, tested against WordPress 7.0 and the latest stable at launch. |
| WooCommerce | Minimum 10.8.x, tested against latest 10.8.x stable and next release candidate when available. |
| PHP | Minimum 8.1. Test 8.1, 8.2, 8.3, and 8.4 where supported by QIT/local CI. |
| Database | MySQL 8.0+ or MariaDB equivalent supported by current WordPress/WooCommerce recommendations. |
| Browser support | Match current WordPress/WooCommerce admin browser support. |
| Internationalization | All user-facing strings under `upsellbay` text domain. |
| Accessibility | WCAG 2.1 AA for admin and storefront widgets. |

Rationale: WooCommerce 10.8 requires WordPress 6.9+, aligns admin styling with WordPress 7.0, and includes performance and Store API improvements. PHP 8.1 is a commercially safer minimum than PHP 8.3 while still allowing typed modern code.

### 10.2 WooCommerce Marketplace Standards

UpsellBay must:

- Follow WordPress Coding Standards and WooCommerce extension best practices.
- Use WooCommerce CRUD APIs for all order and order item data.
- Avoid private/internal WooCommerce classes unless the official docs mark an API as extension-safe.
- Declare HPOS compatibility with `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` before WooCommerce initialization.
- Declare Cart/Checkout Blocks compatibility only after Block Checkout E2E tests pass.
- Pass QIT managed tests, including activation, security, PHPStan, API compatibility, extension compatibility, and E2E packages.
- Use native Woo/WP admin components and avoid a custom app shell.
- Avoid external telemetry, affiliate links, or forced SaaS dependencies in admin.
- Include clear uninstall/data-retention behavior.

### 10.3 Admin Information Architecture

No top-level WordPress menu is allowed.

Recommended admin structure:

- WooCommerce -> UpsellBay
  - Offers
  - Add Offer
  - Analytics
  - Settings
  - Tools
  - Help

Admin UI requirements:

- Use `widefat`, `wp-list-table`, WooCommerce settings tables, native notices, help tips, Select2 product search, WP color picker, and standard capability checks.
- Capability for all management actions: `manage_woocommerce`.
- Settings saves must use nonces and standard WordPress settings patterns.
- List tables must support search, filters by placement/status, bulk pause/delete, and sortable priority.

### 10.4 Data Model

#### 10.4.1 Offer configuration

Storage: private CPT `upsellbay_offer`.

Reason: CPT provides revisions, trash, permissions, admin list table compatibility, export/import paths, and familiar WordPress data management.

Core meta schema:

| Meta key | Type | Notes |
| --- | --- | --- |
| `_ub_offer_type` | string | `checkout_bump`, `product_upsell`, `cart_crosssell`, `thankyou_offer`. |
| `_ub_status` | string | `active`, `paused`, `draft`. |
| `_ub_offer_product_id` | int | Product to offer. Validate with `wc_get_product()`. |
| `_ub_trigger_product_ids` | int[] | Optional explicit product targeting. |
| `_ub_trigger_category_ids` | int[] | Optional category targeting. |
| `_ub_discount_type` | string | `none`, `percent`, `fixed_amount`, `fixed_price`. |
| `_ub_discount_value` | float | Sanitized decimal. |
| `_ub_headline` | string | Max 80 chars. |
| `_ub_body` | string | Max 240 chars, limited HTML. |
| `_ub_button_text` | string | Optional; defaults by placement. |
| `_ub_rules` | array | Normalized rules. |
| `_ub_rules_match` | string | `all` or `any`. |
| `_ub_placement_config` | array | Placement-specific hook/block location and display flags. |
| `_ub_show_image` | bool | Default true. |
| `_ub_start_at` | datetime|null | Optional scheduling. |
| `_ub_end_at` | datetime|null | Optional scheduling. |
| `_ub_priority` | int | Mirrors `menu_order` for sorting. |

#### 10.4.2 Attribution

Storage: WooCommerce order item meta and order meta through CRUD APIs.

Order item meta:

- `_ub_offer_id`
- `_ub_offer_type`
- `_ub_offer_placement`
- `_ub_discount_type`
- `_ub_discount_amount`
- `_ub_source_context`

Follow-on order meta:

- `_ub_source_order_id`
- `_ub_source_offer_id`
- `_ub_follow_on_order`

No direct writes to `wp_posts`, `wp_postmeta`, Woo legacy order tables, or HPOS tables are allowed for order data.

#### 10.4.3 Analytics aggregate table

Use one custom non-PII aggregate table:

`{$wpdb->prefix}upsellbay_offer_stats_daily`

Columns:

| Column | Type | Notes |
| --- | --- | --- |
| `stat_date` | date | Store timezone date. |
| `offer_id` | bigint unsigned | References `upsellbay_offer` post ID. |
| `placement` | varchar(32) | Placement key. |
| `views` | bigint unsigned | Render count. |
| `accepts` | bigint unsigned | Accept count. |
| `dismissals` | bigint unsigned | Dismiss count. |
| `orders` | bigint unsigned | Orders containing accepted offer. |
| `revenue` | decimal(20,6) | Attributed line subtotal after discount, store currency. |
| `discount_total` | decimal(20,6) | Total discounts applied. |
| `updated_at` | datetime | Last update. |

Unique key: `(stat_date, offer_id, placement)`.

Rationale: v2's transient-only counter model is simple but weak for premium reporting. A single aggregate stats table avoids PII, avoids live order scans, supports atomic increments, and keeps analytics fast on larger stores. Raw event logs are not stored in v1.

#### 10.4.4 Plugin settings

Storage: single option `upsellbay_settings`.

Settings include:

- Global enable/disable.
- Test mode.
- Placement toggles.
- Style tokens.
- License status and masked key.
- Data retention period.
- Compatibility notice dismissals.
- Debug logging toggle.

### 10.5 Identifier Contract

All identifiers must be defined once in `app/Core/Constants.php` and reused throughout the plugin. Implementation must not create new prefixes ad hoc.

| Identifier | Required value | Notes |
| --- | --- | --- |
| Plugin slug | `upsellbay` | Public slug, Action Scheduler group, asset handle base. |
| Main file | `upsellbay.php` | Independent plugin entrypoint. |
| Namespace root | `WPAnchorBay\UpsellBay` | Must not use CartBay namespaces. |
| Text domain | `upsellbay` | All PHP and JS strings. |
| Option prefix | `upsellbay_` | Example: `upsellbay_settings`, `upsellbay_db_version`. |
| Offer meta prefix | `_ub_` | Only for UpsellBay offer and order attribution meta. |
| Hook prefix | `upsellbay_` | Public hooks/actions/filters. |
| Nonce prefix | `upsellbay_` | Admin and REST intent checks. |
| REST namespace | `upsellbay/v1` | Public route namespace. |
| CPT | `upsellbay_offer` | Private offer configuration record. |
| Stats table | `{$wpdb->prefix}upsellbay_offer_stats_daily` | Non-PII aggregate table. |
| Action Scheduler group | `upsellbay` | No CartBay group reuse. |
| Asset handle prefix | `upsellbay-` | Admin and frontend bundles. |
| License product slug | `upsellbay` | Separate WP Anchor Bay product identity. |

### 10.6 Architecture and Module Layout

UpsellBay must follow the internal WordPress/WooCommerce plugin blueprint: a thin entry file, one bootstrap coordinator, small services, repositories around storage, route classes around HTTP boundaries, and Woo-native admin components.

Recommended layout:

```text
upsellbay.php
app/
  Admin/
    Offers/
    Settings/
    Tools/
    Wizard/
  Api/
    Routes/
  Core/
    Constants.php
    Container.php
    Installer.php
    Plugin.php
    Settings.php
    Updater.php
  Data/
    OfferRepository.php
    StatsRepository.php
  Domain/
    Analytics/
    Attribution/
    Cart/
    Discounts/
    Offers/
    Rules/
  Integrations/
    WooCommerce/
    Licensing/
  Storefront/
    Blocks/
    Classic/
    Renderers/
  Utils/
assets/
  admin/
  frontend/
src/
  admin/
  classic-checkout/
  block-checkout/
  storefront/
templates/
  storefront/
languages/
tests/
docs/
```

Module rules:

- `upsellbay.php` only handles `ABSPATH`, constants/autoload loading, dependency checks, lifecycle hook registration, and bootstrap startup.
- `Core\Plugin` owns initialization order and hook topology, but not business logic.
- Hook callbacks must delegate to services; no large workflows inside anonymous closures or the main plugin file.
- Repositories hide CPT/meta/table query shape and enforce prefix rules.
- REST route classes validate HTTP input and delegate to services.
- Admin classes use WooCommerce/WP UI patterns and delegate save/normalize work to settings/domain services.
- Storefront renderers return escaped markup or template output and do not calculate eligibility themselves.
- JavaScript source lives in `src`; committed build output lives in `assets`.
- Any reusable WP Anchor Bay starter code must remain infrastructure-only. It must not include CartBay domain logic.

### 10.7 Lifecycle and Upgrade Behavior

Activation must:

- Verify WordPress, WooCommerce, PHP, and required extension minimums.
- Register the `upsellbay_offer` CPT before rewrite flushing.
- Create default options with version markers.
- Create or migrate the aggregate stats table with `dbDelta`.
- Schedule only UpsellBay recurring jobs.
- Declare feature compatibility at the correct WooCommerce lifecycle timing.

Deactivation must:

- Unschedule UpsellBay jobs only.
- Leave offers, settings, attribution, and aggregate stats intact.
- Not touch CartBay jobs, options, sessions, or tables.

Uninstall must:

- Preserve data by default.
- Delete UpsellBay data only when the merchant explicitly enabled cleanup.
- Remove `upsellbay_*` options, `upsellbay_offer` posts/meta, `_ub_*` attribution meta where safely discoverable through CRUD-aware cleanup, the aggregate stats table, transients, and scheduled jobs.
- Never remove CartBay data or shared WP Anchor Bay license infrastructure used by other products.

Runtime self-healing must:

- Check for missing scheduled UpsellBay jobs and reschedule idempotently.
- Check DB version and run versioned migrations.
- Fail open for live offers when license validation is temporarily unreachable.
- Fail closed for discounts if pricing cannot be validated server-side.

### 10.8 Runtime Services

| Service | Responsibility |
| --- | --- |
| OfferRepository | Query active offers, load schemas, validate product availability. |
| RuleEvaluator | Evaluate normalized rules against cart, product, customer, and order context. |
| PlacementRenderer | Render native storefront widgets for each placement. |
| CartMutator | Add/remove accepted offer products and apply discounts safely. |
| AttributionWriter | Write order item and follow-on order metadata through WC CRUD APIs. |
| AnalyticsRecorder | Atomic aggregate stats increments and daily reconciliation. |
| LicenseClient | WP Anchor Bay activation, deactivation, status cache, update checks. |
| CompatibilityScanner | Detect known conflicting plugins and checkout replacements. |
| ImportExportService | JSON export/import and SKU/product mapping. |
| CoexistencePolicy | Keep UpsellBay independent from CartBay and other WP Anchor Bay products while allowing documented integrations. |
| Scheduler | Register, deduplicate, and run UpsellBay-only Action Scheduler jobs. |
| Logger | Write WooCommerce-compatible logs with subsystem context and sensitive-value masking. |

### 10.9 Checkout and Block Integration

#### Classic checkout

Primary hook:

- `woocommerce_review_order_before_submit`

Supporting hooks:

- `woocommerce_cart_calculate_fees`
- `woocommerce_checkout_create_order_line_item`
- `woocommerce_checkout_order_processed`

#### Block Checkout

Required technical strategy:

- Use the official Additional Checkout Fields API for an `order` location field where appropriate.
- Use WooCommerce Blocks extension points, filters, or Slot/Fill APIs for rich offer card rendering if a plain checkbox field is insufficient.
- Use Store API-compatible cart mutation paths.
- Use a compiled JavaScript entry point with WordPress dependency extraction.
- Run a Week 1 proof-of-concept before final implementation.

Launch gate:

- If Block Checkout cannot render a polished, accessible, testable checkout bump without unsupported internal APIs, the product cannot claim Block Checkout support and Marketplace launch must be delayed or scope must change.

### 10.10 REST Endpoints

Namespace: `/wp-json/upsellbay/v1`.

| Endpoint | Method | Purpose | Auth/security |
| --- | --- | --- | --- |
| `/offer-preview` | GET | Admin preview payload for selected offer. | `manage_woocommerce`, nonce. |
| `/bump-toggle` | POST | Add/remove checkout bump product. | Nonce for logged-in users; guest token/session validation; rate limit. |
| `/cart-offer-add` | POST | Add product-page/cart offer product. | Guest-safe validation, stock/product checks, rate limit. |
| `/dismiss` | POST | Dismiss offer for current WC session. | Session-bound, rate limit. |
| `/analytics/summary` | GET | Admin analytics summary. | `manage_woocommerce`, nonce. |
| `/import` | POST | Admin offer import. | `manage_woocommerce`, nonce, file validation. |

REST requirements:

- Validate offer ID, product ID, placement, nonce/session, stock, purchase permissions, and rules at request time.
- Never trust client-sent price or discount.
- Return Woo notices and updated cart fragments/Store API state as appropriate.
- Rate limit public endpoints with transient or object-cache counters.

### 10.11 Discount Application

Preferred model:

- For simple/variable non-subscription products, apply a session-scoped discount using cart item data and price adjustment, not persistent coupons.
- Store original price and offer price in cart item data.
- On order creation, record discount meta on the line item.

Safeguards:

- Do not apply recurring discounts to WooCommerce Subscriptions products in v1.
- Do not create public coupon codes for checkout bumps.
- Do not mutate original order totals for thank-you follow-on offers.
- If discount calculation fails, fail closed by showing the offer at regular price only if configured, otherwise hide the offer.

### 10.12 Security Requirements

- All admin writes require `manage_woocommerce` and nonce checks.
- All inputs sanitized and validated by context: `absint`, `sanitize_text_field`, `wc_format_decimal`, `wp_kses_post`, `sanitize_key`, URL validation.
- All output escaped: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Public REST endpoints rate-limited.
- No customer PII in analytics aggregate table.
- License key never exposed to frontend HTML, JS, logs, REST, or analytics.
- No `eval`, obfuscated code, or remote code loading.
- Debug logs must mask license keys, emails, tokens, and payment identifiers.
- Uninstall routine must offer documented cleanup of plugin settings, offers, and aggregate stats.
- CartBay session identifiers, recovery tokens, notification IDs, and customer recovery state must not be copied into UpsellBay logs, analytics, REST payloads, or settings.

### 10.13 Performance Requirements

| Requirement | Target |
| --- | --- |
| Checkout overhead | Less than 150ms p95 added server time with 50 active offers and object cache disabled. |
| Rule evaluation | Less than 10ms p95 for 50 active offers using already-loaded cart context. |
| Analytics dashboard | Less than 500ms p95 on generated test data representing 100,000 orders and 500 offers. |
| Frontend JS | Separate classic and block bundles; load only on relevant placement pages. |
| DB writes | One aggregate stats update per rendered offer; conversions written during order lifecycle only. |
| Caching | Cart, checkout, and thank-you pages rely on Woo cache exclusions; no global cache-busting scripts. |

### 10.14 Compatibility Matrix

| Area | Requirement |
| --- | --- |
| HPOS | Full support; no direct order postmeta access. |
| Classic checkout | Full support. |
| Block Checkout | Full support only after POC and E2E tests pass. |
| WooPayments | Test checkout bump and follow-on checkout. |
| Stripe for WooCommerce | Test checkout bump and follow-on checkout. |
| PayPal Payments | Test checkout bump and follow-on checkout. |
| WooCommerce Subscriptions | No recurring discount leakage; no subscription product post-purchase offer in v1. |
| Multicurrency plugins | v1 supports store currency attribution only; document limitations. |
| Product bundles/composites | v1 supports simple/variable products; bundles/composites are compatibility backlog unless tests pass. |
| CheckoutWC, CartFlows, FunnelKit | Detect and warn. Do not attempt unsafe injection into replacement checkout templates. |
| CartBay | Coexist without shared state. Optional guidance only; no dependency and no recovery-feature bleed. |
| Page builders | Product-page module must degrade gracefully; shortcodes/block embeds are P1. |

### 10.15 Developer Extensibility

Public filters:

- `upsellbay_offer_schema`
- `upsellbay_available_placements`
- `upsellbay_offer_query_args`
- `upsellbay_rule_context`
- `upsellbay_rule_result`
- `upsellbay_eligible_offers`
- `upsellbay_render_offer_html`
- `upsellbay_offer_price`
- `upsellbay_discount_amount`
- `upsellbay_attribution_meta`
- `upsellbay_analytics_event`

Public actions:

- `upsellbay_offer_created`
- `upsellbay_offer_updated`
- `upsellbay_offer_rendered`
- `upsellbay_offer_accepted`
- `upsellbay_offer_dismissed`
- `upsellbay_attribution_written`
- `upsellbay_follow_on_order_created`
- `upsellbay_daily_stats_reconciled`

Developer docs must include:

- Hook reference.
- Offer schema.
- REST endpoint contracts.
- Analytics table schema.
- Import/export JSON schema.
- Compatibility examples.

## 11. Commercial Strategy

### 11.1 Positioning Strategy

UpsellBay should be positioned as a premium focused WooCommerce extension:

"Native WooCommerce order bumps and offers that increase AOV without replacing checkout."

Do not lead with "funnels." Do not lead with "cart recovery." Do not compete with OptinMonster/Retainful/Metorik on automation breadth. Lead with measurable incremental order value and checkout safety.

### 11.2 Pricing Recommendation

| Tier | Price | Sites | Feature policy | Best for |
| --- | --- | --- | --- | --- |
| Core | $79/year | 1 site | All P0 features; one active checkout bump displayed at a time; unlimited product/cart/thank-you offers. | Small stores. |
| Growth | $149/year | 5 sites | All features; up to three simultaneous checkout bumps; import/export; P1 A/B testing when available. | Growth merchants and small agencies. |
| Agency | $299/year | 25 sites | All features; priority support; template packs; WP-CLI tools; client rollout docs. | Agencies and multi-store operators. |

Pricing rationale:

- $79/year matches the Woo Marketplace Order Bump price and is close to YITH Frequently Bought Together at $79.99/year and UpsellWP at $75/year.
- $149/year leaves room below CheckoutWC Plus ($249/year), CartFlows Plus ($189/year), and FunnelKit Plus ($179.50/year).
- $299/year agency tier aligns with UpsellWP Agency ($295/year) while preserving strong multi-site value.
- All tiers should include the core product to avoid a crippled entry plan that creates support churn.

### 11.3 Launch Model

- Launch premium-only.
- No free tier for v1.0; defer a WP.org Lite version until product-market fit and support load are known.
- Offer 14-day or 30-day refund depending on direct storefront and Woo Marketplace requirements.
- Direct sales primary for margin; Woo Marketplace listing for trust, discovery, and marketplace credibility.
- Marketplace price must not exceed direct channel price.

### 11.4 Why Merchants Will Pay

Merchants will pay when the product turns one extra accessory/add-on per few orders into measurable revenue.

Example ROI framing:

- Store has 300 orders/month.
- One $12 accessory bump is accepted on 5% of orders.
- Monthly attributed revenue is 300 x 5% x $12 = $180.
- Annualized attributed revenue is $2,160 before product costs.
- A $79/year plugin cost is easy to justify if attribution is visible.

This is an example sales model, not a guaranteed claim. Marketing copy must avoid unsupported universal AOV lift promises until beta data exists.

## 12. Competitive Differentiation

### 12.1 Why UpsellBay Wins

UpsellBay wins when the buyer values reliability and native WooCommerce integration over funnel depth.

Winning reasons:

- It does not replace checkout.
- It supports current Woo architecture: HPOS, Block Checkout, WooCommerce 10.8.x, WordPress 7.0 admin expectations.
- It gives merchants enough placements to improve AOV without buying a full funnel suite.
- It measures attributed revenue directly from Woo order data.
- It avoids tokenized post-purchase charges until gateway support is proven.
- It gives agencies hooks, import/export, and predictable data structures.
- It feels like WooCommerce, not a separate marketing app.

### 12.2 What Makes It Meaningfully Better

| Competitor pain | UpsellBay answer |
| --- | --- |
| "I do not want to rebuild checkout." | Additive checkout bump on existing checkout. |
| "My plugin works on classic checkout but not blocks." | Block Checkout support is a launch gate. |
| "I cannot tell whether the offer made money." | Offer-level revenue attribution and AOV reporting. |
| "The tool is too complex for one bump." | First-run wizard and native Woo UI. |
| "The post-purchase upsell broke with my gateway." | Follow-on checkout for v1; no tokenization dependency. |
| "I need to customize this for a client." | Documented hooks, filters, schemas, and import/export. |

### 12.3 Reasons a Merchant Chooses UpsellBay Over Alternatives

- Over CartFlows/FunnelKit: lower price, lower adoption risk, no funnel/checkout replacement.
- Over CheckoutWC: does not require a Shopify-style checkout migration.
- Over UpsellWP/WP Swings: stronger Woo-native/marketplace/QIT positioning and cleaner product boundaries.
- Over Order Bump for WooCommerce: broader AOV journey and stronger analytics.
- Over Retainful/ShopMagic/CartPulse/Abandoned Cart: focuses on increasing the current order, not just recovering abandoned carts later.
- Over OptinMonster: native Woo order/cart integration rather than popup-first lead capture.
- Over Metorik: renders offers and writes attribution, while Metorik remains a reporting/engagement complement.

## 13. Delivery Plan

### 13.1 Phase 0: Validation and Architecture Proof, Week 1

Scope:

- Block Checkout proof-of-concept.
- HPOS compatibility declaration and CRUD audit.
- UpsellBay identifier contract and CartBay isolation audit.
- Analytics aggregate table migration plan.
- License server staging-domain behavior confirmation.
- Final competitor/conflict plugin list.

Exit criteria:

- Block Checkout offer card can render, add/remove product, update totals, and write attribution in a prototype.
- If Block POC fails, product launch plan is re-scoped before core implementation.
- Architecture notes confirm no CartBay option/meta/table/hook/session dependency.
- Architecture docs and ADRs are committed.

### 13.2 Phase 1: Foundation, Weeks 1-2

Scope:

- Plugin bootstrap.
- `Constants.php` identifier contract.
- WPCS/PHPStan/QIT local CI.
- CPT and admin list table.
- Settings page.
- License client.
- Data migrations.
- Uninstall/data retention behavior.
- CartBay coexistence detection without data coupling.

Exit criteria:

- Plugin activates cleanly.
- QIT activation/security baseline passes locally where available.
- Offers can be created/paused/duplicated.
- CartBay can be active or inactive without changing UpsellBay activation, settings, or offer behavior.

### 13.3 Phase 2: Core Checkout Bumps, Weeks 3-5

Scope:

- Classic checkout bump.
- Block Checkout bump.
- REST cart mutation.
- Discount application.
- Attribution writer.
- Test mode.
- Conflict notices.

Exit criteria:

- Classic and Block Checkout E2E tests pass.
- Offer can be accepted/unaccepted without checkout breakage.
- Attribution meta is correct.

### 13.4 Phase 3: Product, Cart, and Thank-You Offers, Weeks 5-7

Scope:

- Product-page offer module.
- Cart offer module.
- Thank-you follow-on checkout.
- Rules engine full P0 rule set.
- Follow-on order linkage.

Exit criteria:

- All P0 placements render and attribute correctly.
- Primary order is not mutated by follow-on offer flow.
- Mobile QA passes.

### 13.5 Phase 4: Analytics, Tools, and Polish, Weeks 7-9

Scope:

- Aggregate stats table.
- Analytics dashboard.
- Import/export.
- Reviewer docs.
- Compatibility docs.
- CartBay coexistence docs and internal portfolio distinction.
- Admin UX polish.

Exit criteria:

- Dashboard is fast on generated test data.
- Offer export/import works.
- Docs are complete enough for support and marketplace review.
- Support docs clearly route recovery-session/email questions to CartBay and AOV offer questions to UpsellBay.

### 13.6 Phase 5: Marketplace Readiness, Weeks 9-10

Scope:

- Full QIT managed tests.
- Playwright E2E suite.
- Security review.
- Accessibility review.
- Marketplace listing draft.
- Demo store and reviewer guide.

Exit criteria:

- QIT managed tests pass.
- Zero PHPStan errors at configured level.
- Zero WPCS violations.
- No high/critical security findings.
- Marketplace submission package complete.

## 14. Success Metrics

### 14.1 Product Success

| Metric | Target |
| --- | --- |
| Checkout stability | Zero production critical bugs involving broken checkout, incorrect order totals, duplicate orders, or payment failure caused by UpsellBay. |
| Beta AOV impact | At least 5 beta stores show measurable attributed revenue within 30 days. |
| Offer accept rate | Median checkout bump accept rate above 3% across beta stores. |
| Time to first offer | Median merchant can create and preview first offer in under 15 minutes. |
| Analytics trust | 100% of accepted test offers write attribution meta and appear in analytics after reconciliation. |

### 14.2 Commercial Success

| Metric | 90-day target |
| --- | --- |
| Paid licenses | 50 active paid licenses. |
| Revenue | $5,000+ annual recurring revenue booked. |
| Refund rate | Less than 10%. |
| Support burden | Fewer than 0.25 checkout-critical tickets per active license. |
| Marketplace | Submitted with no avoidable quality failures. |

### 14.3 Engineering Success

| Metric | Target |
| --- | --- |
| QIT | All relevant managed tests pass before submission. |
| PHPStan | Zero errors at configured level. |
| WPCS | Zero violations. |
| E2E | All critical flows pass on classic and Block Checkout. |
| Performance | Meets checkout and analytics budgets in Section 10.13. |
| Product isolation | All automated scans and review checklist items confirm no CartBay state, prefix, route, or schedule dependency. |

## 15. Launch Gates

Launch is blocked if any of the following are true:

- Block Checkout support is incomplete but marketing or marketplace copy claims support.
- Checkout bump changes order totals incorrectly.
- HPOS compatibility test fails.
- Attribution writes direct order postmeta.
- Follow-on offer mutates the primary order.
- Subscription product discounts can leak into recurring renewals.
- License server outage disables live offers.
- Public REST endpoints accept client-sent pricing.
- QIT has unresolved high-severity security, compatibility, or static analysis failures.
- Admin UI introduces a top-level WordPress menu or non-native app shell.
- UpsellBay requires CartBay to be active or silently changes behavior based on CartBay private data.
- UpsellBay writes to or reads from CartBay options, sessions, metadata, REST routes, scheduled jobs, or recovery email settings.

## 16. Risks and Mitigations

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Block Checkout rich UI is harder than expected | High | Week 1 POC; no launch claim without E2E proof; fallback to classic-only would require repositioning and is not preferred. |
| Checkout conflicts with funnel/checkout replacement plugins | High | Detect known plugins, warn, document limitations, avoid unsafe injection. |
| Discount logic causes incorrect totals | High | Server-side price calculation only; gateway matrix; unit and E2E tests; fail closed. |
| Analytics table migration creates DB issues | Medium | Use dbDelta, versioned migrations, no PII, uninstall cleanup, migration tests. |
| Product becomes too broad | Medium | Keep cart recovery, funnels, AI, CRM sync, and tokenized one-click upsells out of v1. |
| CartBay feature bleed | High | Maintain Section 2 isolation rules, code review prefix scans, independent activation tests, and support-copy separation before launch. |
| Price too close to UpsellWP | Medium | Differentiate through marketplace readiness, Block/HPOS proof, native admin, and quality. |
| Small stores fear checkout breakage | High | Test mode, preview, native UI, refund policy, transparent compatibility docs. |

## 17. Pre-Submission Checklist

### Code and Standards

- [ ] WordPress Coding Standards pass.
- [ ] PHPStan pass at agreed level.
- [ ] All strings internationalized under `upsellbay`.
- [ ] No direct order post/postmeta access.
- [ ] HPOS compatibility declared correctly.
- [ ] Cart/Checkout Blocks compatibility declared only after tests pass.
- [ ] No private Woo internals used without documented stable API approval.
- [ ] No eval, obfuscation, or remote code loading.
- [ ] Identifier constants match Section 10.5.
- [ ] Static scan finds no accidental `cartbay_`, `_cartbay_`, `cartbay-`, `CartBay`, or `WPAnchorBay\CartBay` usage outside docs/coexistence notices.

### UX

- [ ] No top-level WP admin menu.
- [ ] Admin UI uses native Woo/WP components.
- [ ] Offer widgets pass keyboard and screen reader checks.
- [ ] Mobile layouts verified on product, cart, checkout, and thank-you pages.
- [ ] Test mode visible only to admins.
- [ ] Admin copy does not describe recovery sessions, abandoned carts, recovery sequences, or CartBay-only concepts as UpsellBay features.

### Security

- [ ] All admin writes nonce/capability protected.
- [ ] All public endpoints rate-limited.
- [ ] No client-trusted price or discount values.
- [ ] License keys masked everywhere.
- [ ] Debug logs mask sensitive values.
- [ ] CartBay recovery tokens, session IDs, notification IDs, and customer recovery state are never logged or emitted by UpsellBay.

### Compatibility

- [ ] Classic checkout E2E passes.
- [ ] Block Checkout E2E passes.
- [ ] WooPayments test passes.
- [ ] Stripe test passes.
- [ ] PayPal Payments test passes.
- [ ] WooCommerce Subscriptions compatibility test passes.
- [ ] Known checkout replacement plugin notices tested.
- [ ] CartBay active/inactive coexistence tests pass.

### Documentation

- [ ] Merchant setup guide.
- [ ] First offer tutorial.
- [ ] Compatibility matrix.
- [ ] Developer hook reference.
- [ ] Import/export guide.
- [ ] Marketplace reviewer guide.
- [ ] Data retention/uninstall guide.
- [ ] CartBay vs UpsellBay support routing note.

## 18. Research Sources

### Internal Inputs

- [CartBay Complete Feature Guide](../notes/cartbay.md)
- [WordPress and WooCommerce Plugin Development Blueprint](../notes/plugin-development-blueprint.md)

### Platform and Marketplace

- [WooCommerce extension development docs](https://developer.woocommerce.com/docs/extensions/getting-started-extensions/)
- [QIT documentation](https://qit.woo.com/docs/)
- [WooCommerce HPOS extension recipe book](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/)
- [WooCommerce Additional Checkout Fields API](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/additional-checkout-fields/)
- [WooCommerce Cart and Checkout Blocks default FAQ](https://developer.woocommerce.com/2023/11/06/faq-extending-cart-and-checkout-blocks/)
- [WooCommerce 10.8 release notes](https://developer.woocommerce.com/2026/05/26/woocommerce-10-8-0-release/)
- [WordPress 7.0 documentation](https://wordpress.org/documentation/wordpress-version/version-7-0/)

### Market and Pain Point Research

- [Baymard cart abandonment statistics](https://baymard.com/blog/cart-abandonment-statistics)
- [Baymard cart and checkout usability research](https://baymard.com/research/checkout-usability)
- [CartFlows WordPress.org plugin reviews](https://wordpress.org/plugins/cartflows/)
- [Cart Abandonment Recovery support topics](https://wordpress.org/support/plugin/woo-cart-abandonment-recovery/)
- [UpsellWP template/customization complaint](https://wordpress.org/support/topic/disappointed-123/)

### Competitors

- [CartFlows pricing](https://cartflows.com/pricing/)
- [FunnelKit FAQ/pricing](https://funnelkit.com/frequently-asked-questions/)
- [FunnelKit order bump product page](https://funnelkit.com/woocommerce-order-bump/)
- [CheckoutWC pricing](https://www.checkoutwc.com/pricing/)
- [UpsellWP pricing](https://upsellwp.com/pricing/)
- [Order Bump for WooCommerce on Woo Marketplace](https://woocommerce.com/products/order-bump-for-woocommerce/)
- [WP Swings Upsell Funnel Builder](https://wpswings.com/product/upsell-order-bump-offer-for-woocommerce-pro/)
- [YITH Frequently Bought Together](https://yithemes.com/themes/plugins/yith-woocommerce-frequently-bought-together/)
- [YITH Dynamic Pricing and Discounts](https://yithemes.com/themes/plugins/yith-woocommerce-dynamic-pricing-and-discounts/)
- [Retainful pricing](https://www.retainful.com/pricing)
- [ShopMagic Abandoned Carts](https://shopmagic.app/products/shopmagic-abandoned-carts/)
- [Metorik pricing](https://metorik.com/pricing)
- [Metorik abandoned cart emails](https://metorik.com/engage/abandoned-cart-emails)
- [OptinMonster pricing](https://optinmonster.com/pricing/)
- [OptinMonster Exit Intent](https://optinmonster.com/features/exit-intent/)
- [CartPulse for WooCommerce on Woo Marketplace](https://woocommerce.com/products/cartpulse/)
- [Abandoned Cart for WooCommerce on Woo Marketplace](https://woocommerce.com/products/abandoned-cart/)

## 19. Final Product Statement

UpsellBay v1 should ship as a premium, Woo-native AOV engine that helps merchants add relevant offers across the product, cart, checkout, and thank-you journey while preserving the checkout they already trust. It must be separate from CartBay: independently installable, independently licensed, independently stored, and independently supportable. The product is commercially realistic at $79/year because the market already pays for order bumps, upsells, checkout optimization, and recovery-adjacent tools. Its defensible differentiation is not having the longest feature list; it is having the safest, most native, most measurable implementation for merchants who want higher order value without funnel-builder lock-in or abandoned-cart-recovery scope creep.
