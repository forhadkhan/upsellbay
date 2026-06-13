<?php
/**
 * Test OfferConflictDetector.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferConflictDetector;
use WPAnchorBay\UpsellBay\Data\OfferRepository;

/**
 * Tests for the OfferConflictDetector class.
 */
class OfferConflictDetectorTest extends TestCase {
	/**
	 * Setup.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WPAnchorBay\UpsellBay\Data\OfferRepository' ) ) {
			$this->markTestSkipped( 'Classes not loaded.' );
		}
	}

	/**
	 * Test that no warnings are returned when the override flag is set.
	 */
	public function test_detect_returns_empty_when_override_is_set() {
		$repository = $this->createMock( OfferRepository::class );
		$detector   = new OfferConflictDetector( $repository );

		$meta = array(
			'_ub_conflict_override' => true,
		);

		$warnings = $detector->detect( 1, $meta );
		$this->assertEmpty( $warnings );
	}

	/**
	 * Test that no warnings are returned for draft offers.
	 */
	public function test_detect_returns_empty_for_draft_offers() {
		$repository = $this->createMock( OfferRepository::class );
		$detector   = new OfferConflictDetector( $repository );

		$meta = array(
			'_ub_status' => 'draft',
		);

		$warnings = $detector->detect( 1, $meta );
		$this->assertEmpty( $warnings );
	}

	/**
	 * Test placement crowding warning.
	 */
	public function test_placement_crowding() {
		$repository = $this->createMock( OfferRepository::class );
		
		// Return 4 active offers for the same placement
		$repository->method( 'query' )->willReturn( array(
			array( 'id' => 2, 'title' => 'Offer 2', 'meta' => array( '_ub_offer_type' => 'checkout_bump' ) ),
			array( 'id' => 3, 'title' => 'Offer 3', 'meta' => array( '_ub_offer_type' => 'checkout_bump' ) ),
			array( 'id' => 4, 'title' => 'Offer 4', 'meta' => array( '_ub_offer_type' => 'checkout_bump' ) ),
		) );

		$detector = new OfferConflictDetector( $repository );

		$meta = array(
			'_ub_status'     => 'active',
			'_ub_offer_type' => 'checkout_bump',
		);

		$warnings = $detector->detect( 1, $meta );
		
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'Placement crowding', $warnings[0] );
	}

	/**
	 * Test funnel overlap warning.
	 */
	public function test_funnel_overlap() {
		$repository = $this->createMock( OfferRepository::class );
		
		// Return 1 active offer that shares the same goal and trigger
		$repository->method( 'query' )->willReturn( array(
			array( 
				'id' => 2, 
				'title' => 'Conflicting Offer', 
				'meta' => array( 
					'_ub_offer_type' => 'checkout_bump',
					'_ub_offer_goal' => 'add_on',
					'_ub_trigger_product_ids' => array( 10, 20 ),
				) 
			),
		) );

		$detector = new OfferConflictDetector( $repository );

		$meta = array(
			'_ub_status'              => 'active',
			'_ub_offer_type'          => 'checkout_bump',
			'_ub_offer_goal'          => 'add_on',
			'_ub_trigger_product_ids' => array( 20, 30 ),
		);

		$warnings = $detector->detect( 1, $meta );
		
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'Funnel overlap', $warnings[0] );
		$this->assertStringContainsString( 'Conflicting Offer', $warnings[0] );
	}
}
