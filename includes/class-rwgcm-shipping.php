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
		add_filter( 'woocommerce_cart_shipping_packages', array( __CLASS__, 'filter_cart_shipping_packages' ), 99 );
	}

	/**
	 * Package key that carries the Commerce currency into WooCommerce's rate cache.
	 */
	const PACKAGE_CURRENCY_KEY = 'rwgcm_currency';

	/**
	 * WooCommerce caches rates per package in the session
	 * (WC_Shipping::calculate_shipping_for_package). Since WC 11.0 the cache
	 * hash ignores subtotal and total, and it never included the currency,
	 * so rates calculated in one currency could be reused after a switch.
	 * Adding the active currency to each package changes the hash.
	 *
	 * @param mixed $packages Packages.
	 * @return mixed
	 */
	public static function filter_cart_shipping_packages( $packages ) {
		if ( ! is_array( $packages ) || ! class_exists( 'RWGCM_Price_Book_Currency', false ) || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $packages;
		}
		return self::add_currency_to_packages( $packages, get_woocommerce_currency(), RWGCM_Price_Book_Currency::base_currency() );
	}

	/**
	 * Adds the active currency to every package, but only when it differs
	 * from the store currency. Base-currency packages are left exactly as
	 * WooCommerce built them, so their hash (and existing cached rates) is
	 * the same as on a store without Commerce. A switch still changes the
	 * hash in both directions (key present vs absent).
	 *
	 * @param array<mixed> $packages Packages.
	 * @param string       $active   Active currency.
	 * @param string       $base     Store currency.
	 * @return array<mixed>
	 */
	public static function add_currency_to_packages( array $packages, $active, $base ) {
		$active = RWGCM_Price_Book::normalize_currency( $active );
		$base   = RWGCM_Price_Book::normalize_currency( $base );
		foreach ( $packages as $i => $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}
			if ( '' === $active || $active === $base ) {
				unset( $packages[ $i ][ self::PACKAGE_CURRENCY_KEY ] );
				continue;
			}
			$packages[ $i ][ self::PACKAGE_CURRENCY_KEY ] = $active;
		}
		return $packages;
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
