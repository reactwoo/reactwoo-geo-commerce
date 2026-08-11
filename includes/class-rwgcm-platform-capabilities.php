<?php
/**
 * Register Geo Commerce conditions/actions with the ReactWoo platform registry.
 *
 * Does not change pricing/overlay evaluation — registration only.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Commerce → platform capability bridge.
 */
class RWGCM_Platform_Capabilities {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'reactwoo_register_capabilities', array( __CLASS__, 'register' ), 30 );
	}

	/**
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'reactwoo_register_condition' ) || ! function_exists( 'reactwoo_register_action' ) ) {
			return;
		}

		$provider = 'reactwoo-geo-commerce';
		$version  = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '1';

		$conditions = array(
			'commerce.cart_value'       => array(
				'label'       => __( 'Cart value', 'reactwoo-geo-commerce' ),
				'description' => __( 'Cart subtotal / value (maps to cart.subtotal).', 'reactwoo-geo-commerce' ),
			),
			'commerce.cart_contains'    => array(
				'label'       => __( 'Cart contains', 'reactwoo-geo-commerce' ),
				'description' => __( 'Cart contains product/SKU.', 'reactwoo-geo-commerce' ),
			),
			'commerce.product'          => array(
				'label'       => __( 'Product', 'reactwoo-geo-commerce' ),
				'description' => __( 'Current or targeted product.', 'reactwoo-geo-commerce' ),
			),
			'commerce.category'         => array(
				'label'       => __( 'Product category', 'reactwoo-geo-commerce' ),
				'description' => __( 'Product category (maps to product.category).', 'reactwoo-geo-commerce' ),
			),
			'commerce.customer_value'   => array(
				'label'       => __( 'Customer value', 'reactwoo-geo-commerce' ),
				'description' => __( 'Customer segment / value signals.', 'reactwoo-geo-commerce' ),
			),
			'cart.subtotal'             => array( 'label' => __( 'Cart subtotal', 'reactwoo-geo-commerce' ) ),
			'cart.item_count'           => array( 'label' => __( 'Cart item count', 'reactwoo-geo-commerce' ) ),
			'cart.shipping_country'     => array( 'label' => __( 'Shipping country', 'reactwoo-geo-commerce' ) ),
			'cart.billing_country'      => array( 'label' => __( 'Billing country', 'reactwoo-geo-commerce' ) ),
			'product.category'          => array( 'label' => __( 'Product category', 'reactwoo-geo-commerce' ) ),
			'product.tag'               => array( 'label' => __( 'Product tag', 'reactwoo-geo-commerce' ) ),
			'product.price'             => array( 'label' => __( 'Product price', 'reactwoo-geo-commerce' ) ),
			'product.stock_status'      => array( 'label' => __( 'Stock status', 'reactwoo-geo-commerce' ) ),
			'customer.role'             => array( 'label' => __( 'Customer role', 'reactwoo-geo-commerce' ) ),
			'customer.segment'          => array( 'label' => __( 'Customer segment', 'reactwoo-geo-commerce' ) ),
		);

		foreach ( $conditions as $id => $meta ) {
			if ( function_exists( 'reactwoo_has_capability' ) && reactwoo_has_capability( $id ) ) {
				continue;
			}
			reactwoo_register_condition(
				$id,
				array(
					'label'       => $meta['label'],
					'description' => isset( $meta['description'] ) ? $meta['description'] : $meta['label'],
					'provider'    => $provider,
					'version'     => $version,
					'entitlement' => 'geo_commerce',
					'availability'=> static function () {
						return class_exists( 'WooCommerce', false ) || function_exists( 'WC' );
					},
				)
			);
		}

		$actions = array(
			'commerce.product.show'    => __( 'Show product', 'reactwoo-geo-commerce' ),
			'commerce.product.hide'    => __( 'Hide product', 'reactwoo-geo-commerce' ),
			'commerce.product.promote' => __( 'Promote product', 'reactwoo-geo-commerce' ),
			'commerce.offer.show'      => __( 'Show offer', 'reactwoo-geo-commerce' ),
			'commerce.message.replace' => __( 'Replace commerce message', 'reactwoo-geo-commerce' ),
			'commerce.price.adjust'    => __( 'Adjust price', 'reactwoo-geo-commerce' ),
			'commerce.fee.apply'       => __( 'Apply fee', 'reactwoo-geo-commerce' ),
		);

		foreach ( $actions as $id => $label ) {
			if ( function_exists( 'reactwoo_has_capability' ) && reactwoo_has_capability( $id ) ) {
				continue;
			}
			reactwoo_register_action(
				$id,
				array(
					'label'       => $label,
					'description' => $label,
					'provider'    => $provider,
					'version'     => $version,
					'entitlement' => 'geo_commerce',
					'availability'=> static function () {
						return class_exists( 'WooCommerce', false ) || function_exists( 'WC' );
					},
				)
			);
		}
	}
}
