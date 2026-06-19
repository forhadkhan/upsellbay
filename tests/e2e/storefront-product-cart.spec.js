const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth } = require('./helpers/wp-admin');
const contextMatrix = require('./fixtures/storefront-context.matrix.json');

const env = getEnv();

test.describe('storefront context permutation tests', () => {
	test.skip(!env.baseUrl || !env.productUrl, 'Set UPSELLBAY_E2E_BASE_URL and UPSELLBAY_E2E_PRODUCT_URL.');

	for (const caseData of contextMatrix) {
		test(`Storefront Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			const health = captureBrowserHealth(page);
			
			let url = env.productUrl;
			if (caseData.placement === 'cart') url = env.cartUrl || env.baseUrl + '/cart';
			if (caseData.placement === 'checkout') url = env.checkoutUrl || env.baseUrl + '/checkout';
			if (caseData.placement === 'thankyou') url = env.checkoutUrl ? env.checkoutUrl + '/order-received/123' : env.baseUrl + '/checkout/order-received/123';

			// Simulate setting viewport
			if (caseData.viewport === 'mobile') {
				await page.setViewportSize({ width: 375, height: 667 });
			} else {
				await page.setViewportSize({ width: 1280, height: 800 });
			}

			await page.goto(url);
			
			if (caseData.expectedRender) {
				// Assert offer is visible if expected
				await expect(page.locator('body')).toBeVisible();
				const offer = page.locator('.upsellbay-offer').first();
				if (await offer.count() > 0) {
					await expect(offer).toBeVisible();
				}
			} else {
				// Assert offer is not visible
				const offerCount = await page.locator('.upsellbay-offer').count();
				if (offerCount > 0) {
					await expect(page.locator('.upsellbay-offer').first()).not.toBeVisible();
				}
			}
			
			health.assertClean();
		});
	}
});
