<?php
/**
 * Convert a store-currency price into the visitor currency when that
 * currency has no fixed price-book amount.
 *
 * A fixed price in the visitor currency is never converted and is never
 * passed through a geo pricing rule. With no usable rate, or when conversion
 * is off, prices stay in the store currency (the 0.8.0 behaviour).
 *
 * Manual rates live in the existing `rwgcm_exchange_rates` option, which the
 * 0.8.0 cache purge already watches. Provider rates are cached in
 * `rwgcm_exchange_rates_cache` (last good) and the transient
 * `rwgcm_exchange_rates_fresh` (12 hours). Saving either, or
 * `rwgcm_exchange_settings`, is included in `rwgcm_exchange_rate_options`
 * and also fires `rwgcm_exchange_rates_saved`.
 *
 * Formula, in order: store amount, then a fixed-line rule (store-currency
 * units), then rate × (1 + markup%), then a percent rule, then rounding.
 * The store amount is the price-book price in the store currency when one
 * is set, otherwise the regular or sale price.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exchange rates and visitor-currency conversion.
 */
class RWGCM_Exchange {

	const OPTION_MANUAL = 'rwgcm_exchange_rates';

	const OPTION_SETTINGS = 'rwgcm_exchange_settings';

	const OPTION_CACHE = 'rwgcm_exchange_rates_cache';

	const TRANSIENT = 'rwgcm_exchange_rates_fresh';

	const CRON_HOOK = 'rwgcm_exchange_rates_refresh';

	const TTL = 43200;

	/**
	 * Re-entrancy guard. Price getters must not convert while a store amount is being read.
	 *
	 * @var int
	 */
	private static $depth = 0;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgcm_exchange_rate_options', array( __CLASS__, 'filter_option_names' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_refresh' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'admin_post_rwgcm_save_exchange_rates', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Option names whose changes must purge cached prices.
	 *
	 * @param mixed $keys Option names.
	 * @return string[]
	 */
	public static function filter_option_names( $keys ) {
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		$keys[] = self::OPTION_MANUAL;
		$keys[] = self::OPTION_SETTINGS;
		$keys[] = self::OPTION_CACHE;
		$out    = array();
		foreach ( $keys as $key ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $key ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
			if ( '' !== $key ) {
				$out[] = $key;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules['rwgcm_twelve_hours'] = array(
			'interval' => self::TTL,
			'display'  => __( 'Every 12 hours', 'reactwoo-geo-commerce' ),
		);
		return $schedules;
	}

	/**
	 * @return void
	 */
	public static function schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + 60, 'rwgcm_twelve_hours', self::CRON_HOOK );
	}

	/**
	 * @return void
	 */
	public static function cron_refresh() {
		try {
			self::refresh();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			unset( $e );
		}
	}

	/**
	 * Fetch provider rates. A failure leaves the last-good cache in place.
	 *
	 * @return bool True when a new table was stored.
	 */
	public static function refresh() {
		$settings = self::settings();
		if ( 'provider' !== $settings['source'] ) {
			return false;
		}
		$payload = self::fetch( $settings['provider'], self::store_currency(), $settings['api_key'] );
		if ( ! is_array( $payload ) ) {
			return false;
		}
		self::write_cache( $payload );
		return true;
	}

