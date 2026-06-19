const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth, gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('performance and asset scoping smoke', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');

	test('admin editor loads without excessive failed requests', async ({ page }) => {
		const health = captureBrowserHealth(page);
		await loginAsAdmin(page, env);
		const started = Date.now();
		await gotoUpsellBay(page, env, 'tab=offers&action=edit');
		expect(Date.now() - started).toBeLessThan(10000);
		health.assertClean();
	});

	test('frontend assets are scoped away from unrelated pages', async ({ page }) => {
		const requests = [];
		page.on('request', (request) => requests.push(request.url()));
		await page.goto(`${env.baseUrl}/`);
		const upsellbayAssets = requests.filter((url) => /upsellbay-(storefront|offer|checkout|cart)/.test(url));
		expect(upsellbayAssets).toEqual([]);
	});
});
