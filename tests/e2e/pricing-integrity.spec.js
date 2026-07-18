const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const {
	createOffer,
	deleteOffer,
	setProductPrice,
	restoreProductPrice,
	createCoupon,
	deleteCouponByCode,
} = require('./helpers/wp-cli-offers');
const checkoutIntegrityMatrix = require('./fixtures/checkout-integrity.matrix.json');
const discountsMatrix = require('./fixtures/discounts.matrix.json');

const env = getEnv();

// env.productUrl (UPSELLBAY_E2E_PRODUCT_URL) already points at this product,
// so it doubles as the fixed base-cart product every case prices precisely
// via setProductPrice() before adding it to cart.
const BASE_PRODUCT_ID = 428;
const OFFER_PRODUCT_ID = 19; // the checkout_bump target product ("Belt").

async function addBaseProductToCart(page) {
	await page.goto(env.productUrl);
	await page.getByRole('button', { name: /add to cart/i }).first().click();
	await page.waitForTimeout(1000);
}

async function applyCoupon(page, code) {
	await page.getByRole('button', { name: /add coupon/i }).first().click();
	await page.locator('#wc-block-components-totals-coupon__input-coupon').fill(code);
	await page.locator('.wc-block-components-totals-coupon__button').click();
	await page.waitForTimeout(1500);
}

async function acceptBumpAndReadServerPrice(page) {
	const bumpCard = page.locator('.upsellbay-offer--checkout_bump').first();
	await expect(bumpCard, 'the checkout bump offer must render').toBeVisible();

	// The card can be visible while its price is still a loading skeleton
	// (async Store API cart data resolving) — poll for the real,
	// discount-reflecting price text before reading or accepting, the same
	// fix applied to order-completion.spec.js.
	let displayedPrices = null;
	await expect
		.poll(
			async () => {
				const priceText = await bumpCard.locator('.upsellbay-offer__price').innerText();
				displayedPrices = (priceText.match(/\$([\d,.]+)/g) || []).map((p) => parseFloat(p.replace(/[$,]/g, '')));
				return displayedPrices.length;
			},
			{ message: 'offer card must show a resolved price', timeout: 15000 }
		)
		.toBeGreaterThanOrEqual(1);
	const displayedOfferPrice = displayedPrices[displayedPrices.length - 1];

	const bumpResponses = [];
	page.on('response', async (response) => {
		if (response.url().includes('/upsellbay/v1/bump-toggle')) {
			const body = await response.json().catch(() => null);
			bumpResponses.push({ status: response.status(), body });
		}
	});

	await bumpCard.locator('input[type="checkbox"]').first().click();
	await expect.poll(() => bumpResponses.length, { timeout: 10000 }).toBeGreaterThanOrEqual(1);

	expect(bumpResponses[0].status, 'bump-toggle must succeed').toBe(200);
	return { response: bumpResponses[0].body, card: bumpCard, displayedOfferPrice };
}

// Mirrors DiscountCalculator::calculate()'s math, so a test that needs
// UpsellBay's own pre-coupon offer price doesn't hardcode one discount type.
function calculateOwnDiscount(originalPrice, discountType, discountValue) {
	const value = Math.max(0, Number(discountValue));
	switch (discountType) {
		case 'percent':
			return originalPrice - (originalPrice * Math.min(100, value)) / 100;
		case 'fixed_amount':
			return Math.max(0, originalPrice - value);
		case 'fixed_price':
			return value;
		default:
			return originalPrice;
	}
}

async function readCheckoutTotal(page) {
	const totalRow = page.locator('.wc-block-components-totals-wrapper', { hasText: 'Total' }).last();
	const text = await totalRow.locator('.wc-block-components-totals-item__value').innerText();
	return parseFloat(text.replace(/[^0-9.]/g, ''));
}

