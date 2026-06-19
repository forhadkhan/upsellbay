function normalizeBaseUrl(value) {
	return (value || '').replace(/\/+$/, '');
}

function getEnv() {
	const baseUrl = normalizeBaseUrl(process.env.UPSELLBAY_E2E_BASE_URL);

	return {
		baseUrl,
		adminUser: process.env.UPSELLBAY_E2E_ADMIN_USER || '',
		adminPass: process.env.UPSELLBAY_E2E_ADMIN_PASS || '',
		customerEmail: process.env.UPSELLBAY_E2E_CUSTOMER_EMAIL || 'upsellbay-e2e@example.test',
		productUrl: process.env.UPSELLBAY_E2E_PRODUCT_URL || '',
		cartUrl: process.env.UPSELLBAY_E2E_CART_URL || `${baseUrl}/cart/`,
		checkoutUrl: process.env.UPSELLBAY_E2E_CHECKOUT_URL || `${baseUrl}/checkout/`,
		blockCheckoutUrl: process.env.UPSELLBAY_E2E_BLOCK_CHECKOUT_URL || '',
		thankyouUrl: process.env.UPSELLBAY_E2E_THANKYOU_URL || '',
		ready: Boolean(baseUrl && process.env.UPSELLBAY_E2E_ADMIN_USER && process.env.UPSELLBAY_E2E_ADMIN_PASS),
	};
}

module.exports = { getEnv };
