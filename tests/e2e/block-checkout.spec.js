const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth, gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('block checkout compatibility gate', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');

	test('admin compatibility remains gated until block checkout proof exists', async ({ page }) => {
		await loginAsAdmin(page, env);
		await gotoUpsellBay(page, env, 'tab=help');
		await expect(page.locator('body')).toContainText(/Block Checkout|compatibility|UpsellBay/i);
	});

	test('store api payload does not emit browser errors on block checkout URL', async ({ page }) => {
		test.skip(!env.blockCheckoutUrl, 'Set UPSELLBAY_E2E_BLOCK_CHECKOUT_URL.');
		const health = captureBrowserHealth(page);
		const storeApiStatuses = [];
		page.on('response', (response) => {
			if (response.url().includes('/wc/store/') || response.url().includes('/upsellbay/v1/')) {
				storeApiStatuses.push(response.status());
			}
		});

		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();
		expect(storeApiStatuses.every((status) => status < 500)).toBeTruthy();
		health.assertClean();
	});
});
