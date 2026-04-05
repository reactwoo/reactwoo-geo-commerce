<?php
/**
 * Shared deterministic pricing math (meta-based base; avoids filter recursion).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base unit price + rule application for cart and catalog.
 */
class RWGCM_Pricing_Calc {

	/**
	 * Visitor ISO2 from Geo Core (empty if unknown).
	 *
	 * @return string
	 */
	public static function get_visitor_country() {
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return '';
		}
		return strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
	}

	/**
	 * Raw catalog base from product data (regular price, then stored price). Uses WC getters with
	 * context `edit` so we do not call get_meta() on internal keys (WC 3.2+) and we avoid
	 * re-entering `woocommerce_product_get_price` while that filter is running.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return float
	 */
	public static function get_base_unit_price( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return 0.0;
		}
		$regular = $product->get_regular_price( 'edit' );
		if ( '' === $regular || null === $regular ) {
			$regular = $product->get_price( 'edit' );
		}
		$base = floatval( $regular );
		return $base > 0 ? $base : 0.0;
	}

	/**
	 * @param float $base  Positive unit base.
	 * @param array $rule  Rule row.
	 * @return float
	 */
	public static function compute_adjusted( $base, $rule ) {
		$base = (float) $base;
		if ( $base <= 0 || ! is_array( $rule ) ) {
			return $base;
		}
		$new = $base;
		if ( isset( $rule['type'] ) && 'fixed_line' === $rule['type'] ) {
			$new = $base + (float) $rule['value'];
		} else {
			$new = $base * ( 1 + ( (float) $rule['value'] / 100 ) );
		}
		return max( 0, (float) wc_format_decimal( $new, wc_get_price_decimals() ) );
	}
}
