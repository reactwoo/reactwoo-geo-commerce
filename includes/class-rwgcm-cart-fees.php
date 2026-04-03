<?php
/**
 * Extension point for deterministic cart fees (geo-aware rules live in callbacks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies fee rows from the `rwgcm_cart_fees` filter.
 */
class RWGCM_Cart_Fees {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_fees' ), 15, 1 );
	}

	/**
	 * @param \WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_fees( $cart ) {
		if ( ! is_object( $cart ) || ! method_exists( $cart, 'add_fee' ) ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		/**
		 * Return fee rows to add to the cart. Each row: name (string), amount (float), taxable (bool, optional), tax_class (string, optional; WooCommerce slug, e.g. '' for Standard).
		 *
		 * @param list<array{name?: string, amount?: float, taxable?: bool, tax_class?: string}> $fees Default empty.
		 * @param \WC_Cart                                                                          $cart Cart.
		 */
		$fees = apply_filters( 'rwgcm_cart_fees', array(), $cart );
		if ( ! is_array( $fees ) || empty( $fees ) ) {
			return;
		}

		foreach ( $fees as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$amount = isset( $row['amount'] ) ? floatval( $row['amount'] ) : 0.0;
			if ( 0.0 === $amount ) {
				continue;
			}
			$taxable = ! empty( $row['taxable'] );
			$tax_class = '';
			if ( $taxable && isset( $row['tax_class'] ) && '' !== (string) $row['tax_class'] ) {
				$raw = sanitize_text_field( (string) $row['tax_class'] );
				if ( function_exists( 'wc_get_product_tax_class_options' ) ) {
					$opts = wc_get_product_tax_class_options();
					if ( is_array( $opts ) && array_key_exists( $raw, $opts ) ) {
						$tax_class = $raw;
					}
				}
			}
			$cart->add_fee( $name, $amount, $taxable, $tax_class );
		}
	}
}
