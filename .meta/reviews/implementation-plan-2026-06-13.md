# UpsellBay Storefront Value & Governance — Implementation Plan

Date: 2026-06-13
Status: **COMPLETED**

## Sources

- **Root task**: `.meta/reviews/task-2026-06-13.md`
- **Gap analysis**: `.meta/reviews/product-gap-analysis-2026-06-13.md`
- **Original Codex plan**: `.meta/reviews/next-implementation-plan-2026-06-13.md`
- **PRD v4**: `.meta/PRDs/UpsellBay-PRD-v4.md`
- **WP/Woo skill**: `.agents/wordpress-woocommerce-plugin-engineer/SKILL.md`

---

## 1. Summary

This plan defines the work required to bring UpsellBay's storefront experience and offer governance to production quality. It was produced by:

1. Reviewing the Codex-produced gap analysis and implementation plan.
2. Auditing every file the plan touches against the actual codebase.
3. Running a full production-readiness review against WordPress/WooCommerce engineering standards.
4. Incorporating all P0/P1/P2 findings into the plan as explicit tasks.

The plan is organized into **7 components** executed across **6 sequential passes**. Each pass has concrete file targets, acceptance criteria, and test requirements.

### Product positioning (from gap analysis)

> UpsellBay should be the safest WooCommerce-native way to show the single most relevant add-on, upgrade, or follow-on offer at the right buying moment — without replacing checkout.

---

## 2. What the Codex Got Right

- Priority order: storefront first, then governance, then expansion.
- Block Checkout claim gating — correctly identified as stub-only.
- Offer goal / reason label schema additions.
- Warning-based conflict detection concept.
- Realistic P0–P7 priority ranking in the gap analysis.
- Correct identification that admin is relatively mature while storefront is the weak point.

## 3. What This Plan Fixes

| Codex plan issue | Fix in this plan |
|---|---|
| No file-level specificity | Every change maps to an actual file with line references |
| Passes 1 + 2 were split unnecessarily | Merged into one pass — same files, same test surface |
| No exit criteria per pass | Every pass has concrete acceptance criteria |
| Auto-resolution of conflicts | **Removed.** Warn only in v1. Merchants decide |
| No CSS mentioned | Explicit CSS tasks in Component 1 |
| Thank-you redirect UX underscoped | Full JS redirect flow + explanatory copy specified |
| Block Checkout called an "audit" | **It's a code fix** — `cart_checkout_blocks` is declared `true` and must change to `false` |
| REST routes have no arg schemas | **P0 fix added** — `args` with `sanitize_callback`/`validate_callback` for all public routes |
| `DiscountCalculator` instantiated per render | Inject via container instead |
| Settings key naming creates dual structure | Nest `max_display` inside existing `placements` structure with migration |
| `kses_post` fallback allows XSS | Change fallback to `htmlspecialchars()` |
| Product image `alt=""` is empty | Use product name as alt text |
| `upsellbay_render_offer_html` filter not fired | Explicitly fire from each renderer |
| New public methods missing `@since` | Enforced in every component spec |

---

## 4. Proposed Changes

### Component 1: Storefront Renderers

**Goal**: Make each placement render purpose-specific markup instead of generic cards.

#### [MODIFY] `app/Domain/Storefront/AbstractOfferRenderer.php`

Current state: All four renderers delegate to `render_card()` which produces identical generic card HTML for every placement. The `price_html()` method instantiates a new `DiscountCalculator()` per call. Product images have empty `alt=""`. The `kses_post()` fallback allows `<a>` tags with arbitrary attributes.

Changes:

- **Inject `DiscountCalculator`** via constructor instead of `new DiscountCalculator()` in `price_html()` (P1 fix).
- **Fix `kses_post()` fallback** (P1 fix): change `strip_tags( $value, '<a><br><em><strong>' )` to `htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' )`.
- **Fix product image alt text** (P2 fix): pass product name into `product_image_html()` and use it as `alt` attribute.
- **Add `render_dismiss_button()` protected method**: outputs a visually secondary dismiss `<button>` with `data-upsellbay-dismiss` attribute. Accessible label: `__( 'No thanks', 'upsellbay' )`.
- **Add `render_reason_label()` protected method**: reads `_ub_reason_label` from offer meta and renders `<span class="upsellbay-offer__reason">` when present.
- **Add `render_already_in_cart_notice()` protected method**: returns "Already in cart" disabled state HTML when `$context['cart_product_ids']` contains the offered product.
- **Extract `render_price_html()` to protected**: so placement renderers can position price differently.
- **Add `$placement_classes` parameter** to `render_card()` for placement-specific BEM modifiers (optional, backward-compatible).
- **Fire `upsellbay_render_offer_html` filter** (P1 fix): apply this documented public filter to the return value of `render_card()`:
  ```php
  return Hooks::filter( 'render_offer_html', $html, $offer, $placement, $context );
  ```
