const { expect } = require('@playwright/test');

async function loginAsAdmin(page, env) {
	await page.goto(`${env.baseUrl}/wp-login.php`);
	await page.locator('#user_login').fill(env.adminUser);
	await page.locator('#user_pass').fill(env.adminPass);
	await page.locator('#wp-submit').click();
	await expect(page).toHaveURL(/wp-admin/);
}

async function gotoUpsellBay(page, env, query = 'tab=offers') {
	const targetUrl = `${env.baseUrl}/wp-admin/admin.php?page=upsellbay&${query}`;

	await page.goto(targetUrl);

	if (page.url().includes('/wp-login.php') || (await page.locator('#loginform').count())) {
		await loginAsAdmin(page, env);
		await page.goto(targetUrl);
	}

	await expect(page.locator('body')).toContainText('UpsellBay');
}

function captureBrowserHealth(page) {
	const messages = [];
	const failedRequests = [];
	const ignoredRequestHosts = ['pixel.wp.com', 'secure.gravatar.com', 'stats.wp.com'];

	page.on('console', (message) => {
		if (['error', 'warning'].includes(message.type())) {
			messages.push(`${message.type()}: ${message.text()}`);
		}
	});

	page.on('requestfailed', (request) => {
		const failureText = request.failure()?.errorText || '';

		// ERR_ABORTED means the browser cancelled an in-flight request
		// (almost always because the page navigated away before it
		// completed) — it never reached a broken/error state, so it isn't
		// evidence of anything wrong. Background WP admin chatter (dashboard
		// widgets, other active plugins' own admin-ajax calls, etc.)
		// regularly gets cut off this way by an unrelated navigation in the
		// same test and isn't something UpsellBay's code is responsible for.
		if ('net::ERR_ABORTED' === failureText) {
			return;
		}

		try {
			const url = new URL(request.url());
			if (ignoredRequestHosts.includes(url.hostname)) {
				return;
			}
		} catch (error) {
			// Ignore URL parsing failures and record the original request below.
		}

		failedRequests.push(`${request.method()} ${request.url()} ${failureText}`);
	});

	return {
		assertClean() {
			expect(messages, 'console errors/warnings').toEqual([]);
			expect(failedRequests, 'failed network requests').toEqual([]);
		},
	};
}

module.exports = { captureBrowserHealth, gotoUpsellBay, loginAsAdmin };
