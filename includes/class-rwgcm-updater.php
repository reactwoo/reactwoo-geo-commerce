<?php
/**
 * Commerce plugin updates.
 *
 * Geo Core's WordPress.org build ships RWGC_Satellite_Updater as a stub, so
 * register() does not offer an update. This class checks the ReactWoo API
 * itself in that case, and when Core is missing or inactive. A real Core
 * updater is still used, once, so the two do not both register.
 *
 * @package ReactWooGeoCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-contained update check for ReactWoo Geo Commerce.
 */
class RWGCM_Updater {

	const SLUG       = 'reactwoo-geo-commerce';
	const CHECK_PATH = '/api/v5/updates/check';
	const CHANNEL    = 'stable';
	const NOTICE     = 'Add your licence key to receive updates.';

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * @var bool
	 */
	private static $using_own = false;

	/**
	 * Parsed update, or null, for this request. Keyed by version and host.
	 *
	 * @var array<string, array<string, string>|null>
	 */
	private static $remote_cache = array();

	/**
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$booted       = false;
		self::$using_own    = false;
		self::$remote_cache = array();
	}

	/**
	 * True when Geo Core's updater will actually perform the check.
	 *
	 * @return bool
	 */
	public static function core_updater_is_real() {
		if ( ! class_exists( 'RWGC_Satellite_Updater', false ) || ! method_exists( 'RWGC_Satellite_Updater', 'register' ) ) {
			return false;
		}
		if ( defined( 'RWGC_DISTRIBUTION' ) && 'wporg' === RWGC_DISTRIBUTION ) {
			return false;
		}
		if ( function_exists( 'rwgc_is_geo_core_active' ) && ! rwgc_is_geo_core_active() ) {
			return false;
		}
		if ( self::method_body_is_empty( 'RWGC_Satellite_Updater', 'register' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * A stub register() is empty, returns immediately, or only unsets its argument.
	 *
	 * @param string $class  Class name.
	 * @param string $method Method name.
	 * @return bool
	 */
	public static function method_body_is_empty( $class, $method ) {
		try {
			if ( ! class_exists( $class, false ) || ! method_exists( $class, $method ) ) {
				return true;
			}
			$ref   = new ReflectionMethod( $class, $method );
			$file  = $ref->getFileName();
			$start = $ref->getStartLine();
			$end   = $ref->getEndLine();
			if ( ! is_string( $file ) || ! is_readable( $file ) || $end < $start ) {
				return true;
			}
			$lines = file( $file );
			if ( ! is_array( $lines ) ) {
				return true;
			}
			$source = implode( '', array_slice( $lines, $start - 1, $end - $start + 1 ) );
			$source = preg_replace( '#/\*.*?\*/#s', '', $source );
			$source = preg_replace( '/\/\/.*$/m', '', (string) $source );
			$source = preg_replace( '/#.*$/m', '', (string) $source );
			$open   = strpos( (string) $source, '{' );
			$close  = strrpos( (string) $source, '}' );
			if ( false === $open || false === $close || $close <= $open ) {
				return true;
			}
			$body = substr( (string) $source, $open + 1, $close - $open - 1 );
			$body = strtolower( (string) preg_replace( '/\s+/', '', $body ) );
			if ( '' === $body || 'return;' === $body || 'returnnull;' === $body ) {
				return true;
			}
			return 1 === preg_match( '/^(unset\([^;]*\);)*(return;|returnnull;)?$/', $body );
		} catch ( Throwable $e ) {
			unset( $e );
			return true;
		}
	}

	/**
	 * Register with Core's updater, or install Commerce's own hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'admin_notices', array( __CLASS__, 'maybe_notice' ) );

		if ( self::core_updater_is_real() ) {
			self::$using_own = false;
			RWGC_Satellite_Updater::register( self::core_updater_args() );
			return;
		}

		self::$using_own = true;
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_transient' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 10, 3 );
	}

	/**
	 * @return bool
	 */
	public static function is_using_own() {
		return self::$using_own;
	}

	/**
	 * Arguments previously passed to RWGC_Satellite_Updater::register().
	 *
	 * @return array<string, mixed>
	 */
	public static function core_updater_args() {
		return array(
			'basename'              => self::plugin_basename(),
			'version'               => defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '',
			'catalog_slug'          => self::SLUG,
			'name'                  => self::translate( 'ReactWoo Geo Commerce' ),
			'description'           => self::translate( 'WooCommerce geo pricing, fees, and attribution on ReactWoo Geo Core.' ),
			'get_bearer_callback'   => array( 'RWGCM_Platform_Client', 'get_bearer_for_updates' ),
			'get_api_base_callback' => array( 'RWGCM_Platform_Client', 'get_api_base' ),
		);
	}

	/**
	 * @param mixed $transient Update-plugins transient.
	 * @return mixed
	 */
	public static function filter_update_transient( $transient ) {
		try {
			if ( ! is_object( $transient ) ) {
				return $transient;
			}
			if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) || array() === $transient->checked ) {
				return $transient;
			}
			$basename = self::plugin_basename();
			$current  = isset( $transient->checked[ $basename ] ) ? (string) $transient->checked[ $basename ] : ( defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '' );
			$info     = self::remote_check( $current );
			if ( ! is_array( $info ) ) {
				return $transient;
			}
			$offer = self::update_object( $info, $basename );
			if ( version_compare( $info['version'], $current, '>' ) ) {
				if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
					$transient->response = array();
				}
				$transient->response[ $basename ] = $offer;
				if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
					unset( $transient->no_update[ $basename ] );
				}
				return $transient;
			}
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $basename ] = $offer;
			if ( isset( $transient->response ) && is_array( $transient->response ) ) {
				unset( $transient->response[ $basename ] );
			}
			return $transient;
		} catch ( Throwable $e ) {
			unset( $e );
			return $transient;
		}
	}

	/**
	 * @param mixed  $result Existing API result.
	 * @param string $action plugins_api action.
	 * @param mixed  $args   Request args.
	 * @return mixed
	 */
	public static function filter_plugins_api( $result, $action, $args ) {
		try {
			if ( 'plugin_information' !== $action ) {
				return $result;
			}
			$slug = '';
			if ( is_object( $args ) && isset( $args->slug ) ) {
				$slug = (string) $args->slug;
			} elseif ( is_array( $args ) && isset( $args['slug'] ) ) {
				$slug = (string) $args['slug'];
			}
			if ( self::SLUG !== $slug ) {
				return $result;
			}
			$current = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '';
			$info    = self::remote_check( $current );
			if ( ! is_array( $info ) ) {
				return $result;
			}
			$api                = new stdClass();
			$api->name          = self::translate( 'ReactWoo Geo Commerce' );
			$api->slug          = self::SLUG;
			$api->version       = $info['version'];
			$api->download_link = $info['package'];
			$api->homepage      = 'https://reactwoo.com/';
			$api->sections      = array(
				'description' => self::translate( 'WooCommerce geo pricing, fees, and attribution on ReactWoo Geo Core.' ),
			);
			return $api;
		} catch ( Throwable $e ) {
			unset( $e );
			return $result;
		}
	}

	/**
	 * Accept download_url, package, or package_url, including a nested update or data object.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array{version: string, package: string}|null
	 */
	public static function parse_check_response( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		$candidates = array( $data );
		if ( isset( $data['update'] ) && is_array( $data['update'] ) ) {
			$candidates[] = $data['update'];
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$candidates[] = $data['data'];
			if ( isset( $data['data']['update'] ) && is_array( $data['data']['update'] ) ) {
				$candidates[] = $data['data']['update'];
			}
		}
		foreach ( $candidates as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$version = '';
			if ( isset( $row['new_version'] ) && is_scalar( $row['new_version'] ) ) {
				$version = trim( (string) $row['new_version'] );
			} elseif ( isset( $row['version'] ) && is_scalar( $row['version'] ) ) {
				$version = trim( (string) $row['version'] );
			}
			$package = '';
			foreach ( array( 'download_url', 'package', 'package_url' ) as $key ) {
				if ( isset( $row[ $key ] ) && is_string( $row[ $key ] ) && '' !== trim( $row[ $key ] ) ) {
					$package = trim( $row[ $key ] );
					break;
				}
			}
			if ( '' === $version || '' === $package ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $package ) ) {
				continue;
			}
			return array(
				'version' => $version,
				'package' => $package,
			);
		}
		return null;
	}

	/**
	 * @return string
	 */
	public static function site_host() {
		$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$host = '';
		if ( '' !== $home ) {
			if ( function_exists( 'wp_parse_url' ) ) {
				$host = wp_parse_url( $home, PHP_URL_HOST );
			} else {
				$host = parse_url( $home, PHP_URL_HOST );
			}
		}
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$raw = function_exists( 'wp_unslash' ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : $_SERVER['HTTP_HOST'];
			$raw = is_string( $raw ) ? $raw : '';
			return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $raw ) : trim( $raw );
		}
		return '';
	}

	/**
	 * Plugins screen, or a Geo Commerce admin screen.
	 *
	 * @return bool
	 */
	public static function is_update_screen() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && isset( $screen->id ) && '' !== (string) $screen->id ) {
				$id = (string) $screen->id;
				if ( 'plugins' === $id || 'plugins-network' === $id ) {
					return true;
				}
				if ( false !== strpos( $id, 'rwgcm' ) || false !== strpos( strtolower( $id ), 'commerce' ) ) {
					return true;
				}
				return false;
			}
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'plugins.php' === $GLOBALS['pagenow'] ) {
			return true;
		}
		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw  = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['page'] ) : $_GET['page'];
			$page = function_exists( 'sanitize_key' ) ? sanitize_key( $raw ) : strtolower( (string) $raw );
		}
		if ( '' === $page ) {
			return false;
		}
		return 0 === strpos( $page, 'rwgcm' ) || false !== strpos( $page, 'commerce' );
	}

	/**
	 * @return string
	 */
	public static function notice_text() {
		return self::translate( self::NOTICE );
	}

	/**
	 * @return bool
	 */
	public static function should_show_notice() {
		if ( ! self::is_update_screen() || ! self::user_can_see_notice() ) {
			return false;
		}
		return ! self::licence_available();
	}

	/**
	 * @return void
	 */
	public static function maybe_notice() {
		if ( ! self::should_show_notice() ) {
			return;
		}
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=rwgcm-license' ) : 'admin.php?page=rwgcm-license';
		echo '<div class="notice notice-warning"><p><a href="' . self::esc_url_text( $url ) . '">' . self::esc_html_text( self::notice_text() ) . '</a></p></div>';
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build_check_body( $current_version ) {
		return array(
			'slug'            => self::SLUG,
			'current_version' => (string) $current_version,
			'channel'         => self::CHANNEL,
			'site_host'       => self::site_host(),
		);
	}

	/**
	 * @param string $current_version Installed version.
	 * @return array{version: string, package: string}|null
	 */
	private static function remote_check( $current_version ) {
		$key = (string) $current_version . '|' . self::site_host();
		if ( array_key_exists( $key, self::$remote_cache ) ) {
			return self::$remote_cache[ $key ];
		}
		try {
			$result = self::request_remote( $current_version );
		} catch ( Throwable $e ) {
			unset( $e );
			$result = null;
		}
		self::$remote_cache[ $key ] = $result;
		return $result;
	}

	/**
	 * @param string $current_version Installed version.
	 * @return array{version: string, package: string}|null
	 */
	private static function request_remote( $current_version ) {
		$token = self::bearer_token();
		if ( null === $token ) {
			return null;
		}
		$base = 'https://api.reactwoo.com';
		if ( class_exists( 'RWGCM_Platform_Client', false ) && is_callable( array( 'RWGCM_Platform_Client', 'get_api_base' ) ) ) {
			$candidate = RWGCM_Platform_Client::get_api_base();
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$base = $candidate;
			}
		}
		$base = function_exists( 'untrailingslashit' ) ? untrailingslashit( $base ) : rtrim( $base, '/' );
		$url  = $base . self::CHECK_PATH;
		$body = self::build_check_body( $current_version );
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : json_encode( $body );
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
			'body'    => $json,
		);

		$response = null;
		if ( function_exists( 'apply_filters' ) ) {
			$response = apply_filters( 'rwgcm_updater_http_post', null, $url, $args );
		}
		if ( null === $response ) {
			if ( ! function_exists( 'wp_remote_post' ) ) {
				return null;
			}
			$response = wp_remote_post( $url, $args );
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return null;
		}

		$code = 0;
		$raw  = '';
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
		} elseif ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			$code = (int) $response['response']['code'];
		}
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			$raw = (string) wp_remote_retrieve_body( $response );
		} elseif ( is_array( $response ) && isset( $response['body'] ) ) {
			$raw = (string) $response['body'];
		}
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return self::parse_check_response( $decoded );
	}

	/**
	 * @param array{version: string, package: string} $info     Parsed update.
	 * @param string                                  $basename Plugin basename.
	 * @return stdClass
	 */
	private static function update_object( $info, $basename ) {
		$offer              = new stdClass();
		$offer->slug        = self::SLUG;
		$offer->plugin      = $basename;
		$offer->new_version = $info['version'];
		$offer->url         = 'https://reactwoo.com/';
		$offer->package     = $info['package'];
		return $offer;
	}

	/**
	 * @return string|null
	 */
	private static function bearer_token() {
		if ( ! class_exists( 'RWGCM_Platform_Client', false ) || ! is_callable( array( 'RWGCM_Platform_Client', 'get_bearer_for_updates' ) ) ) {
			return null;
		}
		if ( is_callable( array( 'RWGCM_Platform_Client', 'is_configured' ) ) && ! RWGCM_Platform_Client::is_configured() ) {
			return null;
		}
		$token = RWGCM_Platform_Client::get_bearer_for_updates();
		if ( ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * Empty key skips login. A saved key with no token still counts as unavailable.
	 *
	 * @return bool
	 */
	private static function licence_available() {
		if ( ! class_exists( 'RWGCM_Platform_Client', false ) ) {
			return false;
		}
		if ( is_callable( array( 'RWGCM_Platform_Client', 'is_configured' ) ) && ! RWGCM_Platform_Client::is_configured() ) {
			return false;
		}
		return null !== self::bearer_token();
	}

	/**
	 * @return bool
	 */
	private static function user_can_see_notice() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'update_plugins' ) || current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * @return string
	 */
	private static function plugin_basename() {
		$file = defined( 'RWGCM_FILE' ) ? RWGCM_FILE : '';
		if ( function_exists( 'plugin_basename' ) ) {
			$base = plugin_basename( $file );
			if ( is_string( $base ) && '' !== $base ) {
				return $base;
			}
		}
		return 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
	}

	/**
	 * @param string $text English copy.
	 * @return string
	 */
	private static function translate( $text ) {
		return function_exists( '__' ) ? __( $text, 'reactwoo-geo-commerce' ) : $text;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc_html_text( $text ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $text );
		}
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function esc_url_text( $url ) {
		if ( function_exists( 'esc_url' ) ) {
			return esc_url( $url );
		}
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}
