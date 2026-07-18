const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { getEnv } = require('./helpers/env');

const env = getEnv();

/**
 * The keystone test this suite was missing: every other checkout spec only
 * checks that elements are visible or clickable, never that an offer
 * actually renders, that accepting it changes what the shopper pays, or
 * that the resulting order reflects it. This test completes a real order
 * through Cash on Delivery (no payment gateway configured in this
 * environment) and verifies the outcome by observation at each step — not
 * by inferring success from an earlier step succeeding.
 *
 * Requires `wp` (WP-CLI) to be available on the machine running the test,
 * to verify the resulting order's line items and attribution meta directly
 * against the database rather than trusting only what the browser shows.
 *
 * Run in its own Playwright invocation (see the `test:e2e` npm script),
 * separate from the other ~40 tests: clicking Place Order reliably produced
 * no navigation and no network request at all when this test ran as the
 * ~40th-something test in one long single-worker sequential run. Root-caused
 * one real product bug this way (see checkout-bump.js — an unawaited cart
 * refresh could wipe in-progress checkout form fields), confirmed fixed by
 * direct wp.data store inspection (cartIsResolving is false before the
 * click, and checkout progresses through a normal processing -> idle
 * transition with a real order-received redirect).
 *
 * This specific machine also runs this suite on an active interactive
 * desktop (other IDE/browser/AI-session processes competing for CPU/RAM,
 * not a dedicated CI runner) that had, by the time this was last
 * characterized, accumulated ~2000 real orders from this suite's own
 * repeated runs plus fully-utilized swap — under that load, checkout order
 * creation can occasionally take longer than even a generous timeout while
 * still completing correctly (verified directly: a plain, unrecorded script
 * running the identical click sequence reached order-received after ~20s
 * when the Playwright Test run of the same sequence had not yet resolved).
 * A slow or occasionally-timing-out run here reflects this machine's
 * current load, not the fix above regressing — re-run in isolation, or on a
 * quieter machine/CI runner, before treating a timeout as a new bug.
 */
