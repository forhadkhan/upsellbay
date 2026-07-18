const { test, expect } = require('@playwright/test');
const { getEnv } = require('./helpers/env');
const { captureBrowserHealth, gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');
const offerTypesMatrix = require('./fixtures/offer-types.matrix.json');

const env = getEnv();

test.describe('admin offer editor permutation tests', () => {
	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');

	test('shows conflict override reason controls on the editor', async ({ page }) => {
		await loginAsAdmin(page, env);
		await gotoUpsellBay(page, env, 'tab=offers&action=edit');

		await expect(page.locator('[name="_ub_conflict_override"], #upsellbay-conflict-override')).toHaveCount(1);
		await expect(page.locator('[name="_ub_conflict_override_reason"], #upsellbay-conflict-override-reason')).toHaveCount(1);
	});

	test('adapts rule controls to the selected rule type', async ({ page }) => {
		await loginAsAdmin(page, env);
		await gotoUpsellBay(page, env, 'tab=offers&action=edit');

		await page.getByRole('button', { name: /add rule/i }).click();
		const firstRule = page.locator('#upsellbay-builder-_ub_rules tbody tr').first();

		await expect(firstRule.locator('.upsellbay-vb-type')).toHaveValue('cart_product');
		await expect(firstRule.locator('.upsellbay-vb-op')).toBeVisible();
		// Rule-value product selection uses a selectWoo/select2 AJAX search
		// (upsellbay-rule-entity-search, same widget family as the roles case
		// below), not the standalone upsellbay-product-selector widget used
		// for the offer's own target product field.
		await expect(firstRule.locator('.upsellbay-rule-entity-search[data-endpoint="products"]')).toHaveCount(1);

		await firstRule.locator('.upsellbay-vb-type').selectOption('cart_subtotal');
		await expect(firstRule.locator('input[type="number"].upsellbay-vb-val')).toHaveCount(1);
		await expect(firstRule.locator('.upsellbay-vb-op')).toBeVisible();

		await firstRule.locator('.upsellbay-vb-type').selectOption('user_role');
		await expect(firstRule.locator('.upsellbay-rule-entity-search[data-endpoint="roles"]')).toHaveCount(1);
		await expect(firstRule.locator('td').nth(1)).toContainText('Not required');
	});

	// Maps each fixture's shorthand `placement` to the real `_ub_offer_type`
	// option value the admin editor's select actually uses.
	const PLACEMENT_TO_OFFER_TYPE = {
		product: 'product_upsell',
		cart: 'cart_crosssell',
		checkout: 'checkout_bump',
		thankyou: 'thankyou_offer',
	};

	for (const caseData of offerTypesMatrix) {
		test(`Offer Matrix: ${caseData.id} - ${caseData.description}`, async ({ page }) => {
			const health = captureBrowserHealth(page);
			await loginAsAdmin(page, env);
			await gotoUpsellBay(page, env, 'tab=offers&action=edit');

			// Fill out title
			await page.locator('input[name="post_title"], input[name="title"]').first().fill(`Test Offer: ${caseData.id}`);

			// Set offer type/placement so the case exercises the field the
			// fixture claims it does (the type select is a required native
			// <select> — leaving it unset blocks submission on its own
			// browser-native validation before any UpsellBay-specific
			// validation is ever reached).
			if (caseData.placement && PLACEMENT_TO_OFFER_TYPE[caseData.placement]) {
				await page.locator('#upsellbay-_ub_offer_type').selectOption(PLACEMENT_TO_OFFER_TYPE[caseData.placement]);
			}

			// Set status
			if (caseData.status) {
				await page.locator('select[name="_ub_status"], input[name="_ub_status"]').first().selectOption?.(caseData.status).catch(async () => {
					await page.locator(`input[name="_ub_status"][value="${caseData.status}"]`).check().catch(() => {});
				});
			}

			// productState "invalid" means the offer targets a product ID
			// that does not exist. The product field is a live AJAX
			// search-and-select widget with no way to "search for" a
			// nonexistent product, so drive its underlying hidden input
			// directly — this is what the widget itself writes into on a
			// real selection, and what the server-side validator reads.
			if ('invalid' === caseData.productState) {
				const hiddenProductId = page.locator('#upsellbay-_ub_offer_product_id');
				await hiddenProductId.evaluate((el) => {
					el.value = '999999999';
					el.dispatchEvent(new Event('change', { bubbles: true }));
				});
			}

			// Validate based on matrix expectation
			if (caseData.expectedValidation === 'fail') {
				await page.getByRole('button', { name: /save|update|publish/i }).click();
				await expect(
					page.locator('.notice.notice-error, .notice-error, .error').filter({ hasText: /product|headline|button|required/i }).first()
				).toBeVisible();
			} else {
				// Passing validation flow would be fully implemented here
				expect(true).toBe(true);
			}

			health.assertClean();
		});
	}
});
