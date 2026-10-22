<?php
/**
 * Frequently-bought-together miner.
 *
 * @package UpsellBay\Domain\Recommendations
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBayPro\Domain\Recommendations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use WPAnchorBay\UpsellBayPro\Data\FbtRepository;

/**
 * Mines product co-occurrence from order history into a precomputed recommendation index.
 *
 * Scoring is directional confidence with a co-occurrence dampener:
 *   confidence = co-occurrence(A,B) / orders-containing(A)
 *   score      = confidence * ln(1 + co-occurrence(A,B))
 * so a single-order 100%-confidence pair never beats a robust, repeatedly-observed pair.
 * A minimum-support floor suppresses noise on thin data. Order access goes through the
 * WooCommerce CRUD (HPOS-safe) via an injectable provider — the class itself does no SQL,
 * so the scoring is unit-testable without WooCommerce.
 *
 * @since 1.0.0
 */
final class FbtMiner {
	/**
	 * Minimum times a pair must co-occur to be indexed.
	 */
	private const MIN_CO_OCCURRENCE = 3;

	/**
	 * Recommendation index repository.
	 *
	 * @var FbtRepository
	 */
	private FbtRepository $repository;

	/**
	 * Orders provider: yields an array of product IDs per order.
	 *
	 * @var callable(int): iterable<array<int, int>>
	 */
	private $orders_provider;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FbtRepository $repository      Index repository.
	 * @param callable|null $orders_provider Optional order-lines provider (for tests).
	 */
	public function __construct( FbtRepository $repository, ?callable $orders_provider = null ) {
		$this->repository      = $repository;
		$this->orders_provider = $orders_provider ?? array( $this, 'wc_orders_provider' );
	}

	/**
	 * Rebuild the whole index from a trailing window of order history.
	 *
	 * @since 1.0.0
	 *
	 * @param int $window_days      Trailing window to mine.
	 * @param int $limit_per_anchor Max recommendations kept per anchor product.
	 * @return array{orders_scanned: int, anchors: int, rows_written: int}
	 */
	public function rebuild( int $window_days = 180, int $limit_per_anchor = 8 ): array {
		$pair_counts   = array();
		$anchor_orders = array();
		$scanned       = 0;

		foreach ( ( $this->orders_provider )( $window_days ) as $product_ids ) {
			++$scanned;
			$unique = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', is_array( $product_ids ) ? $product_ids : array() ),
						static fn ( int $id ): bool => $id > 0
					)
				)
			);

			foreach ( $unique as $anchor ) {
				$anchor_orders[ $anchor ] = ( $anchor_orders[ $anchor ] ?? 0 ) + 1;
				foreach ( $unique as $recommended ) {
					if ( $anchor === $recommended ) {
						continue;
					}
					$pair_counts[ $anchor ][ $recommended ] = ( $pair_counts[ $anchor ][ $recommended ] ?? 0 ) + 1;
				}
			}
		}

		$rows = $this->score_rows( $pair_counts, $anchor_orders, $limit_per_anchor );

		return array(
			'orders_scanned' => $scanned,
			'anchors'        => count( array_unique( array_column( $rows, 'anchor_product_id' ) ) ),
			'rows_written'   => $this->repository->replace_index( $rows ),
		);
	}

	/**
	 * Turn accumulated pair counts into scored, floored, top-N index rows.
	 *
	 * @param array<int, array<int, int>> $pair_counts   anchor => recommended => count.
	 * @param array<int, int>             $anchor_orders anchor => orders containing it.
	 * @param int                         $limit_per_anchor Max rows per anchor.
	 * @return array<int, array<string, mixed>>
	 */
	private function score_rows( array $pair_counts, array $anchor_orders, int $limit_per_anchor ): array {
		$rows = array();

		foreach ( $pair_counts as $anchor => $recommendations ) {
			$anchor_count = (int) ( $anchor_orders[ $anchor ] ?? 0 );
			if ( $anchor_count <= 0 ) {
				continue;
			}

			$scored = array();
			foreach ( $recommendations as $recommended => $co_occurrence ) {
				if ( $co_occurrence < self::MIN_CO_OCCURRENCE ) {
					continue;
				}
				$confidence = $co_occurrence / $anchor_count;
				$scored[]   = array(
					'anchor_product_id'      => (int) $anchor,
					'recommended_product_id' => (int) $recommended,
					'co_occurrence_count'    => (int) $co_occurrence,
					'anchor_order_count'     => $anchor_count,
					'score'                  => $confidence * log( 1 + $co_occurrence ),
				);
			}

			usort( $scored, static fn ( array $a, array $b ): int => $b['score'] <=> $a['score'] );
			foreach ( array_slice( $scored, 0, max( 1, $limit_per_anchor ) ) as $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Default HPOS-safe orders provider using the WooCommerce CRUD.
	 *
	 * @param int $window_days Trailing window.
	 * @return iterable<array<int, int>>
	 */
	private function wc_orders_provider( int $window_days ): iterable {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$day   = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$after = time() - ( max( 1, $window_days ) * $day );
		$page  = 1;
		$per   = 200;

		do {
			$orders = wc_get_orders(
				array(
					'status'       => array( 'wc-completed', 'wc-processing' ),
					'date_created' => '>' . $after,
					'limit'        => $per,
					'paged'        => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'return'       => 'objects',
				)
			);

			$batch_size = is_array( $orders ) ? count( $orders ) : 0;
			if ( 0 === $batch_size ) {
				return;
			}

			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
					continue;
				}

				$product_ids = array();
				foreach ( $order->get_items() as $item ) {
					if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
						$product_ids[] = (int) $item->get_product_id();
					}
				}
				yield $product_ids;
			}

			++$page;
		} while ( $batch_size === $per );
	}
}
