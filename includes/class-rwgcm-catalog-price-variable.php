<?php
/**
 * Variable product: min/max and variation prices match geo pricing rules (storefront).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks WooCommerce variation price filters + cache hash so per-country rules do not share transients.
 */
class RWGCM_Catalog_Price_Variable {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'filter_price_hash' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_variation_price' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( __CLASS__, 'filter_variation_regular_price' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( __CLASS__, 'filter_variation_sale_price' ), 99, 3 );
	}

	/**
	 * Include visitor country in the variation price cache key when rules apply (WooCommerce recommendation).
	 *
	 * @param array      $price_hash Hash payload.
	 * @param \WC_Product $product   Variable product.
	 * @param bool       $for_display Display vs edit context.
	 * @return array
	 */
	public static function filter_price_hash( $price_hash, $product, $for_display ) {
		unset( $for_display );
		if ( ! RWGCM_Pricing_Rules::is_enabled() ) {
			return $price_hash;
		}
		if ( ! is_array( $price_hash ) ) {
			$price_hash = array();
		}
		if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_type( 'variable' ) ) {
			return $price_hash;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_hash;
		}
		$country = RWGCM_Pricing_Calc::get_visitor_country();
		$price_hash['rwgcm_visitor_country'] = strlen( $country ) === 2 ? $country : '';
		return $price_hash;
	}

	/**
	 * @param string|float $price     Active price (edit).
	 * @param \WC_Product  $variation Variation.
	 * @param \WC_Product  $parent    Variable parent.
	 * @return string
	 */
	public static function filter_variation_price( $price, $variation, $parent ) {
		unset( $parent );
		if ( ! self::guard( $variation ) ) {
			return $price;
		}
		$base = RWGCM_Pricing_Calc::get_base_unit_price( $variation );
		if ( $base <= 0 ) {
			return $price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param string|float $regular_price Regular price (edit).
	 * @param \WC_Product  $variation     Variation.
	 * @param \WC_Product  $parent        Variable parent.
	 * @return string
	 */
	public static function filter_variation_regular_price( $regular_price, $variation, $parent ) {
		unset( $parent );
		if ( ! self::guard( $variation ) ) {
			return $regular_price;
		}
		$base = floatval( $regular_price );
		if ( $base <= 0 ) {
			return $regular_price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param string|float $sale_price Sale price (edit).
	 * @param \WC_Product  $variation  Variation.
	 * @param \WC_Product  $parent     Variable parent.
	 * @return string
	 */
	public static function filter_variation_sale_price( $sale_price, $variation, $parent ) {
		unset( $parent );
		if ( '' === (string) $sale_price || null === $sale_price ) {
			return $sale_price;
		}
		if ( ! self::guard( $variation ) ) {
			return $sale_price;
		}
		$base = floatval( $sale_price );
		if ( $base <= 0 ) {
			return $sale_price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param \WC_Product $variation Variation.
	 * @return bool
	 */
	private static function guard( $variation ) {
		if ( ! RWGCM_Pricing_Rules::is_enabled() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( ! is_a( $variation, 'WC_Product_Variation' ) ) {
			return false;
		}
		if ( ! apply_filters( 'rwgcm_apply_catalog_price', true, $variation ) ) {
			return false;
		}
		$country = RWGCM_Pricing_Calc::get_visitor_country();
		if ( strlen( $country ) !== 2 ) {
			return false;
		}
		$rule = RWGCM_Pricing_Rules::find_matching_rule( $country, $variation );
		return null !== $rule;
	}

	/**
	 * @param float       $base      Positive amount.
	 * @param \WC_Product $variation Variation (for rule + filters).
	 * @param \WC_Product $product_for_filter Same as variation for filter API.
	 * @return string
	 */
	private static function format_adjusted( $base, $variation, $product_for_filter ) {
		$country = RWGCM_Pricing_Calc::get_visitor_country();
		$rule    = RWGCM_Pricing_Rules::find_matching_rule( $country, $variation );
		if ( null === $rule ) {
			return wc_format_decimal( $base, wc_get_price_decimals() );
		}
		$new_price = RWGCM_Pricing_Calc::compute_adjusted( (float) $base, $rule );
		$new_price = apply_filters( 'rwgcm_adjusted_unit_price', $new_price, (float) $base, $rule, $product_for_filter, array(), '' );
		return wc_format_decimal( $new_price, wc_get_price_decimals() );
	}
}
