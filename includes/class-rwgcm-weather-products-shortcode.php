<?php
/**
 * Shortcode [rwgcm_weather_products].
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers weather products shortcode.
 */
class RWGCM_Weather_Products_Shortcode {

	/**
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'rwgcm_weather_products', array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return RWGCM_Weather_Products_Display::render( $atts );
	}
}
