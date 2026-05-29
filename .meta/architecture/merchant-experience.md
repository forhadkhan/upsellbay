# Merchant Experience

Status: accepted for Phase 5.

Task: `UB-P5-001` through `UB-P5-009`.

## Decision

Phase 5 keeps merchant onboarding inside WooCommerce-native admin surfaces. The wizard creates a draft offer only, enables test mode when requested, and records onboarding completion in `upsellbay_settings`; it never creates a live shopper-facing offer automatically.

## Runtime Shape

- `Admin\Wizard\WizardController` owns first-run wizard submission and rendering.
- `Domain\Offers\OfferDefaults` provides draft defaults for new offer forms and wizard steps.
- `Admin\PreviewLinks` builds admin-only preview URLs for product, cart, checkout, and thank-you contexts.
- `Domain\Offers\ProductRecommendationAssistant` ranks optional local product suggestions from Woo upsells, cross-sells, same-category products, and existing aggregate offer signals. It does not call external AI or SaaS APIs.
- Existing admin pages expose empty-state payloads so templates can render native list-table/settings-page guidance without marketing-heavy panels.

## UX Boundaries

- Offer editor configuration is grouped into basics, targeting, discount, placement, schedule, and advanced metadata.
- Risky settings use concise help-tip copy instead of long instructional paragraphs.
- Copy uses AOV offer language and avoids recovery, abandoned-cart, funnel-builder, CartBay upgrade, and unsupported lift claims.
- Preview links explain missing context instead of guessing unsafe storefront URLs.
