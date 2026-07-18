const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { createOffer, deleteOffer } = require('./helpers/wp-cli-offers');
const conflictsMatrix = require('./fixtures/conflicts.matrix.json');

const env = getEnv();

/**
 * Which offer wins when several are eligible for the same placement
 * (priority + id-tiebreak selection) is unit-tested directly against
 * OfferPrioritizer in tests/test-core-business-logic.php — that's the
 * right layer for the actual selection math. What that unit test can't
 * prove is that the real storefront rendering path is wired to the same
 * logic end to end, which is what this spec checks: create the offers for
 * real, load a real checkout page, and confirm only the winner's headline
 * is actually in the DOM.
 */
test.describe('conflict resolution permutation tests', () => {
	test.skip(!env.ready || !env.productUrl, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, UPSELLBAY_E2E_ADMIN_PASS, and UPSELLBAY_E2E_PRODUCT_URL.');

	// Only checkout_bump cases are exercised for real here — the placement
	// is a single, checkable page (real checkout). "conflict-different-placement"
	// asserts something structurally different (two independent placements
	// never compete), which the priority/tiebreak unit test already covers
	// directly and unambiguously; re-deriving it from two page loads here
	// wouldn't add coverage, so it's intentionally skipped rather than faked.
	const renderableCases = conflictsMatrix.filter((c) => c.offers.every((o) => o.placement === 'checkout'));

	for (const caseData of renderableCases) {
		test(`Conflict Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			test.skip(!env.wpPath, 'Set UPSELLBAY_E2E_WP_PATH to create real offers for this case.');

			const winnerId = caseData.offers.reduce((a, b) => (
				a.priority !== b.priority ? (a.priority < b.priority ? a : b) : (a.id < b.id ? a : b)
			));

			const createdOfferIds = [];
			const headlineByOfferKey = new Map();

			try {
				for (const offerSpec of caseData.offers) {
					const headline = `E2E conflict case ${caseData.id} offer ${offerSpec.id}`;
					headlineByOfferKey.set(offerSpec.id, headline);
					// Offset well below any realistic real-world priority value
					// so this case's own relative ordering decides the winner
					// regardless of whatever other checkout_bump offers already
					// exist in this environment (placement_max_display is 1, so
					// the real storefront winner is contested against ALL
					// eligible offers, not just this test's two).
					const offerId = createOffer(env, {
						placement: 'checkout_bump',
						productId: 19,
						priority: offerSpec.priority - 1000,
						headline,
					});
					createdOfferIds.push(offerId);
				}

				await page.goto(env.productUrl);
				const addToCart = page.getByRole('button', { name: /add to cart/i }).first();
				await addToCart.click();
				await page.waitForTimeout(1000);

				await page.goto(env.checkoutUrl, { waitUntil: 'networkidle' });

				const winnerHeadline = headlineByOfferKey.get(winnerId.id);
				await expect(
					page.getByText(winnerHeadline),
					`the winning offer ("${winnerHeadline}") must actually render`
				).toBeVisible();

				for (const offerSpec of caseData.offers) {
					if (offerSpec.id === winnerId.id) {
						continue;
					}
					const loserHeadline = headlineByOfferKey.get(offerSpec.id);
					await expect(
						page.getByText(loserHeadline),
						`a losing offer ("${loserHeadline}") must not render alongside the winner`
					).toHaveCount(0);
				}
			} finally {
				for (const offerId of createdOfferIds) {
					deleteOffer(env, offerId);
				}
			}
		});
	}
});
