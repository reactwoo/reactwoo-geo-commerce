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
}
