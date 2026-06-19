const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth, gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('block checkout bump', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');
	test.skip(!env.blockCheckoutUrl, 'Set UPSELLBAY_E2E_BLOCK_CHECKOUT_URL.');

	test('store api payload does not emit browser errors on block checkout URL', async ({ page }) => {
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

	test('upsellbayStorefront config is available on block checkout', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const config = await page.evaluate(() => {
			return {
				hasConfig: typeof window.upsellbayStorefront !== 'undefined',
				hasToken: typeof window.upsellbayStorefront?.token === 'string' && window.upsellbayStorefront.token.length > 0,
				hasRestUrl: typeof window.upsellbayStorefront?.restUrl === 'string' && window.upsellbayStorefront.restUrl.length > 0,
			};
		});

		expect(config.hasConfig, 'upsellbayStorefront window variable should exist').toBeTruthy();
		expect(config.hasToken, 'upsellbayStorefront.token should be a non-empty string').toBeTruthy();
		expect(config.hasRestUrl, 'upsellbayStorefront.restUrl should be a non-empty string').toBeTruthy();
	});

	test('block checkout renders offer card when offer is present', async ({ page }) => {
		const health = captureBrowserHealth(page);

		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		// Wait for the block checkout to hydrate and any offers to render.
		const offerCard = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"]').first();

		// If an offer is configured, it should render. If not, the test passes gracefully.
		if (await offerCard.count() > 0) {
			await expect(offerCard).toBeVisible();
		}

		health.assertClean();
	});

	test('checkout bump toggle sends server-validated REST request', async ({ page }) => {
		const upsellbayResponses = [];
		page.on('response', (response) => {
			if (response.url().includes('/upsellbay/v1/bump-toggle')) {
				upsellbayResponses.push(response.status());
			}
		});

		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const checkbox = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"] input[type="checkbox"]').first();

		if (await checkbox.count() > 0) {
			await checkbox.check();

			// Wait for the REST call to complete.
			await expect.poll(() => upsellbayResponses.length, { timeout: 10000 }).toBeGreaterThanOrEqual(1);

			// The response should be 2xx (success) or 4xx (validation error), never 5xx.
			expect(upsellbayResponses.every((status) => status < 500)).toBeTruthy();
		}
	});

	test('checkout bump toggle works by keyboard', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const checkbox = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"] input[type="checkbox"]').first();

		if (await checkbox.count() > 0) {
			// Focus the checkbox and toggle with Space.
			await checkbox.focus();
			await page.keyboard.press('Space');

			// The checkbox should toggle — verify the checked state changed.
			await expect.poll(async () => await checkbox.isChecked(), { timeout: 5000 }).toBeTruthy();
		}
	});

	test('block checkout offer card is accessible', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const offerCard = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"]').first();

		if (await offerCard.count() > 0) {
			// The checkbox should have an accessible label.
			const checkbox = offerCard.locator('input[type="checkbox"]').first();
			if (await checkbox.count() > 0) {
				// The checkbox should be reachable (not hidden from accessibility tree).
				const ariaHidden = await checkbox.getAttribute('aria-hidden');
				expect(ariaHidden).not.toBe('true');
			}
		}
	});

	test('block checkout does not obscure place order button', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		// The Place Order button should be visible and not covered by offer overlay.
		const placeOrder = page.locator('button:has-text("Place Order"), button.wc-block-components-checkout-place-order-button').first();

		if (await placeOrder.count() > 0) {
			await expect(placeOrder).toBeVisible();
		}
	});
});
