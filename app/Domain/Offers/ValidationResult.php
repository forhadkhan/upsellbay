<?php
/**
 * Offer validation result.
 *
 * @package UpsellBay\Domain\Offers
 */

declare(strict_types=1);

namespace WPAnchorBay\UpsellBay\Domain\Offers;
// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}


/**
 * Carries normalized data and safe validation errors.
 *
 * @since 1.0.0
 */
final class ValidationResult {
	/**
	 * Whether validation passed.
	 *
	 * @var bool
	 */
	private bool $valid;

	/**
	 * Validation errors.
	 *
	 * @var array<string, string>
	 */
	private array $errors;

	/**
	 * Normalized data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param bool                 $valid  Whether validation passed.
	 * @param array<string,string> $errors Validation errors.
	 * @param array<string,mixed>  $data   Normalized data.
	 */
	public function __construct( bool $valid, array $errors = array(), array $data = array() ) {
		$this->valid  = $valid;
		$this->errors = $errors;
		$this->data   = $data;
	}

	/**
	 * Whether validation passed.
	 *
	 * @since 1.0.0
	 */
	public function is_valid(): bool {
		return $this->valid;
	}

	/**
	 * Return validation errors.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Return normalized data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		return $this->data;
	}
}
