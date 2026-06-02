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
	<form method="post" class="upsellbay-wizard__form">
		<?php wp_nonce_field( 'upsellbay_wizard', 'nonce' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><label for="upsellbay-wizard-product"><?php esc_html_e( 'Offer product', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Choose the product shoppers can add from the first offer. The draft is not shown to shoppers until published.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
				<td>
					<input id="upsellbay-wizard-product" name="offer_product_id" type="number" min="1" class="regular-text" required>
				</td>
			</tr>
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
					<th scope="row"><label for="upsellbay-wizard-headline"><?php esc_html_e( 'Headline', 'upsellbay' ); ?></label> <?php echo $upsellbay_help_tip( __( 'Keep this short and specific. It appears next to the offer product in the selected placement.', 'upsellbay' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $upsellbay_help_tip returns escaped WooCommerce help tip markup. ?></th>
					<td><input id="upsellbay-wizard-headline" name="headline" type="text" class="regular-text"></td>
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