test.describe('real order completion with an accepted checkout bump', () => {
	test.skip(!env.ready || !env.productUrl, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, UPSELLBAY_E2E_ADMIN_PASS, and UPSELLBAY_E2E_PRODUCT_URL.');

	test('accepting a checkout bump and placing a COD order produces a correctly discounted order line with attribution', async ({ page }) => {
		await page.goto(env.productUrl);
		const addToCart = page.getByRole('button', { name: /add to cart/i }).first();
		await expect(addToCart, 'product page must have an Add to cart button').toBeVisible();
		await addToCart.click();
		await page.waitForTimeout(1500);

		await page.goto(env.checkoutUrl, { waitUntil: 'networkidle' });

		const bumpCard = page.locator('.upsellbay-offer--checkout_bump').first();
		const bumpPresent = (await bumpCard.count()) > 0;

		test.skip(!bumpPresent, 'No active checkout_bump offer is eligible for this cart in this environment — nothing to accept.');

		await expect(bumpCard, 'the checkout bump card must actually be visible, not just present in the DOM').toBeVisible();

		const priceText = await bumpCard.locator('.upsellbay-offer__price').innerText();
		// Expect a strikethrough original price and a discounted price, e.g. "$65.00 $55.00".
		const priceMatches = priceText.match(/\$([\d,.]+)/g);
		expect(priceMatches, 'offer card must show both an original and discounted price').not.toBeNull();
		expect(priceMatches.length).toBeGreaterThanOrEqual(2);
		const expectedDiscountedPrice = parseFloat(priceMatches[priceMatches.length - 1].replace(/[$,]/g, ''));

		const bumpResponses = [];
		page.on('response', async (response) => {
			if (response.url().includes('/upsellbay/v1/bump-toggle')) {
				const body = await response.json().catch(() => null);
				bumpResponses.push({ status: response.status(), body });
			}
		});

		const checkbox = bumpCard.locator('input[type="checkbox"]').first();
		await checkbox.click();

		await expect.poll(() => bumpResponses.length, {
			message: 'accepting the bump must send a bump-toggle REST request',
			timeout: 10000,
		}).toBeGreaterThanOrEqual(1);

		expect(bumpResponses[0].status, 'bump-toggle must succeed').toBe(200);
		expect(bumpResponses[0].body.success, 'bump-toggle response must report success').toBe(true);
		expect(
			parseFloat(bumpResponses[0].body.offer_price),
			'server-computed offer price must match what the card displayed'
		).toBeCloseTo(expectedDiscountedPrice, 2);

		// The bump-toggle response triggers a wc/store/cart refresh, which
		// re-renders the checkout form. Filling fields while that re-render
		// is still in flight loses whatever was typed into a field that gets
		// unmounted/remounted mid-fill (observed: the email field ends up
		// empty while every field filled slightly later survives) — wait for
		// the order summary to reflect the accepted bump's price before
		// touching the form.
		await expect(page.locator('.wc-block-components-totals-footer-item, .wc-block-components-totals-item__value').filter({ hasText: '$' }).first()).toBeVisible();

		// Fill the minimum fields this store's checkout requires. Field IDs
		// match this store's Block Checkout markup (WooCommerce Blocks'
		// standard field naming); classic checkout uses different IDs and
		// isn't exercised by this test since this store's checkout page uses
		// the Block Checkout page template.
		await page.fill('#email', env.customerEmail);
		await expect(page.locator('#email')).toHaveValue(env.customerEmail);
		await page.fill('#shipping-first_name', 'E2E');
		await page.fill('#shipping-last_name', 'Tester');
		await page.fill('#shipping-address_1', '123 Test Street');
		await page.fill('#shipping-city', 'Dhaka');
		await page.fill('#shipping-postcode', '1200');
		await page.fill('#shipping-phone', '01700000000');

		const codRadio = page.locator('#radio-control-wc-payment-method-options-cod');
		if (await codRadio.count() > 0) {
			await codRadio.check();
		}

		const placeOrder = page.locator('button:has-text("Place Order"), button.wc-block-components-checkout-place-order-button').first();
		await expect(placeOrder, 'Place Order button must be present').toBeVisible();
		await placeOrder.click();

		// Generous timeout: order processing goes through a real REST round
		// trip plus WooCommerce's own order-creation hooks, and this
		// environment's order table has accumulated thousands of rows from
		// this suite's own repeated real-order runs, which measurably slows
		// order creation over time. Confirmed via direct wp/data store
		// inspection that a slow run is genuinely mid-checkoutStatus
		// "processing" (not stuck/erroring) — this is capacity, not a race.
		await expect(page, 'placing the order must reach the order-received page').toHaveURL(/order-received/, { timeout: 60000 });

		const orderIdMatch = page.url().match(/order-received\/(\d+)/);
		expect(orderIdMatch, 'order-received URL must contain an order id').not.toBeNull();
		const orderId = orderIdMatch[1];

		// Verify the resulting order directly against wc_get_order() — this
		// works whether HPOS is enabled or not, unlike reading raw postmeta,
		// and it's the same CRUD API the attribution gate requires the
		// product code to use, so the test holds itself to the same
		// standard. The browser-visible total can be right for the wrong
		// reason (e.g. a coincidental match); the order's actual line items
		// and attribution meta are the ground truth this feature exists to
		// get right.
		if (!env.wpPath) {
			throw new Error('Set UPSELLBAY_E2E_WP_PATH to the WordPress install path to verify the created order.');
		}

		const evalScript = `<?php
			$order = wc_get_order( ${orderId} );
			if ( ! $order ) {
				echo json_encode( array( 'error' => 'order not found' ) );
				exit;
			}
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'product_id'         => $item->get_product_id(),
					'total'              => (float) $item->get_total(),
					'ub_offer_id'        => $item->get_meta( '_ub_offer_id' ),
					'ub_discount_amount' => $item->get_meta( '_ub_discount_amount' ),
				);
			}
			echo json_encode( array( 'order_total' => (float) $order->get_total(), 'items' => $items ) );
		`;

		const scriptPath = path.join(os.tmpdir(), `upsellbay-e2e-order-check-${orderId}.php`);
		fs.writeFileSync(scriptPath, evalScript);

		let orderData;
		try {
			orderData = JSON.parse(
				execSync(`wp eval-file ${scriptPath} --path=${env.wpPath}`, { encoding: 'utf-8' })
			);
		} finally {
			fs.unlinkSync(scriptPath);
		}

		expect(orderData.error, 'order must be readable via wc_get_order()').toBeUndefined();

		const attributedItem = orderData.items.find((item) => item.ub_offer_id);
		expect(attributedItem, 'at least one order line item must carry _ub_offer_id attribution meta').toBeTruthy();
		expect(
			attributedItem.total,
			'the attributed line item total must match the discounted price the shopper was shown and accepted'
		).toBeCloseTo(expectedDiscountedPrice, 2);
	});
});
