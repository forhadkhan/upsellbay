<?php
/**
 * Phase 7 quality assurance tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);

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
		'block checkout compatibility is declared and verified' => static function (): void {
			$root        = dirname( __DIR__ );
			$plugin_file = (string) file_get_contents( $root . '/app/Core/Plugin.php' );
			$docs        = (string) file_get_contents( $root . '/docs/compatibility-matrix.md' );

			assert_true( str_contains( $plugin_file, 'cart_checkout_blocks' ) );
			assert_true( str_contains( $plugin_file, 'declare_compatibility' ) );
			assert_contains( '| Block Checkout | Supported |', $docs );
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
