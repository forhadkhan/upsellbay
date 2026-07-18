const { execSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

/**
 * Runs a PHP script against the real WordPress install via `wp eval-file`,
 * so tests can set up and verify state directly against the database rather
 * than only through what the browser shows (mirrors order-completion.spec.js).
 */
function runEval(env, phpBody) {
	if (!env.wpPath) {
		throw new Error('Set UPSELLBAY_E2E_WP_PATH to the WordPress install path.');
	}

	const scriptPath = path.join(os.tmpdir(), `upsellbay-e2e-${process.pid}-${Math.random().toString(36).slice(2)}.php`);
	fs.writeFileSync(scriptPath, `<?php\n${phpBody}`);

	try {
		return execSync(`wp eval-file ${scriptPath} --path=${env.wpPath}`, { encoding: 'utf-8' });
	} finally {
		fs.unlinkSync(scriptPath);
	}
}

/**
 * Creates a real, minimal, unrestricted offer post directly (bypassing the
 * admin form, which is already covered separately by admin-offer-editor.spec.js)
 * and returns its post ID. Callers are responsible for deleting it via
 * deleteOffer() once the test is done.
 *
 * @param {import('./env').Env} env
 * @param {object} overrides Meta overrides, e.g. { placement, productId, priority, discountType, discountValue }.
 */
function createOffer(env, overrides = {}) {
	const {
		placement = 'checkout_bump',
		productId,
		priority = 10,
		discountType = 'none',
		discountValue = '0',
		headline = 'UpsellBay E2E test offer',
	} = overrides;

	if (!productId) {
		throw new Error('createOffer requires a productId.');
	}

	const php = `
		$post_id = wp_insert_post( array(
			'post_type'   => 'upsellbay_offer',
			'post_status' => 'publish',
			'post_title'  => 'UpsellBay E2E offer',
		) );
		if ( is_wp_error( $post_id ) ) {
			echo json_encode( array( 'error' => $post_id->get_error_message() ) );
			exit;
		}
		update_post_meta( $post_id, '_ub_offer_type', ${JSON.stringify(placement)} );
		update_post_meta( $post_id, '_ub_status', 'active' );
		update_post_meta( $post_id, '_ub_offer_product_id', ${JSON.stringify(String(productId))} );
		update_post_meta( $post_id, '_ub_discount_type', ${JSON.stringify(discountType)} );
		update_post_meta( $post_id, '_ub_discount_value', ${JSON.stringify(String(discountValue))} );
		update_post_meta( $post_id, '_ub_headline', ${JSON.stringify(headline)} );
		update_post_meta( $post_id, '_ub_body', 'Created by pricing-integrity/conflict-resolution e2e tests.' );
		update_post_meta( $post_id, '_ub_button_text', 'Add to order' );
		update_post_meta( $post_id, '_ub_rules', array() );
		update_post_meta( $post_id, '_ub_rules_match', 'all' );
		update_post_meta( $post_id, '_ub_trigger_product_ids', array() );
		update_post_meta( $post_id, '_ub_trigger_category_ids', array() );
		update_post_meta( $post_id, '_ub_show_image', true );
		update_post_meta( $post_id, '_ub_hide_if_in_cart', true );
		update_post_meta( $post_id, '_ub_priority', ${JSON.stringify(String(priority))} );
		update_post_meta( $post_id, '_ub_start_at', '' );
		update_post_meta( $post_id, '_ub_end_at', '' );
		echo json_encode( array( 'id' => $post_id ) );
	`;

	const result = JSON.parse(runEval(env, php));
	if (result.error) {
		throw new Error(`createOffer failed: ${result.error}`);
	}
	return result.id;
}

/** Deletes an offer post created by createOffer(). Safe to call even if already gone. */
function deleteOffer(env, offerId) {
	if (!offerId) {
		return;
	}
	runEval(env, `wp_delete_post( ${JSON.stringify(offerId)}, true ); echo 'ok';`);
}

/**
 * Sets a product's regular price and returns its previous price, so a test
 * can pin a known price for deterministic total math and restore it after.
 */
function setProductPrice(env, productId, price) {
	const php = `
		$product = wc_get_product( ${JSON.stringify(productId)} );
		if ( ! $product ) {
			echo json_encode( array( 'error' => 'product not found' ) );
			exit;
		}
		$previous = $product->get_regular_price();
		$product->set_regular_price( ${JSON.stringify(String(price))} );
		$product->set_sale_price( '' );
		$product->save();
		echo json_encode( array( 'previous' => $previous ) );
	`;
	const result = JSON.parse(runEval(env, php));
	if (result.error) {
		throw new Error(`setProductPrice failed: ${result.error}`);
	}
	return result.previous;
}

/** Restores a product's regular price to a value captured by setProductPrice(). */
function restoreProductPrice(env, productId, price) {
	runEval(env, `
		$product = wc_get_product( ${JSON.stringify(productId)} );
		if ( $product ) {
			$product->set_regular_price( ${JSON.stringify(String(price))} );
			$product->save();
		}
		echo 'ok';
	`);
}

/** Creates a real WooCommerce coupon and returns its code. */
function createCoupon(env, { type, amount }) {
	const code = `e2etest${Date.now()}${Math.floor(Math.random() * 1000)}`;
	const discountType = type === 'percent' ? 'percent' : 'fixed_cart';
	const php = `
		$coupon = new WC_Coupon();
		$coupon->set_code( ${JSON.stringify(code)} );
		$coupon->set_discount_type( ${JSON.stringify(discountType)} );
		$coupon->set_amount( ${JSON.stringify(String(amount))} );
		$coupon->save();
		echo json_encode( array( 'id' => $coupon->get_id(), 'code' => $coupon->get_code() ) );
	`;
	const result = JSON.parse(runEval(env, php));
	return result.code;
}

/** Deletes a coupon by its code. */
function deleteCouponByCode(env, code) {
	if (!code) {
		return;
	}
	runEval(env, `
		$coupon = new WC_Coupon( ${JSON.stringify(code)} );
		if ( $coupon->get_id() ) {
			wp_delete_post( $coupon->get_id(), true );
		}
		echo 'ok';
	`);
}

module.exports = {
	runEval,
	createOffer,
	deleteOffer,
	setProductPrice,
	restoreProductPrice,
	createCoupon,
	deleteCouponByCode,
};
