# Bundle Feature Discussion, Competitive Scan, and Release Readiness — 2026-07-17

Status: research/discussion record, not an ADR. Produced from (1) code-level analysis of
UpsellBay free/Pro and the third-party `woo-product-bundle` (WPC Product Bundles) plugin,
(2) an independent Opus critique of that analysis, (3) a competitive market scan, and
(4) a release-readiness audit of the Pro plugin's current working tree. See
[free-plugin-architecture.md](../architecture/free-plugin-architecture.md) and
[free-pro-coupling.md](../architecture/free-pro-coupling.md) for the underlying
architecture this builds on.

## 1. Bundle feature — revised recommendation after Opus review

Initial analysis (this session, prior to review): don't build full WPC-style catalog
bundles into Pro (too much duplicated scope, wrong focus); as a cheap win, relax the
`OfferValidator` product-type restriction (`app/Domain/Offers/OfferValidator.php:290`,
currently blocking non-simple/non-variation products for `checkout_bump`/`thankyou_offer`)
so an existing third-party bundle product could be targeted by a checkout/thank-you offer.

**Opus review overturned the second half of that.** Traced against the actually-installed
WPC plugin code, pushing a bundle product through UpsellBay's existing cart/discount
pipeline is not low-risk — it is demonstrably broken:

- `CartMutator::wc_add_to_cart` (`app/Domain/Cart/CartMutator.php:194`) successfully adds
  a bundle product and WPC's own `add_cart_item_data` hook explodes it into per-component
  child line items regardless of caller — so the add-to-cart mechanics do work.
- But `DiscountApplier` sets the discounted price on the **parent** bundle cart item at
  `woocommerce_before_calculate_totals` **priority 10** (`app/Core/Plugin.php:347`), and
  WPC runs on the **same hook at priority 9999** (`woo-product-bundle/includes/class-woosb.php:126`),
  zeroing the parent price and moving the real charge onto the child lines. UpsellBay's
  discount is silently overwritten on every recalculation — **the customer pays full
  price while UpsellBay's analytics record a successful discount and attributed revenue.**
- Order-level attribution (`Plugin.php:348`, `write_offer_line_item_attribution`) writes
  `_ub_*` meta onto the parent line, which WPC can price at 0 and hide entirely on the
  order depending on the `hide_bundled_order` setting — so even revenue attribution reads
  off a line that may not exist on the visible order.

**Conclusion: kill the "relax the validator" idea as originally framed.** The type
restriction is not an incidental limitation — it is the thing currently preventing this
corruption. If anything, the two placements that lack the guard today
(`product_upsell`, `cart_crosssell`) are a **latent bug**, not free interoperability.

