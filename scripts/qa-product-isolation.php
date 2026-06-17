<?php
/**
 * Product isolation scanner for Phase 7 QA.
 *
 * @package UpsellBay\Scripts
 */

declare(strict_types=1);
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


$root = dirname( __DIR__ );

$scan_paths = array(
	'app',
	'assets',
	'src',
	'templates',
	'tests',
);

$allowed_files = array(
	'app/Admin/Coexistence.php',
	'app/Domain/Compatibility/CompatibilityScanner.php',
	'tests/test-admin-architecture.php',
	'tests/test-core-business-logic.php',
	'tests/test-merchant-experience.php',
	'tests/test-quality-assurance.php',
);

$forbidden_tokens = array(
	'cartbay_',
	'_cartbay_',
	'cartbay-',
	'WPAnchorBay\\\\CartBay',
	'WPAnchorBay\\CartBay',
);

$forbidden_recovery_terms = array(
	'recovery sequence',
	'recovery email template',
	'restore link',
	'unsubscribe flow',
	'abandoned cart recovery',
);

/**
 * Collect files recursively.
 *
 * @param string $path Directory or file path.
 * @return array<int, string>
 */
function upsellbay_qa_collect_files( string $path ): array {
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
		if ( $file->isFile() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

$failures = array();

foreach ( $scan_paths as $relative_path ) {
	foreach ( upsellbay_qa_collect_files( $root . '/' . $relative_path ) as $path ) {
		$relative = ltrim( str_replace( $root, '', $path ), '/' );
		$content  = (string) file_get_contents( $path );

		if ( in_array( $relative, $allowed_files, true ) ) {
			continue;
		}

		foreach ( $forbidden_tokens as $token ) {
			if ( str_contains( $content, $token ) ) {
				$failures[] = "{$relative}: forbidden token {$token}";
			}
		}

		foreach ( $forbidden_recovery_terms as $term ) {
			if ( str_contains( strtolower( $content ), $term ) ) {
				$failures[] = "{$relative}: prohibited recovery scope term {$term}";
			}
		}
	}
}

if ( array() !== $failures ) {
	echo "UpsellBay product isolation scan failed:\n";
	foreach ( $failures as $failure ) {
		echo "- {$failure}\n";
	}
	exit( 1 );
}

echo "UpsellBay product isolation scan passed.\n";
echo "Scanned: " . implode( ', ', $scan_paths ) . "\n";
echo "Allowed coexistence files: " . implode( ', ', $allowed_files ) . "\n";
