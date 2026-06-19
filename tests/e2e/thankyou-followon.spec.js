const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('thank-you follow-on offers', () => {
	test.skip(!env.thankyouUrl, 'Set UPSELLBAY_E2E_THANKYOU_URL for a valid test order.');

	test('follow-on offer starts a separate checkout when available', async ({ page }) => {
		const health = captureBrowserHealth(page);
		await page.goto(env.thankyouUrl);
		const offer = page.locator('.upsellbay-offer').first();
		if (await offer.count()) {
			await offer.getByRole('link', { name: /add|buy|continue|checkout/i }).first().click();
			await expect(page).toHaveURL(/checkout|cart|add-to-cart/);
		}
		health.assertClean();
	});
});
