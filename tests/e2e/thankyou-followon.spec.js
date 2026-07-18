const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('thank-you follow-on offers', () => {
	test.skip(!env.thankyouUrl, 'Set UPSELLBAY_E2E_THANKYOU_URL for a valid test order.');

	test('follow-on offer starts a separate checkout', async ({ page }) => {
		const health = captureBrowserHealth(page);
		await page.goto(env.thankyouUrl);
		const offer = page.locator('.upsellbay-offer').first();
		// UPSELLBAY_E2E_THANKYOU_URL is required to be a valid order's
		// thank-you page with an eligible thankyou_offer configured — if
		// that precondition is met but no card renders, that's a real
		// regression to fail on, not something to pass through silently.
		await expect(offer, 'a thank-you follow-on offer must be present').toBeVisible();
		await offer.getByRole('link', { name: /add|buy|continue|checkout/i }).first().click();
		await expect(page).toHaveURL(/checkout|cart|add-to-cart/);
		health.assertClean();
	});
});
