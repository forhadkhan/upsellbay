const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('classic checkout bump', () => {
	test.skip(!env.baseUrl, 'Set UPSELLBAY_E2E_BASE_URL.');

	test('checkout bump toggle updates through server-owned REST flow when present', async ({ page }) => {
		const health = captureBrowserHealth(page);
		const upsellbayResponses = [];
		page.on('response', async (response) => {
			if (response.url().includes('/upsellbay/v1/')) {
				upsellbayResponses.push(response.status());
			}
		});

		await page.goto(env.checkoutUrl);
		const bump = page.locator('.upsellbay-offer input[type="checkbox"]').first();
		if (await bump.count()) {
			await bump.check();
			await expect.poll(() => upsellbayResponses.some((status) => status >= 200 && status < 300)).toBeTruthy();
			await expect(page.locator('.woocommerce-checkout-review-order-table, .wc-block-components-totals-wrapper, body')).toBeVisible();
		}
		health.assertClean();
	});
});
