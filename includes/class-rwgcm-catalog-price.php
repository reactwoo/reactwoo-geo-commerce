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
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_product_price' ), 99, 2 );
	}

	/**
	 * True when the geo rule should change this product's displayed price.
	 *
	 * A fixed book price in the active currency replaces the rule. When the
	 * product has no book price in the visitor currency, the cart charges the
	 * store currency and applies the rule there, so catalog display must too.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return bool
	 */
	public static function adjustment_applies( $product ) {
		if ( class_exists( 'RWGCM_Price_Book_Apply', false ) && RWGCM_Price_Book_Apply::allow_base_adjustment( $product ) ) {
			return true;
		}
		return self::charged_in_base_currency( $product );
	}

	/**
	 * Visitor currency is not the store currency, and this product is not priced
	 * in it, so the cart falls back to the store currency and geo-adjusts that amount.
	 *
	 * @param object $product Product.
	 * @return bool
	 */
	public static function charged_in_base_currency( $product ) {
		if ( ! class_exists( 'RWGCM_Price_Book_Currency', false ) || ! class_exists( 'RWGCM_Price_Book', false ) ) {
			return false;
		}
		if ( ! function_exists( 'get_woocommerce_currency' ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return false;
		}
		$active = RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
		$base   = RWGCM_Price_Book::normalize_currency( RWGCM_Price_Book_Currency::base_currency() );
		if ( '' === $active || '' === $base || $active === $base ) {
			return false;
		}
		$book = RWGCM_Price_Book_Currency::get_book( (int) $product->get_id() );
		if ( RWGCM_Price_Book::has_price( $book, $active ) ) {
			return false;
		}
		return RWGCM_Price_Book::should_apply_base_adjustment(
			$base,
			$base,
			RWGCM_Price_Book::has_price( $book, $base ),
			RWGCM_Price_Book::base_adjustments_enabled()
		);
	}

	/**
	 * @param string      $price   Price string from WC.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function filter_product_price( $price, $product ) {
		if ( ! class_exists( 'RWGCM_Pricing_Resolution', false ) || ! RWGCM_Pricing_Resolution::is_pricing_effective() ) {
			return $price;
		}
		if ( ( function_exists( 'is_admin' ) && is_admin() && ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) ) || ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::forces_store_currency() ) ) {
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
		if ( ! self::adjustment_applies( $product ) ) {
			return $price;
		}

		$rule = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
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
