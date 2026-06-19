<?php
/**
 * Phase 1 bootstrap foundation tests.
 *
 * @package UpsellBay\Tests
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


use WPAnchorBay\UpsellBay\Core\Constants;
use WPAnchorBay\UpsellBay\Core\Container;
use WPAnchorBay\UpsellBay\Core\Installer;
use WPAnchorBay\UpsellBay\Core\Platform;
use WPAnchorBay\UpsellBay\Core\Scheduler;
use WPAnchorBay\UpsellBay\Core\Settings;
use WPAnchorBay\UpsellBay\Integrations\Licensing\LicenseClient;
use WPAnchorBay\UpsellBay\Utils\Logger;
use WPAnchorBay\UpsellBay\Utils\RateLimiter;
use WPAnchorBay\UpsellBay\Utils\TokenHelper;

/**
 * Returns test cases.
 *
 * @since 1.0.0
 *
 * @return array<string, callable>
 */
function upsellbay_foundation_tests(): array {
	return array(
		'constants expose required identifiers'          => static function (): void {
			assert_same( 'upsellbay', Constants::PLUGIN_SLUG );
			assert_same( 'upsellbay', Constants::TEXT_DOMAIN );
			assert_same( 'upsellbay/v1', Constants::REST_NAMESPACE );
			assert_same( 'upsellbay_offer', Constants::OFFER_POST_TYPE );
			assert_same( 'upsellbay_offer_stats_daily', Constants::STATS_TABLE_SUFFIX );
			assert_same( 'upsellbay_settings', Constants::SETTINGS_OPTION );
			assert_same( '_ub_', Constants::META_PREFIX );
			assert_same( 'upsellbay-', Constants::ASSET_HANDLE_PREFIX );
		},
		'container resolves singleton factories once'    => static function (): void {
			$container = new Container();
			$calls     = 0;

			$container->set(
				'service',
				static function () use ( &$calls ): object {
					++$calls;
					return (object) array( 'id' => $calls );
				}
			);

			$first  = $container->get( 'service' );
			$second = $container->get( 'service' );

			assert_true( $first === $second );
			assert_same( 1, $calls );
		},
		'container reports unknown services clearly'     => static function (): void {
			$container = new Container();

			assert_false( $container->has( 'missing' ) );

			try {
				$container->get( 'missing' );
			} catch ( InvalidArgumentException $exception ) {
				assert_contains( 'missing', $exception->getMessage() );
				return;
			}

			throw new RuntimeException( 'Expected missing service exception.' );
		},
		'settings normalize missing and invalid values'  => static function (): void {
			$settings = new Settings(
				static fn (): array => array(
					'enabled'        => '0',
					'test_mode'      => 'yes',
					'retention_days' => -5,
					'placements'     => array(
						'checkout_bump' => '1',
						'unknown'       => '1',
					),
					'debug_logging'  => 1,
				),
				static fn ( array $value ): bool => true
			);

			$normalized = $settings->all();

			assert_false( $normalized['enabled'] );
			assert_true( $normalized['test_mode'] );
			assert_same( 365, $normalized['retention_days'] );
			assert_true( $normalized['placements']['checkout_bump'] );
			assert_false( isset( $normalized['placements']['unknown'] ) );
			assert_true( $normalized['debug_logging'] );
			assert_same( '#3858e9', $normalized['style_tokens']['accent_color'] );
			assert_same( 'unknown', $normalized['license']['status'] );
		},
		'platform dependency checks return actionable failures' => static function (): void {
			$result = Platform::check(
				'8.0.0',
				'6.8',
				false,
				array(
					'wc_get_order'   => false,
					'wc_get_product' => true,
				)
			);

			assert_false( $result['ok'] );
			assert_same( 3, count( $result['errors'] ) );
			assert_contains( 'PHP', $result['errors'][0] );
			assert_contains( 'WordPress', $result['errors'][1] );
			assert_contains( 'WooCommerce', $result['errors'][2] );
		},
		'scheduler deduplicates actions by group'        => static function (): void {
			$scheduled = array();
			$scheduler = new Scheduler(
				static function ( string $hook, array $args, string $group ) use ( &$scheduled ): bool {
					return isset( $scheduled[ $group . ':' . $hook ] );
				},
				static function ( int $timestamp, string $recurrence, string $hook, array $args, string $group ) use ( &$scheduled ): void {
					$scheduled[ $group . ':' . $hook ] = array( $timestamp, $recurrence, $args );
				},
				static function ( string $hook, array $args, string $group ) use ( &$scheduled ): void {
					unset( $scheduled[ $group . ':' . $hook ] );
				}
			);

			$scheduler->ensure_recurring_jobs();
			$scheduler->ensure_recurring_jobs();

			assert_same( 4, count( $scheduled ) );
			assert_true( isset( $scheduled[ Constants::ACTION_SCHEDULER_GROUP . ':upsellbay_refresh_analytics' ] ) );
			assert_true( isset( $scheduled[ Constants::ACTION_SCHEDULER_GROUP . ':upsellbay_prune_logs' ] ) );

			$scheduler->unschedule_all();
			assert_same( 0, count( $scheduled ) );
		},
		'installer creates schema and schedules jobs idempotently' => static function (): void {
			$schema_runs = 0;
			$defaults    = 0;
			$schedules   = 0;
			$scheduler   = new Scheduler(
				static fn (): bool => false,
				static function () use ( &$schedules ): void {
					++$schedules;
				},
				static function (): void {
				}
			);
			$settings    = new Settings(
				static fn (): array => array(),
				static function () use ( &$defaults ): bool {
					++$defaults;
					return true;
				}
			);
			$installer   = new Installer(
				$settings,
				$scheduler,
				static function () use ( &$schema_runs ): void {
					++$schema_runs;
				}
			);

			$installer->activate();

			assert_same( 1, $schema_runs );
			assert_same( 1, $defaults );
			assert_same( 4, $schedules );
		},
		'license client masks keys and fails open on cached valid status' => static function (): void {
			$client = new LicenseClient(
				static fn (): array => array(
					'status'     => 'valid',
					'checked_at' => time() - 60,
				),
				static fn ( array $state ): bool => true
			);

			assert_same( 'ab****yz', $client->mask_key( 'abcdef1234567890xyz' ) );
			assert_true( $client->can_show_live_offers( array( 'transport_error' => true ) ) );
			assert_true( $client->is_non_production_domain( 'store.test' ) );
			assert_true( $client->is_non_production_domain( 'example.local' ) );
			assert_false( $client->is_non_production_domain( 'example.com' ) );
			},
			'license activation rejects invalid server responses without storing active state' => static function (): void {
				$stored                                                    = array();
				$GLOBALS['upsellbay_test_transients']['upsellbay_license_valid'] = true;
				$client = new LicenseClient(
					static fn (): array => $stored,
					static function ( array $state ) use ( &$stored ): bool {
						$stored = $state;
					return true;
				},
				static fn (): string => 'store.test',
				static fn (): array => array(
					'response' => array( 'code' => 404 ),
					'body'     => wp_json_encode(
						array(
							'success' => false,
							'error'   => array(
								'code'    => 'invalid_license',
								'message' => 'The provided license key does not exist.',
							),
						)
					),
				)
			);

			$result = $client->activate( 'WPAB-FAKEKEY-DOESNOTEXIST' );

				assert_true( is_wp_error( $result ) );
				assert_same( array(), $stored );
				assert_false( isset( $GLOBALS['upsellbay_test_transients']['upsellbay_license_valid'] ) );
			},
			'license activation requires a valid server license field before storing active state' => static function (): void {
				$stored = array();
				$client = new LicenseClient(
					static fn (): array => $stored,
					static function ( array $state ) use ( &$stored ): bool {
						$stored = $state;
						return true;
					},
					static fn (): string => 'store.test',
					static fn (): array => array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'success' => true,
								'license' => 'invalid',
							)
						),
					)
				);

				$result = $client->activate( 'WPAB-FAKEKEY-DOESNOTEXIST' );

				assert_true( is_wp_error( $result ) );
				assert_same( array(), $stored );
			},
			'license activation rejection corrects a previously stored matching false key' => static function (): void {
				$stored = array(
					'key'    => 'WPAB-FAKEKEY-DOESNOTEXIST',
					'status' => 'active',
				);
			$client = new LicenseClient(
				static fn (): array => $stored,
				static function ( array $state ) use ( &$stored ): bool {
					$stored = $state;
					return true;
				},
				static fn (): string => 'store.test',
				static fn (): array => array(
					'response' => array( 'code' => 404 ),
					'body'     => wp_json_encode(
						array(
							'success' => false,
							'error'   => array(
								'code'    => 'invalid_license',
								'message' => 'The provided license key does not exist.',
							),
						)
					),
				)
			);

			$result = $client->activate( 'WPAB-FAKEKEY-DOESNOTEXIST' );

			assert_true( is_wp_error( $result ) );
			assert_same( 'invalid', $stored['status'] );
			assert_same( 'WPAB-FAKEKEY-DOESNOTEXIST', $stored['key'] );
		},
		'license activation persists active state only after valid server response' => static function (): void {
			$stored       = array();
			$request_body = array();
			$client       = new LicenseClient(
				static fn (): array => $stored,
				static function ( array $state ) use ( &$stored ): bool {
					$stored = $state;
					return true;
				},
				static fn (): string => 'store.test',
				static function ( string $url, array $args ) use ( &$request_body ): array {
					unset( $url );
					$request_body = json_decode( (string) ( $args['body'] ?? '' ), true );

					return array(
						'response' => array( 'code' => 200 ),
						'body'     => wp_json_encode(
							array(
								'success'    => true,
								'message'    => 'License activated successfully.',
								'license'    => 'valid',
								'expires_at' => '2026-12-31 23:59:59',
							)
						),
					);
				}
			);

			$result = $client->activate( 'WPAB-VALIDKEY-123456789012' );

			assert_true( true === $result );
			assert_same( 'WPAB-VALIDKEY-123456789012', $request_body['license_key'] );
			assert_same( 'upsellbay', $request_body['slug'] );
			assert_same( 'store.test', $request_body['domain'] );
			assert_same( 'active', $stored['status'] );
			assert_same( 'WPAB-VALIDKEY-123456789012', $stored['key'] );
			assert_same( '2026-12-31 23:59:59', $stored['expires_at'] );
		},
		'token helper hashes random tokens without exposing raw values' => static function (): void {
			$helper = new TokenHelper();
			$token  = $helper->generate( 32 );
			$hash   = $helper->hash( $token );

			assert_same( 32, strlen( $token ) );
			assert_same( 64, strlen( $hash ) );
			assert_true( $helper->verify( $token, $hash ) );
			assert_false( $helper->verify( $token . 'x', $hash ) );
		},
		'rate limiter blocks after threshold within ttl' => static function (): void {
			$store   = array();
			$limiter = new RateLimiter(
				static function ( string $key ) use ( &$store ): ?array {
					return $store[ $key ] ?? null;
				},
				static function ( string $key, array $value, int $ttl ) use ( &$store ): void {
					$store[ $key ] = $value + array( 'ttl' => $ttl );
				},
				static fn (): int => 1000
			);

			assert_true( $limiter->hit( 'dismiss', '127.0.0.1', 2, 60 ) );
			assert_true( $limiter->hit( 'dismiss', '127.0.0.1', 2, 60 ) );
			assert_false( $limiter->hit( 'dismiss', '127.0.0.1', 2, 60 ) );
		},
		'logger masks secrets and personal identifiers'  => static function (): void {
			$logger = new Logger( null, true );
			$output = $logger->mask( 'license abcdef1234567890 and test@example.com token=tok_123456789' );

			assert_false( str_contains( $output, 'abcdef1234567890' ) );
			assert_false( str_contains( $output, 'test@example.com' ) );
			assert_false( str_contains( $output, 'tok_123456789' ) );
		},
	);
}

/**
 * Assert strict equality.
 *
 * @since 1.0.0
 *
 * @param mixed $expected Expected value.
 * @param mixed $actual   Actual value.
 */
function assert_same( $expected, $actual ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

/**
 * Assert true.
 *
 * @since 1.0.0
 *
 * @param mixed $value Value to check.
 */
function assert_true( $value ): void {
	assert_same( true, $value );
}

/**
 * Assert false.
 *
 * @since 1.0.0
 *
 * @param mixed $value Value to check.
 */
function assert_false( $value ): void {
	assert_same( false, $value );
}

/**
 * Assert substring exists.
 *
 * @since 1.0.0
 *
 * @param string $needle   Needle.
 * @param string $haystack Haystack.
 */
function assert_contains( string $needle, string $haystack ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( "Expected '{$haystack}' to contain '{$needle}'" );
	}
}
