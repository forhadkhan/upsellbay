<?php
/**
 * Offer CPT repository.
 *
 * @package UpsellBay\Data
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Data;

use RuntimeException;
use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Hooks;
use WPAnchorBay\UpsellBay\Domain\Offers\OfferValidator;

/**
 * Encapsulates private offer CPT and `_ub_` offer meta access.
 *
 * @since 1.0.0
 */
final class OfferRepository {
	/**
	 * Offer validator.
	 *
	 * @since 1.0.0
	 *
	 * @var OfferValidator
	 */
	private OfferValidator $validator;

	/**
	 * Post insert callback.
	 *
	 * @var callable(array<string, mixed>): int
	 */
	private $insert_post;

	/**
	 * Post update callback.
	 *
	 * @var callable(int, array<string, mixed>): bool
	 */
	private $update_post;

	/**
	 * Post read callback.
	 *
	 * @var callable(int): ?array<string, mixed>
	 */
	private $get_post;

	/**
	 * Post query callback.
	 *
	 * @var callable(array<string, mixed>): array<int, array<string, mixed>>
	 */
	private $query_posts;

	/**
	 * Meta update callback.
	 *
	 * @var callable(int, string, mixed): bool
	 */
	private $update_meta;

	/**
	 * Meta read callback.
	 *
	 * @var callable(int, string): mixed
	 */
	private $get_meta;

	/**
	 * Post trash callback.
	 *
	 * @var callable(int): bool
	 */
	private $trash_post;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OfferValidator $validator   Offer validator.
	 * @param callable|null  $insert_post Post insert callback.
	 * @param callable|null  $update_post Post update callback.
	 * @param callable|null  $get_post    Post read callback.
	 * @param callable|null  $query_posts Post query callback.
	 * @param callable|null  $update_meta Meta update callback.
	 * @param callable|null  $get_meta    Meta read callback.
	 * @param callable|null  $trash_post  Post trash callback.
	 */
	public function __construct(
		OfferValidator $validator,
		?callable $insert_post = null,
		?callable $update_post = null,
		?callable $get_post = null,
		?callable $query_posts = null,
		?callable $update_meta = null,
		?callable $get_meta = null,
		?callable $trash_post = null
	) {
		$this->validator   = $validator;
		$this->insert_post = $insert_post ?? array( $this, 'wp_insert_offer' );
		$this->update_post = $update_post ?? array( $this, 'wp_update_offer' );
		$this->get_post    = $get_post ?? array( $this, 'wp_get_offer' );
		$this->query_posts = $query_posts ?? array( $this, 'wp_query_offers' );
		$this->update_meta = $update_meta ?? array( $this, 'wp_update_meta' );
		$this->get_meta    = $get_meta ?? array( $this, 'wp_get_meta' );
		$this->trash_post  = $trash_post ?? array( $this, 'wp_trash_offer' );
	}

