<?php
/**
 * Log rows for blocked public REST requests.
 *
 * @package UpsellBay\Domain\Logging
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Logging;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Writes one minimal, throttled log row per kind of blocked public request.
 *
 * Public routes can be called by anyone, so a client must not be able to grow
 * the log table at will or plant shopper data in it. A row records only the
 * endpoint, the reason and the offer ID, and at most one row is written per
 * endpoint and reason (and offer, for offer-specific reasons) each hour.
 * Rate-limit and bad-token rejections are counted per endpoint only: those are
 * the ones a flood produces, and the offer ID on them is client-chosen.
 *
 * @since 1.0.0
 */
final class ApiFailureLog {
	/**
	 * Seconds during which a repeat of the same failure writes no new row.
	 *
	 * @var int
	 */
	public const WINDOW = 3600;

	/**
	 * Reasons a flood produces; throttled per endpoint, not per offer.
	 *
	 * @var array<int, string>
	 */
	private const FLOOD_REASONS = array( 'rate_limited', 'invalid_token' );

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Throttle claim: returns true when the key was free and is now taken.
	 *
	 * @var callable(string, int): bool
	 */
	private $claim;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LoggerInterface $logger Logger.
	 * @param callable|null   $claim  Throttle claim (key, seconds): bool. Defaults to transients.
	 */
	public function __construct( LoggerInterface $logger, ?callable $claim = null ) {
		$this->logger = $logger;
		$this->claim  = $claim ?? static function ( string $key, int $seconds ): bool {
			if ( false !== get_transient( $key ) ) {
				return false;
			}

			set_transient( $key, 1, $seconds );

			return true;
		};
	}

	/**
	 * Record a blocked request.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $endpoint Endpoint key.
	 * @param string               $reason   Failure reason.
	 * @param array<string, mixed> $params   Request facts; only `offer_id` is read.
	 */
	public function record( string $endpoint, string $reason, array $params ): void {
		$endpoint = sanitize_key( $endpoint );
		$reason   = sanitize_key( $reason );
		$offer_id = max( 0, (int) ( $params['offer_id'] ?? 0 ) );
		$flood    = in_array( $reason, self::FLOOD_REASONS, true );

		$throttle_key = 'upsellbay_pro_api_fail_' . md5( $endpoint . '|' . $reason . '|' . ( $flood ? '' : (string) $offer_id ) );
		if ( ! ( $this->claim )( $throttle_key, self::WINDOW ) ) {
			return;
		}

		$context = array(
			'source'   => 'api_guard',
			'metadata' => array(
				'endpoint' => $endpoint,
				'reason'   => $reason,
			),
		);

		if ( ! $flood && $offer_id > 0 ) {
			$context['object_type'] = 'offer';
			$context['object_id']   = $offer_id;
		}

		$this->logger->warning(
			/* translators: 1: endpoint, 2: reason */
			sprintf( __( 'API request to %1$s failed (%2$s)', 'upsellbay' ), $endpoint, $reason ),
			$context
		);
	}
}
