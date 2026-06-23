<?php
/**
 * Color helper utility.
 *
 * @package UpsellBay\Utils
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility for color calculations.
 *
 * @since 1.0.0
 */
final class ColorHelper {
	/**
	 * Get a contrasting text color (white or dark gray) for a given hex background.
	 *
	 * Uses WCAG relative luminance to determine the best contrast.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex_color The background hex color.
	 * @return string The contrasting hex text color (#ffffff or #111111).
	 */
	public static function get_contrasting_text_color( string $hex_color ): string {
		$hex = ltrim( $hex_color, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
		}

		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff'; // Fallback to white.
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// Calculate relative luminance.
		$rsrgb = $r / 255;
		$gsrgb = $g / 255;
		$bsrgb = $b / 255;

		$r_c = $rsrgb <= 0.03928 ? $rsrgb / 12.92 : pow( ( $rsrgb + 0.055 ) / 1.055, 2.4 );
		$g_c = $gsrgb <= 0.03928 ? $gsrgb / 12.92 : pow( ( $gsrgb + 0.055 ) / 1.055, 2.4 );
		$b_c = $bsrgb <= 0.03928 ? $bsrgb / 12.92 : pow( ( $bsrgb + 0.055 ) / 1.055, 2.4 );

		$luminance = 0.2126 * $r_c + 0.7152 * $g_c + 0.0722 * $b_c;

		// If luminance is > 0.179, it's considered light enough to need dark text.
		return $luminance > 0.179 ? '#111111' : '#ffffff';
	}
}
