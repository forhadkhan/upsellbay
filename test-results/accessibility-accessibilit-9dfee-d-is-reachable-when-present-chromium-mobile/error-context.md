# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: accessibility.spec.js >> accessibility smoke >> storefront offer card is reachable when present
- Location: tests/e2e/accessibility.spec.js:26:2

# Error details

```
Error: axe critical/serious violations

expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 53

- Array []
+ Array [
+   Object {
+     "description": "Ensure every HTML document has a lang attribute",
+     "help": "<html> element must have a lang attribute",
+     "helpUrl": "https://dequeuniversity.com/rules/axe/4.11/html-has-lang?application=playwright",
+     "id": "html-has-lang",
+     "impact": "serious",
+     "nodes": Array [
+       Object {
+         "all": Array [],
+         "any": Array [
+           Object {
+             "data": Object {
+               "messageKey": "noLang",
+             },
+             "id": "has-lang",
+             "impact": "serious",
+             "message": "The <html> element does not have a lang attribute",
+             "relatedNodes": Array [],
+           },
+         ],
+         "failureSummary": "Fix any of the following:
+   The <html> element does not have a lang attribute",
+         "html": "<html><head>
+ <title>404 Not Found</title>
+ </head><body>
+ <h1>Not Found</h1>
+ <p>The requested URL was not found on this server.</p>
+ <hr>
+ <address>Apache/2.4.58 (Ubuntu) Server at forhad.test Port 443</address>
+
+ </body></html>",
+         "impact": "serious",
+         "none": Array [],
+         "target": Array [
+           "html",
+         ],
+       },
+     ],
+     "tags": Array [
+       "cat.language",
+       "wcag2a",
+       "wcag311",
+       "TTv5",
+       "TT11.a",
+       "EN-301-549",
+       "EN-9.3.1.1",
+       "ACT",
+       "RGAAv4",
+       "RGAA-8.3.1",
+     ],
+   },
+ ]
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - heading "Not Found" [level=1] [ref=e2]
  - paragraph [ref=e3]: The requested URL was not found on this server.
  - separator [ref=e4]
  - generic [ref=e5]: Apache/2.4.58 (Ubuntu) Server at forhad.test Port 443
```

# Test source

```ts
  1  | const { test, expect } = require('@playwright/test');
  2  | const AxeBuilder = require('@axe-core/playwright').default;
  3  | const { getEnv } = require('./helpers/env');
  4  | const { gotoUpsellBay, loginAsAdmin } = require('./helpers/wp-admin');
  5  | 
  6  | const env = getEnv();
  7  | 
  8  | async function expectNoSeriousAxeViolations(page) {
  9  | 	const results = await new AxeBuilder({ page }).analyze();
  10 | 	const impactfulViolations = results.violations.filter(({ impact }) => ['critical', 'serious'].includes(impact));
  11 | 
> 12 | 	expect(impactfulViolations, 'axe critical/serious violations').toEqual([]);
     |                                                                 ^ Error: axe critical/serious violations
  13 | }
  14 | 
  15 | test.describe('accessibility smoke', () => {
  16 | 	test.skip(!env.ready, 'Set UPSELLBAY_E2E_BASE_URL, UPSELLBAY_E2E_ADMIN_USER, and UPSELLBAY_E2E_ADMIN_PASS.');
  17 | 
  18 | 	test('admin editor has keyboard focus targets and no serious axe violations', async ({ page }) => {
  19 | 		await loginAsAdmin(page, env);
  20 | 		await gotoUpsellBay(page, env, 'tab=offers&action=edit');
  21 | 		await page.keyboard.press('Tab');
  22 | 		await expect(page.locator(':focus')).toBeVisible();
  23 | 		await expectNoSeriousAxeViolations(page);
  24 | 	});
  25 | 
  26 | 	test('storefront offer card is reachable when present', async ({ page }) => {
  27 | 		test.skip(!env.productUrl, 'Set UPSELLBAY_E2E_PRODUCT_URL.');
  28 | 		await page.goto(env.productUrl);
  29 | 		await page.keyboard.press('Tab');
  30 | 		await expect(page.locator(':focus, body')).toBeVisible();
  31 | 		await expectNoSeriousAxeViolations(page);
  32 | 	});
  33 | });
  34 | 
```