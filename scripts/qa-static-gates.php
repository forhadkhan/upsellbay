<?php
/**
 * Static release gate scanner for Phase 7 QA.
 *
 * @package UpsellBay\Scripts
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


$root = dirname( __DIR__ );

$checks = array(
	'unsafe order metadata hooks' => array(
		'paths'  => array( 'app', 'upsellbay.php', 'uninstall.php' ),
		'tokens' => array(
			'woocommerce_checkout_update_order_meta',
			'woocommerce_new_order_item',
		),
	),
	'direct order/postmeta writes outside offer repository' => array(
		'paths'  => array( 'app/Domain', 'app/Api', 'app/Admin', 'upsellbay.php', 'uninstall.php' ),
		'tokens' => array(
			'update_post_meta',
			'get_post_meta',
			'add_post_meta',
			'delete_post_meta',
		),
	),
	'remote or obfuscated execution' => array(
		'paths'  => array( 'app', 'upsellbay.php', 'uninstall.php' ),
		'tokens' => array(
			'eval(',
			'base64_decode(',
			'create_function(',
			'gzinflate(',
		),
	),
);

/**
 * Collect files recursively.
 *
 * @param string $path Directory or file path.
 * @return array<int, string>
 */
function upsellbay_qa_static_collect_files( string $path ): array {
	if ( is_file( $path ) ) {
		return array( $path );
	}

	if ( ! is_dir( $path ) ) {
		return array();
	}

	$files    = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

$failures = array();

foreach ( $checks as $label => $check ) {
	foreach ( $check['paths'] as $relative_path ) {
		foreach ( upsellbay_qa_static_collect_files( $root . '/' . $relative_path ) as $path ) {
			$relative = ltrim( str_replace( $root, '', $path ), '/' );
			$content  = (string) file_get_contents( $path );

			foreach ( $check['tokens'] as $token ) {
				if ( str_contains( $content, $token ) ) {
					$failures[] = "{$relative}: {$label}: {$token}";
				}
			}
		}
	}
}

if ( array() !== $failures ) {
	echo "UpsellBay static release gates failed:\n";
	foreach ( $failures as $failure ) {
		echo "- {$failure}\n";
	}
	exit( 1 );
}

echo "UpsellBay static release gates passed.\n";
echo "Checks: " . implode( ', ', array_keys( $checks ) ) . "\n";
