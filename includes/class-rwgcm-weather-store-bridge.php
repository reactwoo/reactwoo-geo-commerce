<?php
/**
 * Bridge WooCommerce store coordinates into GeoCore Pro weather (click-and-collect).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies store lat/lon to {@see RWGCP_Weather_Service::get_store_coordinates()} when configured in Commerce.
 */
class RWGCM_Weather_Store_Bridge {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgcp_weather_store_coordinates', array( __CLASS__, 'filter_store_coordinates' ), 10, 1 );
	}

	/**
	 * @param array{lat?: float, lon?: float}|null $coords Existing coords.
	 * @return array{lat?: float, lon?: float}|null
	 */
	public static function filter_store_coordinates( $coords ) {
		if ( is_array( $coords ) && isset( $coords['lat'], $coords['lon'] ) ) {
			return $coords;
		}
		$pair = RWGCM_Settings::get_store_weather_coordinates();
		if ( null === $pair ) {
			return $coords;
		}
		return $pair;
	}
}
