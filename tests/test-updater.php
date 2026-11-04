<?php
/**
 * Commerce's own updater (run with: php tests/test-updater.php).
 *
 * Stub detection and the real-updater path run in separate processes so
 * RWGC_Satellite_Updater can be a stub in one and a working class in the other.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( isset( $argv[1] ) && 'child' === $argv[1] ) {
	rwgcm_updater_child( isset( $argv[2] ) ? $argv[2] : '' );
	exit( ! empty( $GLOBALS['failed'] ) ? 1 : 0 );
}

$modes  = array( 'stub', 'real', 'wporg', 'inactive', 'missing', 'client' );
$failed = 0;
foreach ( $modes as $mode ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' child ' . escapeshellarg( $mode );
	passthru( $cmd, $code );
	if ( 0 !== (int) $code ) {
		++$failed;
		echo "FAIL: child {$mode} exited {$code}\n";
	}
}
echo "\n" . ( 0 === $failed ? 'updater children passed' : "{$failed} updater children failed" ) . "\n";
exit( $failed > 0 ? 1 : 0 );

/**
 * @param string $mode stub|real|wporg|inactive|missing.
 * @return void
 */
function rwgcm_updater_child( $mode ) {
	$GLOBALS['passed']          = 0;
	$GLOBALS['failed']          = 0;
	$GLOBALS['rw_filters']      = array();
	$GLOBALS['rw_filter_meta']  = array();
	$GLOBALS['rw_actions']      = array();
	$GLOBALS['rw_action_meta']  = array();
	$GLOBALS['rw_posts']        = array();
	$GLOBALS['rw_post_throw']   = false;
	$GLOBALS['rw_post_response'] = array(
		'response' => array( 'code' => 200 ),
		'body'     => '',
	);
	$GLOBALS['rw_transients']   = array();
	$GLOBALS['rw_site']         = false;
	$GLOBALS['rw_downloads']    = array();
	$GLOBALS['rw_caps']         = array( 'update_plugins' => true );
	$GLOBALS['rw_screen']       = null;
	$GLOBALS['pagenow']         = 'index.php';
	$_GET                       = array();
	$_SERVER['HTTP_HOST']       = 'shop.reactwoo.com';

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'RWGCM_VERSION' ) ) {
		define( 'RWGCM_VERSION', '0.10.0' );
	}
	if ( ! defined( 'RWGCM_FILE' ) ) {
		define( 'RWGCM_FILE', dirname( __DIR__ ) . '/reactwoo-geo-commerce.php' );
	}
	if ( ! defined( 'RWGCM_PATH' ) ) {
		define( 'RWGCM_PATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! class_exists( 'WP_Error', false ) ) {
		/**
		 * Minimal WP_Error.
		 */
		class WP_Error { // phpcs:ignore
			/**
			 * @var string
			 */
			public $code = '';

			/**
			 * @var string
			 */
			public $message = '';

			/**
			 * @param string $code Code.
			 * @param string $message Message.
			 * @param mixed  $data Data.
			 */
			public function __construct( $code = '', $message = '', $data = '' ) {
				$this->code    = (string) $code;
				$this->message = (string) $message;
				unset( $data );
			}

			/**
			 * @return string
			 */
			public function get_error_code() {
				return $this->code;
			}
		}
	}

	if ( ! function_exists( '__' ) ) {
		/**
		 * @param string $text Text.
		 * @param string $domain Domain.
		 * @return string
		 */
		function __( $text, $domain = 'default' ) { // phpcs:ignore
			unset( $domain );
			return $text;
		}
	}
	if ( ! function_exists( 'add_action' ) ) {
		/**
		 * @param string   $hook Hook.
		 * @param callable $callback Callback.
		 * @param int      $priority Priority.
		 * @param int      $accepted_args Accepted args.
		 * @return void
		 */
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
			$GLOBALS['rw_actions'][ $hook ][]     = $callback;
			$GLOBALS['rw_action_meta'][ $hook ][] = array(
				'priority' => $priority,
				'args'     => $accepted_args,
				'callback' => $callback,
			);
		}
	}
	if ( ! function_exists( 'add_filter' ) ) {
		/**
		 * @param string   $hook Hook.
		 * @param callable $callback Callback.
		 * @param int      $priority Priority.
		 * @param int      $accepted_args Accepted args.
		 * @return void
		 */
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
			$GLOBALS['rw_filters'][ $hook ][]     = $callback;
			$GLOBALS['rw_filter_meta'][ $hook ][] = array(
				'priority' => $priority,
				'args'     => $accepted_args,
				'callback' => $callback,
			);
		}
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		/**
		 * @param string $tag Hook.
		 * @param mixed  $value Value.
		 * @return mixed
		 */
		function apply_filters( $tag, $value ) { // phpcs:ignore
			$args = func_get_args();
			if ( empty( $GLOBALS['rw_filters'][ $tag ] ) ) {
				return $value;
			}
			foreach ( $GLOBALS['rw_filters'][ $tag ] as $callback ) {
				$args[1] = call_user_func_array( $callback, array_slice( $args, 1 ) );
			}
			return $args[1];
		}
	}
	if ( ! function_exists( 'plugin_basename' ) ) {
		/**
		 * @param string $file File.
		 * @return string
		 */
		function plugin_basename( $file ) { // phpcs:ignore
			unset( $file );
			return 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
		}
	}
	if ( ! function_exists( 'home_url' ) ) {
		/**
		 * @param string $path Path.
		 * @return string
		 */
		function home_url( $path = '' ) { // phpcs:ignore
			unset( $path );
			return 'https://shop.reactwoo.com/shop/';
		}
	}
	if ( ! function_exists( 'wp_parse_url' ) ) {
		/**
		 * @param string $url URL.
		 * @param int    $component Component.
		 * @return mixed
		 */
		function wp_parse_url( $url, $component = -1 ) { // phpcs:ignore
			return parse_url( $url, $component );
		}
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		/**
		 * @param mixed $value Value.
		 * @return string
		 */
		function wp_json_encode( $value ) { // phpcs:ignore
			return json_encode( $value );
		}
	}
	if ( ! function_exists( 'wp_unslash' ) ) {
		/**
		 * @param mixed $value Value.
		 * @return mixed
		 */
		function wp_unslash( $value ) { // phpcs:ignore
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		/**
		 * @param string $text Text.
		 * @return string
		 */
		function sanitize_text_field( $text ) { // phpcs:ignore
			return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		/**
		 * @param string $key Key.
		 * @return string
		 */
		function sanitize_key( $key ) { // phpcs:ignore
			$key = strtolower( (string) $key );
			return preg_replace( '/[^a-z0-9_\-]/', '', $key );
		}
	}
	if ( ! function_exists( 'esc_html' ) ) {
		/**
		 * @param string $text Text.
		 * @return string
		 */
		function esc_html( $text ) { // phpcs:ignore
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( ! function_exists( 'esc_url' ) ) {
		/**
		 * @param string $url URL.
		 * @return string
		 */
		function esc_url( $url ) { // phpcs:ignore
			return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
		}
	}
	if ( ! function_exists( 'admin_url' ) ) {
		/**
		 * @param string $path Path.
		 * @return string
		 */
		function admin_url( $path = '' ) { // phpcs:ignore
			return 'https://shop.reactwoo.com/wp-admin/' . ltrim( (string) $path, '/' );
		}
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		/**
		 * @param string $cap Capability.
		 * @return bool
		 */
		function current_user_can( $cap ) { // phpcs:ignore
			return ! empty( $GLOBALS['rw_caps'][ $cap ] );
		}
	}
	if ( ! function_exists( 'get_current_screen' ) ) {
		/**
		 * @return object|null
		 */
		function get_current_screen() { // phpcs:ignore
			return $GLOBALS['rw_screen'];
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		/**
		 * @param mixed $thing Thing.
		 * @return bool
		 */
		function is_wp_error( $thing ) { // phpcs:ignore
			return $thing instanceof WP_Error;
		}
	}
	if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
		/**
		 * @param mixed $response Response.
		 * @return int
		 */
		function wp_remote_retrieve_response_code( $response ) { // phpcs:ignore
			return ( is_array( $response ) && isset( $response['response']['code'] ) ) ? (int) $response['response']['code'] : 0;
		}
	}
	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		/**
		 * @param mixed $response Response.
		 * @return string
		 */
		function wp_remote_retrieve_body( $response ) { // phpcs:ignore
			return ( is_array( $response ) && isset( $response['body'] ) ) ? (string) $response['body'] : '';
		}
	}
	if ( ! function_exists( 'wp_remote_post' ) ) {
		/**
		 * @param string $url URL.
		 * @param array  $args Args.
		 * @return array<string, mixed>
		 */
		function wp_remote_post( $url, $args = array() ) { // phpcs:ignore
			$GLOBALS['rw_posts'][] = array(
				'url'  => $url,
				'args' => $args,
			);
			if ( ! empty( $GLOBALS['rw_post_throw'] ) ) {
				throw new RuntimeException( 'network down' );
			}
			return $GLOBALS['rw_post_response'];
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		/**
		 * @param string $key Key.
		 * @param mixed  $value Value.
		 * @param int    $ttl TTL.
		 * @return bool
		 */
		function set_transient( $key, $value, $ttl = 0 ) { // phpcs:ignore
			$GLOBALS['rw_transients'][ $key ] = array(
				'value' => $value,
				'ttl'   => (int) $ttl,
			);
			return true;
		}
	}
	if ( ! function_exists( 'get_transient' ) ) {
		/**
		 * @param string $key Key.
		 * @return mixed
		 */
		function get_transient( $key ) { // phpcs:ignore
			return isset( $GLOBALS['rw_transients'][ $key ] ) ? $GLOBALS['rw_transients'][ $key ]['value'] : false;
		}
	}
	if ( ! function_exists( 'delete_transient' ) ) {
		/**
		 * @param string $key Key.
		 * @return bool
		 */
		function delete_transient( $key ) { // phpcs:ignore
			unset( $GLOBALS['rw_transients'][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'get_site_transient' ) ) {
		/**
		 * @param string $key Key.
		 * @return mixed
		 */
		function get_site_transient( $key ) { // phpcs:ignore
			if ( 'update_plugins' === $key ) {
				return $GLOBALS['rw_site'];
			}
			return false;
		}
	}
	if ( ! function_exists( 'download_url' ) ) {
		/**
		 * @param string $url URL.
		 * @param int    $timeout Timeout.
		 * @return string
		 */
		function download_url( $url, $timeout = 300 ) { // phpcs:ignore
			$GLOBALS['rw_downloads'][] = array(
				'url'     => $url,
				'timeout' => (int) $timeout,
			);
			return '/tmp/reactwoo-geo-commerce-update.zip';
		}
	}

	if ( 'client' === $mode ) {
		rwgcm_updater_assert_platform_client();
		echo "\n[{$mode}] {$GLOBALS['passed']} passed, {$GLOBALS['failed']} failed\n";
		return;
	}

	if ( 'wporg' === $mode && ! defined( 'RWGC_DISTRIBUTION' ) ) {
		define( 'RWGC_DISTRIBUTION', 'wporg' );
	}
	if ( 'inactive' === $mode && ! function_exists( 'rwgc_is_geo_core_active' ) ) {
		/**
		 * @return bool
		 */
		function rwgc_is_geo_core_active() { // phpcs:ignore
			return false;
		}
	}

	if ( 'stub' === $mode ) {
		rwgcm_updater_define_stub_classes();
	} elseif ( 'real' === $mode || 'wporg' === $mode || 'inactive' === $mode ) {
		rwgcm_updater_define_real_core();
	}

	require_once RWGCM_PATH . 'includes/class-rwgcm-plugin.php';
	RWGCM_Plugin::register_satellite_updater();
	RWGCM_Plugin::register_satellite_updater();

	if ( 'real' === $mode ) {
		rwgcm_updater_assert_real();
	} else {
		rwgcm_updater_assert_own( $mode );
	}

	echo "\n[{$mode}] {$GLOBALS['passed']} passed, {$GLOBALS['failed']} failed\n";
}

/**
 * @param string $label Label.
 * @param bool   $condition Condition.
 * @return void
 */
function rwgcm_updater_assert( $label, $condition ) {
	if ( $condition ) {
		++$GLOBALS['passed'];
		echo "PASS: {$label}\n";
		return;
	}
	++$GLOBALS['failed'];
	echo "FAIL: {$label}\n";
}

/**
 * @param string $hook Hook.
 * @param string $method Method on RWGCM_Updater.
 * @param string $kind filter|action.
 * @return int
 */
function rwgcm_updater_hook_count( $hook, $method, $kind ) {
	$key  = 'filter' === $kind ? 'rw_filter_meta' : 'rw_action_meta';
	$rows = isset( $GLOBALS[ $key ][ $hook ] ) ? $GLOBALS[ $key ][ $hook ] : array();
	$n    = 0;
	foreach ( $rows as $row ) {
		$callback = isset( $row['callback'] ) ? $row['callback'] : null;
		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && 'RWGCM_Updater' === $callback[0] && $method === $callback[1] ) {
			++$n;
		}
	}
	return $n;
}

/**
 * Empty Core updater, plus bodies the detector must treat as stubs.
 *
 * @return void
 */
function rwgcm_updater_define_stub_classes() {
	if ( ! class_exists( 'RWGC_Satellite_Updater', false ) ) {
		/**
		 * WordPress.org stand-in: register() does nothing.
		 */
		class RWGC_Satellite_Updater { // phpcs:ignore
			/**
			 * @param array<string, mixed> $args Args.
			 * @return void
			 */
			public static function register( $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				// WordPress.org builds do not offer satellite updates.
			}
		}
	}

	if ( ! class_exists( 'RWGCM_Test_Return_Null', false ) ) {
		/**
		 * Stub that returns null.
		 */
		class RWGCM_Test_Return_Null { // phpcs:ignore
			/**
			 * @return null
			 */
			public static function register() {
				return null;
			}
		}
	}

	if ( ! class_exists( 'RWGCM_Test_Multiline_Stub', false ) ) {
		/**
		 * Empty body, signature split across lines.
		 */
		class RWGCM_Test_Multiline_Stub { // phpcs:ignore
			/**
			 * @param array<string, mixed> $args Args.
			 * @return void
			 */
			public static function register(
				$args = array()
			) {
				unset( $args );
			}
		}
	}

	if ( ! class_exists( 'RWGCM_Test_Real_Body', false ) ) {
		/**
		 * Non-empty body used only to prove the detector.
		 */
		class RWGCM_Test_Real_Body { // phpcs:ignore
			/**
			 * @param array<string, mixed> $args Args.
			 * @return int
			 */
			public static function register( $args = array() ) {
				$kept = is_array( $args ) ? count( $args ) : 0;
				return $kept;
			}
		}
	}

	if ( ! class_exists( 'RWGCM_Platform_Client', false ) ) {
		/**
		 * Licence login stand-in. Does not perform HTTP itself.
		 */
		class RWGCM_Platform_Client { // phpcs:ignore
			/** @var bool */
			public static $configured = false;

			/** @var string|null */
			public static $bearer = null;

			/** @var int */
			public static $bearer_calls = 0;

			/** @var int[] */
			public static $timeouts = array();

			/**
			 * @return bool
			 */
			public static function is_configured() {
				return (bool) self::$configured;
			}

			/**
			 * @param int $timeout Login timeout.
			 * @return string|null
			 */
			public static function get_bearer_for_updates( $timeout = 30 ) {
				++self::$bearer_calls;
				self::$timeouts[] = (int) $timeout;
				return self::$bearer;
			}

			/**
			 * @return string
			 */
			public static function get_api_base() {
				return 'https://api.reactwoo.com';
			}
		}
	}
}

/**
 * @return void
 */
function rwgcm_updater_define_real_core() {
	if ( class_exists( 'RWGC_Satellite_Updater', false ) ) {
		return;
	}

	/**
	 * Working Geo Core updater.
	 */
	class RWGC_Satellite_Updater { // phpcs:ignore
		/** @var int */
		public static $calls = 0;

		/** @var array<string, mixed>|null */
		public static $last = null;

		/**
		 * @param array<string, mixed> $args Args.
		 * @return array<string, mixed>
		 */
		public static function register( $args = array() ) {
			++self::$calls;
			self::$last = $args;
			return $args;
		}
	}
}

/**
 * @param string $mode Mode name.
 * @return void
 */
function rwgcm_updater_assert_own( $mode ) {
	rwgcm_updater_assert(
		"{$mode}: Core updater is not treated as real",
		false === RWGCM_Updater::core_updater_is_real()
	);
	rwgcm_updater_assert( "{$mode}: Commerce uses its own updater", RWGCM_Updater::is_using_own() );
	rwgcm_updater_assert(
		"{$mode}: update transient hook is registered once",
		1 === rwgcm_updater_hook_count( 'pre_set_site_transient_update_plugins', 'filter_update_transient', 'filter' )
	);
	$api_meta = isset( $GLOBALS['rw_filter_meta']['plugins_api'][0] ) ? $GLOBALS['rw_filter_meta']['plugins_api'][0] : array();
	rwgcm_updater_assert(
		"{$mode}: plugins_api hook is registered once with 3 args",
		1 === rwgcm_updater_hook_count( 'plugins_api', 'filter_plugins_api', 'filter' )
			&& isset( $api_meta['args'] ) && 3 === (int) $api_meta['args']
	);
	rwgcm_updater_assert(
		"{$mode}: the licence notice is registered once",
		1 === rwgcm_updater_hook_count( 'admin_notices', 'maybe_notice', 'action' )
	);
	$download_meta = isset( $GLOBALS['rw_filter_meta']['upgrader_pre_download'][0] ) ? $GLOBALS['rw_filter_meta']['upgrader_pre_download'][0] : array();
	rwgcm_updater_assert(
		"{$mode}: upgrader_pre_download is registered once with 4 args",
		1 === rwgcm_updater_hook_count( 'upgrader_pre_download', 'filter_upgrader_pre_download', 'filter' )
			&& isset( $download_meta['args'] ) && 4 === (int) $download_meta['args']
	);

	if ( 'wporg' === $mode || 'inactive' === $mode ) {
		rwgcm_updater_assert(
			"{$mode}: Core register() is not called",
			0 === RWGC_Satellite_Updater::$calls
		);
	}

	if ( 'stub' !== $mode ) {
		return;
	}

	rwgcm_updater_assert(
		'a comment-only register() is a stub',
		RWGCM_Updater::method_body_is_empty( 'RWGC_Satellite_Updater', 'register' )
	);
	rwgcm_updater_assert(
		'return null is a stub',
		RWGCM_Updater::method_body_is_empty( 'RWGCM_Test_Return_Null', 'register' )
	);
	rwgcm_updater_assert(
		'a multi-line signature that only unsets its argument is a stub',
		RWGCM_Updater::method_body_is_empty( 'RWGCM_Test_Multiline_Stub', 'register' )
	);
	rwgcm_updater_assert(
		'a register() that uses its arguments is not a stub',
		false === RWGCM_Updater::method_body_is_empty( 'RWGCM_Test_Real_Body', 'register' )
	);

	$signed = 'https://downloads.reactwoo.com/r2/reactwoo-geo-commerce-0.9.4.zip?X-Amz-Signature=abc123';
	$parsed = RWGCM_Updater::parse_check_response(
		array(
			'download_url' => $signed,
			'version'      => '0.9.4',
		)
	);
	rwgcm_updater_assert(
		'download_url and version are the package',
		is_array( $parsed ) && $signed === $parsed['package'] && '0.9.4' === $parsed['version']
	);
	$nested = RWGCM_Updater::parse_check_response(
		array(
			'update' => array(
				'package'     => $signed,
				'new_version' => '0.9.4',
			),
		)
	);
	rwgcm_updater_assert( 'nested update.package is accepted', is_array( $nested ) && $signed === $nested['package'] );
	$data = RWGCM_Updater::parse_check_response(
		array(
			'data' => array(
				'package_url' => $signed,
				'version'     => '0.9.4',
			),
		)
	);
	rwgcm_updater_assert( 'data.package_url is accepted', is_array( $data ) && $signed === $data['package'] );
	$inner = RWGCM_Updater::parse_check_response(
		array(
			'data' => array(
				'update' => array(
					'download_url' => $signed,
					'new_version'  => '0.9.4',
				),
			),
		)
	);
	rwgcm_updater_assert( 'data.update.download_url is accepted', is_array( $inner ) && $signed === $inner['package'] );
	rwgcm_updater_assert( 'a payload without a package URL is ignored', null === RWGCM_Updater::parse_check_response( array( 'version' => '0.9.4' ) ) );
	rwgcm_updater_assert( 'a non-array payload is ignored', null === RWGCM_Updater::parse_check_response( 'nope' ) );

	rwgcm_updater_assert( 'site host comes from the home URL', 'shop.reactwoo.com' === RWGCM_Updater::site_host() );

	$GLOBALS['rw_post_response'] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'download_url' => $signed,
				'version'      => '0.10.1',
			)
		),
	);
	RWGCM_Platform_Client::$configured   = true;
	RWGCM_Platform_Client::$bearer       = 'jwt-token';
	RWGCM_Platform_Client::$bearer_calls = 0;
	RWGCM_Platform_Client::$timeouts     = array();

	$basename = 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
	$stored                 = new stdClass();
	$stored->last_checked   = 10;
	$stored->checked        = array( $basename => '0.10.0' );
	$stored->response       = array( 'keep' => 'yes' );
	$incoming               = new stdClass();
	$incoming->last_checked = 99;
	$incoming->checked      = array( $basename => '0.10.0' );
	$incoming->response     = array( 'keep' => 'yes' );
	$GLOBALS['rw_site']     = $stored;
	$before                 = count( $GLOBALS['rw_posts'] );
	$skipped                = RWGCM_Updater::filter_update_transient( $incoming );
	rwgcm_updater_assert( 'a save that only changes last_checked makes no remote call', count( $GLOBALS['rw_posts'] ) === $before && 0 === RWGCM_Platform_Client::$bearer_calls );
	rwgcm_updater_assert( 'that save returns the transient unchanged', $skipped === $incoming );

	$same               = new stdClass();
	$same->last_checked = 1;
	$same->checked      = array( $basename => '0.10.0' );
	$same->response     = array();
	$GLOBALS['rw_site'] = $same;
	$same->last_checked = 2;
	$before             = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::filter_update_transient( $same );
	rwgcm_updater_assert( 'WordPress mutating last_checked on the stored object makes no remote call', count( $GLOBALS['rw_posts'] ) === $before && 0 === RWGCM_Platform_Client::$bearer_calls );
	$GLOBALS['rw_site'] = false;

	$transient = new stdClass();
	$transient->checked = array( $basename => '0.10.0' );
	$updated   = RWGCM_Updater::filter_update_transient( $transient );
	rwgcm_updater_assert( 'one check is posted', 1 === count( $GLOBALS['rw_posts'] ) );
	rwgcm_updater_assert( 'the update check times out at 8 seconds', 8 === (int) $GLOBALS['rw_posts'][0]['args']['timeout'] );
	rwgcm_updater_assert( 'the update path asks for a 10 second login', array( 10 ) === RWGCM_Platform_Client::$timeouts );
	$post = $GLOBALS['rw_posts'][0];
	$body = json_decode( $post['args']['body'], true );
	rwgcm_updater_assert(
		'the check hits the v5 updates endpoint',
		'https://api.reactwoo.com/api/v5/updates/check' === $post['url']
	);
	rwgcm_updater_assert(
		'the body is slug, current_version, channel, and site_host',
		is_array( $body )
			&& array( 'slug', 'current_version', 'channel', 'site_host' ) === array_keys( $body )
			&& 'reactwoo-geo-commerce' === $body['slug']
			&& '0.10.0' === $body['current_version']
			&& 'stable' === $body['channel']
			&& 'shop.reactwoo.com' === $body['site_host']
	);
	rwgcm_updater_assert(
		'the bearer token is sent',
		isset( $post['args']['headers']['Authorization'] ) && 'Bearer jwt-token' === $post['args']['headers']['Authorization']
	);
	$placeholder = 'reactwoo-update://reactwoo-geo-commerce?version=0.10.1&current=0.10.0';
	rwgcm_updater_assert(
		'the transient stores a placeholder instead of the signed URL',
		is_object( $updated )
			&& isset( $updated->response[ $basename ] )
			&& $placeholder === $updated->response[ $basename ]->package
			&& false === strpos( (string) $updated->response[ $basename ]->package, 'X-Amz-' )
			&& false === strpos( (string) $updated->response[ $basename ]->package, 'downloads.reactwoo.com' )
			&& '0.10.1' === $updated->response[ $basename ]->new_version
			&& 'reactwoo-geo-commerce' === $updated->response[ $basename ]->slug
	);
	$offer_ttls = array();
	foreach ( $GLOBALS['rw_transients'] as $key => $row ) {
		if ( 0 === strpos( (string) $key, 'rwgcm_uo_' ) ) {
			$offer_ttls[] = (int) $row['ttl'];
			$cached       = $row['value'];
			rwgcm_updater_assert(
				'the one-hour offer cache does not keep the signed URL',
				is_array( $cached ) && isset( $cached['offer']['version'] ) && '0.10.1' === $cached['offer']['version'] && ! isset( $cached['offer']['package'] ) && false === strpos( wp_json_encode( $cached ), 'X-Amz-' )
			);
		}
	}
	rwgcm_updater_assert( 'offers are cached for one hour', array( 3600 ) === $offer_ttls );
	RWGCM_Updater::clear_request_cache_for_tests();
	$before = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::filter_update_transient( $transient );
	rwgcm_updater_assert( 'a second save within the hour does not call the API again', count( $GLOBALS['rw_posts'] ) === $before );

	$again = RWGCM_Updater::filter_update_transient( $transient );
	rwgcm_updater_assert( 'the same check is not posted twice in one request', 1 === count( $GLOBALS['rw_posts'] ) );
	unset( $again );

	$args         = new stdClass();
	$args->slug   = 'reactwoo-geo-commerce';
	$info         = RWGCM_Updater::filter_plugins_api( false, 'plugin_information', $args );
	rwgcm_updater_assert(
		'plugins_api returns the placeholder instead of the signed download link',
		is_object( $info ) && $placeholder === $info->download_link && false === strpos( (string) $info->download_link, 'X-Amz-' ) && '0.10.1' === $info->version
	);
	rwgcm_updater_assert( 'plugins_api reuses the check from this request', 1 === count( $GLOBALS['rw_posts'] ) );

	$fresh = 'https://downloads.reactwoo.com/r2/reactwoo-geo-commerce-0.10.1.zip?X-Amz-Signature=fresh';
	$GLOBALS['rw_post_response']['body'] = wp_json_encode(
		array(
			'download_url' => $fresh,
			'version'      => '0.10.1',
		)
	);
	$posts_before_download = count( $GLOBALS['rw_posts'] );
	$downloaded            = RWGCM_Updater::filter_upgrader_pre_download( false, $placeholder, null, array() );
	$download_post         = $GLOBALS['rw_posts'][ count( $GLOBALS['rw_posts'] ) - 1 ];
	rwgcm_updater_assert( 'install performs one fresh check', count( $GLOBALS['rw_posts'] ) === $posts_before_download + 1 );
	rwgcm_updater_assert( 'the install-time check times out at 10 seconds', 10 === (int) $download_post['args']['timeout'] );
	rwgcm_updater_assert(
		'the download uses the fresh signed URL',
		'/tmp/reactwoo-geo-commerce-update.zip' === $downloaded
			&& isset( $GLOBALS['rw_downloads'][0]['url'], $GLOBALS['rw_downloads'][0]['timeout'] )
			&& $fresh === $GLOBALS['rw_downloads'][0]['url']
			&& $signed !== $GLOBALS['rw_downloads'][0]['url']
			&& 300 === (int) $GLOBALS['rw_downloads'][0]['timeout']
	);
	rwgcm_updater_assert(
		'a foreign package is left for WordPress',
		false === RWGCM_Updater::filter_upgrader_pre_download( false, 'https://downloads.wordpress.org/plugin/akismet.zip', null, array() )
	);
	$unknown = RWGCM_Updater::filter_upgrader_pre_download( false, 'reactwoo-update://other-plugin?version=1&current=0', null, array() );
	rwgcm_updater_assert( 'another ReactWoo slug is not downloaded', $unknown instanceof WP_Error && 'rwgcm_updater_unknown_slug' === $unknown->code );
	$other = RWGCM_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) );
	rwgcm_updater_assert( 'another plugin slug is left alone', false === $other );

	$same                  = new stdClass();
	$same->checked         = array( $basename => '0.10.1' );
	$GLOBALS['rw_post_response']['body'] = wp_json_encode(
		array(
			'download_url' => $signed,
			'version'      => '0.10.1',
		)
	);
	$not_newer = RWGCM_Updater::filter_update_transient( $same );
	rwgcm_updater_assert(
		'a version that is not newer is recorded as no_update',
		is_object( $not_newer )
			&& isset( $not_newer->no_update[ $basename ] )
			&& 'reactwoo-update://reactwoo-geo-commerce?version=0.10.1&current=0.10.1' === $not_newer->no_update[ $basename ]->package
			&& false === strpos( (string) $not_newer->no_update[ $basename ]->package, 'X-Amz-' )
			&& ! isset( $not_newer->response[ $basename ] )
	);

	$empty           = new stdClass();
	$empty->checked  = array();
	$posts_before    = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::filter_update_transient( $empty );
	rwgcm_updater_assert( 'an empty checked list does not post', count( $GLOBALS['rw_posts'] ) === $posts_before );

	$filtered_posts = count( $GLOBALS['rw_posts'] );
	add_filter(
		'rwgcm_updater_http_post',
		static function ( $response, $url, $args ) use ( $signed ) {
			unset( $response, $url );
			$GLOBALS['rw_filter_timeout'] = isset( $args['timeout'] ) ? (int) $args['timeout'] : 0;
			$raw = isset( $args['body'] ) ? (string) $args['body'] : '';
			if ( false === strpos( $raw, '1.0.0' ) ) {
				return null;
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						'download_url' => $signed,
						'version'      => '1.2.0',
					)
				),
			);
		},
		10,
		3
	);
	$via_filter          = new stdClass();
	$via_filter->checked = array( $basename => '1.0.0' );
	$filtered            = RWGCM_Updater::filter_update_transient( $via_filter );
	rwgcm_updater_assert(
		'rwgcm_updater_http_post can supply the check without wp_remote_post',
		count( $GLOBALS['rw_posts'] ) === $filtered_posts
			&& isset( $filtered->response[ $basename ] )
			&& '1.2.0' === $filtered->response[ $basename ]->new_version
			&& 'reactwoo-update://reactwoo-geo-commerce?version=1.2.0&current=1.0.0' === $filtered->response[ $basename ]->package
			&& false === strpos( (string) $filtered->response[ $basename ]->package, 'X-Amz-' )
			&& 8 === (int) $GLOBALS['rw_filter_timeout']
	);

	$GLOBALS['rw_post_throw'] = true;
	$thrown                   = new stdClass();
	$thrown->checked          = array( $basename => '9.9.9' );
	$safe                     = RWGCM_Updater::filter_update_transient( $thrown );
	rwgcm_updater_assert( 'a failed HTTP call does not fatal and offers no update', is_object( $safe ) && ! isset( $safe->response[ $basename ] ) );
	$posts_after_throw = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::clear_request_cache_for_tests();
	RWGCM_Updater::filter_update_transient( $thrown );
	rwgcm_updater_assert( 'a transport error is not cached for an hour', count( $GLOBALS['rw_posts'] ) === $posts_after_throw + 1 );
	$GLOBALS['rw_post_throw'] = false;

	RWGCM_Platform_Client::$configured   = false;
	RWGCM_Platform_Client::$bearer       = null;
	RWGCM_Platform_Client::$bearer_calls = 0;
	$GLOBALS['pagenow']                  = 'plugins.php';
	$GLOBALS['rw_screen']                = null;
	$_GET                                = array();
	$silent                              = new stdClass();
	$silent->checked                     = array( $basename => '8.0.0' );
	$posts_before                        = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::filter_update_transient( $silent );
	rwgcm_updater_assert( 'no licence means no update request', count( $GLOBALS['rw_posts'] ) === $posts_before && 0 === RWGCM_Platform_Client::$bearer_calls );
	rwgcm_updater_assert( 'plugins.php asks for a licence', RWGCM_Updater::should_show_notice() );
	ob_start();
	RWGCM_Updater::maybe_notice();
	$html = ob_get_clean();
	rwgcm_updater_assert(
		'the notice says to add a licence key and links to the licence screen',
		is_string( $html )
			&& false !== strpos( $html, 'Add your licence key to receive updates' )
			&& false !== strpos( $html, 'rwgcm-license' )
			&& false !== strpos( $html, 'notice-warning' )
	);
	rwgcm_updater_assert( 'showing the notice still does not log in', 0 === RWGCM_Platform_Client::$bearer_calls );

	$GLOBALS['rw_screen'] = (object) array( 'id' => 'plugins' );
	$GLOBALS['pagenow']   = 'index.php';
	rwgcm_updater_assert( 'the plugins screen id shows the notice', RWGCM_Updater::should_show_notice() );
	$GLOBALS['rw_screen'] = (object) array( 'id' => 'plugins-network' );
	rwgcm_updater_assert( 'the network plugins screen shows the notice', RWGCM_Updater::should_show_notice() );
	$GLOBALS['rw_screen'] = (object) array( 'id' => 'dashboard' );
	$GLOBALS['pagenow']   = 'plugins.php';
	rwgcm_updater_assert( 'a non-plugin screen id does not show the notice', false === RWGCM_Updater::should_show_notice() );

	$GLOBALS['rw_screen'] = null;
	$GLOBALS['pagenow']   = 'admin.php';
	$_GET['page']         = 'rwgcm-license';
	rwgcm_updater_assert( 'the Commerce licence screen shows the notice', RWGCM_Updater::should_show_notice() );
	$_GET['page']         = 'rwgc-commerce-hub';
	rwgcm_updater_assert( 'a Commerce admin page shows the notice', RWGCM_Updater::should_show_notice() );
	$_GET['page']         = 'tools';
	rwgcm_updater_assert( 'an unrelated admin page does not show the notice', false === RWGCM_Updater::should_show_notice() );
	rwgcm_updater_assert( 'those screens did not log in without a key', 0 === RWGCM_Platform_Client::$bearer_calls );

	$_GET                                = array();
	$GLOBALS['pagenow']                  = 'plugins.php';
	$GLOBALS['rw_caps']                  = array();
	rwgcm_updater_assert( 'a user who cannot update plugins does not see the notice', false === RWGCM_Updater::should_show_notice() );
	$GLOBALS['rw_caps']                  = array( 'manage_options' => true );
	rwgcm_updater_assert( 'manage_options can see the notice', RWGCM_Updater::should_show_notice() );

	RWGCM_Platform_Client::$configured = true;
	RWGCM_Platform_Client::$bearer     = null;
	$before                            = RWGCM_Platform_Client::$bearer_calls;
	rwgcm_updater_assert( 'a saved key that does not log in still shows the notice', RWGCM_Updater::should_show_notice() );
	rwgcm_updater_assert( 'login is attempted when a key is saved', RWGCM_Platform_Client::$bearer_calls > $before );

	RWGCM_Platform_Client::$bearer = 'jwt-token';
	rwgcm_updater_assert( 'a usable token hides the notice', false === RWGCM_Updater::should_show_notice() );

	$GLOBALS['rw_transients']                = array();
	RWGCM_Platform_Client::$bearer           = null;
	RWGCM_Platform_Client::$bearer_calls     = 0;
	RWGCM_Platform_Client::$timeouts         = array();
	$failed_login                            = new stdClass();
	$failed_login->checked                   = array( $basename => '4.0.0' );
	$posts_before                            = count( $GLOBALS['rw_posts'] );
	$unpaid                                  = RWGCM_Updater::filter_update_transient( $failed_login );
	rwgcm_updater_assert(
		'a failed login uses a 10 second timeout and does not check for an update',
		1 === RWGCM_Platform_Client::$bearer_calls
			&& array( 10 ) === RWGCM_Platform_Client::$timeouts
			&& count( $GLOBALS['rw_posts'] ) === $posts_before
			&& is_object( $unpaid )
			&& ! isset( $unpaid->response[ $basename ] )
	);
	$fail_ttls = array();
	foreach ( $GLOBALS['rw_transients'] as $key => $row ) {
		if ( 0 === strpos( (string) $key, 'rwgcm_ulf_' ) ) {
			$fail_ttls[] = (int) $row['ttl'];
		}
	}
	rwgcm_updater_assert( 'a failed login is cached for 15 minutes', array( 900 ) === $fail_ttls );
	RWGCM_Updater::clear_request_cache_for_tests();
	RWGCM_Updater::filter_update_transient( $failed_login );
	rwgcm_updater_assert(
		'the cached login failure is not attempted again',
		1 === RWGCM_Platform_Client::$bearer_calls && count( $GLOBALS['rw_posts'] ) === $posts_before
	);

	$GLOBALS['rw_post_response'] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode( array( 'update' => false ) ),
	);
	$GLOBALS['rw_transients'] = array();
	RWGCM_Platform_Client::$bearer = 'jwt-token';
	RWGCM_Updater::clear_request_cache_for_tests();
	$none_version = new stdClass();
	$none_version->checked = array( $basename => '5.0.0' );
	$posts_before = count( $GLOBALS['rw_posts'] );
	$none = RWGCM_Updater::filter_update_transient( $none_version );
	rwgcm_updater_assert( 'a completed check with no update is remembered', count( $GLOBALS['rw_posts'] ) === $posts_before + 1 && is_object( $none ) && ! isset( $none->response[ $basename ] ) );
	RWGCM_Updater::clear_request_cache_for_tests();
	$posts_before = count( $GLOBALS['rw_posts'] );
	RWGCM_Updater::filter_update_transient( $none_version );
	rwgcm_updater_assert( 'a cached no-update result is not requested again', count( $GLOBALS['rw_posts'] ) === $posts_before );
}