**Revised pre-launch action (cheap, and now a correctness fix, not a feature)**:
extend the existing product-type guard to all four placements, not just
`checkout_bump`/`thankyou_offer`, so an offer can never target a composite/bundle-typed
product. Optionally surface a notice via the existing `OfferConflictDetector`
infrastructure ("Offer targets a product managed by another plugin; pricing/analytics may
be unreliable") rather than a hard block, reusing infrastructure that already exists.
Pair with one sentence in the compatibility docs: UpsellBay offers target individual
products; discounting/attribution on third-party composite/bundle products is not
supported. This is the entire scope of "bundle handling" that belongs in v1.

**On the bigger question — build native bundle support at all?** Confirmed no: a WPC
bundle is something a merchant *authors* into the catalog ahead of time; an UpsellBay
offer is a *runtime decision* about what to show a given shopper. Those are different
problems with different owners, and UpsellBay has no edge on the authoring half. Building
it would mean ~6,000 lines of duplicated scope (new WC product type, cart-exploding,
stock sync, admin kit-builder) competing with a mature $29-one-time incumbent, for zero
reuse of what UpsellBay already does well (rules, targeting, analytics, attribution).

**Better long-term wedge, if bundle-adjacent demand actually appears post-launch**:
"frequently bought together" — N independent single-product offers surfaced together
with one "add all" affordance. This composes primitives UpsellBay has already shipped and
debugged (single `_ub_offer_product_id`, quantity 1, native `add_to_cart`, clean per-line
attribution) instead of introducing a parallel cart-exploding pricing model that has to be
reconciled with a third party's. Gate this on real user demand; don't build speculatively.

## 2. Competitive landscape (WooCommerce order-bump / AOV plugins, July 2026)

| Plugin | Pricing | Placements | Rules engine | Analytics | Blocks support | Differentiator |
| --- | --- | --- | --- | --- | --- | --- |
| FunnelKit | $99.50–$399.50/yr, no free tier | Checkout only (bump); post-purchase is a separate product | Strong (cart/geo/date/history/role) | Views/clicks/conversions/revenue | Own custom checkout blocks, not native WC block | A/B testing (Plus+), full automation/CRM/email suite |
| CartFlows | $129–$449/yr, no free tier | Checkout bumps (Plus+); funnel-step upsell/downsell | Basic personalization | Gated to Pro ($449/yr) | Own checkout-builder blocks, not native | Split testing at Pro; large install base |
| WPFunnels | Free (limited) + ~$130–$600/yr | Checkout bumps inside its own "Store Checkout" flow | Conditional bumps via flow builder | Included in paid tiers | Replaces default checkout entirely | Newest entrant, visual funnel canvas |
| **UpsellWP** (Flycart) | Free (WP.org) + $75/$135/$295/yr | Product, cart, checkout, thank-you, email — broadest free-tier coverage | Smart targeting free; segments/history in Pro | Real-time dashboard even in free tier | **Native WC Blocks confirmed in free tier** | A/B testing on all paid tiers; closest structural twin to UpsellBay |
| Order Bump for WooCommerce (official Marketplace) | $79–$158/1-2yr, no free tier | Checkout only, multiple positions | Sophisticated AND/OR rule groups | Visits/orders/revenue/conversion, exportable | Confirmed classic + block checkout | Woo-branded trust signal; auto-suppress if already in cart |
| CommerceKit | $39–$49/yr | Checkout + mini-cart only | Minimal | Not bump-specific | Not addressed | Cheapest entry point (13 tools bundled) |
| Molongui Order Bump | Free + $69/site Pro | Checkout only | Minimal | None | Not confirmed | Generous free tier, shallow feature set |
| WowRevenue | $63–$247 (annual/lifetime) | Product, cart, checkout, thank-you | Scheduling, hide-if-in-cart | Sales/conversion/AOV dashboard | Not specified | A/B testing; lifetime-license option; BOGO/mix-and-match |

**Positioning takeaways:**

- **A/B testing is now a mainstream Pro-tier feature**, live in FunnelKit, UpsellWP,
  WowRevenue, and others. UpsellBay's free-tier marketing copy currently teases it as an
  upsell reason to go Pro, but no A/B-testing code exists anywhere in Pro (confirmed —
  grep for `a/b test|split test|ab_test` across `app/` returns nothing). This is not
  currently an overclaim in shipped copy (PRDs correctly defer it to a later version) —
  but it is a real feature gap against UpsellWP specifically, UpsellBay's closest
  structural peer (free WP.org plugin + paid Pro, order-bump-centric).
- **Native WooCommerce Blocks (Cart/Checkout block) integration is a real but narrower
  advantage than it first looks.** The two dominant funnel suites (FunnelKit, CartFlows)
  both replace the checkout with their own custom blocks rather than integrating with the
  native block — so "no checkout replacement required" is a legitimate pitch against
  them. But it is not a market-first: UpsellWP (even free) and the official Woo
  Marketplace order-bump plugin already confirm native block support today.
- **Pricing should land in the $60–$100/year band**, not the funnel-suite band
  ($99–$450+/yr). UpsellBay Pro's scope (4 placements, analytics, Blocks integration,
  import/export) maps closely onto UpsellWP and the official Woo plugin, both priced in
  that range.
- **One genuinely unclaimed differentiator**: no competitor surveyed advertises anything
  like UpsellBay's offer-conflict detector (placement-crowding/funnel-overlap warnings).
  Worth foregrounding in launch marketing — it's real and checkable, unlike A/B testing.

## 3. Release readiness — not ready to ship yet

**Release-blocking:**