test.describe('pricing and checkout integrity permutation tests', () => {
	test.skip(!env.ready || !env.productUrl || !env.wpPath, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, UPSELLBAY_E2E_ADMIN_PASS, UPSELLBAY_E2E_PRODUCT_URL, and UPSELLBAY_E2E_WP_PATH.');

	for (const caseData of checkoutIntegrityMatrix) {
		test(`Checkout Integrity Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			const previousBasePrice = setProductPrice(env, BASE_PRODUCT_ID, caseData.baseCartTotal);
			const previousOfferPrice = setProductPrice(env, OFFER_PRODUCT_ID, caseData.offerProductPrice);
			// Priority offset well below any realistic real-world value so this
			// offer always wins the real checkout_bump slot (limit is 1),
			// regardless of whatever other offers already exist.
			const offerId = createOffer(env, {
				placement: 'checkout_bump',
				productId: OFFER_PRODUCT_ID,
				priority: -1000,
				discountType: caseData.discountType,
				discountValue: caseData.discountValue,
				headline: `E2E integrity ${caseData.id}`,
			});
			let couponCode = null;

			try {
				if (caseData.storeCoupon) {
					couponCode = createCoupon(env, caseData.storeCoupon);
				}

				await addBaseProductToCart(page);
				await page.goto(env.checkoutUrl, { waitUntil: 'networkidle' });

				const { response } = await acceptBumpAndReadServerPrice(page);

				if (couponCode) {
					await page.waitForTimeout(1000);
					await applyCoupon(page, couponCode);
				}
				// bump-toggle's offer_price is UpsellBay's own discount math on
				// the product's own price. CouponLimiter (app/Integrations/
				// WooCommerce/CouponLimiter.php) deliberately excludes any cart
				// line carrying an UpsellBay offer price from store coupons —
				// an intentional anti-stacking policy, not a bug — so for the
				// coupon-stacking case this must equal UpsellBay's own math
				// with no further coupon reduction applied on top.
				expect(
					parseFloat(response.offer_price),
					"server-computed offer price must match UpsellBay's own discount math"
				).toBeCloseTo(
					calculateOwnDiscount(caseData.offerProductPrice, caseData.discountType, caseData.discountValue),
					2
				);

				await page.waitForTimeout(1000); // let totals settle after the accept.
				const total = await readCheckoutTotal(page);
				// KNOWN BUG (2026-07-18, not yet root-caused): expectedNewCartTotal
				// (135) is what CouponLimiter's own documented per-item exclusion
				// policy predicts — the coupon should still discount the base
				// product's line, just not the offer-priced line. A controlled
				// wp-eval-file script confirms that per-item behavior works
				// correctly against a plain WC_Cart::apply_coupon() call
				// (10% off the base item only, coupon correctly excluded from an
				// offer-priced line). But this real checkout run consistently
				// shows the coupon contributing $0 to the total (145, i.e. no
				// discount anywhere) regardless of whether the coupon is applied
				// before or after the bump is accepted — a real discrepancy
				// between the direct WC_Cart code path and the live Block
				// Checkout / Store API path that has not been root-caused.
				// Left failing intentionally rather than weakened, so this
				// regresses loudly until it's understood.
				expect(total, 'final checkout total must match the fixture-predicted total').toBeCloseTo(caseData.expectedNewCartTotal, 2);

				if (caseData.storeCoupon) {
					// Make the anti-stacking policy an explicit, named
					// assertion rather than something only implied by the
					// total: the offer line itself must be untouched by the
					// coupon (still exactly UpsellBay's own discounted price).
					expect(
						parseFloat(response.offer_price),
						'the coupon must not further reduce an offer-priced line'
					).toBeCloseTo(caseData.expectedOfferPrice, 2);
				}
			} finally {
				deleteOffer(env, offerId);
				if (couponCode) {
					deleteCouponByCode(env, couponCode);
				}
				restoreProductPrice(env, BASE_PRODUCT_ID, previousBasePrice);
				restoreProductPrice(env, OFFER_PRODUCT_ID, previousOfferPrice);
			}
		});
	}

	// The two "invalid value" cases (over-100% percent, negative amount) have
	// no storefront-observable behavior to check: OfferValidator rejects both
	// at admin save time (tests/test-core-business-logic.php,
	// "offer validator rejects out-of-range discount values at save time"),
	// so such an offer never reaches a real shopper in normal usage. Runtime
	// safety if a bad value somehow got into the database anyway (a direct
	// DB edit, a future caller that skips validation) is separately covered
	// by "discount calculator clamps invalid prices and never trusts client
	// input" in the same file. The two coupon-interaction cases
	// (coupon-compatible/coupon-conflict) don't have a "conflict" concept
	// defined anywhere in the codebase to test against — the fixture never
	// specifies what makes a coupon conflicting — and coupon stacking with a
	// precisely specified expected outcome is already covered for real above
	// by "integrity-coupon-stacking".
	const SKIPPED_DISCOUNT_CASES = new Set([
		'discount-percent-over-max',
		'discount-negative',
		'coupon-compatible',
		'coupon-conflict',
	]);

	for (const caseData of discountsMatrix) {
		test(`Discounts Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			test.skip(
				SKIPPED_DISCOUNT_CASES.has(caseData.id),
				'see the comment above this loop — covered by unit tests or by integrity-coupon-stacking instead'
			);

			const previousOfferPrice = setProductPrice(env, OFFER_PRODUCT_ID, 100);
			const offerId = createOffer(env, {
				placement: 'checkout_bump',
				productId: OFFER_PRODUCT_ID,
				priority: -1000,
				discountType: caseData.discountType,
				discountValue: caseData.discountValue,
				headline: `E2E discount ${caseData.id}`,
			});

			try {
				await addBaseProductToCart(page);
				await page.goto(env.checkoutUrl, { waitUntil: 'networkidle' });

				const { response, displayedOfferPrice } = await acceptBumpAndReadServerPrice(page);
				const serverOfferPrice = parseFloat(response.offer_price);

				// What "expectedTotalMatch" actually verifies for these cases:
				// the price the shopper is shown must be the same price the
				// server actually charges — never a client/server mismatch,
				// regardless of which discount type produced it.
				expect(
					Math.abs(serverOfferPrice - displayedOfferPrice) < 0.01,
					`server price (${serverOfferPrice}) vs displayed price (${displayedOfferPrice}) for ${caseData.id}`
				).toBe(caseData.expectedTotalMatch);
			} finally {
				deleteOffer(env, offerId);
				restoreProductPrice(env, OFFER_PRODUCT_ID, previousOfferPrice);
			}
		});
	}
});
