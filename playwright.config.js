const { defineConfig, devices } = require('@playwright/test');

const baseURL = process.env.UPSELLBAY_E2E_BASE_URL || 'http://127.0.0.1';

module.exports = defineConfig({
	testDir: './tests/e2e',
	timeout: 60000,
	expect: {
		timeout: 10000,
	},
	fullyParallel: false,
	reporter: [
		['list'],
		['html', { outputFolder: process.env.UPSELLBAY_E2E_REPORT_DIR || '/tmp/upsellbay-playwright-report', open: 'never' }],
	],
	use: {
		baseURL,
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium-desktop',
			use: { ...devices['Desktop Chrome'] },
		},
		{
			name: 'chromium-mobile',
			use: { ...devices['Pixel 5'] },
		},
	],
});