	/**
	 * @param string $provider ecb or open_er.
	 * @param string $base     Store currency, used by the open.er request.
	 * @param string $api_key  Optional key for the open.er provider.
	 * @return array<string, mixed>|null
	 */
	public static function fetch( $provider, $base, $api_key ) {
		try {
			$provider = self::provider_key( $provider );
			$base     = self::currency_code( $base );
			if ( '' === $base ) {
				$base = 'GBP';
			}
			if ( 'open_er' === $provider ) {
				$key = trim( (string) $api_key );
				$url = '' !== $key
					? 'https://v6.exchangerate-api.com/v6/' . rawurlencode( $key ) . '/latest/' . rawurlencode( $base )
					: 'https://open.er-api.com/v6/latest/' . rawurlencode( $base );
				$body = self::http_get( $url );
				return is_string( $body ) ? self::parse_er_api( $body, $base ) : null;
			}
			$body = self::http_get( 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' );
			return is_string( $body ) ? self::parse_ecb( $body ) : null;
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			unset( $e );
			return null;
		}
	}

	/**
	 * @param string $url URL.
	 * @return string|null
	 */
	public static function http_get( $url ) {
		try {
			if ( function_exists( 'apply_filters' ) ) {
				$filtered = apply_filters( 'rwgcm_exchange_http_get', null, $url );
				if ( false === $filtered ) {
					return null;
				}
				if ( is_string( $filtered ) && '' !== $filtered ) {
					return $filtered;
				}
			}
			if ( ! function_exists( 'wp_remote_get' ) ) {
				return null;
			}
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 8,
				)
			);
			if ( is_wp_error( $response ) || ! is_array( $response ) ) {
				return null;
			}
			$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
			if ( $code < 200 || $code >= 300 ) {
				return null;
			}
			$body = function_exists( 'wp_remote_retrieve_body' ) ? wp_remote_retrieve_body( $response ) : '';
			return is_string( $body ) && '' !== $body ? $body : null;
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			unset( $e );
			return null;
		}
	}

	/**
	 * ECB daily reference XML. Rates are foreign currency per 1 EUR.
	 *
	 * @param string $xml XML body.
	 * @return array<string, mixed>|null
	 */
	public static function parse_ecb( $xml ) {
		if ( ! is_string( $xml ) || '' === $xml ) {
			return null;
		}
		if ( ! preg_match_all( "/currency=['\"]([A-Z]{3})['\"]\\s+rate=['\"]([0-9]*\\.?[0-9]+)['\"]/", $xml, $matches, PREG_SET_ORDER ) ) {
			return null;
		}
		$rates = array( 'EUR' => '1' );
		foreach ( $matches as $row ) {
			if ( ! isset( $row[1], $row[2] ) || (float) $row[2] <= 0 ) {
				continue;
			}
			$rates[ $row[1] ] = $row[2];
		}
		if ( count( $rates ) < 2 ) {
			return null;
		}
		return array(
			'base'       => 'EUR',
			'rates'      => $rates,
			'provider'   => 'ecb',
			'fetched_at' => time(),
		);
	}

	/**
	 * open.er-api.com / ExchangeRate-API v6 JSON.
	 *
	 * @param string|array<string, mixed> $json           Body or decoded payload.
	 * @param string                      $expected_base  Requested base.
	 * @return array<string, mixed>|null
	 */
	public static function parse_er_api( $json, $expected_base = '' ) {
		$data = is_array( $json ) ? $json : json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( isset( $data['result'] ) && 'success' !== (string) $data['result'] ) {
			return null;
		}
		$base = '';
		if ( isset( $data['base_code'] ) ) {
			$base = self::currency_code( $data['base_code'] );
		} elseif ( isset( $data['base'] ) ) {
			$base = self::currency_code( $data['base'] );
		}
		if ( '' === $base ) {
			$base = self::currency_code( $expected_base );
		}
		$raw   = isset( $data['rates'] ) && is_array( $data['rates'] ) ? $data['rates'] : array();
		$rates = array();
		foreach ( $raw as $code => $rate ) {
			$code = self::currency_code( $code );
			if ( '' === $code || ! is_numeric( $rate ) || (float) $rate <= 0 ) {
				continue;
			}
			$rates[ $code ] = self::format_rate( $rate );
		}
		if ( '' === $base || empty( $rates ) ) {
			return null;
		}
		$rates[ $base ] = '1';
		return array(
			'base'       => $base,
			'rates'      => $rates,
			'provider'   => 'open_er',
			'fetched_at' => time(),
		);
	}

	/**
	 * Units of $to per 1 unit of $from, after markup. Null when conversion
	 * must not run.
	 *
	 * @param string $from Source currency.
	 * @param string $to   Target currency.
	 * @return array{rate: string, market: string, effective: float, source: string, base: string}|null
	 */
	public static function quote( $from, $to ) {
		$from = self::currency_code( $from );
		$to   = self::currency_code( $to );
		if ( '' === $from || '' === $to || $from === $to ) {
			return null;
		}
		$table = self::rate_table();
		if ( null === $table ) {
			return null;
		}
		$market = self::cross_rate( $table, $from, $to );
		if ( null === $market || $market <= 0 ) {
			return null;
		}
		$markup    = (float) self::settings()['markup'];
		$effective = $market * ( 1 + ( $markup / 100 ) );
		if ( $effective <= 0 ) {
			return null;
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_exchange_rate', $effective, $from, $to, $table );
			if ( is_numeric( $filtered ) && (float) $filtered > 0 ) {
				$effective = (float) $filtered;
			}
		}
		return array(
			'rate'      => self::format_rate( $effective ),
			'market'    => self::format_rate( $market ),
			'effective' => $effective,
			'source'    => isset( $table['origin'] ) ? (string) $table['origin'] : 'manual',
			'base'      => $from,
		);
	}

	/**
	 * True when this product should be charged in $currency by conversion.
	 * A fixed book price in $currency is not conversion.
	 *
	 * @param int    $product_id Product or variation id.
	 * @param string $currency   Visitor currency.
	 * @return bool
	 */
	public static function supplies( $product_id, $currency ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null;
		return self::supplies_product( $product, $currency );
	}

	/**
	 * @param object|null $product  Product.
	 * @param string      $currency Visitor currency.
	 * @return bool
	 */
	public static function supplies_product( $product, $currency ) {
		if ( self::$depth > 1 || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return false;
		}
		$currency = self::currency_code( $currency );
		if ( '' === $currency || 'off' === self::settings()['mode'] ) {
			return false;
		}
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::forces_store_currency() ) {
			return false;
		}
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::is_renewal_request() ) {
			return false;
		}
		$base = self::store_currency();
		if ( '' === $base || $currency === $base ) {
			return false;
		}
		if ( class_exists( 'RWGCM_Price_Book', false ) && class_exists( 'RWGCM_Price_Book_Currency', false ) ) {
			$book = RWGCM_Price_Book_Currency::get_book( (int) $product->get_id() );
			if ( RWGCM_Price_Book::has_price( $book, $currency ) ) {
				return false;
			}
		}
		if ( null === self::quote( $base, $currency ) ) {
			return false;
		}
		return null !== self::store_amounts( $product );
	}

	/**
	 * Converted regular, sale, and active prices. The geo rule is included.
	 * Null when this product is not converted into $currency.
	 *
	 * @param object $product  Product.
	 * @param string $currency Visitor currency.
	 * @return array{regular: string, sale: string, signup_fee: string, active: string, on_sale: bool}|null
	 */
	public static function prices_for_product( $product, $currency ) {
		if ( self::$depth > 0 ) {
			return null;
		}
		++self::$depth;
		try {
			if ( ! self::supplies_product( $product, $currency ) ) {
				return null;
			}
			$store = self::store_amounts( $product );
			$quote = self::quote( self::store_currency(), $currency );
			if ( null === $store || null === $quote ) {
				return null;
			}
			$regular = self::finish_amount( $store['regular'], $quote, $product );
			$sale    = '';
			$on_sale = ! empty( $store['on_sale'] ) && '' !== (string) $store['sale'];
			if ( $on_sale ) {
				$sale = self::finish_amount( $store['sale'], $quote, $product );
				if ( '' === $sale || (float) $sale >= (float) $regular ) {
					$sale    = '';
					$on_sale = false;
				}
			}
			$signup = '';
			if ( '' !== (string) $store['signup_fee'] && class_exists( 'RWGCM_Price_Book', false ) && RWGCM_Price_Book::amount_is_set( $store['signup_fee'] ) ) {
				$signup = self::convert_number( $store['signup_fee'], $quote );
			}
			return array(
				'regular'    => $regular,
				'sale'       => $sale,
				'signup_fee' => $signup,
				'active'     => $on_sale ? $sale : $regular,
				'on_sale'    => $on_sale,
			);
		} finally {
			--self::$depth;
		}
	}

	/**
	 * Convert a store-currency fee (no geo pricing rule).
	 *
	 * @param mixed  $amount   Store-currency amount.
	 * @param string $currency Visitor currency.
	 * @return string|null Null when conversion does not apply.
	 */
	public static function convert_fee( $amount, $currency ) {
		if ( ! class_exists( 'RWGCM_Price_Book', false ) || ! RWGCM_Price_Book::amount_is_set( $amount ) ) {
			return null;
		}
		$currency = self::currency_code( $currency );
		$base     = self::store_currency();
		if ( '' === $currency || $currency === $base || 'off' === self::settings()['mode'] ) {
			return null;
		}
		$quote = self::quote( $base, $currency );
		if ( null === $quote ) {
			return null;
		}
		return self::convert_number( $amount, $quote );
	}

	/**
	 * Rate to store on an order when at least one line was converted.
	 *
	 * @param string      $currency    Order currency.
	 * @param array<int>  $product_ids Line product or variation ids.
	 * @return array{rate: string, base: string, source: string}|null
	 */
	public static function order_rate_meta( $currency, array $product_ids ) {
		$currency = self::currency_code( $currency );
		$base     = self::store_currency();
		if ( '' === $currency || $currency === $base || 'off' === self::settings()['mode'] ) {
			return null;
		}
		$used = false;
		foreach ( $product_ids as $id ) {
			if ( self::supplies( (int) $id, $currency ) ) {
				$used = true;
				break;
			}
		}
		if ( ! $used ) {
			return null;
		}
		$quote = self::quote( $base, $currency );
		if ( null === $quote ) {
			return null;
		}
		return array(
			'rate'   => $quote['rate'],
			'base'   => $base,
			'source' => $quote['source'],
		);
	}

	/**
	 * Variation-price hash fragment. Changes when rates, markup, or rounding change.
	 *
	 * @return string
	 */
	public static function cache_token() {
		$settings = self::settings();
		$table    = self::rate_table();
		$print    = 'none';
		if ( is_array( $table ) ) {
			$rates = isset( $table['rates'] ) && is_array( $table['rates'] ) ? $table['rates'] : array();
			ksort( $rates );
			$print = substr( md5( (string) $table['base'] . '|' . (string) json_encode( $rates ) ), 0, 12 );
		}
		return $settings['mode'] . '|' . $settings['source'] . '|' . $settings['rounding'] . '|' . $settings['markup'] . '|' . $print;
	}

	/**
	 * @param mixed  $amount Amount.
	 * @param string $mode   none, whole, or 99. Null uses the saved setting.
	 * @return string
	 */
	public static function round_amount( $amount, $mode = null ) {
		$amount = (float) $amount;
		if ( $amount < 0 ) {
			$amount = 0.0;
		}
		if ( null === $mode ) {
			$mode = self::settings()['rounding'];
		}
		$mode = (string) $mode;
		if ( 'whole' === $mode ) {
			return number_format( round( $amount, 0 ), 2, '.', '' );
		}
		if ( '99' === $mode ) {
			$charm = max( 0, round( $amount, 0 ) - 0.01 );
			return number_format( $charm, 2, '.', '' );
		}
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (string) wc_format_decimal( $amount, $decimals );
		}
		return number_format( $amount, $decimals, '.', '' );
	}

	/**
	 * @return array{mode: string, source: string, provider: string, api_key: string, rounding: string, markup: string}
	 */
	public static function settings() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_SETTINGS, array() ) : array();
		return self::sanitize_settings( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @param mixed $raw Posted or stored settings.
	 * @return array{mode: string, source: string, provider: string, api_key: string, rounding: string, markup: string}
	 */
	public static function sanitize_settings( $raw ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$mode     = isset( $raw['mode'] ) ? (string) $raw['mode'] : ( isset( $raw['rwgcm_exchange_mode'] ) ? (string) $raw['rwgcm_exchange_mode'] : 'auto' );
		$source   = isset( $raw['source'] ) ? (string) $raw['source'] : ( isset( $raw['rwgcm_exchange_source'] ) ? (string) $raw['rwgcm_exchange_source'] : 'manual' );
		$provider = isset( $raw['provider'] ) ? (string) $raw['provider'] : ( isset( $raw['rwgcm_exchange_provider'] ) ? (string) $raw['rwgcm_exchange_provider'] : 'ecb' );
		$rounding = isset( $raw['rounding'] ) ? (string) $raw['rounding'] : ( isset( $raw['rwgcm_exchange_rounding'] ) ? (string) $raw['rwgcm_exchange_rounding'] : 'none' );
		$markup   = isset( $raw['markup'] ) ? $raw['markup'] : ( isset( $raw['rwgcm_exchange_markup'] ) ? $raw['rwgcm_exchange_markup'] : '0' );
		$key      = isset( $raw['api_key'] ) ? (string) $raw['api_key'] : ( isset( $raw['rwgcm_exchange_api_key'] ) ? (string) $raw['rwgcm_exchange_api_key'] : '' );
		$mode     = self::choice( $mode, array( 'auto', 'off' ), 'auto' );
		$source   = self::choice( $source, array( 'manual', 'provider' ), 'manual' );
		$provider = self::provider_key( $provider );
		$rounding = self::choice( $rounding, array( 'none', 'whole', '99' ), 'none' );
		$markup_n = is_numeric( $markup ) ? (float) $markup : 0.0;
		if ( $markup_n < -100 ) {
			$markup_n = -100.0;
		}
		if ( $markup_n > 500 ) {
			$markup_n = 500.0;
		}
		$key         = trim( preg_replace( '/[^A-Za-z0-9_\-]/', '', $key ) );
		$markup_text = rtrim( rtrim( number_format( $markup_n, 4, '.', '' ), '0' ), '.' );
		if ( '' === $markup_text || '-0' === $markup_text ) {
			$markup_text = '0';
		}
		return array(
			'mode'     => $mode,
			'source'   => $source,
			'provider' => $provider,
			'api_key'  => $key,
			'rounding' => $rounding,
			'markup'   => $markup_text,
		);
	}

	/**
	 * @param mixed $raw Posted rates, keyed by currency.
	 * @return array{base: string, rates: array<string, string>}
	 */
	public static function sanitize_manual( $raw ) {
		$base  = self::store_currency();
		$rates = array();
		if ( is_array( $raw ) ) {
			$allowed = class_exists( 'RWGCM_Price_Book', false ) ? RWGCM_Price_Book::currencies() : array( 'ZAR', 'GBP', 'EUR', 'USD' );
			foreach ( $raw as $code => $amount ) {
				$code = self::currency_code( $code );
				if ( '' === $code || $code === $base || ! in_array( $code, $allowed, true ) ) {
					continue;
				}
				if ( ! class_exists( 'RWGCM_Price_Book', false ) || ! RWGCM_Price_Book::amount_is_set( $amount ) || (float) $amount <= 0 ) {
					continue;
				}
				$rates[ $code ] = self::format_rate( $amount );
			}
		}
		ksort( $rates );
		return array(
			'base'  => $base,
			'rates' => $rates,
		);
	}

	/**
	 * @return array{ok: bool, error?: string}
	 */
	public static function process_save_request() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'ok'    => false,
				'error' => 'forbidden',
			);
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? (string) $_POST['_wpnonce'] : '';
		if ( function_exists( 'wp_unslash' ) ) {
			$nonce = (string) wp_unslash( $nonce );
		}
		if ( function_exists( 'sanitize_text_field' ) ) {
			$nonce = sanitize_text_field( $nonce );
		}
		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'rwgcm_save_exchange_rates' ) ) {
			return array(
				'ok'    => false,
				'error' => 'nonce',
			);
		}
		$posted   = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST ) : $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings = self::sanitize_settings( is_array( $posted ) ? $posted : array() );
		if ( '' === $settings['api_key'] && empty( $posted['rwgcm_exchange_clear_key'] ) ) {
			$settings['api_key'] = self::settings()['api_key'];
		}
		if ( ! empty( $posted['rwgcm_exchange_clear_key'] ) ) {
			$settings['api_key'] = '';
		}
		$manual_raw = ( is_array( $posted ) && isset( $posted['rwgcm_exchange_manual'] ) ) ? $posted['rwgcm_exchange_manual'] : array();
		$manual     = self::sanitize_manual( $manual_raw );
		self::persist( $settings, $manual, true );
		return array(
			'ok' => true,
		);
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		$result = self::process_save_request();
		if ( empty( $result['ok'] ) ) {
			$message = ( isset( $result['error'] ) && 'forbidden' === $result['error'] )
				? __( 'You need permission to manage WooCommerce to change exchange rates.', 'reactwoo-geo-commerce' )
				: __( 'The link you followed has expired.', 'reactwoo-geo-commerce' );
			wp_die( esc_html( $message ), '', array( 'response' => 403 ) );
		}
		$flag = '1';
		if ( 'provider' === self::settings()['source'] && ! self::has_provider_rates() ) {
			$flag = '0';
		}
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=rwgcm-settings&rwgcm_exchange_saved=' . $flag ) : '';
		if ( function_exists( 'wp_safe_redirect' ) && '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param array<string, mixed> $manual   Sanitized manual table.
	 * @param bool                 $fetch    Whether to try the provider now.
	 * @return void
	 */
	public static function persist( array $settings, array $manual, $fetch ) {
		self::update_option( self::OPTION_SETTINGS, $settings );
		self::update_option( self::OPTION_MANUAL, $manual );
		if ( $fetch && isset( $settings['source'] ) && 'provider' === $settings['source'] ) {
			try {
				self::refresh();
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $e );
			}
		}
		self::notify_saved( $manual );
	}

	/**
	 * @return array{mode: string, source: string, provider: string, api_key: string, rounding: string, markup: string, manual: array<string, mixed>, cache: array<string, mixed>}
	 */
	public static function admin_state() {
		$settings = self::settings();
		$manual   = self::read_manual_option();
		$cache    = self::read_cache_option();
		return array(
			'mode'     => $settings['mode'],
			'source'   => $settings['source'],
			'provider' => $settings['provider'],
			'api_key'  => $settings['api_key'],
			'rounding' => $settings['rounding'],
			'markup'   => $settings['markup'],
			'manual'   => is_array( $manual ) ? $manual : array(),
			'cache'    => is_array( $cache ) ? $cache : array(),
		);
	}

	/**
	 * @return bool
	 */
	public static function has_provider_rates() {
		return self::valid_table( self::read_cache_option() ) || self::valid_table( self::read_transient() );
	}

	/**
	 * @param array<string, mixed> $payload Parsed provider table.
	 * @return void
	 */
	public static function write_cache( array $payload ) {
		if ( ! self::valid_table( $payload ) ) {
			return;
		}
		if ( empty( $payload['fetched_at'] ) ) {
			$payload['fetched_at'] = time();
		}
		$previous = self::read_cache_option();
		$changed  = ! self::rates_equivalent( $previous, $payload );
		$quiet    = ! $changed && class_exists( 'RWGCM_Cache_Purge', false );
		if ( $quiet ) {
			RWGCM_Cache_Purge::silence_exchange_purge( true );
		}
		try {
			self::update_option( self::OPTION_CACHE, $payload );
			if ( function_exists( 'set_transient' ) ) {
				set_transient( self::TRANSIENT, $payload, self::TTL );
			}
		} finally {
			if ( $quiet ) {
				RWGCM_Cache_Purge::silence_exchange_purge( false );
			}
		}
		if ( $changed ) {
			self::notify_saved( $payload );
		}
	}

	/**
	 * True when two rate tables quote the same currencies at 6 decimal places.
	 * fetched_at and the provider name are ignored. Float noise below that
	 * precision does not count as a change.
	 *
	 * @param mixed $before Previous table, or null.
	 * @param mixed $after  New table, or null.
	 * @return bool
	 */
	public static function rates_equivalent( $before, $after ) {
		return self::rate_fingerprint( $before ) === self::rate_fingerprint( $after );
	}

	/**
	 * @param mixed $table Rate table or null.
	 * @return string
	 */
	public static function rate_fingerprint( $table ) {
		if ( ! is_array( $table ) ) {
			return '';
		}
		$base = '';
		if ( isset( $table['base'] ) ) {
			$base = self::currency_code( $table['base'] );
		}
		$raw = ( isset( $table['rates'] ) && is_array( $table['rates'] ) ) ? $table['rates'] : $table;
		if ( ! is_array( $raw ) ) {
			return $base . '|';
		}
		$rates = array();
		foreach ( $raw as $code => $rate ) {
			if ( in_array( (string) $code, array( 'base', 'rates', 'provider', 'fetched_at', 'origin' ), true ) ) {
				continue;
			}
			$code = self::currency_code( $code );
			if ( '' === $code || ! is_numeric( $rate ) || (float) $rate <= 0 ) {
				continue;
			}
			$rates[ $code ] = number_format( (float) $rate, 6, '.', '' );
		}
		ksort( $rates );
		$parts = array();
		foreach ( $rates as $code => $rate ) {
			$parts[] = $code . '=' . $rate;
		}
		return $base . '|' . implode( ',', $parts );
	}

	/**
	 * @param mixed $payload Saved rates.
	 * @return void
	 */
	private static function notify_saved( $payload ) {
		if ( function_exists( 'do_action' ) ) {
			do_action( 'rwgcm_exchange_rates_saved', $payload );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function rate_table() {
		if ( 'off' === self::settings()['mode'] ) {
			return null;
		}
		if ( 'manual' === self::settings()['source'] ) {
			return self::manual_table();
		}
		$fresh = self::read_transient();
		if ( self::valid_table( $fresh ) ) {
			$fresh['origin'] = self::provider_key( isset( $fresh['provider'] ) ? $fresh['provider'] : 'ecb' );
			return $fresh;
		}
		$cache = self::read_cache_option();
		if ( self::valid_table( $cache ) ) {
			$age    = time() - (int) ( isset( $cache['fetched_at'] ) ? $cache['fetched_at'] : 0 );
			$origin = ( $age >= 0 && $age < self::TTL )
				? self::provider_key( isset( $cache['provider'] ) ? $cache['provider'] : 'ecb' )
				: 'cache';
			$cache['origin'] = $origin;
			return $cache;
		}
		$manual = self::manual_table();
		if ( null !== $manual ) {
			$manual['origin'] = 'manual';
			return $manual;
		}
		return null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function manual_table() {
		$stored = self::read_manual_option();
		if ( ! is_array( $stored ) ) {
			return null;
		}
		$base = self::store_currency();
		if ( isset( $stored['rates'] ) && is_array( $stored['rates'] ) ) {
			$stored_base = self::currency_code( isset( $stored['base'] ) ? $stored['base'] : '' );
			if ( '' !== $stored_base && $stored_base !== $base ) {
				return null;
			}
			$rates = self::positive_rates( $stored['rates'] );
		} else {
			$rates = self::positive_rates( $stored );
		}
		if ( empty( $rates ) ) {
			return null;
		}
		$rates[ $base ] = '1';
		return array(
			'base'   => $base,
			'rates'  => $rates,
			'origin' => 'manual',
		);
	}

	/**
	 * @param array<string, mixed> $table Rate table.
	 * @param string               $from  Source currency.
	 * @param string               $to    Target currency.
	 * @return float|null
	 */
	private static function cross_rate( array $table, $from, $to ) {
		$base  = isset( $table['base'] ) ? self::currency_code( $table['base'] ) : '';
		$rates = isset( $table['rates'] ) && is_array( $table['rates'] ) ? $table['rates'] : array();
		if ( '' === $base ) {
			return null;
		}
		$from_per = ( $from === $base ) ? 1.0 : ( isset( $rates[ $from ] ) ? (float) $rates[ $from ] : 0.0 );
		$to_per   = ( $to === $base ) ? 1.0 : ( isset( $rates[ $to ] ) ? (float) $rates[ $to ] : 0.0 );
		if ( $from_per <= 0 || $to_per <= 0 ) {
			return null;
		}
		return $to_per / $from_per;
	}

	/**
	 * Store-currency prices with the matching geo rule applied and no conversion.
	 * Used when the cart fell back to the store currency.
	 *
	 * @param object $product Product.
	 * @return array{regular: string, sale: string, signup_fee: string, active: string, on_sale: bool}|null
	 */
	public static function ruled_store_prices( $product ) {
		$store = self::store_amounts( $product );
		if ( null === $store ) {
			return null;
		}
		$regular = self::apply_rule_amount( $store['regular'], $product );
		$sale    = '';
		$on_sale = ! empty( $store['on_sale'] ) && '' !== (string) $store['sale'];
		if ( $on_sale ) {
			$sale = self::apply_rule_amount( $store['sale'], $product );
			if ( '' === $sale || (float) $sale >= (float) $regular ) {
				$sale    = '';
				$on_sale = false;
			}
		}
		return array(
			'regular'    => $regular,
			'sale'       => $sale,
			'signup_fee' => isset( $store['signup_fee'] ) ? (string) $store['signup_fee'] : '',
			'active'     => $on_sale ? $sale : $regular,
			'on_sale'    => $on_sale,
		);
	}

	/**
	 * @param object $product Product.
	 * @return array{regular: string, sale: string, signup_fee: string, active: string, on_sale: bool}|null
	 */
	private static function store_amounts( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! class_exists( 'RWGCM_Price_Book', false ) ) {
			return null;
		}
		$base = self::store_currency();
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) ) {
			$book = RWGCM_Price_Book_Currency::get_book( (int) $product->get_id() );
			if ( RWGCM_Price_Book::has_price( $book, $base ) ) {
				return RWGCM_Price_Book::resolve_prices( $book[ $base ] );
			}
		}
		$regular = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price( 'edit' ) : '';
		if ( ! RWGCM_Price_Book::amount_is_set( $regular ) && method_exists( $product, 'get_price' ) ) {
			$regular = $product->get_price( 'edit' );
		}
		if ( ! RWGCM_Price_Book::amount_is_set( $regular ) ) {
			return null;
		}
		$sale    = method_exists( $product, 'get_sale_price' ) ? $product->get_sale_price( 'edit' ) : '';
		$on_sale = RWGCM_Price_Book::amount_is_set( $sale ) && (float) $sale < (float) $regular;
		return array(
			'regular'    => RWGCM_Price_Book::format_amount( $regular ),
			'sale'       => $on_sale ? RWGCM_Price_Book::format_amount( $sale ) : '',
			'signup_fee' => '',
			'active'     => $on_sale ? RWGCM_Price_Book::format_amount( $sale ) : RWGCM_Price_Book::format_amount( $regular ),
			'on_sale'    => $on_sale,
		);
	}

	/**
	 * @param object $product Product.
	 * @return array{type: string, value: float}|null
	 */
	private static function rule_for( $product ) {
		if ( ! class_exists( 'RWGCM_Pricing_Resolution', false ) || ! RWGCM_Pricing_Resolution::is_pricing_effective() ) {
			return null;
		}
		$rule = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
		return is_array( $rule ) ? $rule : null;
	}

	/**
	 * @param mixed                        $store_amount Store-currency amount.
	 * @param array<string, mixed>         $quote        Quote.
	 * @param object                       $product      Product.
	 * @return string
	 */
	private static function finish_amount( $store_amount, array $quote, $product ) {
		$amount = (float) $store_amount;
		$rule   = self::rule_for( $product );
		if ( is_array( $rule ) && isset( $rule['type'] ) && 'fixed_line' === $rule['type'] ) {
			$amount = max( 0, $amount + (float) $rule['value'] );
		}
		$converted = $amount * (float) $quote['effective'];
		return self::finish_ruled_amount( $converted, $converted, $rule, $product );
	}

	/**
	 * Apply a geo rule to a store-currency figure. Fixed-line and percent both
	 * use that currency because nothing is being converted.
	 *
	 * @param mixed  $amount  Store amount.
	 * @param object $product Product.
	 * @return string
	 */
	private static function apply_rule_amount( $amount, $product ) {
		$figure = (float) $amount;
		$rule   = self::rule_for( $product );
		$base   = $figure;
		if ( is_array( $rule ) && isset( $rule['type'] ) && 'fixed_line' === $rule['type'] ) {
			$figure = max( 0, $figure + (float) $rule['value'] );
		}
		return self::finish_ruled_amount( $figure, $base, $rule, $product );
	}

	/**
	 * @param float                        $amount  Amount after a fixed-line change, before percent.
	 * @param float                        $pre_rule Amount passed to the adjustment filter as the base.
	 * @param array<string, mixed>|null    $rule    Rule or null.
	 * @param object                       $product Product.
	 * @return string
	 */
	private static function finish_ruled_amount( $amount, $pre_rule, $rule, $product ) {
		$figure = (float) $amount;
		if ( is_array( $rule ) && ( ! isset( $rule['type'] ) || 'fixed_line' !== $rule['type'] ) ) {
			$figure = $figure * ( 1 + ( (float) $rule['value'] / 100 ) );
		}
		if ( $figure < 0 ) {
			$figure = 0.0;
		}
		if ( is_array( $rule ) && function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_adjusted_unit_price', $figure, $pre_rule, $rule, $product, array(), '' );
			if ( is_numeric( $filtered ) ) {
				$figure = max( 0, (float) $filtered );
			}
		}
		return self::round_amount( $figure );
	}

	/**
	 * @param mixed                $amount Store amount.
	 * @param array<string, mixed> $quote  Quote.
	 * @return string
	 */
	private static function convert_number( $amount, array $quote ) {
		$converted = (float) $amount * (float) $quote['effective'];
		return self::round_amount( $converted );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	private static function update_option( $option, $value ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( $option, $value, false );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function read_manual_option() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		$value = get_option( self::OPTION_MANUAL, array() );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function read_cache_option() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}
		$value = get_option( self::OPTION_CACHE, array() );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function read_transient() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$value = get_transient( self::TRANSIENT );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * @param mixed $table Table.
	 * @return bool
	 */
	private static function valid_table( $table ) {
		if ( ! is_array( $table ) ) {
			return false;
		}
		$base = self::currency_code( isset( $table['base'] ) ? $table['base'] : '' );
		if ( '' === $base || empty( $table['rates'] ) || ! is_array( $table['rates'] ) ) {
			return false;
		}
		return ! empty( self::positive_rates( $table['rates'] ) );
	}

	/**
	 * @param array<mixed> $raw Rates.
	 * @return array<string, string>
	 */
	private static function positive_rates( array $raw ) {
		$out = array();
		foreach ( $raw as $code => $rate ) {
			$code = self::currency_code( $code );
			if ( '' === $code || ! is_numeric( $rate ) || (float) $rate <= 0 ) {
				continue;
			}
			$out[ $code ] = self::format_rate( $rate );
		}
		return $out;
	}

	/**
	 * @return string
	 */
	private static function store_currency() {
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) ) {
			$base = RWGCM_Price_Book_Currency::base_currency();
			if ( '' !== $base ) {
				return $base;
			}
		}
		$raw = function_exists( 'get_option' ) ? get_option( 'woocommerce_currency', 'GBP' ) : 'GBP';
		$code = self::currency_code( $raw );
		return '' !== $code ? $code : 'GBP';
	}

	/**
	 * @param mixed $code Code.
	 * @return string
	 */
	private static function currency_code( $code ) {
		if ( class_exists( 'RWGCM_Price_Book', false ) ) {
			return RWGCM_Price_Book::normalize_currency( $code );
		}
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $code ) );
		return strlen( $code ) === 3 ? $code : '';
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function format_rate( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}
		$number = (float) $value;
		if ( $number <= 0 ) {
			return '';
		}
		$text = number_format( $number, 8, '.', '' );
		$text = rtrim( rtrim( $text, '0' ), '.' );
		return '' === $text ? '' : $text;
	}

	/**
	 * @param string        $value   Raw choice.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string        $default Default.
	 * @return string
	 */
	private static function choice( $value, array $allowed, $default ) {
		$value = strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $value ) );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * @param mixed $provider Provider id.
	 * @return string
	 */
	private static function provider_key( $provider ) {
		$provider = strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $provider ) );
		return in_array( $provider, array( 'ecb', 'open_er' ), true ) ? $provider : 'ecb';
	}
}
