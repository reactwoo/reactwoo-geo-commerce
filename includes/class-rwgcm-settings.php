<?php
/**
 * ReactWoo API URL + product license for Geo Commerce (commercial satellite — not stored in Geo Core).
 *
 * @package ReactWooGeoCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for ReactWoo Geo Commerce.
 */
class RWGCM_Settings {

	const OPTION_KEY = 'rwgcm_settings';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_clear_jwt_on_change' ), 10, 2 );
	}

	/**
	 * Legacy no-op: Geo Commerce now owns its own platform client and does not register shared license filters.
	 *
	 * @return void
	 */
	public static function register_platform_filters() {
		return;
	}

	/**
	 * Legacy no-op: automatic cross-plugin license migration has been removed.
	 *
	 * @return void
	 */
	public static function maybe_migrate_from_geo_core() {
		return;
	}

	/**
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'rwgcm_license_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * @param string $key Default from Core.
	 * @return string
	 */
	public static function filter_license_key( $key ) {
		$s = self::get_settings();
		if ( is_array( $s ) && isset( $s['reactwoo_license_key'] ) ) {
			$k = trim( (string) $s['reactwoo_license_key'] );
			if ( '' !== $k ) {
				return $k;
			}
		}
		if ( class_exists( 'RWGA_Settings', false ) ) {
			$ai = get_option( RWGA_Settings::OPTION_KEY, array() );
			if ( is_array( $ai ) && ! empty( $ai['reactwoo_license_key'] ) ) {
				return trim( (string) $ai['reactwoo_license_key'] );
			}
		}
		if ( class_exists( 'RWGO_Settings', false ) ) {
			$go = get_option( RWGO_Settings::OPTION_KEY, array() );
			if ( is_array( $go ) && ! empty( $go['reactwoo_license_key'] ) ) {
				return trim( (string) $go['reactwoo_license_key'] );
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				return trim( (string) $raw['reactwoo_license_key'] );
			}
		}
		return (string) $key;
	}

	/**
	 * @param string $base Default URL.
	 * @return string
	 */
	public static function filter_api_base( $base ) {
		if ( defined( 'RWGCM_REACTWOO_API_BASE' ) && is_string( RWGCM_REACTWOO_API_BASE ) ) {
			$c = trim( (string) RWGCM_REACTWOO_API_BASE );
			if ( '' !== $c && wp_http_validate_url( $c ) ) {
				return untrailingslashit( esc_url_raw( $c ) );
			}
		}
		$via_filter = apply_filters( 'rwgcm_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$u = esc_url_raw( trim( $via_filter ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}
		$s = self::get_settings();
		if ( is_array( $s ) && ! empty( $s['reactwoo_api_base'] ) ) {
			$u = esc_url_raw( trim( (string) $s['reactwoo_api_base'] ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}
		if ( class_exists( 'RWGA_Settings', false ) ) {
			$ai = get_option( RWGA_Settings::OPTION_KEY, array() );
			if ( is_array( $ai ) && ! empty( $ai['reactwoo_api_base'] ) ) {
				$u = esc_url_raw( trim( (string) $ai['reactwoo_api_base'] ) );
				if ( $u && wp_http_validate_url( $u ) ) {
					return untrailingslashit( $u );
				}
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_api_base'] ) ) {
				$u = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
				if ( $u && wp_http_validate_url( $u ) ) {
					return untrailingslashit( $u );
				}
			}
		}
		$def = is_string( $base ) && '' !== trim( $base ) ? trim( $base ) : 'https://api.reactwoo.com';
		return untrailingslashit( $def );
	}

	/**
	 * @return void
	 */
	public static function clear_license_key() {
		$s                           = self::get_settings();
		$s['reactwoo_license_key'] = '';
		update_option( self::OPTION_KEY, $s );
		if ( class_exists( 'RWGCM_Platform_Client', false ) ) {
			RWGCM_Platform_Client::clear_token_cache();
		}
	}

	/**
	 * @param mixed $old_value Previous option.
	 * @param mixed $value     New option.
	 * @return void
	 */
	public static function maybe_clear_jwt_on_change( $old_value, $value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$val = is_array( $value ) ? $value : array();
		$o_k = isset( $old['reactwoo_license_key'] ) ? (string) $old['reactwoo_license_key'] : '';
		$n_k = isset( $val['reactwoo_license_key'] ) ? (string) $val['reactwoo_license_key'] : '';
		$o_b = isset( $old['reactwoo_api_base'] ) ? (string) $old['reactwoo_api_base'] : '';
		$n_b = isset( $val['reactwoo_api_base'] ) ? (string) $val['reactwoo_api_base'] : '';
		if ( $o_k !== $n_k || $o_b !== $n_b ) {
			if ( class_exists( 'RWGCM_Platform_Client', false ) ) {
				RWGCM_Platform_Client::clear_token_cache();
			}
		}
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_manual_import_sources() {
		$sources = array();
		foreach ( self::get_manual_import_source_map() as $source => $cfg ) {
			$raw = get_option( $cfg['option_key'], array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				$sources[ $source ] = (string) $cfg['label'];
			}
		}
		return $sources;
	}

	/**
	 * @param string $source Source key.
	 * @return true|\WP_Error
	 */
	public static function import_license_from_source( $source ) {
		$map = self::get_manual_import_source_map();
		if ( ! isset( $map[ $source ] ) ) {
			return new WP_Error( 'rwgcm_bad_import_source', __( 'Unknown import source.', 'reactwoo-geo-commerce' ) );
		}

		$raw = get_option( $map[ $source ]['option_key'], array() );
		if ( ! is_array( $raw ) || empty( $raw['reactwoo_license_key'] ) ) {
			return new WP_Error( 'rwgcm_import_missing_key', __( 'The selected source does not have a saved license key.', 'reactwoo-geo-commerce' ) );
		}

		$settings                         = self::get_settings();
		$settings['reactwoo_license_key'] = sanitize_text_field( (string) $raw['reactwoo_license_key'] );
		if ( ! empty( $raw['reactwoo_api_base'] ) ) {
			$base = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
			if ( $base && wp_http_validate_url( $base ) ) {
				$settings['reactwoo_api_base'] = untrailingslashit( $base );
			}
		}

		update_option( self::OPTION_KEY, self::sanitize_settings( $settings ) );
		if ( class_exists( 'RWGCM_Platform_Client', false ) ) {
			RWGCM_Platform_Client::clear_token_cache();
		}
		return true;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function get_manual_import_source_map() {
		return array(
			'geo_ai' => array(
				'label'      => __( 'Geo AI', 'reactwoo-geo-commerce' ),
				'option_key' => 'rwga_settings',
			),
			'geo_optimise' => array(
				'label'      => __( 'Geo Optimise', 'reactwoo-geo-commerce' ),
				'option_key' => 'rwgo_settings',
			),
			'geo_core_legacy' => array(
				'label'      => __( 'Geo Core (legacy)', 'reactwoo-geo-commerce' ),
				'option_key' => 'rwgc_settings',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param array $input Raw.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults     = self::get_defaults();
		$settings     = is_array( $input ) ? $input : array();
		$prev         = get_option( self::OPTION_KEY, array() );
		$prev         = is_array( $prev ) ? $prev : array();
		$out          = array_merge( $defaults, $prev );
		$scope        = isset( $settings['rwgcm_form_scope'] ) ? sanitize_key( (string) $settings['rwgcm_form_scope'] ) : 'license';
		$prev_license = isset( $prev['reactwoo_license_key'] ) ? (string) $prev['reactwoo_license_key'] : '';

		if ( isset( $prev['reactwoo_api_base'] ) ) {
			$out['reactwoo_api_base'] = (string) $prev['reactwoo_api_base'];
		}

		$new_license = isset( $settings['reactwoo_license_key'] ) ? sanitize_text_field( (string) $settings['reactwoo_license_key'] ) : '';
		if ( 'license' === $scope ) {
			$out['reactwoo_license_key'] = ( '' !== $new_license ) ? $new_license : $prev_license;
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'reactwoo_api_base'    => 'https://api.reactwoo.com',
			'reactwoo_license_key' => '',
		);
	}
}
