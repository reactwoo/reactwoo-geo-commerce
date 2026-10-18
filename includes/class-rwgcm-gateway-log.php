<?php
/**
 * De-duplicated warnings for Payments by country (source rwgcm-gateways).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs each distinct gateway warning at most once per request and once per 24 hours.
 */
class RWGCM_Gateway_Log {

	/**
	 * Option behind the "Log payment-rule fallbacks" checkbox ('yes' or 'no', default yes).
	 */
	const OPTION_ENABLED = 'rwgcm_log_gateway_warnings';

	/**
	 * Transient prefix.
	 */
	const TRANSIENT_PREFIX = 'rwgcm_gwlog_';

	/**
	 * Seconds a distinct warning stays quiet after it is logged.
	 */
	const TTL = 86400;

	/**
	 * Keys already handled in this request.
	 *
	 * @var array<string, bool>
	 */
	private static $seen = array();

	/**
	 * Whether warnings are logged: the setting, then the rwgcm_log_gateway_warnings filter.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$setting = function_exists( 'get_option' ) ? get_option( self::OPTION_ENABLED, 'yes' ) : 'yes';
		$on      = 'no' !== $setting;
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Whether Geo Commerce logs payment-rule warnings (source rwgcm-gateways).
			 *
			 * @param bool $on Default true, or false when the Payments by country checkbox is off.
			 */
			$on = (bool) apply_filters( 'rwgcm_log_gateway_warnings', $on );
		}
		return $on;
	}

	/**
	 * Stable key for one distinct warning. The gateway order does not matter.
	 *
	 * @param string             $type     Warning type (fallback, no_gateway_for_currency).
	 * @param string             $country  Billing ISO2 or empty.
	 * @param string             $currency Currency or empty.
	 * @param array<int, string> $gateways Gateway ids involved (removed or remaining).
	 * @return string
	 */
	public static function key( $type, $country, $currency, array $gateways ) {
		$ids = array_values( array_unique( array_map( 'strval', $gateways ) ) );
		sort( $ids );
		return md5( self::encode( array( (string) $type, strtoupper( (string) $country ), strtoupper( (string) $currency ), $ids ) ) );
	}

	/**
	 * Log a warning unless the same one was logged in this request or in the last 24 hours.
	 *
	 * @param string             $type     Warning type.
	 * @param string             $country  Billing ISO2.
	 * @param string             $currency Currency.
	 * @param array<int, string> $gateways Gateway ids involved.
	 * @param string             $message  Message.
	 * @return bool True when the line was written.
	 */
	public static function warn( $type, $country, $currency, array $gateways, $message ) {
		if ( ! self::enabled() || ! function_exists( 'wc_get_logger' ) ) {
			return false;
		}
		$key = self::key( $type, $country, $currency, $gateways );
		if ( isset( self::$seen[ $key ] ) ) {
			return false;
		}
		self::$seen[ $key ] = true;
		$transient          = self::TRANSIENT_PREFIX . $key;
		if ( function_exists( 'get_transient' ) && false !== get_transient( $transient ) ) {
			return false;
		}
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $transient, time(), self::TTL );
		}
		wc_get_logger()->warning( (string) $message, array( 'source' => 'rwgcm-gateways' ) );
		return true;
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function encode( $value ) {
		$out = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Tests: forget the per-request memory.
	 *
	 * @return void
	 */
	public static function reset_request_cache() {
		self::$seen = array();
	}

	/**
	 * Save the checkbox. Only called when the Payments by country form posted it.
	 *
	 * @param bool $on Checked.
	 * @return void
	 */
	public static function save_setting( $on ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_ENABLED, $on ? 'yes' : 'no', false );
		}
	}
}