	/**
	 * Create an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $offer Offer data.
	 */
	public function create( array $offer ): int {
		$meta       = $this->normalize_meta( $offer['meta'] ?? array() );
		$post_title = (string) ( $offer['title'] ?? '' );
		$post_id    = ( $this->insert_post )(
			array(
				'post_type'   => Constants::OFFER_POST_TYPE,
				'post_title'  => $post_title,
				'post_status' => 'publish',
				'menu_order'  => $meta['_ub_priority'],
			)
		);

		foreach ( $meta as $key => $value ) {
			( $this->update_meta )( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Update an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $offer_id Offer ID.
	 * @param array<string, mixed> $offer    Offer data.
	 */
	public function update( int $offer_id, array $offer ): bool {
		$meta = $this->normalize_meta( $offer['meta'] ?? array() );
		foreach ( $meta as $key => $value ) {
			( $this->update_meta )( $offer_id, $key, $value );
		}

		return ( $this->update_post )(
			$offer_id,
			array(
				'ID'         => $offer_id,
				'post_title' => (string) ( $offer['title'] ?? '' ),
				'menu_order' => $meta['_ub_priority'],
			)
		);
	}

	/**
	 * Duplicate an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @throws RuntimeException When the source offer is missing.
	 */
	public function duplicate( int $offer_id ): int {
		$offer = $this->get( $offer_id );
		if ( null === $offer ) {
			throw new RuntimeException( 'Offer not found.' );
		}

		return $this->create(
			array(
				'title' => trim( (string) $offer['title'] . ' Copy' ),
				'meta'  => $offer['meta'],
			)
		);
	}

	/**
	 * Pause an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function pause( int $offer_id ): bool {
		return ( $this->update_meta )( $offer_id, '_ub_status', 'paused' );
	}

	/**
	 * Activate an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function activate( int $offer_id ): bool {
		return ( $this->update_meta )( $offer_id, '_ub_status', 'active' );
	}

	/**
	 * Trash an offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 */
	public function trash( int $offer_id ): bool {
		return ( $this->trash_post )( $offer_id );
	}

	/**
	 * Load a normalized offer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offer_id Offer ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $offer_id ): ?array {
		$post = ( $this->get_post )( $offer_id );
		if ( null === $post ) {
			return null;
		}

		$meta = array();
		foreach ( array_keys( $this->validator->normalize( array() ) ) as $key ) {
			$value = ( $this->get_meta )( $offer_id, $key );
			if ( null !== $value ) {
				$meta[ $key ] = $value;
			}
		}

		return array(
			'id'     => $offer_id,
			'title'  => (string) ( $post['post_title'] ?? $post['title'] ?? '' ),
			'status' => (string) ( $post['post_status'] ?? '' ),
			'meta'   => $this->validator->normalize( $meta ),
		);
	}

	/**
	 * Query offers by supported filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $filters Query filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function query( array $filters = array() ): array {
		$query_args = array(
			'post_type'      => Constants::OFFER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => isset( $filters['limit'] ) ? (int) $filters['limit'] : 50,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		/**
		 * Filter private offer CPT query arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $query_args WP_Query-compatible arguments.
		 * @param array<string, mixed> $filters    Normalized repository filters.
		 */
		$query_args = Hooks::filter( 'offer_query_args', $query_args, $filters );
		$posts      = ( $this->query_posts )( $query_args );
		$offers     = array();

		foreach ( $posts as $post ) {
			$offer_id = (int) ( $post['id'] ?? $post['ID'] ?? 0 );
			$offer    = $this->get( $offer_id );
			if ( null !== $offer ) {
				$offers[] = $offer;
			}
		}

		return $offers;
	}

	/**
	 * Normalize and validate meta.
	 *
	 * @param array<string, mixed> $meta Raw meta.
	 * @return array<string, mixed>
	 * @throws RuntimeException When offer meta is invalid.
	 */
	private function normalize_meta( array $meta ): array {
		$result = $this->validator->validate( $meta );
		if ( ! $result->is_valid() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( implode( ' ', $result->errors() ) );
		}

		return $result->data();
	}

	/**
	 * WordPress insert adapter.
	 *
	 * @param array<string, mixed> $post_data Post data.
	 */
	private function wp_insert_offer( array $post_data ): int {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return 0;
		}

		return (int) wp_insert_post( $post_data, true );
	}

	/**
	 * WordPress update adapter.
	 *
	 * @param int                  $post_id   Post ID.
	 * @param array<string, mixed> $post_data Post data.
	 */
	private function wp_update_offer( int $post_id, array $post_data ): bool {
		if ( ! function_exists( 'wp_update_post' ) ) {
			return false;
		}

		$post_data['ID'] = $post_id;
		return (bool) wp_update_post( $post_data, true );
	}

	/**
	 * WordPress get adapter.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function wp_get_offer( int $post_id ): ?array {
		if ( ! function_exists( 'get_post' ) ) {
			return null;
		}

		$post = get_post( $post_id, ARRAY_A );
		return is_array( $post ) && Constants::OFFER_POST_TYPE === ( $post['post_type'] ?? null ) ? $post : null;
	}

	/**
	 * WordPress query adapter.
	 *
	 * @param array<string, mixed> $query Query args.
	 * @return array<int, array<string, mixed>>
	 */
	private function wp_query_offers( array $query ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$query['fields'] = 'all';
		$posts           = get_posts( $query );

		return array_map(
			static fn ( $post ): array => (array) $post,
			$posts
		);
	}

	/**
	 * WordPress offer meta update adapter.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 */
	private function wp_update_meta( int $post_id, string $key, $value ): bool {
		return function_exists( 'update_post_meta' ) && false !== update_post_meta( $post_id, $key, $value );
	}

	/**
	 * WordPress offer meta read adapter.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return mixed
	 */
	private function wp_get_meta( int $post_id, string $key ) {
		return function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $key, true ) : null;
	}

	/**
	 * WordPress trash adapter.
	 *
	 * @param int $post_id Post ID.
	 */
	private function wp_trash_offer( int $post_id ): bool {
		return function_exists( 'wp_trash_post' ) && false !== wp_trash_post( $post_id );
	}
}