/**
 * The real platform client must honour the update-path login timeout.
 *
 * @return void
 */
function rwgcm_updater_assert_platform_client() {
	$GLOBALS['rw_is_admin'] = true;
	$GLOBALS['rw_cron']     = false;
	$GLOBALS['rw_options']  = array();
	if ( ! function_exists( 'is_admin' ) ) {
		/**
		 * @return bool
		 */
		function is_admin() { // phpcs:ignore
			return ! empty( $GLOBALS['rw_is_admin'] );
		}
	}
	if ( ! function_exists( 'wp_doing_cron' ) ) {
		/**
		 * @return bool
		 */
		function wp_doing_cron() { // phpcs:ignore
			return ! empty( $GLOBALS['rw_cron'] );
		}
	}
	if ( ! function_exists( 'get_option' ) ) {
		/**
		 * @param string $key Key.
		 * @param mixed  $default Default.
		 * @return mixed
		 */
		function get_option( $key, $default = false ) { // phpcs:ignore
			return array_key_exists( $key, $GLOBALS['rw_options'] ) ? $GLOBALS['rw_options'][ $key ] : $default;
		}
	}
	if ( ! function_exists( 'update_option' ) ) {
		/**
		 * @param string $key Key.
		 * @param mixed  $value Value.
		 * @return bool
		 */
		function update_option( $key, $value ) { // phpcs:ignore
			$GLOBALS['rw_options'][ $key ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_option' ) ) {
		/**
		 * @param string $key Key.
		 * @return bool
		 */
		function delete_option( $key ) { // phpcs:ignore
			unset( $GLOBALS['rw_options'][ $key ] );
			return true;
		}
	}
	if ( ! class_exists( 'RWGCM_Settings', false ) ) {
		/**
		 * Licence stand-in. The real settings class is not loaded in this process.
		 */
		class RWGCM_Settings { // phpcs:ignore
			/**
			 * @return array<string, string>
			 */
			public static function get_settings() {
				return array(
					'reactwoo_license_key' => 'test-key',
				);
			}
		}
	}

	require_once RWGCM_PATH . 'includes/class-rwgcm-platform-client.php';

	$GLOBALS['rw_post_response'] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'access_token' => 'jwt-from-login',
				'expires_in'   => 3600,
			)
		),
	);
	$GLOBALS['rw_transients'] = array();
	$GLOBALS['rw_posts']      = array();

	$token = RWGCM_Platform_Client::get_access_token();
	rwgcm_updater_assert( 'the default login timeout stays 30 seconds', 'jwt-from-login' === $token && 1 === count( $GLOBALS['rw_posts'] ) && 30 === (int) $GLOBALS['rw_posts'][0]['args']['timeout'] );
	rwgcm_updater_assert(
		'login posts to the v5 auth endpoint',
		false !== strpos( (string) $GLOBALS['rw_posts'][0]['url'], '/api/v5/auth/login' )
	);
	$before = count( $GLOBALS['rw_posts'] );
	$again  = RWGCM_Platform_Client::get_access_token( 10 );
	rwgcm_updater_assert( 'a cached token does not log in again', $again === $token && count( $GLOBALS['rw_posts'] ) === $before );

	delete_transient( 'rwgcm_rw_jwt_cache' );
	RWGCM_Platform_Client::get_access_token( 10 );
	rwgcm_updater_assert( 'an explicit 10 second login timeout is sent', 10 === (int) $GLOBALS['rw_posts'][ count( $GLOBALS['rw_posts'] ) - 1 ]['args']['timeout'] );

	delete_transient( 'rwgcm_rw_jwt_cache' );
	RWGCM_Platform_Client::get_access_token( 0 );
	rwgcm_updater_assert( 'a timeout below 1 second falls back to 30', 30 === (int) $GLOBALS['rw_posts'][ count( $GLOBALS['rw_posts'] ) - 1 ]['args']['timeout'] );

	delete_transient( 'rwgcm_rw_jwt_cache' );
	$bearer = RWGCM_Platform_Client::get_bearer_for_updates( 10 );
	rwgcm_updater_assert(
		'get_bearer_for_updates passes the timeout through to login',
		'jwt-from-login' === $bearer && 10 === (int) $GLOBALS['rw_posts'][ count( $GLOBALS['rw_posts'] ) - 1 ]['args']['timeout']
	);

	RWGCM_Platform_Client::clear_token_cache();
	$GLOBALS['rw_is_admin'] = false;
	$GLOBALS['rw_cron']     = false;
	$posts_before           = count( $GLOBALS['rw_posts'] );
	$skipped                = RWGCM_Platform_Client::get_access_token();
	$skipped_again          = RWGCM_Platform_Client::get_bearer_for_updates();
	rwgcm_updater_assert(
		'an ordinary front-end request does not log in',
		is_wp_error( $skipped )
			&& 'rwgcm_login_skipped' === $skipped->get_error_code()
			&& null === $skipped_again
			&& count( $GLOBALS['rw_posts'] ) === $posts_before
	);

	$GLOBALS['rw_cron']          = true;
	$GLOBALS['rw_post_response'] = array(
		'response' => array( 'code' => 401 ),
		'body'     => wp_json_encode( array( 'message' => 'Invalid licence key' ) ),
	);
	$posts_before = count( $GLOBALS['rw_posts'] );
	$first        = RWGCM_Platform_Client::get_access_token();
	$second       = RWGCM_Platform_Client::get_access_token();
	$third        = RWGCM_Platform_Client::get_bearer_for_updates();
	$login_posts  = 0;
	foreach ( array_slice( $GLOBALS['rw_posts'], $posts_before ) as $post ) {
		if ( false !== strpos( (string) $post['url'], '/api/v5/auth/login' ) ) {
			++$login_posts;
		}
	}
	$fail_ttls = array();
	foreach ( $GLOBALS['rw_transients'] as $key => $row ) {
		if ( 0 === strpos( (string) $key, 'rwgcm_login_fail_' ) ) {
			$fail_ttls[] = (int) $row['ttl'];
		}
	}
	$fail_option = get_option( 'rwgcm_login_fail', array() );
	rwgcm_updater_assert(
		'two runs with a bad key make at most one login',
		is_wp_error( $first )
			&& is_wp_error( $second )
			&& null === $third
			&& 1 === $login_posts
			&& 10 === (int) $GLOBALS['rw_posts'][ $posts_before ]['args']['timeout']
	);
	rwgcm_updater_assert(
		'a failed login is cached for 900 seconds',
		array( 900 ) === $fail_ttls
			&& is_array( $fail_option )
			&& isset( $fail_option['until'] )
			&& (int) $fail_option['until'] >= time() + 900 - 5
			&& (int) $fail_option['until'] <= time() + 900
	);
}