1. **HEAD (last commit `768a9e4`) is fatally broken when run with its required
   dependency.** At the last commit, Pro's namespace is still `WPAnchorBay\UpsellBay\`
   (identical to free's), with identical class names (`Plugin`, `Constants`, `Container`,
   etc.) under an identical Composer PSR-4 prefix. Since Pro's own header hard-requires
   the free plugin (`Requires Plugins: ... upsellbay-order-bump-for-woocommerce`),
   activating both at HEAD collides — a PHP fatal "cannot declare class" or shadowing
   failure. **The huge uncommitted diff is not optional cleanup — it's the fix**: it
   renames Pro's namespace to `WPAnchorBay\UpsellBayPro\` throughout, which is why nearly
   every file is touched (mechanical rename, not risky logic churn). This diff must be
   committed before anything ships.
2. **The Block Checkout compatibility claim is not currently backed by evidence.**
   `docs/compatibility-matrix.md` and `readme.txt` claim Block Checkout is "Supported"
   after an E2E proof that "passed" 2026-06-19; `Plugin.php` declares
   `cart_checkout_blocks` compatibility `true`. But the last real Playwright E2E run on
   disk (`test-results/.last-run.json`, dated 2026-06-23 — *after* the claimed proof date)
   shows **24 failed tests**, and the automated check backing the "proof" is a
   string-match on the docs file, not an actual test execution. The team's own prior
   review (`.meta/reviews/next-implementation-plan-2026-06-13.md`) already recommended
   gating this claim until real proof exists; that recommendation was not acted on. Either
   produce a genuine current passing E2E run with evidence, or downgrade the claim in code
   and copy before launch — shipping a false compatibility claim on checkout (the most
   revenue-sensitive surface) is a real risk.
3. **`readme.txt` "Requires at least: 6.8" contradicts the plugin header's "6.9."**
   Trivial to fix, must be reconciled before submission.

**Should fix / verify before release:**

4. No confirmed WordPress.org Plugin Check run (blocked by sandbox permissions here —
   genuinely unknown, not a pass).
5. Re-run the Classic/Block/Storefront E2E suites for real and record results (see #2).
6. Per the project's own `.meta/tasks/index.md`: **Phase 7 QA is 1/13 complete, Phase 8
   Documentation is 0/12, Phase 9 Release Prep is 0/10.** `.meta/qa/launch-gates.md` has
   all 15 release-blocking checklist items unchecked. This is the project's own tracking
   saying it isn't done, independent of anything found above.
7. `tests/test-offer-conflict-detector.php` is dead code — a real PHPUnit test class not
   wired into `composer test` (no PHPUnit installed) and not otherwise run.
8. Delete `test-url.php` (an untracked debug script sitting in the live plugin root — not
   a packaging risk since `scripts/release.js` uses an explicit file allowlist, but bad
   hygiene and a stray directly-requestable PHP endpoint in the working install).
9. Reconcile `License: GPLv2 or later` in the plugin header vs. `"license": "proprietary"`
   in `composer.json`; no `LICENSE.txt` present.

**Confirmed solid:**

- The admin-integration refactor itself is coherent: zero dangling references to the
  deleted `AdminPage`/`Navigation`/`OfferEditPage`/`OffersPage` stack anywhere in `app/`;
  the two new `Integrations/*` classes are correctly wired into `Plugin.php`'s container
  and hook registration, and attach to hooks the free plugin genuinely fires.
- `composer test` (79/79, custom runner against a stubbed WP environment — not real
  WP/WooCommerce integration testing, worth noting as a ceiling on what this proves),
  `composer phpstan` (0 errors), `composer phpcs` (clean), `bun run build` (clean).
- License client fails open on license-server errors (matches the launch-gate
  requirement that a server outage must not disable live offers); the license server
  endpoint is live (HTTP 400 on a bare check, not unreachable).
- No A/B-testing overclaim in current shipped copy.

## Bottom line

Ship order: (1) commit the namespace-fix refactor — this is a correctness fix, not a
feature branch to sit on, (2) resolve or gate the Block Checkout claim, (3) close the
Phase 7–9 checklist gaps the project already tracks, (4) fix the two trivial version/
license inconsistencies. Bundle work for v1 is limited to tightening the existing
product-type guard to all four placements plus a one-line compatibility note — nothing
else bundle-related belongs in this release.
