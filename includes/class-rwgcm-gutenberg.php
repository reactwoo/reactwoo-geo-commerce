<?php
/**
 * Gutenberg block — weather products.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rwgcm/weather-products block.
 */
class RWGCM_Gutenberg {

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

		wp_register_script(
			'rwgcm-weather-products-editor',
			RWGCM_URL . 'blocks/weather-products/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
			RWGCM_VERSION,
			true
		);

		register_block_type(
			RWGCM_PATH . 'blocks/weather-products',
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
		$args  = array(
			'title'               => isset( $attrs['title'] ) ? (string) $attrs['title'] : '',
			'limit'               => isset( $attrs['limit'] ) ? (int) $attrs['limit'] : 8,
			'columns'             => isset( $attrs['columns'] ) ? (int) $attrs['columns'] : 4,
			'category'            => isset( $attrs['category'] ) ? (string) $attrs['category'] : '',
			'ids'                 => isset( $attrs['ids'] ) ? (string) $attrs['ids'] : '',
			'orderby'             => isset( $attrs['orderby'] ) ? (string) $attrs['orderby'] : 'relevance',
			'fallback'            => isset( $attrs['fallback'] ) ? (string) $attrs['fallback'] : 'hide',
			'fallback_category'   => isset( $attrs['fallback_category'] ) ? (string) $attrs['fallback_category'] : '',
			'fallback_message'    => isset( $attrs['fallback_message'] ) ? (string) $attrs['fallback_message'] : '',
			'weather_unavailable' => isset( $attrs['weather_unavailable'] ) ? (string) $attrs['weather_unavailable'] : 'hide',
			'class'               => isset( $attrs['className'] ) ? (string) $attrs['className'] : '',
		);
		return RWGCM_Weather_Products_Display::render( $args );
	}
}