/**
 * @return void
 */
function rwgcm_updater_assert_real() {
	rwgcm_updater_assert( 'a real Core updater is detected', RWGCM_Updater::core_updater_is_real() );
	rwgcm_updater_assert( 'Commerce does not install its own update hooks', false === RWGCM_Updater::is_using_own() );
	rwgcm_updater_assert( 'Core register() runs once for two Commerce register calls', 1 === RWGC_Satellite_Updater::$calls );
	rwgcm_updater_assert(
		'the update transient hook is not added beside Core',
		0 === rwgcm_updater_hook_count( 'pre_set_site_transient_update_plugins', 'filter_update_transient', 'filter' )
	);
	rwgcm_updater_assert(
		'plugins_api is not added beside Core',
		0 === rwgcm_updater_hook_count( 'plugins_api', 'filter_plugins_api', 'filter' )
	);
	rwgcm_updater_assert(
		'upgrader_pre_download is not added beside Core',
		0 === rwgcm_updater_hook_count( 'upgrader_pre_download', 'filter_upgrader_pre_download', 'filter' )
	);
	rwgcm_updater_assert(
		'the licence notice is still registered once',
		1 === rwgcm_updater_hook_count( 'admin_notices', 'maybe_notice', 'action' )
	);

	$args = RWGC_Satellite_Updater::$last;
	rwgcm_updater_assert(
		'Core receives the Commerce catalogue slug, version, and licence callbacks',
		is_array( $args )
			&& 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' === $args['basename']
			&& '0.10.0' === $args['version']
			&& 'reactwoo-geo-commerce' === $args['catalog_slug']
			&& array( 'RWGCM_Platform_Client', 'get_bearer_for_updates' ) === $args['get_bearer_callback']
			&& array( 'RWGCM_Platform_Client', 'get_api_base' ) === $args['get_api_base_callback']
			&& 'ReactWoo Geo Commerce' === $args['name']
	);
	rwgcm_updater_assert( 'no update check is posted when Core handles updates', array() === $GLOBALS['rw_posts'] );
}
