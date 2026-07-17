<?php
/**
 * Phase 7 quality assurance tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Returns Phase 7 test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_quality_assurance_tests(): array {
	return array(
		'qa command scripts and evidence documents exist' => static function (): void {
			$root = dirname( __DIR__ );

			foreach (
				array(
					'scripts/qa-product-isolation.php',
					'scripts/qa-static-gates.php',
					'scripts/qa-performance-bench.php',
					'.meta/qa/quality-assurance-runbook.md',
					'.meta/qa/e2e-test-plan.md',
					'.meta/qa/performance-results.md',
					'.meta/qa/product-isolation-scan.md',
					'.meta/qa/release-validation.md',
					'docs/compatibility-matrix.md',
				) as $relative_path
			) {
				assert_true( file_exists( $root . '/' . $relative_path ) );
			}
		},
		'composer and package scripts expose required standards gates' => static function (): void {
			$root     = dirname( __DIR__ );
			$composer = json_decode( (string) file_get_contents( $root . '/composer.json' ), true );
			$package  = json_decode( (string) file_get_contents( $root . '/package.json' ), true );

			foreach ( array( 'test', 'phpcs', 'phpstan', 'plugin-check' ) as $script ) {
				assert_true( isset( $composer['scripts'][ $script ] ) );
			}

			foreach ( array( 'build', 'i18n:make-pot' ) as $script ) {
				assert_true( isset( $package['scripts'][ $script ] ) );
			}
		},
		'qa runbook covers every phase seven task'       => static function (): void {
			$root    = dirname( __DIR__ );
			$runbook = (string) file_get_contents( $root . '/.meta/qa/quality-assurance-runbook.md' );

			foreach (
				array(
					'UB-P7-001',
					'UB-P7-002',
					'UB-P7-003',
					'UB-P7-004',
					'UB-P7-005',
					'UB-P7-006',
					'UB-P7-007',
					'UB-P7-008',
					'UB-P7-009',
					'UB-P7-010',
					'UB-P7-011',
					'UB-P7-012',
					'UB-P7-013',
				) as $task_id
			) {
				assert_contains( $task_id, $runbook );
			}
		},
		'block checkout compatibility is declared after e2e proof passes' => static function (): void {
			$root        = dirname( __DIR__ );
			$plugin_file = (string) file_get_contents( $root . '/app/Core/Plugin.php' );
			$docs        = (string) file_get_contents( $root . '/docs/compatibility-matrix.md' );

			assert_true( str_contains( $plugin_file, 'cart_checkout_blocks' ) );
			assert_true( str_contains( $plugin_file, 'declare_compatibility' ) );
			assert_true( str_contains( $plugin_file, "'cart_checkout_blocks',\n\t\t\tConstants::plugin_file(),\n\t\t\ttrue" ) );
			assert_contains( '| Block Checkout | Supported after E2E proof |', $docs );

			// The declaration and docs claim are necessary but not sufficient: they can be
			// hand-edited without ever running the suite. Require a real, current evidence
			// file generated only by scripts/verify-block-checkout-proof.sh (see
			// .meta/reviews/bundle-feature-and-release-readiness-2026-07-17.md for why this
			// gate used to be satisfiable by editing this docs file alone).
			$evidence_path = $root . '/tests/e2e/evidence/block-checkout-proof.json';
			assert_true( file_exists( $evidence_path ), 'Run scripts/verify-block-checkout-proof.sh to generate real E2E evidence before claiming Block Checkout compatibility.' );

			$evidence = json_decode( (string) file_get_contents( $evidence_path ), true );
			assert_true( is_array( $evidence ) );
			assert_same( 'tests/e2e/block-checkout.spec.js', $evidence['spec'] ?? null );
			assert_true( ( $evidence['total'] ?? 0 ) > 0 );
			assert_same( 0, $evidence['failed'] ?? -1 );
			assert_same( $evidence['total'] ?? -1, $evidence['passed'] ?? -2 );
		},
		'package exposes first class playwright e2e commands' => static function (): void {
			$root    = dirname( __DIR__ );
			$package = json_decode( (string) file_get_contents( $root . '/package.json' ), true );

			foreach ( array( 'test:e2e', 'test:e2e:headed', 'test:e2e:report', 'test:e2e:trace' ) as $script ) {
				assert_true( isset( $package['scripts'][ $script ] ) );
			}

			assert_true( isset( $package['devDependencies']['@playwright/test'] ) );
			assert_true( file_exists( $root . '/playwright.config.js' ) );
			assert_true( file_exists( $root . '/tests/e2e/admin-offer-editor.spec.js' ) );
			assert_true( file_exists( $root . '/tests/e2e/classic-checkout.spec.js' ) );
		},
		'static qa scripts scan forbidden runtime coupling and unsafe storage access' => static function (): void {
			$root      = dirname( __DIR__ );
			$isolation = (string) file_get_contents( $root . '/scripts/qa-product-isolation.php' );
			$static    = (string) file_get_contents( $root . '/scripts/qa-static-gates.php' );

			foreach ( array( 'cartbay_', '_cartbay_', 'cartbay-', 'WPAnchorBay\\\\CartBay' ) as $forbidden ) {
				assert_contains( $forbidden, $isolation );
			}

			foreach ( array( 'update_post_meta', 'get_post_meta', 'woocommerce_checkout_update_order_meta', 'eval(' ) as $forbidden ) {
				assert_contains( $forbidden, $static );
			}
		},
	);
}
