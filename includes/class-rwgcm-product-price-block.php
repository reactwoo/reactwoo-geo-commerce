<?php
/**
 * Gutenberg block — localised product price.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the rwgcm/product-price block.
 */
class RWGCM_Product_Price_Block {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script(
				'rwgcm-product-price-editor',
				RWGCM_URL . 'blocks/product-price/index.js',
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
				defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.7.0',
				true
			);
		}
		if ( class_exists( 'RWGCM_Product_Price', false ) ) {
			RWGCM_Product_Price::register_assets();
		}
		register_block_type(
			RWGCM_PATH . 'blocks/product-price',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$attrs = is_array( $attributes ) ? $attributes : array();
		return RWGCM_Product_Price::render( $attrs );
	}
}