- **All new public/protected methods get `@since 1.0.0` PHPDoc** (P1 fix).

#### [MODIFY] `app/Domain/Storefront/ClassicCheckoutBump.php`

- Override `render_offer()` to produce compact bump-specific markup:
  - Product image (small/thumbnail), name, one-line description, savings badge, checkbox.
  - No generic card frame — tight layout optimized for review-order position.
- Add placement class `upsellbay-offer--checkout-compact`.
- **No dismiss button** — checkbox uncheck IS the dismiss action.
- Add `aria-describedby` linking checkbox to offer description for accessibility.
- Add unique description `id` for the `aria-describedby` target.

#### [MODIFY] `app/Domain/Storefront/ProductPageRenderer.php`

- Override `render_offer()` to produce "Complete this product" module:
  - Heading: "Complete this product" (filterable).
  - Show reason label or auto-generated "Works with [current product]" context.
  - CTA default text: `__( 'Add with product', 'upsellbay' )`.
  - Already-in-cart suppression state (disabled button + "Already in cart" text).
  - Dismiss button (visually secondary).

#### [MODIFY] `app/Domain/Storefront/CartCrossSellRenderer.php`

- Override `render_offer()` to produce "Still missing?" section:
  - Section wrapper with heading: `__( 'Still missing?', 'upsellbay' )`.
  - Heading renders only when ≥ 1 offer is displayed.
  - Each item: small image, name, reason label, price, "Add" button.
  - Per-offer dismiss button.

#### [MODIFY] `app/Domain/Storefront/ThankYouOfferRenderer.php`

- Override `render_offer()` to produce follow-on checkout specific markup:
  - **Critical explanatory text**: `__( 'Your original order is complete. Adding this item starts a separate checkout.', 'upsellbay' )`.
  - CTA text: `__( 'Add to a new checkout', 'upsellbay' )` — not generic "Add offer".
  - "No thanks" dismiss button.

#### [MODIFY] `app/Core/Plugin.php` lines 198–203 — renderer container registration

- Pass `DiscountCalculator` into each renderer constructor (or into `AbstractOfferRenderer` via a setter/constructor param that child classes inherit):
  ```php
  'checkout_bump'  => new ClassicCheckoutBump( $container->get( DiscountCalculator::class ) ),
  'product_upsell' => new ProductPageRenderer( $container->get( DiscountCalculator::class ) ),
  'cart_crosssell' => new CartCrossSellRenderer( $container->get( DiscountCalculator::class ) ),
  'thankyou_offer' => new ThankYouOfferRenderer( $container->get( DiscountCalculator::class ) ),
  ```

#### [MODIFY] `assets/frontend/storefront.css`

- Add placement-specific card styles:
  - `.upsellbay-offer--checkout-compact` — compact horizontal layout for checkout.
  - `.upsellbay-offer--product-addon` — inline module style for product page.
  - `.upsellbay-offer--cart-crosssell` — list-item style within section wrapper.
  - `.upsellbay-offer--thankyou-followon` — standalone card with explanatory text emphasis.
- Add dismiss button styling: secondary, text-only, right-aligned, muted color.
- Add reason label styling: small font, muted color, above CTA.
- Add already-in-cart disabled state: reduced opacity, disabled cursor.
- Add loading state: `opacity: 0.6` + CSS spinner on button via `.is-loading`.
- Add success state: green checkmark icon via `.is-success`.
- Mobile-responsive rules: no horizontal scroll, no layout shift on checkout, minimum 44px tap targets.
- **Scope all CSS under `.upsellbay-offer` parent** — never override global body, headings, links, buttons, or Woo templates.

---

### Component 2: Storefront JS Interactions

#### [MODIFY] `src/storefront/index.js`

- **Add dismiss button click handler**:
  - Listen for `click` on `[data-upsellbay-dismiss]`.
  - Call `/dismiss` REST endpoint with offer_id and placement.
  - Hide card with CSS fade transition.
  - Prevent re-render until session expires (handled server-side by `CartSession::dismiss_offer()`).
- **Add loading state management**:
  - Set `aria-busy="true"` on card.
  - Add `is-loading` class to card.
  - Disable button during fetch.
- **Add success state**:
  - After successful add: replace button text with `✓ Added` for 2 seconds, add `is-success` class.
  - Then suppress card (or leave it suppressed if already-in-cart suppression takes over on next render).
