# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin-offer-editor.spec.js >> admin offer editor permutation tests >> Offer Matrix: tier2-product-invalid - Invalid product ID
- Location: tests/e2e/admin-offer-editor.spec.js:20:3

# Error details

```
Error: expect(page).toHaveURL(expected) failed

Expected pattern: /wp-admin/
Received string:  "https://forhad.test/wp-login.php"
Timeout: 10000ms

Call log:
  - Expect "toHaveURL" with timeout 10000ms
    24 × unexpected value "https://forhad.test/wp-login.php"

```

```yaml
- heading "Log In" [level=1]
- link "Powered by WordPress":
  - /url: https://wordpress.org/
- paragraph:
  - strong: "Error:"
  - text: The username
  - strong: admin
  - text: is not registered on this site. If you are unsure of your username, try your email address instead.
- paragraph:
  - text: Username or Email Address
  - textbox "Username or Email Address"
- text: Password
- textbox "Password"
- button "Show password"
- paragraph:
  - checkbox "Remember Me"
  - text: Remember Me
- paragraph:
  - button "Log In"
- paragraph:
  - link "Lost your password?":
    - /url: https://forhad.test/wp-login.php?action=lostpassword
- paragraph:
  - link "← Go to WP-Pilot":
    - /url: https://forhad.test/
```

# Test source

```ts
  1  | const { expect } = require('@playwright/test');
  2  | 
  3  | async function loginAsAdmin(page, env) {
  4  | 	await page.goto(`${env.baseUrl}/wp-login.php`);
  5  | 	await page.locator('#user_login').fill(env.adminUser);
  6  | 	await page.locator('#user_pass').fill(env.adminPass);
  7  | 	await page.locator('#wp-submit').click();
> 8  | 	await expect(page).toHaveURL(/wp-admin/);
     |                     ^ Error: expect(page).toHaveURL(expected) failed
  9  | }
  10 | 
  11 | async function gotoUpsellBay(page, env, query = 'tab=offers') {
  12 | 	const targetUrl = `${env.baseUrl}/wp-admin/admin.php?page=upsellbay&${query}`;
  13 | 
  14 | 	await page.goto(targetUrl);
  15 | 
  16 | 	if (page.url().includes('/wp-login.php') || (await page.locator('#loginform').count())) {
  17 | 		await loginAsAdmin(page, env);
  18 | 		await page.goto(targetUrl);
  19 | 	}
  20 | 
  21 | 	await expect(page.locator('body')).toContainText('UpsellBay');
  22 | }
  23 | 
  24 | function captureBrowserHealth(page) {
  25 | 	const messages = [];
  26 | 	const failedRequests = [];
  27 | 	const ignoredRequestHosts = ['pixel.wp.com', 'secure.gravatar.com', 'stats.wp.com'];
  28 | 
  29 | 	page.on('console', (message) => {
  30 | 		if (['error', 'warning'].includes(message.type())) {
  31 | 			messages.push(`${message.type()}: ${message.text()}`);
  32 | 		}
  33 | 	});
  34 | 
  35 | 	page.on('requestfailed', (request) => {
  36 | 		const failureText = request.failure()?.errorText || '';
  37 | 
  38 | 		try {
  39 | 			const url = new URL(request.url());
  40 | 			if (ignoredRequestHosts.includes(url.hostname)) {
  41 | 				return;
  42 | 			}
  43 | 
  44 | 			if (
  45 | 				'GET' === request.method() &&
  46 | 				'net::ERR_ABORTED' === failureText &&
  47 | 				/\.(css|gif|ico|jpg|jpeg|js|png|svg|webm)(\?|$)/i.test(url.pathname)
  48 | 			) {
  49 | 				return;
  50 | 			}
  51 | 		} catch (error) {
  52 | 			// Ignore URL parsing failures and record the original request below.
  53 | 		}
  54 | 
  55 | 		failedRequests.push(`${request.method()} ${request.url()} ${failureText}`);
  56 | 	});
  57 | 
  58 | 	return {
  59 | 		assertClean() {
  60 | 			expect(messages, 'console errors/warnings').toEqual([]);
  61 | 			expect(failedRequests, 'failed network requests').toEqual([]);
  62 | 		},
  63 | 	};
  64 | }
  65 | 
  66 | module.exports = { captureBrowserHealth, gotoUpsellBay, loginAsAdmin };
  67 | 
```