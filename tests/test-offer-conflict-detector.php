<?php
/**
 * OfferConflictDetector tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Domain\Offers\OfferConflictDetector;

/**
 * Returns test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_offer_conflict_detector_tests(): array {
	return array(
		'detect returns no warnings when the override flag is set' => static function (): void {
			$detector = new OfferConflictDetector( upsellbay_test_offer_repository( array() ) );

			$warnings = $detector->detect( 1, array( '_ub_conflict_override' => true ) );

			assert_true( array() === $warnings );
		},
		'detect returns no warnings for draft offers' => static function (): void {
			$detector = new OfferConflictDetector( upsellbay_test_offer_repository( array() ) );

			$warnings = $detector->detect( 1, array( '_ub_status' => 'draft' ) );

			assert_true( array() === $warnings );
		},
		'detect warns on placement crowding past three active offers in the same placement' => static function (): void {
			$offers = array(
				2 => array(
					'id'   => 2,
					'title' => 'Offer 2',
					'meta' => array(
						'_ub_status'     => 'active',
						'_ub_offer_type' => 'checkout_bump',
					),
				),
				3 => array(
					'id'   => 3,
					'title' => 'Offer 3',
					'meta' => array(
						'_ub_status'     => 'active',
						'_ub_offer_type' => 'checkout_bump',
					),
				),
				4 => array(
					'id'   => 4,
					'title' => 'Offer 4',
					'meta' => array(
						'_ub_status'     => 'active',
						'_ub_offer_type' => 'checkout_bump',
					),
				),
			);

			$detector = new OfferConflictDetector( upsellbay_test_offer_repository( $offers ) );

			$warnings = $detector->detect(
				1,
				array(
					'_ub_status'     => 'active',
					'_ub_offer_type' => 'checkout_bump',
				)
			);

			assert_same( 1, count( $warnings ) );
			assert_contains( 'Placement crowding', $warnings[0] );
		},
		'detect warns on funnel overlap for offers sharing placement, goal, and targeting' => static function (): void {
			$offers = array(
				2 => array(
					'id'   => 2,
					'title' => 'Conflicting Offer',
					'meta' => array(
						'_ub_status'               => 'active',
						'_ub_offer_type'           => 'checkout_bump',
						'_ub_offer_goal'           => 'add_on',
						'_ub_trigger_product_ids'  => array( 10, 20 ),
					),
				),
			);

			$detector = new OfferConflictDetector( upsellbay_test_offer_repository( $offers ) );

			$warnings = $detector->detect(
				1,
				array(
					'_ub_status'              => 'active',
					'_ub_offer_type'          => 'checkout_bump',
					'_ub_offer_goal'          => 'add_on',
					'_ub_trigger_product_ids' => array( 20, 30 ),
				)
			);

			assert_same( 1, count( $warnings ) );
			assert_contains( 'Funnel overlap', $warnings[0] );
			assert_contains( 'Conflicting Offer', $warnings[0] );
		},
	);
}
