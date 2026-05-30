<?php
/**
 * Lightweight Phase 7 performance benchmark.
 *
 * @package UpsellBay\Scripts
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

require_once $root . '/tests/bootstrap.php';
require_once $root . '/tests/test-core-business-logic.php';

use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleEvaluator;
use WPAnchorBay\UpsellBay\Domain\Rules\RuleParser;

$offers = array();
for ( $i = 1; $i <= 50; ++$i ) {
	$offers[] = upsellbay_phase4_offer( $i, 'checkout_bump', 100 + $i, $i );
}

$prioritizer = new OfferPrioritizer(
	new RuleEvaluator( new RuleParser() ),
	static fn (): bool => true,
	static fn (): int => 100
);

$durations = array();

for ( $i = 0; $i < 100; ++$i ) {
	$start = hrtime( true );
	$prioritizer->select(
		$offers,
		'checkout_bump',
		array(
			'cart_subtotal' => '250.00',
			'product_ids'   => array( 1, 2, 3 ),
		)
	);
	$durations[] = ( hrtime( true ) - $start ) / 1000000;
}

sort( $durations );

$p95_index = (int) floor( count( $durations ) * 0.95 ) - 1;
$p95       = $durations[ max( 0, $p95_index ) ];

echo 'Rule evaluation p95 with 50 active offers: ' . number_format( $p95, 3 ) . "ms\n";
echo "Target: less than 10.000ms\n";

if ( $p95 >= 10.0 ) {
	exit( 1 );
}
