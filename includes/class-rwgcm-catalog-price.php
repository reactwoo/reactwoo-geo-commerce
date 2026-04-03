<?php
/**
 * Storefront / loop price parity with cart rules (hooks only; skips wp-admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters `woocommerce_product_get_price` so shop and product pages match cart geo pricing.
 */
class RWGCM_Catalog_Price {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_product_price' ), 99, 2 );
	}

	/**
	 * @param string      $price   Price string from WC.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function filter_product_price( $price, $product ) {
		if ( ! RWGCM_Pricing_Rules::is_enabled() ) {
			return $price;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price;
		}
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $price;
		}
		if ( $product->is_type( 'variable' ) ) {
			return $price;
		}

		/**
		 * Allow disabling storefront adjustment (e.g. use cart-only pricing).
		 *
		 * @param bool        $apply   Default true.
		 * @param \WC_Product $product Product.
		 */
		if ( ! apply_filters( 'rwgcm_apply_catalog_price', true, $product ) ) {
			return $price;
		}

		$country = RWGCM_Pricing_Calc::get_visitor_country();
		if ( strlen( $country ) !== 2 ) {
			return $price;
		}

		$rule = RWGCM_Pricing_Rules::find_matching_rule( $country, $product );
		if ( null === $rule ) {
			return $price;
		}

		$base = RWGCM_Pricing_Calc::get_base_unit_price( $product );
		if ( $base <= 0 ) {
			return $price;
		}

		$new_price = RWGCM_Pricing_Calc::compute_adjusted( $base, $rule );

		/**
		 * Same filter as cart; cart_item empty on storefront.
		 *
		 * @param float       $new_price Adjusted price.
		 * @param float       $base      Meta-based base.
		 * @param array       $rule      Rule.
		 * @param \WC_Product $product   Product.
		 * @param array       $cart_item Empty on catalog.
		 * @param string      $cart_item_key Empty on catalog.
		 */
		$new_price = apply_filters( 'rwgcm_adjusted_unit_price', $new_price, $base, $rule, $product, array(), '' );

		return wc_format_decimal( $new_price, wc_get_price_decimals() );
	}
}
