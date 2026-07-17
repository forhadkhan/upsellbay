<?php
/**
 * First-run wizard shell.
 *
 * @package UpsellBay\Templates\Admin
 */

defined( 'ABSPATH' ) || exit;

$upsellbay_help_tip = static function ( string $text ): string {
	if ( function_exists( 'wc_help_tip' ) ) {
		return wc_help_tip( $text, false );
	}

	return '<span class="description">' . esc_html( $text ) . '</span>';
};
?>
<div class="upsellbay-wizard">
	<?php if ( isset( $result ) && is_array( $result ) ) : ?>
		<?php if ( ! empty( $result['success'] ) ) : ?>
			<div class="notice notice-success inline is-dismissible" style="margin-bottom: 20px;">
				<p>
					<?php echo esc_html( $result['message'] ); ?>
					<?php if ( ! empty( $result['offer_id'] ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=upsellbay-pro&tab=offers&action=edit&offer_id=' . (int) $result['offer_id'] ) ); ?>" style="margin-left: 10px;" class="button button-small"><?php esc_html_e( 'View / Edit Offer', 'upsellbay' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-error inline is-dismissible" style="margin-bottom: 20px;">
				<p><?php echo esc_html( $result['message'] ?? __( 'An error occurred.', 'upsellbay' ) ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" class="upsellbay-wizard__form">
		<?php wp_nonce_field( 'upsellbay_pro_wizard', 'nonce' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="upsellbay-wizard-placement"><?php esc_html_e( 'Placement', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Start with the offer location you want to test first. You can change placement settings later in the offer editor.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td>
						<select id="upsellbay-wizard-placement" name="placement">
							<option value="checkout_bump"><?php esc_html_e( 'Checkout bump', 'upsellbay' ); ?></option>
							<option value="product_upsell"><?php esc_html_e( 'Product page offer', 'upsellbay' ); ?></option>
							<option value="cart_crosssell"><?php esc_html_e( 'Cart offer', 'upsellbay' ); ?></option>
							<option value="thankyou_offer"><?php esc_html_e( 'Thank-you follow-on offer', 'upsellbay' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upsellbay-wizard-product-search"><?php esc_html_e( 'Offer product', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Choose the product shoppers can add from the first offer. The draft is not shown to shoppers until published.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td>
						<div class="upsellbay-product-selector" data-upsellbay-product-selector>
							<div class="upsellbay-product-selector__input-wrapper">
								<input id="upsellbay-wizard-product-search" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'Search for a product...', 'upsellbay' ); ?>" autocomplete="off">
								<button type="button" class="upsellbay-product-selector__clear" style="display: none;" title="<?php esc_attr_e( 'Clear search', 'upsellbay' ); ?>">&times;</button>
							</div>
							<input type="hidden" id="upsellbay-wizard-product" name="offer_product_id" required>
							<div class="upsellbay-product-selector__results" data-upsellbay-results></div>
							<div class="upsellbay-product-selector__selection" data-upsellbay-selection></div>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upsellbay-wizard-headline"><?php esc_html_e( 'Headline', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Keep this short and specific. It appears next to the offer product in the selected placement.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td><input id="upsellbay-wizard-headline" name="headline" type="text" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="upsellbay-wizard-discount-type"><?php esc_html_e( 'Discount', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Optionally offer a discount to encourage shoppers to accept.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td>
						<select id="upsellbay-wizard-discount-type" name="discount_type" style="vertical-align: top;">
							<option value="none"><?php esc_html_e( 'No discount', 'upsellbay' ); ?></option>
							<option value="percent"><?php esc_html_e( 'Percentage (%)', 'upsellbay' ); ?></option>
							<option value="fixed_amount"><?php esc_html_e( 'Fixed amount', 'upsellbay' ); ?></option>
							<option value="fixed_price"><?php esc_html_e( 'Fixed price', 'upsellbay' ); ?></option>
						</select>
						<input type="text" name="discount_value" class="regular-text" placeholder="0" style="width: 100px; vertical-align: top;" aria-label="<?php esc_attr_e( 'Discount value', 'upsellbay' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="upsellbay-_ub_rules"><?php esc_html_e( 'Targeting rule', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Optionally restrict when this offer appears. You can add more rules later.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td>
						<textarea id="upsellbay-_ub_rules" name="_ub_rules" class="large-text code" rows="5" style="display: none;">[]</textarea>
						<div id="upsellbay-builder-_ub_rules" class="upsellbay-builder"></div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Preview first', 'upsellbay' ); ?> <?php echo $upsellbay_help_tip( __( 'Test mode is admin-only and helps verify the offer before live shoppers can see it.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td>
						<label>
							<input name="enable_test_mode" type="checkbox" value="1" checked>
							<?php esc_html_e( 'Enable test mode so only admins can preview the offer before publishing.', 'upsellbay' ); ?>
						</label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Create draft offer', 'upsellbay' ) ); ?>
	</form>
</div>
