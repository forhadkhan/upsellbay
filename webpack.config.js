const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'admin/js/upsellbay-admin': path.resolve(__dirname, 'src/admin/index.js'),
		'admin/js/upsellbay-offer-editor': path.resolve(__dirname, 'src/admin/offer-editor/index.js'),
		'admin/js/upsellbay-analytics': path.resolve(__dirname, 'src/admin/analytics/index.js'),
		'frontend/classic-checkout': path.resolve(__dirname, 'src/classic-checkout/index.js'),
		'frontend/block-checkout': path.resolve(__dirname, 'src/block-checkout/index.js'),
		'frontend/storefront': path.resolve(__dirname, 'src/storefront/index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'assets'),
		filename: '[name].js',
		clean: false,
	},
};
