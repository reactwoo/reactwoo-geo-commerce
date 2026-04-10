<?php
/**
 * Geo Commerce-owned ReactWoo API client and JWT cache.
 *
 * @package ReactWooGeoCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGCM_Platform_Client {

	const TOKEN_TRANSIENT  = 'rwgcm_rw_jwt_cache';
	const LOGIN_PATH       = '/api/v5/auth/login';
	const DEFAULT_API_BASE = 'https://api.reactwoo.com';
	const PRODUCT_SLUG     = 'reactwoo-geo-commerce';

	/**
	 * @return string
	 */
	public static function get_api_base() {
		if ( defined( 'RWGCM_REACTWOO_API_BASE' ) && is_string( RWGCM_REACTWOO_API_BASE ) ) {
			$configured = trim( (string) RWGCM_REACTWOO_API_BASE );
			if ( '' !== $configured && wp_http_validate_url( $configured ) ) {
				return untrailingslashit( esc_url_raw( $configured ) );
			}
		}

		$via_filter = apply_filters( 'rwgcm_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$filtered = esc_url_raw( trim( $via_filter ) );
			if ( $filtered && wp_http_validate_url( $filtered ) ) {
				return untrailingslashit( $filtered );
			}
		}

		if ( class_exists( 'RWGCM_Settings', false ) ) {
			$settings = RWGCM_Settings::get_settings();
			if ( is_array( $settings ) && ! empty( $settings['reactwoo_api_base'] ) ) {
				$saved = esc_url_raw( trim( (string) $settings['reactwoo_api_base'] ) );
				if ( $saved && wp_http_validate_url( $saved ) ) {
					return untrailingslashit( $saved );
				}
			}
		}

		return self::DEFAULT_API_BASE;
	}

	/**
	 * @return string
	 */
	public static function get_license_key() {
		if ( ! class_exists( 'RWGCM_Settings', false ) ) {
			return '';
		}
		$settings = RWGCM_Settings::get_settings();
		if ( ! is_array( $settings ) || empty( $settings['reactwoo_license_key'] ) ) {
			return '';
		}
		return trim( (string) $settings['reactwoo_license_key'] );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_license_key();
	}

	/**
	 * @return void
	 */
	public static function clear_token_cache() {
		delete_transient( self::TOKEN_TRANSIENT );
	}

	/**
	 * @return string|null
	 */
	public static function get_bearer_for_updates() {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * @return string|\WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && isset( $cached['expires'] ) && (int) $cached['expires'] > time() + 120 ) {
			return (string) $cached['token'];
		}

		$license = self::get_license_key();
		if ( '' === $license ) {
			return new WP_Error( 'rwgcm_no_license', __( 'Save a Geo Commerce license key before validating or updating this plugin.', 'reactwoo-geo-commerce' ) );
		}

		$domain = self::get_site_domain();
		if ( '' === $domain ) {
			return new WP_Error( 'rwgcm_no_domain', __( 'Could not determine this site domain for license login.', 'reactwoo-geo-commerce' ) );
		}

		$body = array(
			'license_key'  => $license,
			'domain'       => $domain,
			'product_slug' => self::PRODUCT_SLUG,
			'catalog_slug' => self::PRODUCT_SLUG,
		);
		$filtered = apply_filters( 'rwgc_auth_login_body', $body, $license, $domain );
		$body     = is_array( $filtered ) ? $filtered : $body;

		$response = wp_remote_post(
			self::get_api_base() . self::LOGIN_PATH,
			array(
				'timeout' => 30,
				'headers' => self::base_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'License login failed.', 'reactwoo-geo-commerce' );
			return new WP_Error( 'rwgcm_login_failed', $msg, array( 'status' => $code ) );
		}

		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			return new WP_Error( 'rwgcm_login_no_token', __( 'License login response did not include a token.', 'reactwoo-geo-commerce' ) );
		}

		$ttl = min( self::parse_expires_in( isset( $data['expires_in'] ) ? $data['expires_in'] : null ), 23 * HOUR_IN_SECONDS );
		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token'   => $token,
				'expires' => time() + $ttl,
			),
			$ttl
		);
		return $token;
	}

	/**
	 * @return string
	 */
	private static function get_site_domain() {
		$home = home_url( '/' );
		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		return '';
	}

	/**
	 * @param mixed $raw Expiry from API.
	 * @return int
	 */
	private static function parse_expires_in( $raw ) {
		if ( is_numeric( $raw ) ) {
			return max( 300, (int) $raw );
		}
		if ( is_string( $raw ) && preg_match( '/^(\d+)h$/i', $raw, $m ) ) {
			return max( 300, (int) $m[1] * HOUR_IN_SECONDS );
		}
		return DAY_IN_SECONDS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function base_headers() {
		return array(
			'Content-Type'     => 'application/json',
			'X-Requested-With' => 'XMLHttpRequest',
		);
	}
}
