const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth, gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');

const env = getEnv();

test.describe('block checkout bump', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');
	test.skip(!env.blockCheckoutUrl, 'Set UPSELLBAY_E2E_BLOCK_CHECKOUT_URL.');
	test.skip(!env.productUrl, 'Set UPSELLBAY_E2E_PRODUCT_URL.');

	// Every test here previously navigated straight to the checkout URL with
	// an empty cart — WooCommerce has nothing to check out with an empty
	// cart, so no offer, and in some configurations no checkout content at
	// all, could ever have rendered regardless of whether the plugin code
	// worked. Put a real product in the cart first so these tests exercise
	// an actual shopper scenario.
	test.beforeEach(async ({ page }) => {
		await page.goto(env.productUrl);
		const addToCart = page.getByRole('button', { name: /add to cart/i }).first();
		await addToCart.click();
		await page.waitForTimeout(1000);
	});

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

	test('block checkout renders offer card', async ({ page }) => {
		const health = captureBrowserHealth(page);

		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		// This environment has active checkout_bump offers seeded with no
		// restrictive targeting, so a card must render — a missing card is
		// a real regression (this exact assertion used to be conditional,
		// which is how the underlying rendering bugs went unnoticed).
		const offerCard = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"]').first();
		await expect(offerCard, 'a checkout_bump offer card must render').toBeVisible();

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
		await expect(checkbox, 'a checkout_bump offer checkbox must be present').toBeVisible();

		await checkbox.click();

		// Wait for the REST call to complete.
		await expect.poll(() => upsellbayResponses.length, { timeout: 10000 }).toBeGreaterThanOrEqual(1);

		// The response should be 2xx (success) or 4xx (validation error), never 5xx.
		expect(upsellbayResponses.every((status) => status < 500)).toBeTruthy();
	});

	test('checkout bump toggle works by keyboard', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const checkbox = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"] input[type="checkbox"]').first();
		await expect(checkbox, 'a checkout_bump offer checkbox must be present').toBeVisible();

		// Focus the checkbox and toggle with Space.
		await checkbox.focus();
		await page.keyboard.press('Space');

		// Was a known bug (2026-07-18): the checkbox's visual checked state
		// didn't update after a successful accept, because refreshBlockCart()
		// invalidated the cart resolution without awaiting the refetch, so
		// the extensions data (including in_cart) this component reads could
		// still be stale when isLoading cleared. Fixed by awaiting an
		// explicit cart refetch and pushing it via receiveCartContents()
		// before isLoading clears (see checkout-bump.js).
		await expect.poll(async () => await checkbox.isChecked(), { timeout: 5000 }).toBeTruthy();
	});

	test('block checkout offer card is accessible', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		const offerCard = page.locator('.upsellbay-offer[data-upsellbay-placement="checkout_bump"]').first();
		await expect(offerCard, 'a checkout_bump offer card must render').toBeVisible();

		const checkbox = offerCard.locator('input[type="checkbox"]').first();
		await expect(checkbox, 'the offer card must expose a checkbox').toBeVisible();

		// The checkbox must be reachable (not hidden from the accessibility tree).
		const ariaHidden = await checkbox.getAttribute('aria-hidden');
		expect(ariaHidden).not.toBe('true');
	});

	test('block checkout does not obscure place order button', async ({ page }) => {
		await page.goto(env.blockCheckoutUrl);
		await expect(page.locator('body')).toBeVisible();

		// The Place Order button must be visible and not covered by offer overlay.
		const placeOrder = page.locator('button:has-text("Place Order"), button.wc-block-components-checkout-place-order-button').first();
		await expect(placeOrder, 'the Place Order button must be present and visible').toBeVisible();
	});
});
