<?php
/**
 * Merge saved fee rules into rwgcm_cart_fees.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs early on rwgcm_cart_fees so extensions can adjust merged rows later.
 */
class RWGCM_Fee_Rules_Apply {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgcm_cart_fees', array( __CLASS__, 'merge_saved_fees' ), 5, 2 );
	}

	/**
	 * @param array<int, array<string, mixed>> $fees Fee rows.
	 * @param \WC_Cart                        $cart Cart.
	 * @return array<int, array<string, mixed>>
	 */
	public static function merge_saved_fees( $fees, $cart ) {
		unset( $cart );
		if ( ! is_array( $fees ) ) {
			$fees = array();
		}
		if ( ! class_exists( 'RWGCM_Fee_Rules', false ) || ! RWGCM_Fee_Rules::is_enabled() ) {
			return $fees;
		}
		$country = '';
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$country = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( strlen( $country ) !== 2 && function_exists( 'rwgc_get_visitor_data' ) ) {
			$v = rwgc_get_visitor_data();
			if ( is_array( $v ) && isset( $v['country_code'] ) ) {
				$country = strtoupper( substr( sanitize_text_field( (string) $v['country_code'] ), 0, 2 ) );
			}
		}
		if ( strlen( $country ) !== 2 ) {
			return $fees;
		}
		$extra = RWGCM_Fee_Rules::get_rows_for_country( $country );
		if ( empty( $extra ) ) {
			return $fees;
		}
		foreach ( $extra as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$fees[] = $row;
		}
		return $fees;
	}
}
