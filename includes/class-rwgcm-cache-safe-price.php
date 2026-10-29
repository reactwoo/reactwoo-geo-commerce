<?php
/**
 * Cache-safe geo prices.
 *
 * Default mode is auto. With no known page cache, prices stay server-rendered
 * and the vary cookie is not set. Auto prints AJAX placeholders when a known
 * page cache is active and does not vary by country. Cart and checkout stay
 * server-rendered in every mode.
 *
 * The vary cookie is rwgc_country. Its value is the Geo Core visitor country.
 * LiteSpeed receives it through litespeed_vary_cookies and X-LiteSpeed-Vary.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vary cookie, cache-safe mode, and the public price endpoint.
 */
class RWGCM_Cache_Safe_Price {

	const COOKIE = 'rwgc_country';

	const OPTION = 'rwgcm_cache_safe_price_mode';

	const REST_NS = 'rwgcm/v1';

	const RATE_LIMIT = 40;

	const BATCH_LIMIT = 24;

	/**
	 * True while the REST renderer is building the real price HTML.
	 *
	 * @var bool
	 */
	private static $serving = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'send_vary' ), 20 );
		add_action( 'send_headers', array( __CLASS__, 'send_litespeed_vary_header' ) );
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'filter_litespeed_vary_cookies' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_catalog_price_html' ), 120, 2 );
		add_action( 'admin_post_rwgcm_save_cache_safe_price', array( __CLASS__, 'handle_save_mode' ) );
	}

	/**
	 * @return string off|auto|ajax
	 */
	public static function mode() {
		$stored = get_option( self::OPTION, 'auto' );
		return self::sanitize_mode( is_string( $stored ) ? $stored : 'auto' );
	}

	/**
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	public static function sanitize_mode( $mode ) {
		$mode = strtolower( trim( (string) $mode ) );
		return in_array( $mode, array( 'off', 'auto', 'ajax' ), true ) ? $mode : 'auto';
	}

	/**
	 * LiteSpeed vary is registered whenever cache-safe mode is not off.
	 *
	 * @return bool
	 */
	public static function registers_litespeed_vary() {
		return 'off' !== self::mode();
	}

	/**
	 * @return bool
	 */
	public static function ajax_active() {
		$mode = self::mode();
		if ( 'off' === $mode ) {
			return false;
		}
		if ( 'ajax' === $mode ) {
			return true;
		}
		return self::should_auto_ajax();
	}

	/**
	 * Auto uses AJAX when any active known cache does not vary by country.
	 *
	 * @return bool
	 */
	public static function should_auto_ajax() {
		if ( ! class_exists( 'RWGCM_Cache_Adapters', false ) ) {
			return false;
		}
		$active = RWGCM_Cache_Adapters::active();
		if ( empty( $active ) ) {
			return false;
		}
		foreach ( $active as $slug ) {
			if ( ! RWGCM_Cache_Adapters::varies_by_country( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string[]|mixed $cookies Cookie names LiteSpeed already varies on.
	 * @return string[]
	 */
	public static function filter_litespeed_vary_cookies( $cookies ) {
		if ( ! is_array( $cookies ) ) {
			$cookies = array();
		}
		if ( ! self::registers_litespeed_vary() ) {
			return $cookies;
		}
		if ( ! in_array( self::COOKIE, $cookies, true ) ) {
			$cookies[] = self::COOKIE;
		}
		return $cookies;
	}

	/**
	 * Set rwgc_country from the Geo Core visitor country.
	 *
	 * @return void
	 */
	public static function send_vary() {
		if ( ! self::should_send_cookie() ) {
			return;
		}
		$country = self::visitor_country();
		if ( '' === $country ) {
			return;
		}
		$existing = '';
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$raw      = (string) $_COOKIE[ self::COOKIE ];
			$raw      = function_exists( 'wp_unslash' ) ? (string) wp_unslash( $raw ) : $raw;
			$existing = strtoupper( substr( $raw, 0, 2 ) );
		}
		if ( $existing === $country ) {
			return;
		}
		self::set_vary_cookie( $country );
	}

	/**
	 * @return void
	 */
	public static function send_litespeed_vary_header() {
		if ( ! self::should_signal_vary() || '' === self::visitor_country() ) {
			return;
		}
		$value = 'X-LiteSpeed-Vary: cookie=' . self::COOKIE;
		if ( defined( 'RWGCM_UNIT_TESTS' ) && RWGCM_UNIT_TESTS ) {
			$GLOBALS['rw_headers'][] = $value;
			return;
		}
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		header( 'X-LiteSpeed-Vary: cookie=' . self::COOKIE, false );
	}

	/**
	 * @return bool
	 */
	public static function should_send_cookie() {
		if ( 'off' === self::mode() ) {
			return false;
		}
		if ( self::is_background_request() ) {
			return false;
		}
		if ( 'ajax' === self::mode() ) {
			return true;
		}
		return class_exists( 'RWGCM_Cache_Adapters', false ) && ! empty( RWGCM_Cache_Adapters::active() );
	}

	/**
	 * @return bool
	 */
	public static function should_signal_vary() {
		if ( ! self::registers_litespeed_vary() || self::is_background_request() ) {
			return false;
		}
		return class_exists( 'RWGCM_Cache_Adapters', false ) && RWGCM_Cache_Adapters::plugin_active( 'litespeed' );
	}

	/**
	 * @param string $context element or catalog.
	 * @return bool
	 */
	public static function should_placeholder( $context ) {
		if ( self::$serving || ! self::ajax_active() ) {
			return false;
		}
		if ( self::request_stays_server_rendered() ) {
			return false;
		}
		if ( apply_filters( 'rwgcm_cache_safe_server_render', false, $context ) ) {
			return false;
		}
		if ( 'catalog' === $context && ! self::catalog_context() ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $args Parsed price-element args.
	 * @return string
	 */
	public static function element_placeholder( array $args ) {
		$classes = array();
		if ( isset( $args['classes'] ) && is_array( $args['classes'] ) ) {
			$classes = $args['classes'];
		}
		return self::placeholder_markup(
			array(
				'context'      => 'element',
				'product_id'   => isset( $args['product_id'] ) ? (int) $args['product_id'] : 0,
				'variation_id' => isset( $args['variation_id'] ) ? (int) $args['variation_id'] : 0,
				'format'       => isset( $args['format'] ) ? (string) $args['format'] : 'symbol',
				'period'       => isset( $args['period'] ) ? (string) $args['period'] : '',
				'from'         => isset( $args['from'] ) ? (string) $args['from'] : 'auto',
				'sale'         => ! empty( $args['sale'] ) ? 'yes' : 'no',
				'fallback'     => isset( $args['fallback'] ) ? (string) $args['fallback'] : '',
				'color'        => isset( $args['color'] ) ? (string) $args['color'] : 'inherit',
				'class'        => implode( ' ', $classes ),
			)
		);
	}

	/**
	 * @param int $product_id Product id.
	 * @return string
	 */
	public static function catalog_placeholder( $product_id ) {
		return self::placeholder_markup(
			array(
				'context'    => 'catalog',
				'product_id' => (int) $product_id,
			)
		);
	}

	/**
	 * @param string      $html    Price HTML.
	 * @param object|null $product Product.
	 * @return string
	 */
	public static function filter_catalog_price_html( $html, $product ) {
		if ( ! self::should_placeholder( 'catalog' ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $html;
		}
		$id = (int) $product->get_id();
		if ( $id <= 0 ) {
			return $html;
		}
		return self::catalog_placeholder( $id );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		$args = array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'rest_prices' ),
			'permission_callback' => array( __CLASS__, 'rest_permission' ),
		);
		register_rest_route( self::REST_NS, '/prices', $args );
		register_rest_route( self::REST_NS, '/price', $args );
	}

	/**
	 * Public route. Country is resolved by Geo Core on this request.
	 *
	 * @return bool
	 */
	public static function rest_permission() {
		return true;
	}

	/**
	 * @param WP_REST_Request|array<string, mixed> $request Request.
	 * @return WP_REST_Response|array<string, mixed>
	 */
	public static function rest_prices( $request ) {
		$built = self::build_prices_response( self::items_from_request( $request ), self::client_ip() );
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new WP_REST_Response( $built['body'], (int) $built['status'] );
			foreach ( $built['headers'] as $name => $value ) {
				$response->header( $name, $value );
			}
			return $response;
		}
		return $built;
	}

	/**
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param string                           $ip    Client IP.
	 * @return array{status: int, headers: array<string, string>, body: array<string, mixed>}
	 */
	public static function build_prices_response( $items, $ip = '' ) {
		$limit = self::consume_rate_limit( '' !== $ip ? $ip : self::client_ip() );
		if ( empty( $limit['allowed'] ) ) {
			return array(
				'status'  => 429,
				'headers' => self::response_headers( $limit ),
				'body'    => array(
					'code'    => 'rwgcm_rate_limited',
					'message' => __( 'Too many price requests. Try again shortly.', 'reactwoo-geo-commerce' ),
				),
			);
		}
		$rows = array();
		foreach ( self::normalize_items( $items ) as $item ) {
			$rows[] = array(
				'html' => 'catalog' === $item['context'] ? self::render_catalog( (int) $item['product_id'] ) : self::render_element( $item ),
			);
		}
		$body = array( 'prices' => $rows );
		if ( 1 === count( $rows ) ) {
			$body['html'] = $rows[0]['html'];
		}
		return array(
			'status'  => 200,
			'headers' => self::response_headers( $limit ),
			'body'    => $body,
		);
	}

	/**
	 * @param mixed $items Raw items.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_items( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( count( $out ) >= self::BATCH_LIMIT ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			unset( $item['country'] );
			$context = isset( $item['context'] ) ? sanitize_key( (string) $item['context'] ) : 'element';
			if ( ! in_array( $context, array( 'element', 'catalog' ), true ) ) {
				$context = 'element';
			}
			$out[] = array(
				'context'      => $context,
				'product_id'   => absint( isset( $item['product_id'] ) ? $item['product_id'] : 0 ),
				'variation_id' => absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ),
				'format'       => isset( $item['format'] ) ? (string) $item['format'] : 'symbol',
				'period'       => isset( $item['period'] ) ? (string) $item['period'] : '',
				'from'         => isset( $item['from'] ) ? (string) $item['from'] : 'auto',
				'sale'         => isset( $item['sale'] ) ? (string) $item['sale'] : 'yes',
				'fallback'     => isset( $item['fallback'] ) ? (string) $item['fallback'] : '',
				'color'        => isset( $item['color'] ) ? (string) $item['color'] : 'inherit',
				'class'        => isset( $item['class'] ) ? (string) $item['class'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param WP_REST_Request|array<string, mixed> $request Request.
	 * @return array<int, mixed>
	 */
	public static function items_from_request( $request ) {
		$raw = null;
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$raw = $request->get_param( 'items' );
			if ( null === $raw || '' === $raw ) {
				$product_id = absint( $request->get_param( 'product_id' ) );
				if ( $product_id > 0 ) {
					return array(
						array(
							'context'      => $request->get_param( 'context' ),
							'product_id'   => $product_id,
							'variation_id' => $request->get_param( 'variation_id' ),
							'format'       => $request->get_param( 'format' ),
							'period'       => $request->get_param( 'period' ),
							'from'         => $request->get_param( 'from' ),
							'sale'         => $request->get_param( 'sale' ),
							'fallback'     => $request->get_param( 'fallback' ),
							'color'        => $request->get_param( 'color' ),
							'class'        => $request->get_param( 'class' ),
						),
					);
				}
			}
		} elseif ( is_array( $request ) ) {
			$raw = isset( $request['items'] ) ? $request['items'] : null;
		}
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @return void
	 */
	public static function handle_save_mode() {
		$result = self::process_save_mode_request();
		if ( empty( $result['ok'] ) ) {
			$message = ( isset( $result['error'] ) && 'forbidden' === $result['error'] )
				? __( 'You need permission to manage WooCommerce to change this setting.', 'reactwoo-geo-commerce' )
				: __( 'The link you followed has expired.', 'reactwoo-geo-commerce' );
			wp_die( esc_html( $message ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-settings&rwgcm_cache_saved=1' ) );
		exit;
	}

	/**
	 * @return array{ok: bool, error?: string, mode?: string}
	 */
	public static function process_save_mode_request() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'ok'    => false,
				'error' => 'forbidden',
			);
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'rwgcm_save_cache_safe_price' ) ) {
			return array(
				'ok'    => false,
				'error' => 'nonce',
			);
		}
		$raw  = isset( $_POST['rwgcm_cache_safe_price_mode'] ) ? wp_unslash( $_POST['rwgcm_cache_safe_price_mode'] ) : 'auto';
		$mode = self::sanitize_mode( $raw );
		update_option( self::OPTION, $mode );
		if ( class_exists( 'RWGCM_Cache_Purge', false ) ) {
			RWGCM_Cache_Purge::purge_all( 'cache_mode' );
		}
		return array(
			'ok'   => true,
			'mode' => $mode,
		);
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		if ( ! function_exists( 'wp_register_script' ) || ! defined( 'RWGCM_URL' ) ) {
			return;
		}
		wp_register_script(
			'rwgcm-cache-safe-price',
			RWGCM_URL . 'assets/js/cache-safe-price.js',
			array(),
			defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.8.0',
			true
		);
	}

	/**
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( class_exists( 'RWGCM_Product_Price', false ) ) {
			RWGCM_Product_Price::register_assets();
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rwgcm-product-price' );
		}
		self::register_assets();
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$already = function_exists( 'wp_script_is' ) && wp_script_is( 'rwgcm-cache-safe-price', 'enqueued' );
		if ( function_exists( 'wp_localize_script' ) && ! $already ) {
			$url = function_exists( 'rest_url' ) ? rest_url( self::REST_NS . '/prices' ) : '';
			$url = apply_filters( 'rwgcm_cache_safe_price_endpoint', $url );
			if ( is_string( $url ) && function_exists( 'esc_url_raw' ) ) {
				$url = esc_url_raw( $url );
			}
			wp_localize_script(
				'rwgcm-cache-safe-price',
				'rwgcmCacheSafePrice',
				array(
					'endpoint' => is_string( $url ) ? $url : '',
				)
			);
		}
		wp_enqueue_script( 'rwgcm-cache-safe-price' );
	}

	/**
	 * Geo Core visitor country. The cookie is not a source.
	 *
	 * @return string
	 */
	public static function visitor_country() {
		$country = '';
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && method_exists( 'RWGCM_Price_Book_Currency', 'visitor_country' ) ) {
			$country = (string) RWGCM_Price_Book_Currency::visitor_country();
		} elseif ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$country = (string) rwgc_get_visitor_country();
		}
		$country = strtoupper( substr( (string) preg_replace( '/[^A-Za-z]/', '', $country ), 0, 2 ) );
		return 2 === strlen( $country ) ? $country : '';
	}

	/**
	 * @param array<string, mixed> $query Placeholder query.
	 * @return string
	 */
	private static function placeholder_markup( array $query ) {
		unset( $query['country'] );
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $query ) : json_encode( $query );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		self::enqueue_assets();
		return '<span class="rwgcm-price rwgcm-price--pending" data-rwgcm-price-ajax="' . esc_attr( $json ) . '" aria-busy="true">'
			. '<span class="rwgcm-price__pending" aria-hidden="true"></span>'
			. '<span class="screen-reader-text">' . esc_html__( 'Loading price', 'reactwoo-geo-commerce' ) . '</span>'
			. '<noscript><span class="rwgcm-price__fallback">' . esc_html__( 'Price available with JavaScript.', 'reactwoo-geo-commerce' ) . '</span></noscript>'
			. '</span>';
	}

	/**
	 * @param array<string, mixed> $item Normalised element item.
	 * @return string
	 */
	private static function render_element( array $item ) {
		if ( ! class_exists( 'RWGCM_Product_Price', false ) ) {
			return '';
		}
		self::$serving = true;
		try {
			return (string) RWGCM_Product_Price::render( $item );
		} finally {
			self::$serving = false;
		}
	}

	/**
	 * @param int $product_id Product id.
	 * @return string
	 */
	private static function render_catalog( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! is_object( $product ) ) {
			return '';
		}
		self::$serving = true;
		try {
			if ( method_exists( $product, 'get_price_html' ) ) {
				return (string) $product->get_price_html();
			}
			if ( method_exists( $product, 'get_price' ) && function_exists( 'wc_price' ) ) {
				return (string) wc_price( $product->get_price() );
			}
			return '';
		} finally {
			self::$serving = false;
		}
	}

	/**
	 * @param string $ip Client IP.
	 * @return array{allowed: bool, limit: int, remaining: int, retry_after: int}
	 */
	private static function consume_rate_limit( $ip ) {
		$limit = (int) apply_filters( 'rwgcm_price_rate_limit', self::RATE_LIMIT );
		if ( $limit < 1 ) {
			$limit = self::RATE_LIMIT;
		}
		$key    = 'rwgcm_price_rl_' . md5( self::normal_ip( $ip ) );
		$now    = time();
		$bucket = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( ! is_array( $bucket ) || ! isset( $bucket['start'], $bucket['count'] ) || ( $now - (int) $bucket['start'] ) >= 60 ) {
			$bucket = array(
				'start' => $now,
				'count' => 0,
			);
		}
		$bucket['count'] = (int) $bucket['count'] + 1;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $bucket, 60 );
		}
		$allowed = (int) $bucket['count'] <= $limit;
		return array(
			'allowed'     => $allowed,
			'limit'       => $limit,
			'remaining'   => max( 0, $limit - (int) $bucket['count'] ),
			'retry_after' => $allowed ? 0 : max( 1, 60 - ( $now - (int) $bucket['start'] ) ),
		);
	}

	/**
	 * @param array{allowed: bool, limit: int, remaining: int, retry_after: int} $limit Limit state.
	 * @return array<string, string>
	 */
	private static function response_headers( $limit ) {
		$headers = array(
			'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'        => 'no-cache',
			'Expires'       => '0',
			'X-Robots-Tag'  => 'noindex',
		);
		if ( empty( $limit['allowed'] ) ) {
			$headers['Retry-After'] = (string) (int) $limit['retry_after'];
		}
		return $headers;
	}

	/**
	 * @param string $ip Raw IP.
	 * @return string
	 */
	private static function normal_ip( $ip ) {
		$ip = trim( (string) $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return self::normal_ip( $ip );
	}

	/**
	 * @param string $country ISO2.
	 * @return void
	 */
	private static function set_vary_cookie( $country ) {
		$_COOKIE[ self::COOKIE ] = $country;
		if ( defined( 'RWGCM_UNIT_TESTS' ) && RWGCM_UNIT_TESTS ) {
			$GLOBALS['rw_set_cookies'][] = array( self::COOKIE, $country );
			return;
		}
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		$expires = time() + ( defined( 'YEAR_IN_SECONDS' ) ? (int) YEAR_IN_SECONDS : 31536000 );
		setcookie(
			self::COOKIE,
			$country,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => function_exists( 'is_ssl' ) ? is_ssl() : false,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * REST is often still on init when the vary cookie would be set, before
	 * REST_REQUEST exists. The URI is enough to keep the cookie off the response.
	 *
	 * @return bool
	 */
	private static function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' );
	}

	/**
	 * @return bool
	 */
	private static function is_background_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( self::is_rest_request() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}
		return false;
	}

	/**
	 * Cart, checkout, emails, admin, and editors keep the server price.
	 *
	 * @return bool
	 */
	private static function request_stays_server_rendered() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( (string) $_GET['action'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $uri, 'block-renderer/rwgcm/' ) || false !== strpos( $uri, 'rwgcm/v1/elementor-preview' ) ) {
			return true;
		}
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::is_editor_preview_request() ) {
			return true;
		}
		if ( function_exists( 'doing_action' ) && ( doing_action( 'wp_head' ) || doing_action( 'wp_footer' ) ) ) {
			return true;
		}
		if ( function_exists( 'did_action' ) ) {
			foreach ( array( 'woocommerce_before_mini_cart', 'woocommerce_email_header', 'woocommerce_email_before_order_table', 'woocommerce_review_order_before_order_total' ) as $hook ) {
				if ( did_action( $hook ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Shop, product, and product taxonomy archives.
	 *
	 * @return bool
	 */
	private static function catalog_context() {
		foreach ( array( 'is_shop', 'is_product', 'is_product_category', 'is_product_tag', 'is_product_taxonomy' ) as $fn ) {
			if ( function_exists( $fn ) && $fn() ) {
				return true;
			}
		}
		return false;
	}
}
