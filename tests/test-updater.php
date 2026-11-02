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

$modes  = array( 'stub', 'real', 'wporg', 'inactive', 'missing' );
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
			unset( $thing );
			return false;
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

			/**
			 * @return bool
			 */
			public static function is_configured() {
				return (bool) self::$configured;
			}

			/**
			 * @return string|null
			 */
			public static function get_bearer_for_updates() {
				++self::$bearer_calls;
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

	$basename  = 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
	$transient = new stdClass();
	$transient->checked = array( $basename => '0.10.0' );
	$updated   = RWGCM_Updater::filter_update_transient( $transient );
	rwgcm_updater_assert( 'one check is posted', 1 === count( $GLOBALS['rw_posts'] ) );
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
	rwgcm_updater_assert(
		'the signed package URL is the update package',
		is_object( $updated )
			&& isset( $updated->response[ $basename ] )
			&& $signed === $updated->response[ $basename ]->package
			&& '0.10.1' === $updated->response[ $basename ]->new_version
			&& 'reactwoo-geo-commerce' === $updated->response[ $basename ]->slug
	);

	$again = RWGCM_Updater::filter_update_transient( $transient );
	rwgcm_updater_assert( 'the same check is not posted twice in one request', 1 === count( $GLOBALS['rw_posts'] ) );
	unset( $again );

	$args         = new stdClass();
	$args->slug   = 'reactwoo-geo-commerce';
	$info         = RWGCM_Updater::filter_plugins_api( false, 'plugin_information', $args );
	rwgcm_updater_assert(
		'plugins_api returns the signed download link',
		is_object( $info ) && $signed === $info->download_link && '0.10.1' === $info->version
	);
	rwgcm_updater_assert( 'plugins_api reuses the check from this request', 1 === count( $GLOBALS['rw_posts'] ) );
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
			&& $signed === $not_newer->no_update[ $basename ]->package
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
			&& $signed === $filtered->response[ $basename ]->package
	);

	$GLOBALS['rw_post_throw'] = true;
	$thrown                   = new stdClass();
	$thrown->checked          = array( $basename => '9.9.9' );
	$safe                     = RWGCM_Updater::filter_update_transient( $thrown );
	rwgcm_updater_assert( 'a failed HTTP call does not fatal and offers no update', is_object( $safe ) && ! isset( $safe->response[ $basename ] ) );
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
