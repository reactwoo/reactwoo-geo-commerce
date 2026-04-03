<?php
/**
 * WooCommerce package rates + Geo Core visitor country (extension point).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps {@see 'woocommerce_package_rates'} with visitor ISO2 for geo-aware shipping rules.
 */
class RWGCM_Shipping {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 99, 2 );
	}

	/**
	 * @param array<string, \WC_Shipping_Rate> $rates   Package rates.
	 * @param array<string, mixed>             $package WooCommerce package.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public static function filter_package_rates( $rates, $package ) {
		if ( ! is_array( $rates ) ) {
			$rates = array();
		}
		if ( ! is_array( $package ) ) {
			$package = array();
		}
		$iso2 = self::visitor_country_iso2();

		/**
		 * Filter shipping rates after WooCommerce builds them. Geo Commerce passes visitor ISO2 from Geo Core.
		 *
		 * @param array<string, \WC_Shipping_Rate> $rates   Rates keyed by rate id.
		 * @param array<string, mixed>              $package WooCommerce package.
		 * @param string                            $iso2    Two-letter country or empty if unknown.
		 */
		$filtered = apply_filters( 'rwgcm_package_rates', $rates, $package, $iso2 );
		return is_array( $filtered ) ? $filtered : $rates;
	}

	/**
	 * @return string Two-letter uppercase or empty.
	 */
	private static function visitor_country_iso2() {
		$iso2 = '';
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$iso2 = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( strlen( $iso2 ) !== 2 && function_exists( 'rwgc_get_visitor_data' ) ) {
			$v = rwgc_get_visitor_data();
			if ( is_array( $v ) && isset( $v['country_code'] ) ) {
				$iso2 = strtoupper( substr( sanitize_text_field( (string) $v['country_code'] ), 0, 2 ) );
			}
		}
		return strlen( $iso2 ) === 2 ? $iso2 : '';
	}
}
