<?php
/**
 * Placement renderer coordinator.
 *
 * @package UpsellBay\Domain\Storefront
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Storefront;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Hooks;
use WPAnchorBay\UpsellBay\Domain\Analytics\AnalyticsRecorder;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferPrioritizer;

/**
 * Coordinates placement eligibility and delegates markup to renderers.
 *
 * @since 1.0.0
 */
final class PlacementRenderer {
	/**
	 * Offer prioritizer.
	 *
	 * @var OfferPrioritizer
	 */
	private OfferPrioritizer $prioritizer;

	/**
	 * Analytics recorder.
	 *
	 * @var AnalyticsRecorder
	 */
	private AnalyticsRecorder $analytics;

	/**
	 * Placement renderers.
	 *
	 * @var array<string, OfferRendererInterface>
	 */
	private array $renderers;

	/**
	 * Date callback.
	 *
	 * @var callable(): string
	 */
	private $date_provider;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferPrioritizer                      $prioritizer  Offer prioritizer.
	 * @param AnalyticsRecorder                     $analytics    Analytics recorder.
	 * @param array<string, OfferRendererInterface> $renderers    Renderers.
	 * @param callable|null                         $date_provider Store-date callback.
	 */
	public function __construct( OfferPrioritizer $prioritizer, AnalyticsRecorder $analytics, array $renderers, ?callable $date_provider = null ) {
		$this->prioritizer   = $prioritizer;
		$this->analytics     = $analytics;
		$this->renderers     = $renderers;
		$this->date_provider = $date_provider ?? array( $this, 'store_date' );
	}

	/**
	 * Render eligible offers for a placement.
	 *
	 * @since 1.0.0
	 *
	 * @param string                           $placement Placement.
	 * @param array<int, array<string, mixed>> $offers    Offers.
	 * @param array<string, mixed>             $context   Context.
	 * @param int                              $limit     Limit.
	 */
	public function render( string $placement, array $offers, array $context = array(), int $limit = 1 ): string {
		if ( ! isset( $this->renderers[ $placement ] ) ) {
			return '';
		}

		$selected = $this->prioritizer->select( $offers, $placement, $context, $limit );
		$html     = '';
		$date     = ( $this->date_provider )();

		foreach ( $selected as $offer ) {
			$offer_id = (int) ( $offer['id'] ?? 0 );
			$this->analytics->record_view( $offer_id, $placement, $date );
			$offer_html = $this->renderers[ $placement ]->render_offer( $offer, $context );
			/**
			 * Filter escaped offer HTML for a rendered storefront placement.
			 *
			 * @since 1.0.0
			 *
			 * @param string               $offer_html Escaped offer HTML.
			 * @param array<string, mixed> $offer      Offer payload.
			 * @param string               $placement  Placement key.
			 * @param array<string, mixed> $context    Render context.
			 */
			$offer_html = Hooks::filter( 'render_offer_html', $offer_html, $offer, $placement, $context );
			$html      .= $offer_html;
			Hooks::action( 'offer_rendered', $offer_id, $placement, $offer, $context );
		}

		return $html;
	}

	/**
	 * Return current store date.
	 */
	private function store_date(): string {
		return function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}
}
