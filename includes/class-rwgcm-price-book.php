<?php
/**
 * Fixed per-currency price books.
 *
 * Prices are absolute amounts in each currency. Nothing here converts one
 * currency into another. Country and currency lists are filterable so stores
 * can add codes without a plugin change.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure price-book decisions (no WooCommerce objects).
 */
class RWGCM_Price_Book {

	const META_KEY = '_rwgcm_price_book';

	const OPTION_BASE_ADJUSTMENTS = 'rwgcm_price_book_base_adjustments';

	/**
	 * Built-in currencies. Filter `rwgcm_price_book_currencies` to extend.
	 *
	 * @return array<int, string>
	 */
	public static function default_currencies() {
		return array( 'ZAR', 'GBP', 'EUR', 'USD' );
	}

	/**
	 * @return array<int, string>
	 */
	public static function currencies() {
		$list = self::default_currencies();
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_price_book_currencies', $list );
			if ( is_array( $filtered ) ) {
				$list = $filtered;
			}
		}
		$out = array();
		foreach ( $list as $code ) {
			$norm = self::normalize_currency( $code );
			if ( '' === $norm || in_array( $norm, $out, true ) ) {
				continue;
			}
			$out[] = $norm;
		}
		return $out;
	}

	/**
	 * ISO2 country => currency. Filter `rwgcm_price_book_country_currencies`.
	 *
	 * Eurozone members include Bulgaria (euro from 1 Jan 2026).
	 *
	 * @return array<string, string>
	 */
	public static function default_country_map() {
		$map  = array(
			'ZA' => 'ZAR',
			'GB' => 'GBP',
			'US' => 'USD',
		);
		$euro = array(
			'AT',
			'BE',
			'BG',
			'CY',
			'DE',
			'EE',
			'ES',
			'FI',
			'FR',
			'GR',
			'HR',
			'IE',
			'IT',
			'LT',
			'LU',
			'LV',
			'MT',
			'NL',
			'PT',
			'SI',
			'SK',
		);
		foreach ( $euro as $country ) {
			$map[ $country ] = 'EUR';
		}
		return $map;
	}

	/**
	 * @return array<string, string>
	 */
	public static function country_currency_map() {
		$map = self::default_country_map();
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_price_book_country_currencies', $map );
			if ( is_array( $filtered ) ) {
				$map = $filtered;
			}
		}
		return self::normalize_map( $map );
	}

	/**
	 * @param mixed $code Raw code.
	 * @return string Uppercase ISO 4217 or empty.
	 */
	public static function normalize_currency( $code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $code ) );
		return strlen( $code ) === 3 ? $code : '';
	}

	/**
	 * @param mixed $country Raw country.
	 * @return string Uppercase ISO2 or empty.
	 */
	public static function normalize_country( $country ) {
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $country ) );
		return strlen( $country ) === 2 ? $country : '';
	}

	/**
	 * Billing country wins. Geo IP is used only when billing is empty.
	 * An unmapped billing country falls back to the store currency and does
	 * not consult geo IP ("IP alone never decides once billing is known").
	 *
	 * @param string                    $billing_country Billing ISO2 or empty.
	 * @param string                    $geo_country     Geo IP ISO2 or empty.
	 * @param string                    $base_currency   Store currency.
	 * @param array<string, string>|null $map            Optional map override.
	 * @param array<int, string>|null   $currencies      Optional currency override.
	 * @return array{currency: string, source: string, country: string, reason: string}
	 */
	public static function resolve_context( $billing_country, $geo_country, $base_currency, $map = null, $currencies = null ) {
		$base = self::normalize_currency( $base_currency );
		if ( '' === $base ) {
			$base = 'GBP';
		}
		if ( null === $map ) {
			$map = self::country_currency_map();
		} else {
			$map = self::normalize_map( $map );
		}
		if ( null === $currencies ) {
			$currencies = self::currencies();
		} else {
			$currencies = self::normalize_currency_list( $currencies );
		}

		$billing = self::normalize_country( $billing_country );
		if ( '' !== $billing ) {
			$currency = self::lookup_currency( $billing, $map, $currencies );
			if ( '' === $currency ) {
				return array(
					'currency' => $base,
					'source'   => 'base',
					'country'  => $billing,
					'reason'   => 'billing_unmapped',
				);
			}
			return array(
				'currency' => $currency,
				'source'   => 'billing',
				'country'  => $billing,
				'reason'   => 'billing',
			);
		}

		$geo = self::normalize_country( $geo_country );
		if ( '' !== $geo ) {
			$currency = self::lookup_currency( $geo, $map, $currencies );
			if ( '' === $currency ) {
				return array(
					'currency' => $base,
					'source'   => 'base',
					'country'  => $geo,
					'reason'   => 'geo_unmapped',
				);
			}
			return array(
				'currency' => $currency,
				'source'   => 'geo',
				'country'  => $geo,
				'reason'   => 'geo',
			);
		}

		return array(
			'currency' => $base,
			'source'   => 'base',
			'country'  => '',
			'reason'   => 'unknown',
		);
	}

	/**
	 * Renewal currency is kept even when the current billing country differs.
	 *
	 * @param string $billing_country Billing ISO2.
	 * @param string $geo_country     Geo ISO2.
	 * @param string $base_currency   Store currency.
	 * @param string $renewal_currency Subscription currency, or empty.
	 * @return array{currency: string, source: string, country: string, reason: string}
	 */
	public static function resolve_charge( $billing_country, $geo_country, $base_currency, $renewal_currency = '' ) {
		$renewal = self::normalize_currency( $renewal_currency );
		if ( '' !== $renewal ) {
			return array(
				'currency' => $renewal,
				'source'   => 'renewal',
				'country'  => '',
				'reason'   => 'renewal',
			);
		}
		return self::resolve_context( $billing_country, $geo_country, $base_currency );
	}

	/**
	 * One order has one currency. If any line has no fixed price in the
	 * preferred currency, the cart stays in the store currency.
	 *
	 * @param string        $preferred Preferred currency.
	 * @param string        $base      Store currency.
	 * @param array<int, bool> $line_has_price Whether each priced line has a book price.
	 * @return string
	 */
	public static function currency_for_lines( $preferred, $base, array $line_has_price ) {
		$preferred = self::normalize_currency( $preferred );
		$base      = self::normalize_currency( $base );
		if ( '' === $base ) {
			$base = 'GBP';
		}
		if ( '' === $preferred || $preferred === $base ) {
			return $base;
		}
		foreach ( $line_has_price as $has ) {
			if ( ! $has ) {
				return $base;
			}
		}
		return $preferred;
	}

	/**
	 * @param array<string, mixed> $book     Stored book.
	 * @param string               $currency Currency code.
	 * @return bool
	 */
	public static function has_price( array $book, $currency ) {
		$currency = self::normalize_currency( $currency );
		if ( '' === $currency || ! isset( $book[ $currency ] ) || ! is_array( $book[ $currency ] ) ) {
			return false;
		}
		$regular = isset( $book[ $currency ]['regular'] ) ? $book[ $currency ]['regular'] : '';
		return self::amount_is_set( $regular );
	}

	/**
	 * Active price for one currency entry. Sale is used only when it is lower
	 * than the regular (or recurring) price.
	 *
	 * @param array<string, mixed> $entry Regular / sale / signup_fee.
	 * @return array{regular: string, sale: string, signup_fee: string, active: string, on_sale: bool}|null
	 */
	public static function resolve_prices( array $entry ) {
		$regular_raw = isset( $entry['regular'] ) ? $entry['regular'] : '';
		if ( ! self::amount_is_set( $regular_raw ) ) {
			return null;
		}
		$regular = self::format_amount( $regular_raw );
		$sale    = '';
		$on_sale = false;
		if ( isset( $entry['sale'] ) && self::amount_is_set( $entry['sale'] ) && (float) $entry['sale'] < (float) $regular ) {
			$sale    = self::format_amount( $entry['sale'] );
			$on_sale = true;
		}
		$signup = '';
		if ( isset( $entry['signup_fee'] ) && self::amount_is_set( $entry['signup_fee'] ) ) {
			$signup = self::format_amount( $entry['signup_fee'] );
		}
		return array(
			'regular'    => $regular,
			'sale'       => $sale,
			'signup_fee' => $signup,
			'active'     => $on_sale ? $sale : $regular,
			'on_sale'    => $on_sale,
		);
	}

	/**
	 * Geo percent/fixed rules keep adjusting the store currency unless this is
	 * turned off, or this product already has a fixed book price in that currency.
	 *
	 * @param string $active_currency     Currency currently in effect.
	 * @param string $base_currency       Store currency.
	 * @param bool   $uses_book_in_base   Product has a fixed base-currency price.
	 * @param bool   $adjustments_enabled Migrated option (default true).
	 * @return bool
	 */
	public static function should_apply_base_adjustment( $active_currency, $base_currency, $uses_book_in_base, $adjustments_enabled ) {
		if ( ! $adjustments_enabled ) {
			return false;
		}
		$active = self::normalize_currency( $active_currency );
		$base   = self::normalize_currency( $base_currency );
		if ( '' === $active || $active !== $base ) {
			return false;
		}
		if ( $uses_book_in_base ) {
			return false;
		}
		return true;
	}

	/**
	 * @param mixed                    $raw        Posted book.
	 * @param array<int, string>|null  $currencies Allowed currencies.
	 * @return array<string, array{regular: string, sale: string, signup_fee: string}>
	 */
	public static function sanitize_book( $raw, $currencies = null ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		if ( null === $currencies ) {
			$currencies = self::currencies();
		} else {
			$currencies = self::normalize_currency_list( $currencies );
		}
		$out = array();
		foreach ( $currencies as $code ) {
			if ( ! isset( $raw[ $code ] ) || ! is_array( $raw[ $code ] ) ) {
				continue;
			}
			$row     = $raw[ $code ];
			$regular = self::sanitize_amount( isset( $row['regular'] ) ? $row['regular'] : '' );
			if ( '' === $regular ) {
				continue;
			}
			$out[ $code ] = array(
				'regular'    => $regular,
				'sale'       => self::sanitize_amount( isset( $row['sale'] ) ? $row['sale'] : '' ),
				'signup_fee' => self::sanitize_amount( isset( $row['signup_fee'] ) ? $row['signup_fee'] : '' ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw amount.
	 * @return bool
	 */
	public static function amount_is_set( $value ) {
		if ( null === $value ) {
			return false;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return false;
		}
		$value = str_replace( ',', '.', $value );
		return is_numeric( $value ) && (float) $value >= 0;
	}

	/**
	 * @param mixed $value Raw amount.
	 * @return string Empty when unset or invalid.
	 */
	public static function sanitize_amount( $value ) {
		if ( ! self::amount_is_set( $value ) ) {
			return '';
		}
		return self::format_amount( $value );
	}

	/**
	 * @param mixed $value Numeric amount.
	 * @return string
	 */
	public static function format_amount( $value ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		if ( $decimals < 0 ) {
			$decimals = 0;
		}
		$number = (float) str_replace( ',', '.', trim( (string) $value ) );
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $number, $decimals );
		}
		return number_format( $number, $decimals, '.', '' );
	}

	/**
	 * Existing installs keep GBP (store-currency) geo adjustments.
	 *
	 * @return void
	 */
	public static function maybe_migrate_base_adjustments() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'add_option' ) ) {
			return;
		}
		if ( 'missing' === get_option( self::OPTION_BASE_ADJUSTMENTS, 'missing' ) ) {
			add_option( self::OPTION_BASE_ADJUSTMENTS, 'yes', '', false );
		}
	}

	/**
	 * @return bool
	 */
	public static function base_adjustments_enabled() {
		if ( ! function_exists( 'get_option' ) ) {
			return true;
		}
		$value = get_option( self::OPTION_BASE_ADJUSTMENTS, 'yes' );
		return 'yes' === $value || '1' === (string) $value || 1 === $value || true === $value;
	}

	/**
	 * @param array<string, string> $map Raw map.
	 * @return array<string, string>
	 */
	private static function normalize_map( array $map ) {
		$out = array();
		foreach ( $map as $country => $currency ) {
			$country  = self::normalize_country( $country );
			$currency = self::normalize_currency( $currency );
			if ( '' === $country || '' === $currency ) {
				continue;
			}
			$out[ $country ] = $currency;
		}
		return $out;
	}

	/**
	 * @param array<int, mixed> $list Raw codes.
	 * @return array<int, string>
	 */
	private static function normalize_currency_list( array $list ) {
		$out = array();
		foreach ( $list as $code ) {
			$norm = self::normalize_currency( $code );
			if ( '' === $norm || in_array( $norm, $out, true ) ) {
				continue;
			}
			$out[] = $norm;
		}
		return $out;
	}

	/**
	 * @param string               $country    ISO2.
	 * @param array<string, string> $map       Country map.
	 * @param array<int, string>   $currencies Enabled currencies.
	 * @return string
	 */
	private static function lookup_currency( $country, array $map, array $currencies ) {
		if ( ! isset( $map[ $country ] ) ) {
			return '';
		}
		$currency = self::normalize_currency( $map[ $country ] );
		if ( '' === $currency || ! in_array( $currency, $currencies, true ) ) {
			return '';
		}
		return $currency;
	}
}
