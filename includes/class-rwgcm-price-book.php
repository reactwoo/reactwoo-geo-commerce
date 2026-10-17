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

	const OPTION_COUNTRY_MAP = 'rwgcm_price_book_country_map';

	const OPTION_FALLBACK_CURRENCY = 'rwgcm_price_book_fallback_currency';

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
	 * Stored map, or the built-in defaults when nothing has been saved.
	 * An empty array is a saved map with no countries, not "use the defaults".
	 *
	 * @param mixed $stored Option value.
	 * @return array<string, string>
	 */
	public static function map_from_storage( $stored ) {
		if ( ! is_array( $stored ) ) {
			return self::normalize_map( self::default_country_map() );
		}
		return self::normalize_map( $stored );
	}

	/**
	 * @return array<string, string>
	 */
	public static function country_currency_map() {
		$stored = null;
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( self::OPTION_COUNTRY_MAP, null );
		}
		$map = self::map_from_storage( $stored );
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_price_book_country_currencies', $map );
			if ( is_array( $filtered ) ) {
				$map = self::normalize_map( $filtered );
			}
		}
		return $map;
	}

	/**
	 * Fallback used for a known country that is not in the map.
	 * Empty or unknown codes use the store currency.
	 *
	 * @param string $base_currency Store currency.
	 * @return string
	 */
	public static function configured_fallback_currency( $base_currency ) {
		$base = self::normalize_currency( $base_currency );
		if ( '' === $base ) {
			$base = 'GBP';
		}
		if ( ! function_exists( 'get_option' ) ) {
			return $base;
		}
		$raw  = get_option( self::OPTION_FALLBACK_CURRENCY, '' );
		$code = self::normalize_currency( is_string( $raw ) ? $raw : '' );
		if ( '' === $code || ( $code !== $base && ! in_array( $code, self::currencies(), true ) ) ) {
			return $base;
		}
		return $code;
	}

	/**
	 * One `CC CURRENCY` pair per line. Blank lines and # comments are ignored.
	 *
	 * @param string $text Textarea contents.
	 * @return array<string, string>
	 */
	public static function parse_country_map_text( $text ) {
		$map   = array();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		if ( ! is_array( $lines ) ) {
			return $map;
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
				continue;
			}
			if ( ! preg_match( '/^([A-Za-z]{2})\s*[:=\s]\s*([A-Za-z]{3})\s*$/', $line, $matches ) ) {
				continue;
			}
			$country  = self::normalize_country( $matches[1] );
			$currency = self::normalize_currency( $matches[2] );
			if ( '' === $country || '' === $currency ) {
				continue;
			}
			$map[ $country ] = $currency;
		}
		return $map;
	}

	/**
	 * @param array<string, string> $map Country map.
	 * @return string
	 */
	public static function format_country_map_text( array $map ) {
		$map = self::normalize_map( $map );
		ksort( $map );
		$lines = array();
		foreach ( $map as $country => $currency ) {
			$lines[] = $country . ' ' . $currency;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Posted billing always counts. A remembered country counts only on
	 * cart, checkout, or the Store API cart/checkout request, so catalog HTML
	 * stays on Geo Core's visitor country and can share that cache vary.
	 *
	 * @param string $posted        Posted billing country.
	 * @param string $session       Remembered billing country.
	 * @param bool   $checkout_flow True when cart or checkout is calculating.
	 * @return string
	 */
	public static function effective_billing_country( $posted, $session, $checkout_flow ) {
		$posted = self::normalize_country( $posted );
		if ( '' !== $posted ) {
			return $posted;
		}
		if ( $checkout_flow ) {
			return self::normalize_country( $session );
		}
		return '';
	}

	/**
	 * Default checkout country for a customer who has not chosen one: the
	 * geo country when the store sells there, otherwise WooCommerce's own
	 * default.
	 *
	 * @param string            $geo_country Geo Core visitor ISO2.
	 * @param array<int|string> $allowed     Allowed ISO2 codes (list or code => name).
	 * @param string            $wc_default  WooCommerce default.
	 * @return string
	 */
	public static function default_checkout_country( $geo_country, array $allowed, $wc_default ) {
		$geo = self::normalize_country( $geo_country );
		if ( '' === $geo ) {
			return (string) $wc_default;
		}
		$codes = array();
		foreach ( $allowed as $key => $value ) {
			$codes[] = self::normalize_country( is_string( $key ) ? $key : $value );
		}
		return in_array( $geo, $codes, true ) ? $geo : (string) $wc_default;
	}

	/**
	 * Billing country the customer actually set: posted this request, then
	 * remembered from a checkout or Store API update, then (cart and
	 * checkout only) the saved account country. A pre-filled default is
	 * never one of these, so it never overrides the geo currency.
	 *
	 * @param string $posted        Posted billing country.
	 * @param string $remembered    Session-remembered billing country.
	 * @param string $account       Saved account billing country (logged in).
	 * @param bool   $checkout_flow Cart, checkout, or Store API cart/checkout.
	 * @return string
	 */
	public static function chosen_billing_country( $posted, $remembered, $account, $checkout_flow ) {
		$country = self::effective_billing_country( $posted, $remembered, $checkout_flow );
		if ( '' !== $country ) {
			return $country;
		}
		return $checkout_flow ? self::normalize_country( $account ) : '';
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
	 * A known country that is not in the map uses the fallback currency
	 * (the store currency when no fallback is configured) and does not
	 * consult the other country ("IP alone never decides once billing is known").
	 *
	 * @param string                    $billing_country   Billing ISO2 or empty.
	 * @param string                    $geo_country       Geo Core visitor ISO2 or empty.
	 * @param string                    $base_currency     Store currency.
	 * @param array<string, string>|null $map              Optional map override.
	 * @param array<int, string>|null   $currencies        Optional currency override.
	 * @param string                    $fallback_currency Unmapped-country currency, or empty for the store currency.
	 * @return array{currency: string, source: string, country: string, reason: string}
	 */
	public static function resolve_context( $billing_country, $geo_country, $base_currency, $map = null, $currencies = null, $fallback_currency = '' ) {
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
		$fallback = self::resolve_fallback_currency( $fallback_currency, $base, $currencies );

		$billing = self::normalize_country( $billing_country );
		if ( '' !== $billing ) {
			$currency = self::lookup_currency( $billing, $map, $currencies );
			if ( '' === $currency ) {
				return self::unmapped_decision( $fallback, $base, $billing, 'billing_unmapped' );
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
				return self::unmapped_decision( $fallback, $base, $geo, 'geo_unmapped' );
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
	 * @param string $billing_country   Billing ISO2.
	 * @param string $geo_country       Geo ISO2.
	 * @param string $base_currency     Store currency.
	 * @param string $renewal_currency  Subscription currency, or empty.
	 * @param string $fallback_currency Unmapped-country currency, or empty for the store currency.
	 * @return array{currency: string, source: string, country: string, reason: string}
	 */
	public static function resolve_charge( $billing_country, $geo_country, $base_currency, $renewal_currency = '', $fallback_currency = '' ) {
		$renewal = self::normalize_currency( $renewal_currency );
		if ( '' !== $renewal ) {
			return array(
				'currency' => $renewal,
				'source'   => 'renewal',
				'country'  => '',
				'reason'   => 'renewal',
			);
		}
		return self::resolve_context( $billing_country, $geo_country, $base_currency, null, null, $fallback_currency );
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
	 * @param string             $fallback_currency Requested fallback.
	 * @param string             $base              Store currency.
	 * @param array<int, string> $currencies        Enabled currencies.
	 * @return string
	 */
	/**
	 * From/range for a variable product.
	 *
	 * Every priced variation must have a book amount before the active currency
	 * is used. Otherwise the raw store-currency amounts stay in the store currency,
	 * so a local symbol is never placed on a store-currency number.
	 *
	 * @param array<int, array{raw?: string, book?: string}> $variations Variation rows.
	 * @param string                                        $active     Visitor currency.
	 * @param string                                        $base       Store currency.
	 * @return array{currency: string, min: string, max: string, mode: string}|null Null when the active currency is already the store currency.
	 */
	public static function variable_range( array $variations, $active, $base ) {
		$active = self::normalize_currency( $active );
		$base   = self::normalize_currency( $base );
		if ( '' === $base ) {
			$base = 'GBP';
		}
		if ( '' === $active || $active === $base || empty( $variations ) ) {
			return null;
		}
		$raws    = array();
		$books   = array();
		$priced  = 0;
		$missing = false;
		foreach ( $variations as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$raw      = isset( $row['raw'] ) ? $row['raw'] : '';
			$book     = isset( $row['book'] ) ? $row['book'] : '';
			$has_raw  = self::amount_is_set( $raw );
			$has_book = self::amount_is_set( $book );
			if ( ! $has_raw && ! $has_book ) {
				continue;
			}
			++$priced;
			if ( $has_raw ) {
				$raws[] = (float) str_replace( ',', '.', trim( (string) $raw ) );
			}
			if ( $has_book ) {
				$books[] = (float) str_replace( ',', '.', trim( (string) $book ) );
			} else {
				$missing = true;
			}
		}
		if ( $priced < 1 ) {
			return null;
		}
		if ( ! $missing && count( $books ) === $priced ) {
			return array(
				'currency' => $active,
				'min'      => self::format_amount( min( $books ) ),
				'max'      => self::format_amount( max( $books ) ),
				'mode'     => 'book',
			);
		}
		if ( empty( $raws ) ) {
			return null;
		}
		return array(
			'currency' => $base,
			'min'      => self::format_amount( min( $raws ) ),
			'max'      => self::format_amount( max( $raws ) ),
			'mode'     => 'base',
		);
	}

	/**
	 * Variation price transients are keyed by this hash. One entry per currency
	 * on the storefront. Writes stay on the unscoped store-currency entry.
	 *
	 * @param array  $hash        Existing hash payload.
	 * @param string $currency    Active currency.
	 * @param bool   $force_store True when prices must not switch.
	 * @return array
	 */
	public static function variation_prices_hash( $hash, $currency, $force_store ) {
		if ( ! is_array( $hash ) ) {
			$hash = array();
		}
		if ( $force_store ) {
			return $hash;
		}
		$code = self::normalize_currency( $currency );
		if ( '' === $code ) {
			return $hash;
		}
		$hash['rwgcm_price_book_currency'] = $code;
		return $hash;
	}

	/**
	 * @param string             $fallback_currency Requested fallback.
	 * @param string             $base              Store currency.
	 * @param array<int, string> $currencies        Enabled currencies.
	 * @return string
	 */
	private static function resolve_fallback_currency( $fallback_currency, $base, array $currencies ) {
		$fallback = self::normalize_currency( $fallback_currency );
		if ( '' === $fallback || ( $fallback !== $base && ! in_array( $fallback, $currencies, true ) ) ) {
			return $base;
		}
		return $fallback;
	}

	/**
	 * @param string $fallback Fallback currency.
	 * @param string $base     Store currency.
	 * @param string $country  Known ISO2.
	 * @param string $reason   billing_unmapped or geo_unmapped.
	 * @return array{currency: string, source: string, country: string, reason: string}
	 */
	private static function unmapped_decision( $fallback, $base, $country, $reason ) {
		return array(
			'currency' => $fallback,
			'source'   => ( $fallback === $base ) ? 'base' : 'fallback',
			'country'  => $country,
			'reason'   => $reason,
		);
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
