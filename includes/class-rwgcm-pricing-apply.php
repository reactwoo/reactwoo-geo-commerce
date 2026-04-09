<?php
/**
 * Apply country rules to cart line unit prices (before tax calculation).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks `rwgcm_before_cart_totals` and adjusts WC_Product line prices.
 */
class RWGCM_Pricing_Apply {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgcm_before_cart_totals', array( __CLASS__, 'apply_rules' ), 10, 1 );
	}

	/**
	 * @param \WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_rules( $cart ) {
		if ( ! class_exists( 'RWGCM_Pricing_Resolution', false ) || ! RWGCM_Pricing_Resolution::is_pricing_effective() ) {
			return;
		}
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) || $cart->is_empty() ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}
			/** @var \WC_Product $product */
			$product = $cart_item['data'];
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$skip_bundled   = ! empty( $cart_item['bundled_by'] );
			$skip_composite = ! empty( $cart_item['composite_parent'] ) || ! empty( $cart_item['composite_item'] );
			$default_skip   = $skip_bundled || $skip_composite;
			/**
			 * Skip geo pricing on this cart line (e.g. bundle children, composite components).
			 *
			 * @param bool        $skip      Default true when `bundled_by` or composite keys (`composite_parent` / `composite_item`) are set.
			 * @param array       $cart_item Cart line.
			 * @param \WC_Product $product   Product.
			 */
			if ( apply_filters( 'rwgcm_skip_pricing_for_cart_item', $default_skip, $cart_item, $product ) ) {
				continue;
			}

			$rule = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
			if ( null === $rule ) {
				continue;
			}

			$base = RWGCM_Pricing_Calc::get_base_unit_price( $product );
			if ( $base <= 0 ) {
				continue;
			}

			$new_price = RWGCM_Pricing_Calc::compute_adjusted( $base, $rule );

			/**
			 * Filter adjusted unit price before it is set on the cart product.
			 *
			 * @param float      $new_price Adjusted price.
			 * @param float      $base      Base unit price used for calculation.
			 * @param array      $rule      Rule row (country, type, value, category_ids).
			 * @param \WC_Product $product  Product object.
			 * @param array       $cart_item Cart line.
			 * @param string      $cart_item_key Key.
			 */
			$new_price = apply_filters( 'rwgcm_adjusted_unit_price', $new_price, $base, $rule, $product, $cart_item, $cart_item_key );

			$product->set_price( $new_price );
		}
	}
}