- **Add already-in-cart detection on page load**:
  - Read `config.cartProductIds` (array of product IDs from localized data).
  - If offered product ID is in that array, set disabled state on card.
- **Fix thank-you redirect** (lines 73–76):
  - Current: silent `window.location.href` redirect.
  - New: show brief `__( 'Redirecting to checkout…', 'upsellbay' )` notice, then redirect after 800ms.
- **Add focus management on card removal** (P2 fix):
  - On dismiss/success: move focus to next sibling `.upsellbay-offer`, or to section heading if no siblings remain.
  - On error: return focus to the control that triggered the action.

#### [MODIFY] `src/classic-checkout/index.js`

- **Add loading state** on checkbox toggle: disable checkbox + add `is-loading` to card during fetch.
- **Fix error notice class**: currently uses `woocommerce-error` — change to `woocommerce-notice woocommerce-notice--error` for consistent WooCommerce styling.
- **Add `aria-describedby`** for the checkout bump description element.
- **Focus management**: on error, return focus to checkbox.

---

### Component 3: Offer Eligibility Hardening

#### [MODIFY] `app/Domain/Offers/OfferPrioritizer.php`

- **Add already-in-cart suppression** to `is_eligible()`:
  - If `$context['cart_product_ids']` contains `$meta['_ub_offer_product_id']`, return `false`.
  - This is the server-side gate. JS provides the client-side visual state.
- **Add unsupported product type suppression**:
  - If product type is `bundle`, `composite`, `grouped`, `subscription`, or `variable_subscription`, return `false`.
  - Use `wc_get_product()` with `instanceof \WC_Product` check before calling `->get_type()` (P2 fix).
  - Allow an `upsellbay_allowed_product_types` filter for extensibility.

#### [MODIFY] `app/Domain/Storefront/StorefrontController.php`

- **Read `max_display` from settings** for each placement instead of hardcoded values (lines ~90, 99, 108, 121).
  - Use the new `Settings::placement_max_display( $placement )` getter.
- **Add `source_order_id` validation for thank-you**:
  ```php
  $order = wc_get_order( $source_order_id );
  if ( ! $order instanceof \WC_Order ) {
      return; // Order doesn't exist
  }
  $status = $order->get_status();
  if ( in_array( $status, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
      return; // Suppress offer for terminal orders
  }
  ```
- **Pass `cart_product_ids` into render context** so renderers and prioritizer can use it:
  ```php
  $cart_product_ids = array();
  if ( function_exists( 'WC' ) && WC()->cart ) {
      foreach ( WC()->cart->get_cart() as $item ) {
          $cart_product_ids[] = (int) ( $item['product_id'] ?? 0 );
      }
  }
  $context['cart_product_ids'] = $cart_product_ids;
  ```

---

### Component 4: REST Route Hardening (P0 Fix)

#### [MODIFY] `app/Api/Routes/PublicOfferRoutes.php`

Current state: All three routes (`/bump-toggle`, `/cart-offer-add`, `/dismiss`) register with no `args` key. Parameters are extracted via `$request->get_params()` with no sanitization. Validation is limited to `guard()` checking token and offer_id existence.

Changes — add `args` schema to each route registration:

**`/bump-toggle`**:
```php
'args' => array(
    'offer_id'  => array(
        'required'          => true,
        'type'              => 'integer',
        'minimum'           => 1,
        'sanitize_callback' => 'absint',
        'validate_callback' => static fn( $value ) => absint( $value ) > 0,
    ),
    'placement' => array(
        'required'          => false,
        'type'              => 'string',
        'default'           => 'checkout_bump',
        'sanitize_callback' => 'sanitize_key',
        'validate_callback' => static fn( $value ) => in_array(
            sanitize_key( $value ),
            array( 'checkout_bump', 'cart_crosssell', 'product_upsell', 'thankyou_offer' ),
            true
        ),
    ),
    'accepted'  => array(
        'required'          => true,
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
    ),
    'token'     => array(
        'required'          => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ),
),
```

**`/cart-offer-add`**:
```php
'args' => array(
    'offer_id'        => array(
        'required'          => true,
        'type'              => 'integer',
        'minimum'           => 1,
        'sanitize_callback' => 'absint',
        'validate_callback' => static fn( $value ) => absint( $value ) > 0,
    ),
    'placement'       => array(
        'required'          => false,
        'type'              => 'string',
        'default'           => 'cart_crosssell',
        'sanitize_callback' => 'sanitize_key',
        'validate_callback' => static fn( $value ) => in_array(
            sanitize_key( $value ),
            array( 'checkout_bump', 'cart_crosssell', 'product_upsell', 'thankyou_offer' ),
            true
        ),
    ),
    'source_order_id' => array(
        'required'          => false,
        'type'              => 'integer',
        'default'           => 0,
        'sanitize_callback' => 'absint',
    ),
    'token'           => array(
        'required'          => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ),
),
```

