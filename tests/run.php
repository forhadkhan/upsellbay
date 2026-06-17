<?php
/**
 * Minimal test runner for Phase 1 foundation tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/test-foundation.php';
require_once __DIR__ . '/test-data-architecture.php';
require_once __DIR__ . '/test-admin-architecture.php';
require_once __DIR__ . '/test-core-business-logic.php';
require_once __DIR__ . '/test-merchant-experience.php';
require_once __DIR__ . '/test-developer-extensibility.php';
require_once __DIR__ . '/test-quality-assurance.php';

$tests  = array_merge(
	upsellbay_foundation_tests(),
	upsellbay_data_architecture_tests(),
	upsellbay_admin_architecture_tests(),
	upsellbay_core_business_logic_tests(),
	upsellbay_merchant_experience_tests(),
	upsellbay_developer_extensibility_tests(),
	upsellbay_quality_assurance_tests()
);
$passed = 0;
$failed = 0;

foreach ( $tests as $name => $test ) {
	try {
		$test();
		++$passed;
		echo "PASS {$name}\n";
	} catch ( Throwable $throwable ) {
		++$failed;
		echo "FAIL {$name}: {$throwable->getMessage()}\n";
	}
}

echo "\n{$passed} passed, {$failed} failed\n";

exit( $failed > 0 ? 1 : 0 );
