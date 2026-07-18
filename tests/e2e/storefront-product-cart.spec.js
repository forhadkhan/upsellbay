const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth } = require('./helpers/wp-admin');
const contextMatrix = require('./fixtures/storefront-context.matrix.json');

const env = getEnv();

// Cases that need real precondition state this suite doesn't yet set up:
// "dismissed" and "accepted" require actually dismissing/accepting an offer
// first (not just navigating to the page), and the thank-you cases need a
// real completed order rather than a hardcoded, almost certainly
// nonexistent order id (123). Asserting "not visible" against a page that
// was never put in the claimed state doesn't test what these cases claim to
// test — it would pass whether or not dismissal/acceptance/thank-you
// rendering actually works. Skip them explicitly rather than leave a
// costume assertion; see order-completion.spec.js for real order-based
// coverage of the accept path.
const NOT_YET_IMPLEMENTED = new Set([
	'context-cart-dismissed',
	'context-checkout-accepted',
	'context-thankyou-guest',
	'context-thankyou-invalid-order',
]);

test.describe('storefront context permutation tests', () => {
	test.skip(!env.baseUrl || !env.productUrl, 'Set UPSELLBAY_E2E_BASE_URL and UPSELLBAY_E2E_PRODUCT_URL.');

	for (const caseData of contextMatrix) {
		test(`Storefront Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			test.skip(
				NOT_YET_IMPLEMENTED.has(caseData.id),
				'requires real precondition state (dismissal, acceptance, or a real completed order) this suite does not yet establish — see the comment above'
			);

			const health = captureBrowserHealth(page);

			let url = env.productUrl;
			if (caseData.placement === 'cart') url = env.cartUrl || env.baseUrl + '/cart';
			if (caseData.placement === 'checkout') url = env.checkoutUrl || env.baseUrl + '/checkout';

			if (caseData.viewport === 'mobile') {
				await page.setViewportSize({ width: 375, height: 667 });
			} else {
				await page.setViewportSize({ width: 1280, height: 800 });
			}

			await page.goto(url);

			const offer = page.locator('.upsellbay-offer').first();

			if (caseData.expectedRender) {
				await expect(offer, `a ${caseData.placement} offer must be present for case ${caseData.id}`).toBeVisible();
			} else {
				await expect(offer, `no offer should render for case ${caseData.id}`).toHaveCount(0);
			}

			health.assertClean();
		});
	}
});