**`/dismiss`**:
```php
'args' => array(
    'offer_id'  => array(
        'required'          => true,
        'type'              => 'integer',
        'minimum'           => 1,
        'sanitize_callback' => 'absint',
        'validate_callback' => static fn( $value ) => absint( $value ) > 0,
    ),
    'placement' => array(
        'required'          => false,
        'type'              => 'string',
        'default'           => '',
        'sanitize_callback' => 'sanitize_key',
    ),
    'token'     => array(
        'required'          => true,
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ),
),
```

After adding `args`, the `guard()` method can be simplified — the framework now handles basic validation before the callback is invoked.

---

### Component 5: Block Checkout Compatibility Fix (P0 Fix)

#### [MODIFY] `app/Core/Plugin.php` lines 448–452

Current code declares Block Checkout compatibility as `true`:
```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
    'cart_checkout_blocks',
    Constants::plugin_file(),
    true  // ← WRONG — E2E tests have not passed
);
```

Change to `false`:
```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
    'cart_checkout_blocks',
    Constants::plugin_file(),
    false
);
```

#### [MODIFY] `tests/test-quality-assurance.php` line 80

Current test asserts `cart_checkout_blocks` string is present in the plugin file — this verifies the declaration exists but not its value. Update or add a test that verifies the value is `false` until E2E tests pass.

#### [MODIFY] `docs/compatibility.md` and any marketplace-facing copy

Change any "Block Checkout supported" language to:
> Block Checkout: Integration path implemented, full E2E verification pending. Classic checkout is fully supported.

---

### Component 6: Offer Schema Extensions

#### [MODIFY] `app/Domain/Offers/OfferSchema.php`

Add to `defaults()`:

| Key | Type | Default | Validation |
|-----|------|---------|------------|
| `_ub_offer_goal` | string | `add_on` | Allowed: `add_on`, `upgrade`, `protection`, `threshold_helper`, `follow_on` |
| `_ub_reason_label` | string | `''` | `sanitize_text_field()`, max 80 chars |
| `_ub_conflict_override` | bool | `false` | `to_bool()` normalization |
| `_ub_conflict_override_reason` | string | `''` | `sanitize_textarea_field()`, max 240 chars |

#### [MODIFY] `app/Domain/Offers/OfferValidator.php`

- Add normalization and validation for the four new meta fields.
- `_ub_offer_goal`: validate against allowed values list. If invalid, default to `add_on`.
- `_ub_reason_label`: `sanitize_text_field()`, truncate to 80 chars via `mb_substr()`.
- `_ub_conflict_override`: normalize via existing `to_bool()` pattern.
- `_ub_conflict_override_reason`: `sanitize_textarea_field()`, truncate to 240 chars.

#### [MODIFY] `app/Domain/Offers/OfferDefaults.php`

- Include the new defaults for offer goal and reason label in the defaults provider.
- Ensure `generate_defaults()` sets `_ub_offer_goal` based on placement type when auto-generating.

---

### Component 7: Settings Enhancements

#### [MODIFY] `app/Core/Settings.php`

**Settings shape change** (P1 fix): Nest `max_display` inside the existing `placements` structure instead of creating a separate `placement_max_display` key:

New `defaults()` for placements:
```php
'placements' => array(
    'product_upsell' => array( 'enabled' => true, 'max_display' => 1 ),
    'cart_crosssell' => array( 'enabled' => true, 'max_display' => 3 ),
    'checkout_bump'  => array( 'enabled' => true, 'max_display' => 1 ),
    'thankyou_offer' => array( 'enabled' => true, 'max_display' => 1 ),
),
```

**Backward-compatible `normalize()`**: The existing saved settings have `placements.checkout_bump = true` (a boolean). The normalizer must handle both formats:

```php
$placements = array();
foreach ( $defaults['placements'] as $key => $default_config ) {
    $saved = $settings['placements'][ $key ] ?? $default_config;

    // Backward compat: old format stored booleans directly.
    if ( is_bool( $saved ) || is_string( $saved ) || is_int( $saved ) ) {
        $placements[ $key ] = array(
            'enabled'     => $this->to_bool( $saved ),
            'max_display' => $default_config['max_display'],
        );
    } elseif ( is_array( $saved ) ) {
        $placements[ $key ] = array(
            'enabled'     => $this->to_bool( $saved['enabled'] ?? $default_config['enabled'] ),
            'max_display' => max( 1, min( 5, (int) ( $saved['max_display'] ?? $default_config['max_display'] ) ) ),
        );
    } else {
        $placements[ $key ] = $default_config;
    }
}
$settings['placements'] = $placements;
```

