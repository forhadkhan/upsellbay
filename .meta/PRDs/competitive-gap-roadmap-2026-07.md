# UpsellBay — Competitive Gap Roadmap (2026-07)

Status: **proposed**, produced by a PM-led expert team after a market/competitor
assessment (see the buyer-persona evaluation and 2026 competitor scan). This roadmap
addresses the gaps a prospective WooCommerce merchant would hold against UpsellBay today.

## The gaps (from market research)

1. **No true one-click post-purchase upsell** — the #1 category feature; UpsellBay's
   `thankyou_offer` re-sends the shopper through a separate checkout (re-enter payment).
2. **Free tier too thin** — 1 checkout bump vs. UpsellWP's free (bumps + one-click
   checkout upsell + cart cross-sell + frequently-bought-together).
3. **No A/B testing** — a Pro-tier expectation for AOV optimizers.
4. **No frequently-bought-together / auto-recommendations** — merchants expect some
   automatic recommendation, not only manual offers.
5. **Positioning / trust** — brand-new, no reviews, competing on features it will lose;
   must own a defensible wedge.

## Positioning decision (GTM)

Do **not** fight on feature count (UpsellWP's free already out-features us; the suites
ship 10+ campaign types). Own the validated wedge the bloated suites structurally cannot
claim: **lightweight, native, checkout-safe, Block-first, no-bloat.** The market's real
pain (merchants auditing 28→12 plugins; independent checkout hooks breaking orders) is
*caused by* the heavy funnel suites.

- **Tagline:** "Order bumps that never touch your checkout."
- **Message pillars:** additive (can't break orders) · Block-first via Store API ·
  lightweight/HPOS-safe · honest per-offer AOV attribution · reversible/staging-safe.

## Free/Pro line (repackaging to match UpsellWP's free bar at "one of each")

| Capability | Tier |
|---|---|
| 1 checkout order bump (classic + Block) | Free |
| 1 cart cross-sell | Free |
| Basic **manual** FBT (merchant-picked pairs) OR product-page auto-FBT (single placement) | Free |
| Simple product/category targeting | Free |
| Basic attribution: revenue + one AOV-lift headline | Free |
| All 4 placements + multi-bump | Pro |
| Advanced rules (role / order count / lifetime spend) | Pro |
| Full revenue dashboard + import/export + conflict detection | Pro |
| **One-click post-purchase upsell** | Pro (flagship) |
| A/B testing + automated/smart FBT | Pro |

## Pricing (GTM recommendation)

Parity single-site with UpsellWP so price is never the reason to skip us; win on the
safety narrative and multi-site value. 1 site **$69/yr**, 5 sites **$129/yr**, agency
**$199/yr** (shown first to anchor). Launch: founder 40% off + **$249 lifetime** to seed
early adopters/reviews. 30-day money-back.

## Build sequence

Strategic priority (GTM + impact) vs. what's verifiable in the current dev environment
(no tokenizing payment gateway installed — only COD):

1. **One-click post-purchase upsell (Pro flagship)** — *strategic #1.* Biggest AOV lever,
   the exact feature CartFlows/FunnelKit are known for, and — decisively — it fires
   *after* payment, so it **cannot break checkout**, reinforcing the safety wedge.
   **~70% of the plumbing already exists** (thank-you hook, order-bound HMAC token,
   `AttributionWriter::write_follow_on_order()` linkage meta, attribution, analytics,
   `DiscountCalculator`); net-new surface is essentially **one gateway charge adapter**.
   **Blocked on env:** needs a Stripe/WooPayments test-mode gateway to build+verify the
   off-session charge. Build MVP against Stripe test mode; graceful fallback to today's
   separate-checkout flow for unsupported gateways preserves "works everywhere."
   Effort: M (charge adapter) + M (3DS/refunds) + L (multi-gateway/downsell).
2. **FBT / auto-recommendations** — *first build in this env* (887 real orders to mine;
   reuses the existing renderer/attribution/analytics pipeline and the existing
   `ProductRecommendationAssistant` fallback ladder). Closes the free-tier parity gap
   (product-page FBT in free). Order-co-occurrence confidence score, precomputed index
   table + Action Scheduler mining job (HPOS-safe `wc_get_orders`), read via an indexed
   `FbtRepository`, served through a new `auto_fbt` offer mode. Effort: S (MVP) + M + L.
3. **A/B testing** — *last* (needs installed base + traffic to matter). "Offer-with-
   variants" model (no new CPT), sticky hash-mod assignment off the existing session
   token, one `variant_id` column on the stats table, results via the existing analytics.
   Effort: S (2-variant/manual winner) + M + L.

### Execution note

Strategic order is one-click → FBT → A/B. **Execution order in this environment is FBT
first** (fully buildable + verifiable here and closes a named gap), with one-click teed up
as the flagship the moment a payment test-mode gateway is available. A/B follows.

## Design references

Full technical designs (payments architect, experimentation engineer, recommendations
engineer, GTM strategist) are captured in the team review that produced this roadmap.
Key grounding files verified present: `StorefrontController.php`, `ThankYouOfferRenderer.php`,
`AttributionWriter.php`, `TokenHelper.php`, `OfferPrioritizer.php`, `AnalyticsRecorder.php`,
`StatsRepository.php`, `Installer.php`, `Scheduler.php`, `ProductRecommendationAssistant.php`.
