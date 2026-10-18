<?php
/**
 * Geo Commerce — extend Geo Core visitor payload with Woo hints (no duplicate geo).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges commerce context into `rwgc_geo_data` for themes and APIs.
 */
class RWGCM_Geo {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_geo_data', array( __CLASS__, 'merge_commerce_hints' ), 20, 1 );
	}

	/**
	 * @param array<string, mixed> $data Visitor geo payload.
	 * @return array<string, mixed>
	 */
	public static function merge_commerce_hints( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( ! function_exists( 'WC' ) ) {
			return $data;
		}

		$wc = WC();
		if ( ! is_object( $wc ) ) {
			return $data;
		}

		// Geo Core resolves the visitor during plugins_loaded/init (cache-vary
		// cookie, widget registration calling get_woocommerce_currency()).
		// Reading the cart then triggers WooCommerce's "get_cart was called
		// incorrectly" notice and loads the session early, so the cart count is
		// only added once wp_loaded has run. Geo Core applies rwgc_geo_data on
		// every resolve, so later calls still get the count.
		if ( ! self::cart_is_ready() ) {
			return apply_filters( 'rwgcm_geo_data', $data );
		}

		$cart = $wc->cart;
		if ( $cart && is_object( $cart ) && method_exists( $cart, 'get_cart_contents_count' ) ) {
			$data['rwgc_commerce_cart_items'] = (int) $cart->get_cart_contents_count();
		}

		/**
		 * Filter commerce fields merged into Geo Core visitor data.
		 *
		 * @param array<string, mixed> $data Full geo payload after merge.
		 */
		return apply_filters( 'rwgcm_geo_data', $data );
	}

	/**
	 * True once WooCommerce allows cart access (after wp_loaded).
	 *
	 * @return bool
	 */
	public static function cart_is_ready() {
		return function_exists( 'did_action' ) && did_action( 'wp_loaded' ) > 0;
	}
}