**Add public getters**:
```php
/**
 * Check whether a placement is enabled.
 *
 * @since 1.0.0
 */
public function placement_enabled( string $placement ): bool {
    $all = $this->all();
    return (bool) ( $all['placements'][ $placement ]['enabled'] ?? false );
}

/**
 * Return the max display count for a placement.
 *
 * @since 1.0.0
 */
public function placement_max_display( string $placement ): int {
    $all      = $this->all();
    $config   = $all['placements'][ $placement ] ?? array();
    $defaults = array(
        'checkout_bump'  => 1,
        'cart_crosssell' => 3,
        'product_upsell' => 1,
        'thankyou_offer' => 1,
    );
    return max( 1, (int) ( $config['max_display'] ?? $defaults[ $placement ] ?? 1 ) );
}
```

#### [MODIFY] Admin Settings page

- Update the "Placements" settings section to show both enabled toggle and max display count per placement.
- Validate bounds: checkout_bump 1–3, cart_crosssell 1–5, product_upsell 1–3, thankyou_offer 1–2.
- Update nonce and save handler to normalize the nested array structure.

---

### Component 8: Offer Conflict Health Service

#### [NEW] `app/Domain/Offers/OfferConflictDetector.php`

New service class:

```php
namespace WPAnchorBay\UpsellBay\Domain\Offers;

use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Detects configuration conflicts between offers.
 *
 * @since 1.0.0
 */
final class OfferConflictDetector {
    private OfferRepository $repository;
    /** @var callable(int): (\WC_Product|null) */
    private $product_loader;

    public function __construct( OfferRepository $repository, callable $product_loader ) { ... }

    /**
     * Detect conflicts for an offer.
     *
     * @since 1.0.0
     *
     * @param int                  $offer_id       Offer ID (0 for new unsaved offers).
     * @param array<string, mixed> $normalized_meta Normalized offer meta.
     * @param string               $status          Target status (draft, active, paused).
     * @return array<string, array{severity: string, message: string, related_offer_id: int}>
     */
    public function detect( int $offer_id, array $normalized_meta, string $status ): array { ... }
}
```

Conflict types detected:

| Key | Severity | Description |
|-----|----------|-------------|
| `same_product_same_placement` | warning | Another active offer targets the same product on the same placement with overlapping schedule |
| `duplicate_trigger` | warning | Another active offer has the same trigger + same offered product |
| `self_offer` | error | Offered product is also in the trigger product list |
| `sale_product` | warning | Offered product is currently on sale AND this offer has a discount |
| `unsupported_product_type` | error | Offered product is bundle/composite/subscription with discount |
| `pricing_conflict` | warning | Another active offer for the same product with a different discount exists |

Product type check must use `instanceof \WC_Product` before calling `->is_on_sale()` or `->get_type()` (P2 fix):

```php
$product = ( $this->product_loader )( $product_id );
if ( ! $product instanceof \WC_Product ) {
    $warnings['missing_product'] = array(
        'severity'         => 'error',
        'message'          => __( 'Offered product does not exist.', 'upsellbay' ),
        'related_offer_id' => 0,
    );
    return $warnings;
}
```

> **No auto-resolution in v1.** The Codex plan suggested "pause older overlapping offer" and "lower existing priority" as auto-resolution actions. This is dangerous — merchants should make these decisions. The conflict detector **warns only**. Override is manual and requires explicit checkbox + reason.

#### [MODIFY] `app/Core/Plugin.php` — container registration (P2 fix)

```php
$this->container->set(
    OfferConflictDetector::class,
    static fn ( Container $container ): OfferConflictDetector => new OfferConflictDetector(
        $container->get( OfferRepository::class ),
        static fn ( int $id ) => function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null
    )
);
```

#### [MODIFY] `app/Admin/Offers/OfferEditPage.php` — container injection

- Update container registration (Plugin.php line 232) to inject `OfferConflictDetector`.
- On save: call `OfferConflictDetector::detect()`.
- Render conflict warnings as dismissible WooCommerce admin notices below the editor.
- If `_ub_conflict_override` is false and there are severity=error conflicts, prevent setting status to `active`. Drafts and paused are always allowed.
- Add override UI: checkbox "I acknowledge these conflicts and want this offer active anyway" + textarea for reason.

#### [MODIFY] `app/Admin/Offers/OfferListTable.php` — health column

