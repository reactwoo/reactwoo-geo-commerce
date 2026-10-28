<?php
/**
 * Checkout and cart block integration for the shipping-currency notice.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
	return;
}

/**
 * Loads the notice script on the Cart and Checkout blocks.
 */
class RWGCM_Shipping_Block_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'rwgcm-shipping-currency-notice';
	}

	/**
	 * @return void
	 */
	public function initialize() {
		if ( ! defined( 'RWGCM_URL' ) || ! function_exists( 'wp_register_script' ) ) {
			return;
		}
		$ver = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.6.0';
		wp_register_script(
			'rwgcm-shipping-currency-notice',
			RWGCM_URL . 'assets/js/shipping-currency-notice.js',
			array( 'wp-plugins', 'wp-element', 'wp-data', 'wc-blocks-checkout' ),
			$ver,
			true
		);
		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style(
				'rwgcm-shipping-currency-notice',
				RWGCM_URL . 'assets/css/shipping-currency-notice.css',
				array(),
				$ver
			);
			wp_enqueue_style( 'rwgcm-shipping-currency-notice' );
		}
	}

	/**
	 * @return array<int, string>
	 */
	public function get_script_handles() {
		return array( 'rwgcm-shipping-currency-notice' );
	}

	/**
	 * The notice is storefront-only. The editor does not resolve a visitor currency.
	 *
	 * @return array<int, string>
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * The text comes from the cart extension, which updates when the currency changes.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data() {
		return array();
	}
}
