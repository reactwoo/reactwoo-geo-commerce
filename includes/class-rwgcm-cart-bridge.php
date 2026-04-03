<?php
/**
 * WooCommerce cart lifecycle hooks for geo-aware pricing rules (extension point only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires before Woo totals run so add-ons can adjust line items deterministically (not AI-driven).
 */
class RWGCM_Cart_Bridge {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'before_calculate_totals' ), 5, 1 );
	}

	/**
	 * @param \WC_Cart $cart Cart instance.
	 * @return void
	 */
	public static function before_calculate_totals( $cart ) {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}
		/**
		 * Geo Commerce: cart is about to calculate totals — attach geo-based pricing here (rule-based, deterministic).
		 *
		 * @param \WC_Cart $cart Cart.
		 */
		do_action( 'rwgcm_before_cart_totals', $cart );
	}
}