- Add "Health" column after "Status":
  - ✓ Eligible (green dot) — active, no conflicts, product available.
  - ⚠ Conflict (yellow dot + tooltip listing warnings).
  - ✕ Blocked (red dot) — product unavailable, placement disabled, unsupported type.
  - ⏸ Paused / 📝 Draft — neutral status indicators (no health check needed).
- Health check runs only for active offers to keep list table queries fast.

---

## 5. Execution Order

### Pass 1 — Storefront Value, Safety, and P0 Fixes

**Rationale**: Merges "truth and safety" with "checkout bump value" — they operate on the same files. Also includes both P0 fixes because they must ship before any storefront change is meaningful.

**Scope**: Components 1, 2, 3, 4, 5

**Files touched**:
- `app/Domain/Storefront/AbstractOfferRenderer.php`
- `app/Domain/Storefront/ClassicCheckoutBump.php`
- `app/Domain/Storefront/ProductPageRenderer.php`
- `app/Domain/Storefront/CartCrossSellRenderer.php`
- `app/Domain/Storefront/ThankYouOfferRenderer.php`
- `app/Domain/Offers/OfferPrioritizer.php`
- `app/Domain/Storefront/StorefrontController.php`
- `app/Api/Routes/PublicOfferRoutes.php`
- `app/Core/Plugin.php` (lines 198–203 for renderer DI, lines 448–452 for compat fix)
- `src/storefront/index.js`
- `src/classic-checkout/index.js`
- `assets/frontend/storefront.css`
- `tests/test-quality-assurance.php`
- `docs/compatibility.md`

**Exit criteria**:
- [ ] `cart_checkout_blocks` declared `false` in `Plugin.php`.
- [ ] All three public REST routes have `args` with `sanitize_callback` and `validate_callback`.
- [ ] `DiscountCalculator` injected into renderers via container, not instantiated per render.
- [ ] `kses_post()` fallback uses `htmlspecialchars()`, not `strip_tags()`.
- [ ] Product images use product name as `alt` text.
- [ ] `upsellbay_render_offer_html` filter fired from `render_card()`.
- [ ] Checkout bump renders compact, accessible markup with checkbox, price, savings, `aria-describedby`.
- [ ] Product page renders "Complete this product" module with reason label support.
- [ ] Cart renders up to N offers with "Still missing?" heading and per-offer dismiss.
- [ ] Thank-you explains follow-on checkout, has "No thanks" dismiss and redirect notice.
- [ ] Offered product in cart → card suppressed or shows "Already in cart" state.
- [ ] Unsupported product types (bundle, composite, grouped, subscription) → not rendered.
- [ ] Dismiss click → REST call → card hidden → session-persisted via `CartSession`.
- [ ] Loading/success/error states visible on all CTA interactions.
- [ ] Focus moves to next card on dismiss/success; returns to trigger on error.
- [ ] Mobile: no horizontal scroll, no layout shift on checkout, 44px minimum tap targets.
- [ ] `composer phpcs` passes on changed files.
- [ ] `bun run build` succeeds.

---

### Pass 2 — Offer Schema + Settings

**Scope**: Components 6, 7

**Files touched**:
- `app/Domain/Offers/OfferSchema.php`
- `app/Domain/Offers/OfferValidator.php`
- `app/Domain/Offers/OfferDefaults.php`
- `app/Core/Settings.php`
- Admin settings page template/handler
- Offer editor template (goal selector + reason label fields)

**Exit criteria**:
- [ ] `_ub_offer_goal`, `_ub_reason_label`, `_ub_conflict_override`, `_ub_conflict_override_reason` saved and loaded correctly.
- [ ] Settings `placements` structure nests `enabled` + `max_display` per placement.
- [ ] Old boolean-format `placements` values normalize correctly (backward compat).
- [ ] `StorefrontController` reads `max_display` from `Settings::placement_max_display()`.
- [ ] Existing offers load with backward-compatible defaults (no migration required).
- [ ] Offer editor shows goal selector dropdown and reason label text field.
- [ ] Settings → Placements section shows enabled toggle + max display count per placement.
- [ ] `composer phpcs` and `composer phpstan` pass.
- [ ] `composer test` passes (existing tests + new settings normalization tests).

---

### Pass 3 — Offer Governance

**Scope**: Component 8

**Files touched**:
- `app/Domain/Offers/OfferConflictDetector.php` (NEW)
- `app/Core/Plugin.php` (container registration)
- `app/Admin/Offers/OfferEditPage.php` (inject detector, save handler, override UI)
- `app/Admin/Offers/OfferListTable.php` (health column)
- `tests/` (unit tests for conflict detector)

