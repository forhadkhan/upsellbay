const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { getEnv } = require('./helpers/env');
const { gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');

const env = getEnv();

async function expectNoSeriousAxeViolations(page) {
	const results = await new AxeBuilder({ page }).analyze();
	const impactfulViolations = results.violations.filter(({ impact }) => ['critical', 'serious'].includes(impact));

	expect(impactfulViolations, 'axe critical/serious violations').toEqual([]);
}

test.describe('accessibility smoke', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');

	test('admin editor has keyboard focus targets and no serious axe violations', async ({ page }) => {
		await loginAsAdmin(page, env);
		await gotoUpsellBay(page, env, 'tab=offers&action=edit');
		await page.keyboard.press('Tab');
		await expect(page.locator(':focus')).toBeVisible();
		await expectNoSeriousAxeViolations(page);
	});

	test('storefront offer card is reachable when present', async ({ page }) => {
		test.skip(!env.productUrl, 'Set UPSELLBAY_E2E_PRODUCT_URL.');
		await page.goto(env.productUrl);
		await page.keyboard.press('Tab');
		await expect(page.locator(':focus, body')).toBeVisible();
		await expectNoSeriousAxeViolations(page);
	});
});
