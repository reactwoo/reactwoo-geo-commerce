<?php
/**
 * First-touch / last-touch marketing params (UTM + common click ids) → cookies → order meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures query-string attribution into cookies and merges into {@see 'rwgcm_checkout_order_meta'}.
 */
class RWGCM_Attribution {

	const COOKIE_FT = 'rwgcm_utm_ft';
	const COOKIE_LT = 'rwgcm_utm_lt';
	const OPTION_STORE_UTM = 'rwgcm_store_utm';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_capture_query' ), 1 );
		add_filter( 'rwgcm_checkout_order_meta', array( __CLASS__, 'merge_order_meta' ), 8, 4 );
	}

	/**
	 * Whether to persist attribution (option + filter).
	 *
	 * @return bool
	 */
	public static function is_storage_enabled() {
		$opt = get_option( self::OPTION_STORE_UTM, 'yes' );
		$on  = ( 'yes' === $opt );
		return (bool) apply_filters( 'rwgcm_store_utm_on_orders', $on );
	}

	/**
	 * @return void
	 */
	public static function maybe_capture_query() {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}
		if ( ! self::is_storage_enabled() ) {
			return;
		}

		$bag = self::collect_from_request();
		if ( empty( $bag ) ) {
			return;
		}

		$ft = self::read_cookie_payload( self::COOKIE_FT );
		if ( empty( $ft ) ) {
			$ft = $bag;
		}
		$lt = $bag;

		self::write_cookie( self::COOKIE_FT, $ft );
		self::write_cookie( self::COOKIE_LT, $lt );
	}

	/**
	 * @return array<string, string>
	 */
	private static function collect_from_request() {
		$keys = apply_filters(
			'rwgcm_attribution_query_keys',
			array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' )
		);
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		$out = array();
		foreach ( $keys as $k ) {
			if ( ! is_string( $k ) || ! isset( $_GET[ $k ] ) ) {
				continue;
			}
			$v = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
			if ( '' === $v ) {
				continue;
			}
			$out[ $k ] = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 255 ) : substr( $v, 0, 255 );
		}
		return $out;
	}

	/**
	 * @param string $name Cookie name.
	 * @return array<string, string>
	 */
	private static function read_cookie_payload( $name ) {
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return array();
		}
		$raw = wp_unslash( $_COOKIE[ $name ] );
		$dec = json_decode( $raw, true );
		return is_array( $dec ) ? self::sanitize_payload( $dec ) : array();
	}

	/**
	 * @param array<string, mixed> $data Raw.
	 * @return array<string, string>
	 */
	private static function sanitize_payload( $data ) {
		$out = array();
		foreach ( $data as $k => $v ) {
			if ( ! is_string( $k ) || ! is_string( $v ) ) {
				continue;
			}
			$k = preg_replace( '/[^a-z0-9_]/', '', strtolower( $k ) );
			if ( '' === $k ) {
				continue;
			}
			$out[ $k ] = function_exists( 'mb_substr' ) ? mb_substr( sanitize_text_field( $v ), 0, 255 ) : substr( sanitize_text_field( $v ), 0, 255 );
		}
		return $out;
	}

	/**
	 * @param array<string, string> $data Payload.
	 * @return void
	 */
	private static function write_cookie( $name, $data ) {
		if ( empty( $data ) ) {
			return;
		}
		$val     = wp_json_encode( $data );
		$expires = time() + (int) apply_filters( 'rwgcm_attribution_cookie_ttl', 180 * DAY_IN_SECONDS );
		$path    = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		$domain  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure  = is_ssl();
		$httponly = true;

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				$name,
				$val,
				array(
					'expires'  => $expires,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => $httponly,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( $name, $val, $expires, $path, $domain, $secure, $httponly );
		}

		$_COOKIE[ $name ] = $val;
	}

	/**
	 * @param array<string, scalar|null> $meta    Meta keys.
	 * @param \WC_Order                  $order   Order.
	 * @param array<string, mixed>       $visitor Visitor geo.
	 * @param string                     $iso2    Country.
	 * @return array<string, scalar|null>
	 */
	public static function merge_order_meta( $meta, $order, $visitor, $iso2 ) {
		unset( $order, $visitor, $iso2 );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( ! self::is_storage_enabled() ) {
			return $meta;
		}

		$ft = self::read_cookie_payload( self::COOKIE_FT );
		$lt = self::read_cookie_payload( self::COOKIE_LT );

		foreach ( array( 'ft' => $ft, 'lt' => $lt ) as $suffix => $payload ) {
			foreach ( $payload as $param => $value ) {
				$key = self::meta_key_for_param( $param, $suffix );
				if ( null !== $key && '' !== $value ) {
					$meta[ $key ] = $value;
				}
			}
		}

		return $meta;
	}

	/**
	 * @param string $param Query param (e.g. utm_source).
	 * @param string $suffix ft|lt.
	 * @return string|null Meta key or null if invalid.
	 */
	public static function meta_key_for_param( $param, $suffix ) {
		$param = strtolower( (string) $param );
		$param = preg_replace( '/[^a-z0-9_]/', '', $param );
		if ( '' === $param || ! in_array( $suffix, array( 'ft', 'lt' ), true ) ) {
			return null;
		}
		return '_rwgcm_' . $param . '_' . $suffix;
	}

	/**
	 * Human-readable labels for order screen (keys we manage).
	 *
	 * @return array<string, string>
	 */
	public static function order_meta_labels() {
		$keys = apply_filters(
			'rwgcm_attribution_query_keys',
			array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' )
		);
		$labels = array();
		foreach ( is_array( $keys ) ? $keys : array() as $k ) {
			if ( ! is_string( $k ) ) {
				continue;
			}
			$k = preg_replace( '/[^a-z0-9_]/', '', strtolower( $k ) );
			if ( '' === $k ) {
				continue;
			}
			/* translators: 1: parameter name, 2: first or last touch */
			$labels[ self::meta_key_for_param( $k, 'ft' ) ] = sprintf( __( '%1$s (first touch)', 'reactwoo-geo-commerce' ), $k );
			$labels[ self::meta_key_for_param( $k, 'lt' ) ] = sprintf( __( '%1$s (last touch)', 'reactwoo-geo-commerce' ), $k );
		}
		return $labels;
	}
}