**Exit criteria**:
- [ ] Save an active offer with same product+placement as existing active offer → warning notice shown.
- [ ] Save a draft offer with conflicts → no block, warnings are informational only.
- [ ] Check override checkbox + enter reason → offer activates despite conflicts.
- [ ] Self-offer (offered product = trigger product) → severity=error → blocked unless override.
- [ ] Unsupported product type with discount → severity=error → blocked unless override.
- [ ] Offers list shows health column with correct status for all offers.
- [ ] Health check queries only active offers (no performance impact on list for large offer counts).
- [ ] Unit tests cover: overlapping active, different-placement no-conflict, draft bypass, paused bypass, schedule window overlap, self-offer, sale product with discount, unsupported type, override flag.
- [ ] `composer test` passes.

---

### Pass 4 — Recommendation Workflow Integration

**Scope**: Integrate existing `ProductRecommendationAssistant` into the offer editor.

**Files touched**:
- Offer editor admin template
- `app/Domain/Offers/ProductRecommendationAssistant.php` (minor enhancements)
- Admin JS for suggestion display

**Exit criteria**:
- [ ] Offer editor shows "Suggested products" panel when trigger product/category is set.
- [ ] Suggestions sourced from WooCommerce upsells/cross-sells, same category, low-priced accessories.
- [ ] Clicking a suggestion populates offer product, suggests a reason label, and sets default goal.
- [ ] Panel is non-blocking — merchant can ignore suggestions.
- [ ] Reason label auto-generation follows option (c): auto-generated default from goal/trigger, merchant can override.

---

### Pass 5 — Thank-You Follow-On Clarity

**Scope**: Deep thank-you UX pass (building on Pass 1 renderer changes).

**Files touched**:
- `src/storefront/index.js` (thank-you redirect flow refinement)
- `app/Domain/Storefront/ThankYouOfferRenderer.php` (already modified in Pass 1, further refinement)
- `app/Api/Routes/PublicOfferRoutes.php` (ensure `source_order_id` flows through to cart item data)

**Exit criteria**:
- [ ] Thank-you offer click → brief "Adding to new checkout…" notice → redirect after 800ms.
- [ ] Source order ID passed through cart item data and written to follow-on order attribution.
- [ ] Source order that is refunded/cancelled → offer suppressed (via `StorefrontController` validation from Pass 1).
- [ ] "No thanks" dismiss persisted per session.
- [ ] Original primary order is never mutated by the follow-on checkout flow.

---

### Pass 6 — QA, Tests, and Docs

**Scope**: Focused testing and documentation for all new behavior.

**Files touched**:
- `tests/` (new and updated tests)
- `docs/` (merchant docs, compatibility matrix, developer hook reference)
- `.meta/architecture/` (architecture notes for conflict detector, schema extensions)

**Exit criteria**:
- [ ] Unit tests for: conflict detector, eligibility suppression, schema validation, settings normalization.
- [ ] Integration tests for: offer editor save warnings, cart mutation, attribution write.
- [ ] E2E smoke tests for: product/cart/classic-checkout/thank-you placements.
- [ ] Accessibility audit: keyboard navigation, screen reader testing on all placements.
- [ ] Mobile visual QA: Storefront, Astra, and Kadence themes.
- [ ] Updated merchant docs: placement descriptions, settings reference.
- [ ] Updated compatibility matrix: Block Checkout status, conflict plugin list.
- [ ] Updated developer docs: `upsellbay_render_offer_html` filter, new schema fields, conflict detector hooks.
- [ ] Architecture notes: conflict detector service, schema extensions.
- [ ] All validation commands pass:
  ```bash
  composer phpcs
  composer phpstan
  composer test
  bun run build
  bun run i18n:make-pot
  composer plugin-check
  ```

---

## 6. Deferred to Post-v1

| Feature | Reason |
|---------|--------|
| Mini-cart/side-cart offers | Theme/plugin fragmentation makes this high-risk for v1. Needs per-plugin compatibility research |
| Account/order-details placement | Requires new offer type constant, schema migration, new REST surface. Good P1 feature |
| A/B testing | Requires variant management infrastructure. Good P1 |
| Auto-resolution of conflicts | Dangerous UX — merchants should decide. Warn only in v1 |
| Block Checkout real support | Stub exists. Needs dedicated Blocks extension point implementation + E2E. Separate workstream |
| Threshold-helper offer type | Requires free-shipping settings integration and cart total monitoring. P1 candidate |
| `archived` offer status | Nice-to-have. `paused` serves the purpose for v1 |
| Template file overridability | v1 uses filter-based customization via `upsellbay_render_offer_html`. Template file restructuring is P1 |

---

## 7. Test Plan

### Unit Tests

| Test target | Cases |
|---|---|
| `OfferConflictDetector` | Same product + same placement overlap; different placement no conflict; draft bypass; paused bypass; self-offer; sale product + discount; unsupported product type; override flag; missing product |
| `OfferPrioritizer` already-in-cart | Cart contains offered product → filtered out; cart doesn't contain → passes through |
| `OfferPrioritizer` unsupported type | Bundle, composite, subscription → filtered; simple, variable → pass |
| `OfferValidator` new fields | Goal validation with allowed values; reason label sanitization + truncation; override boolean normalization; override reason sanitization |
| `Settings` normalization | Old boolean placements → new array format; new array format round-trips; missing placement defaults; max_display bounds enforced |
| `Settings` getters | `placement_enabled()` returns correct value; `placement_max_display()` returns configured value, falls back to default |

### Integration Tests

| Test target | Cases |
|---|---|
| Offer save with conflicts | Active conflicting offer → warning rendered; draft saves clean; override allows active; self-offer without override → blocked |
| REST route validation | Missing offer_id → 400; invalid placement enum → 400; missing token → 403; valid params → reaches callback |
| Cart mutation | Server-calculated prices used; client price ignored; HPOS-safe attribution via order CRUD |
| Thank-you follow-on | Source order ID passed through; original order not mutated; refunded source → suppressed |

### Frontend/E2E Scenarios

- Product page: render → dismiss → dismissed in session → reload → not shown.
- Cart: render up to 3 → add one → refresh → added item not re-offered.
- Classic checkout: accept bump → totals update → place order → attribution in order meta.
- Thank-you: click → notice → redirect → new checkout with source attribution.
- Mobile: all placements render without horizontal scroll or checkout shift.
- Accessibility: tab through all offer controls; screen reader announces offer name, price, and action.

---

## 8. Resolved Design Decisions

| Question | Decision | Rationale |
|----------|----------|-----------|
| **Reason label UX** | Option (c): auto-generated default that merchant can override | Lowest friction for setup. Auto-generate from goal + trigger product. Full control when needed |
| **Conflict severity escalation** | Option (b) for `self_offer` and `unsupported_product_type` (blocked unless override). Option (c) for `same_product_same_placement` and `pricing_conflict` (warning only, always saveable) | Merchants know their intent for overlapping offers. Self-offers and unsupported types are almost always mistakes |
| **Placement max display in Settings UI** | Option (b): part of each placement's toggle row (enabled/disabled + max display) | Keeps UI compact and contextual |
| **Template overridability** | Filter-based via `upsellbay_render_offer_html` for v1. Template file restructuring deferred to P1 | Simpler for v1. Avoids WooCommerce template override complexity before storefront markup stabilizes |
| **Settings migration strategy** | Normalize-on-read in `Settings::normalize()`. No one-time migration step | Old boolean values are auto-promoted to new array format on every read. Zero-downtime. Existing settings never corrupted |

---

## 9. Risk Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| Block Checkout `true` declaration in production | **Critical** | P0 fix in Pass 1 — change to `false` before any other work |
| Unsanitized REST params | **Critical** | P0 fix in Pass 1 — add `args` schemas |
| Settings shape migration | **Medium** | Backward-compat `normalize()` handles both old and new formats |
| Accessibility regressions from new markup | **Medium** | Manual audit in Pass 1 exit criteria; keyboard + screen reader testing in Pass 6 |
| Third-party filter breakage | **Low** | Preserve `upsellbay_render_offer_html` filter contract |
| Performance on list table health column | **Low** | Health checks only for active offers; uses existing `OfferRepository` queries |

**Overall release risk**: **Medium** — architecturally sound plan. The two P0 items are fixed in Pass 1 before any storefront changes are visible. With all passes complete, the plan is production-ready for Woo Marketplace submission.

---

## 10. Assumptions

- Phases 0–6.5 from `.meta/tasks/index.md` are complete and stable. This work builds on existing architecture.
- Block Checkout remains claim-gated (`false`). No real Block Checkout integration in this wave.
- No new REST routes required — existing `/cart-offer-add`, `/dismiss`, and `/bump-toggle` endpoints are sufficient with the arg schema additions.
- No new CPT or custom table — all new data fits in existing offer meta and the `upsellbay_settings` option.
- Runtime changes go on the `main` branch. `.meta` updates follow the orphan overlay workflow on `config-assets`.
- CartBay separation rules remain fully enforced — no recovery, email, or CartBay state coupling introduced by any component.
