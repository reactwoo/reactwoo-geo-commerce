<?php
/**
 * Cache-safe geo prices (run with: php tests/test-cache-safe-price.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'RWGCM_UNIT_TESTS' ) ) {
	define( 'RWGCM_UNIT_TESTS', true );
}

$_SERVER['REQUEST_URI'] = '/product/example/';

$GLOBALS['rw_options']      = array();
$GLOBALS['rw_filters']      = array();
$GLOBALS['rw_actions']      = array();
$GLOBALS['rw_plugin_flags'] = null;
$GLOBALS['rw_products']     = array();
$GLOBALS['rw_transients']   = array();
$GLOBALS['rw_headers']      = array();
$GLOBALS['rw_set_cookies']  = array();
$GLOBALS['rw_visitor']      = 'ZA';
$GLOBALS['rw_caps']         = array();
$GLOBALS['rw_nonces']       = array();
$GLOBALS['rw_rate_limit']   = 40;
$GLOBALS['rw_is_admin']     = false;
$GLOBALS['rw_is_cart']      = false;
$GLOBALS['rw_is_checkout']  = false;
$GLOBALS['rw_is_shop']      = false;
$GLOBALS['rw_did_action']   = array();
$GLOBALS['rw_dynamic']      = array();

function __( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function esc_html( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function absint( $value ) { // phpcs:ignore
	return abs( (int) $value );
}
function sanitize_key( $key ) { // phpcs:ignore
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function sanitize_text_field( $text ) { // phpcs:ignore
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}
function sanitize_html_class( $class, $fallback = '' ) { // phpcs:ignore
	$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	return '' === $clean ? (string) $fallback : $clean;
}
function wp_kses_post( $html ) { // phpcs:ignore
	return (string) $html;
}
function wp_unslash( $value ) { // phpcs:ignore
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_json_encode( $value ) { // phpcs:ignore
	return json_encode( $value );
}
function is_admin() { // phpcs:ignore
	return ! empty( $GLOBALS['rw_is_admin'] );
}
function is_cart() { // phpcs:ignore
	return ! empty( $GLOBALS['rw_is_cart'] );
}
function is_checkout() { // phpcs:ignore
	return ! empty( $GLOBALS['rw_is_checkout'] );
}
function is_shop() { // phpcs:ignore
	return ! empty( $GLOBALS['rw_is_shop'] );
}
function is_product() { // phpcs:ignore
	return false;
}
function is_product_category() { // phpcs:ignore
	return false;
}
function is_product_tag() { // phpcs:ignore
	return false;
}
function is_product_taxonomy() { // phpcs:ignore
	return false;
}
function is_feed() { // phpcs:ignore
	return false;
}
function wp_doing_ajax() { // phpcs:ignore
	return false;
}
function wp_doing_cron() { // phpcs:ignore
	return false;
}
function did_action( $hook ) { // phpcs:ignore
	return ! empty( $GLOBALS['rw_did_action'][ $hook ] ) ? 1 : 0;
}
function doing_action( $hook ) { // phpcs:ignore
	unset( $hook );
	return false;
}
function rwgc_get_visitor_country() { // phpcs:ignore
	return (string) $GLOBALS['rw_visitor'];
}
function current_user_can( $cap ) { // phpcs:ignore
	return ! empty( $GLOBALS['rw_caps'][ $cap ] );
}
function wp_verify_nonce( $nonce, $action ) { // phpcs:ignore
	return isset( $GLOBALS['rw_nonces'][ $action ] ) && (string) $GLOBALS['rw_nonces'][ $action ] === (string) $nonce;
}
function get_option( $key, $default = false ) { // phpcs:ignore
	if ( is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] ) ) {
		return $GLOBALS['rw_options'][ $key ];
	}
	return $default;
}
function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
	unset( $autoload );
	$old = array_key_exists( $key, $GLOBALS['rw_options'] ) ? $GLOBALS['rw_options'][ $key ] : false;
	$GLOBALS['rw_options'][ $key ] = $value;
	if ( function_exists( 'do_action' ) ) {
		do_action( 'updated_option', $key, $old, $value );
	}
	return true;
}
function get_transient( $key ) { // phpcs:ignore
	return isset( $GLOBALS['rw_transients'][ $key ] ) ? $GLOBALS['rw_transients'][ $key ] : false;
}
function set_transient( $key, $value, $expiration ) { // phpcs:ignore
	unset( $expiration );
	$GLOBALS['rw_transients'][ $key ] = $value;
	return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
	unset( $priority, $accepted_args );
	$GLOBALS['rw_actions'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
	unset( $priority, $accepted_args );
	$GLOBALS['rw_filters'][ $hook ][] = $callback;
}
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
function do_action( $hook ) { // phpcs:ignore
	$args = array_slice( func_get_args(), 1 );
	if ( empty( $GLOBALS['rw_actions'][ $hook ] ) ) {
		return;
	}
	foreach ( $GLOBALS['rw_actions'][ $hook ] as $callback ) {
		call_user_func_array( $callback, $args );
	}
}
function get_woocommerce_currency() { // phpcs:ignore
	return 'GBP';
}
function wc_get_product( $id ) { // phpcs:ignore
	return isset( $GLOBALS['rw_products'][ $id ] ) ? $GLOBALS['rw_products'][ $id ] : null;
}
function wc_get_price_decimals() { // phpcs:ignore
	return 2;
}
function wc_get_price_decimal_separator() { // phpcs:ignore
	return '.';
}
function wc_get_price_thousand_separator() { // phpcs:ignore
	return ',';
}
function wc_get_price_to_display( $product, $args = array() ) { // phpcs:ignore
	if ( is_array( $args ) && array_key_exists( 'price', $args ) ) {
		return (float) $args['price'];
	}
	return is_object( $product ) && method_exists( $product, 'get_price' ) ? (float) $product->get_price() : 0.0;
}
function wc_price( $price, $args = array() ) { // phpcs:ignore
	unset( $args );
	return '<span class="woocommerce-Price-amount">GBP ' . number_format( (float) $price, 2, '.', '' ) . '</span>';
}

/**
 * Simple product whose catalog HTML goes through the price-html filter.
 */
class RW_Safe_Product {
	/** @var int */
	public $id;
	/** @var string */
	public $price;

	/**
	 * @param int    $id    Id.
	 * @param string $price Price.
	 */
	public function __construct( $id, $price ) {
		$this->id    = (int) $id;
		$this->price = (string) $price;
	}
	public function get_id() { // phpcs:ignore
		return $this->id;
	}
	public function get_price() { // phpcs:ignore
		return $this->price;
	}
	public function get_regular_price() { // phpcs:ignore
		return $this->price;
	}
	public function get_sale_price() { // phpcs:ignore
		return '';
	}
	public function is_on_sale() { // phpcs:ignore
		return false;
	}
	public function is_purchasable() { // phpcs:ignore
		return true;
	}
	public function is_in_stock() { // phpcs:ignore
		return true;
	}
	public function is_type( $type ) { // phpcs:ignore
		return 'simple' === $type;
	}
	public function get_price_html() { // phpcs:ignore
		$html = '<span class="woocommerce-Price-amount">GBP 19.50</span>';
		return apply_filters( 'woocommerce_get_price_html', $html, $this );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-adapters.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-purge.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-safe-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-product-price.php';

add_filter(
	'rwgcm_active_cache_plugins',
	static function () {
		return $GLOBALS['rw_plugin_flags'];
	}
);
add_filter(
	'rwgcm_price_rate_limit',
	static function () {
		return (int) $GLOBALS['rw_rate_limit'];
	}
);

RWGCM_Cache_Purge::init();
RWGCM_Cache_Safe_Price::init();

$passed = 0;
$failed = 0;

/**
 * @param string $label     Label.
 * @param bool   $condition Condition.
 * @return void
 */
function assert_true( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

$GLOBALS['rw_products'][41] = new RW_Safe_Product( 41, '19.50' );

assert_true( 'the default mode is auto', 'auto' === RWGCM_Cache_Safe_Price::mode() );
assert_true( 'auto with no cache plugin does not use AJAX', ! RWGCM_Cache_Safe_Price::ajax_active() && ! RWGCM_Cache_Safe_Price::should_send_cookie() );
$ssr = RWGCM_Product_Price::render( array( 'product_id' => 41 ) );
assert_true( 'auto with no cache plugin keeps the server price', is_string( $ssr ) && false !== strpos( $ssr, 'GBP 19.50' ) && false === strpos( $ssr, 'data-rwgcm-price-ajax' ) );

$GLOBALS['rw_plugin_flags'] = array( 'rocket' => true );
assert_true( 'auto uses AJAX when WP Rocket is active and not varied', RWGCM_Cache_Safe_Price::should_auto_ajax() && RWGCM_Cache_Safe_Price::ajax_active() );
$GLOBALS['rw_filters']['rocket_cache_dynamic_cookies'] = array(
	static function () {
		return array( 'rwgc_country' );
	},
);
assert_true( 'auto stays server-rendered when WP Rocket already varies on rwgc_country', ! RWGCM_Cache_Safe_Price::should_auto_ajax() );
unset( $GLOBALS['rw_filters']['rocket_cache_dynamic_cookies'] );

$GLOBALS['rw_plugin_flags'] = array( 'litespeed' => true );
assert_true( 'auto treats LiteSpeed as varied', RWGCM_Cache_Safe_Price::registers_litespeed_vary() && ! RWGCM_Cache_Safe_Price::ajax_active() );
$varied = RWGCM_Cache_Safe_Price::filter_litespeed_vary_cookies( array( 'woocommerce_cart_hash' ) );
assert_true( 'the LiteSpeed vary filter includes rwgc_country', in_array( 'rwgc_country', $varied, true ) && in_array( 'woocommerce_cart_hash', $varied, true ) );
$saved_price_uri = $_SERVER['REQUEST_URI'];
$_SERVER['REQUEST_URI'] = '/wp-json/rwgcm/v1/prices?items=%5B%5D';
$_COOKIE['rwgc_country'] = 'US';
$cookies_before_rest = count( $GLOBALS['rw_set_cookies'] );
RWGCM_Cache_Safe_Price::send_vary();
RWGCM_Cache_Safe_Price::send_litespeed_vary_header();
assert_true( 'a REST price request does not set rwgc_country before REST_REQUEST exists', count( $GLOBALS['rw_set_cookies'] ) === $cookies_before_rest && isset( $_COOKIE['rwgc_country'] ) && 'US' === $_COOKIE['rwgc_country'] );
$_SERVER['REQUEST_URI'] = '/?rest_route=/rwgcm/v1/price';
RWGCM_Cache_Safe_Price::send_vary();
assert_true( 'a rest_route price request does not set rwgc_country', count( $GLOBALS['rw_set_cookies'] ) === $cookies_before_rest && 'US' === $_COOKIE['rwgc_country'] );
$_SERVER['REQUEST_URI'] = $saved_price_uri;
$_COOKIE['rwgc_country'] = 'US';
RWGCM_Cache_Safe_Price::send_vary();
assert_true( 'the vary cookie is rewritten from the Geo Core country', isset( $_COOKIE['rwgc_country'] ) && 'ZA' === $_COOKIE['rwgc_country'] && isset( $GLOBALS['rw_set_cookies'][0][1] ) && 'ZA' === $GLOBALS['rw_set_cookies'][0][1] );
$cookie_sets = count( $GLOBALS['rw_set_cookies'] );
RWGCM_Cache_Safe_Price::send_vary();
assert_true( 'a matching vary cookie is not set again', count( $GLOBALS['rw_set_cookies'] ) === $cookie_sets );
RWGCM_Cache_Safe_Price::send_litespeed_vary_header();
assert_true( 'LiteSpeed receives X-LiteSpeed-Vary for the country cookie', in_array( 'X-LiteSpeed-Vary: cookie=rwgc_country', $GLOBALS['rw_headers'], true ) );

$GLOBALS['rw_plugin_flags'] = array(
	'litespeed'  => true,
	'cloudflare' => true,
);
assert_true( 'auto uses AJAX when Cloudflare APO is active beside LiteSpeed', RWGCM_Cache_Safe_Price::ajax_active() );

update_option( RWGCM_Cache_Safe_Price::OPTION, 'off' );
$off_varied = RWGCM_Cache_Safe_Price::filter_litespeed_vary_cookies( array( 'woocommerce_cart_hash' ) );
assert_true( 'off does not add the LiteSpeed vary cookie or switch to AJAX', ! RWGCM_Cache_Safe_Price::ajax_active() && ! RWGCM_Cache_Safe_Price::should_send_cookie() && $off_varied === array( 'woocommerce_cart_hash' ) );
$GLOBALS['rw_headers'] = array();
RWGCM_Cache_Safe_Price::send_litespeed_vary_header();
assert_true( 'off does not send X-LiteSpeed-Vary', array() === $GLOBALS['rw_headers'] );

update_option( RWGCM_Cache_Safe_Price::OPTION, 'ajax' );
$GLOBALS['rw_plugin_flags'] = null;
assert_true( 'ajax mode is on even with no cache plugin', RWGCM_Cache_Safe_Price::ajax_active() && RWGCM_Cache_Safe_Price::should_send_cookie() );
$GLOBALS['rw_is_shop'] = true;
$placeholder = RWGCM_Product_Price::render( array( 'product_id' => 41, 'format' => 'symbol' ) );
assert_true( 'ajax mode prints a placeholder instead of the country price', is_string( $placeholder ) && false !== strpos( $placeholder, 'data-rwgcm-price-ajax' ) && false !== strpos( $placeholder, 'Loading price' ) && false === strpos( $placeholder, '19.50' ) );
$decoded = array();
if ( preg_match( '/data-rwgcm-price-ajax="([^"]*)"/', $placeholder, $match ) ) {
	$decoded = json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
}
assert_true( 'the placeholder carries the product id and does not carry a country', is_array( $decoded ) && 41 === (int) $decoded['product_id'] && ! isset( $decoded['country'] ) );

$built = RWGCM_Cache_Safe_Price::build_prices_response(
	array(
		array(
			'context'    => 'element',
			'product_id' => 41,
			'country'    => 'US',
			'format'     => 'symbol',
		),
	),
	'203.0.113.10'
);
assert_true( 'the REST price is the server render and is not stored', 200 === (int) $built['status'] && isset( $built['body']['html'] ) && false !== strpos( $built['body']['html'], 'GBP 19.50' ) && false === strpos( $built['body']['html'], 'data-rwgcm-price-ajax' ) && false !== strpos( $built['headers']['Cache-Control'], 'no-store' ) );
assert_true( 'the REST response is noindex', isset( $built['headers']['X-Robots-Tag'] ) && 'noindex' === $built['headers']['X-Robots-Tag'] );

$catalog = RWGCM_Cache_Safe_Price::build_prices_response(
	array(
		array(
			'context'    => 'catalog',
			'product_id' => 41,
			'country'    => 'DE',
		),
	),
	'203.0.113.11'
);
assert_true( 'catalog REST HTML stays the real price while the shop view is active', 200 === (int) $catalog['status'] && false !== strpos( $catalog['body']['html'], 'GBP 19.50' ) && false === strpos( $catalog['body']['html'], 'data-rwgcm-price-ajax' ) );

$wrapped = RWGCM_Cache_Safe_Price::filter_catalog_price_html( '<span class="woocommerce-Price-amount">GBP 19.50</span>', $GLOBALS['rw_products'][41] );
assert_true( 'shop catalog HTML uses the same placeholder in AJAX mode', is_string( $wrapped ) && false !== strpos( $wrapped, 'data-rwgcm-price-ajax' ) && false === strpos( $wrapped, '19.50' ) );
$GLOBALS['rw_is_shop'] = false;
$untouched = RWGCM_Cache_Safe_Price::filter_catalog_price_html( '<span class="woocommerce-Price-amount">GBP 19.50</span>', $GLOBALS['rw_products'][41] );
assert_true( 'catalog HTML outside the shop, product, and category views stays server-rendered', false !== strpos( $untouched, 'GBP 19.50' ) && false === strpos( $untouched, 'data-rwgcm-price-ajax' ) );

$GLOBALS['rw_is_cart'] = true;
$cart_html = RWGCM_Product_Price::render( array( 'product_id' => 41 ) );
assert_true( 'cart prices stay server-rendered in AJAX mode', false !== strpos( $cart_html, 'GBP 19.50' ) && false === strpos( $cart_html, 'data-rwgcm-price-ajax' ) );
$GLOBALS['rw_is_cart']     = false;
$GLOBALS['rw_is_checkout'] = true;
$checkout_html = RWGCM_Cache_Safe_Price::filter_catalog_price_html( '<span class="woocommerce-Price-amount">GBP 19.50</span>', $GLOBALS['rw_products'][41] );
$GLOBALS['rw_is_shop'] = true;
assert_true( 'checkout catalog HTML stays server-rendered', false !== strpos( $checkout_html, 'GBP 19.50' ) );
$GLOBALS['rw_is_checkout'] = false;
$GLOBALS['rw_did_action']['woocommerce_before_mini_cart'] = true;
$mini = RWGCM_Cache_Safe_Price::filter_catalog_price_html( '<span class="woocommerce-Price-amount">GBP 19.50</span>', $GLOBALS['rw_products'][41] );
assert_true( 'the mini-cart stays server-rendered', false !== strpos( $mini, 'GBP 19.50' ) && false === strpos( $mini, 'data-rwgcm-price-ajax' ) );
unset( $GLOBALS['rw_did_action']['woocommerce_before_mini_cart'] );

$_GET['elementor-preview'] = '1';
$editor = RWGCM_Product_Price::render( array( 'product_id' => 41 ) );
assert_true( 'the Elementor preview stays server-rendered', false !== strpos( $editor, 'GBP 19.50' ) && false === strpos( $editor, 'data-rwgcm-price-ajax' ) );
unset( $_GET['elementor-preview'] );

$items = array();
for ( $i = 0; $i < 30; $i++ ) {
	$items[] = array(
		'product_id' => $i + 1,
		'context'    => 'catalog',
		'country'    => 'US',
	);
}
$normalised = RWGCM_Cache_Safe_Price::normalize_items( $items );
assert_true( 'a batch is capped at 24 items and the country argument is dropped', 24 === count( $normalised ) && ! isset( $normalised[0]['country'] ) );

$GLOBALS['rw_rate_limit'] = 2;
$first  = RWGCM_Cache_Safe_Price::build_prices_response( array( array( 'product_id' => 41, 'context' => 'catalog' ) ), '198.51.100.8' );
$second = RWGCM_Cache_Safe_Price::build_prices_response( array( array( 'product_id' => 41, 'context' => 'catalog' ) ), '198.51.100.8' );
$third  = RWGCM_Cache_Safe_Price::build_prices_response( array( array( 'product_id' => 41, 'context' => 'catalog' ) ), '198.51.100.8' );
assert_true( 'the public endpoint rate-limits per IP', 200 === (int) $first['status'] && 200 === (int) $second['status'] && 429 === (int) $third['status'] && isset( $third['headers']['Retry-After'] ) && false !== strpos( $third['headers']['Cache-Control'], 'no-store' ) );
assert_true( 'permission for the price route is public', true === RWGCM_Cache_Safe_Price::rest_permission() );

$saved_mode_time = isset( RWGCM_Cache_Purge::last_purge()['time'] ) ? (int) RWGCM_Cache_Purge::last_purge()['time'] : 0;
update_option( RWGCM_Cache_Safe_Price::OPTION, 'auto' );
assert_true( 'writing the mode option by itself does not purge twice through the option hook', (int) ( isset( RWGCM_Cache_Purge::last_purge()['time'] ) ? RWGCM_Cache_Purge::last_purge()['time'] : 0 ) === $saved_mode_time );
$GLOBALS['rw_caps']['manage_woocommerce'] = true;
$GLOBALS['rw_nonces']['rwgcm_save_cache_safe_price'] = 'mode-nonce';
$_POST = array(
	'_wpnonce'                     => 'nope',
	'rwgcm_cache_safe_price_mode'  => 'ajax',
);
$bad = RWGCM_Cache_Safe_Price::process_save_mode_request();
assert_true( 'saving the mode requires a nonce', empty( $bad['ok'] ) && 'nonce' === $bad['error'] && 'auto' === RWGCM_Cache_Safe_Price::mode() );
unset( $GLOBALS['rw_caps']['manage_woocommerce'] );
$_POST['_wpnonce'] = 'mode-nonce';
$forbidden = RWGCM_Cache_Safe_Price::process_save_mode_request();
assert_true( 'saving the mode requires manage_woocommerce', empty( $forbidden['ok'] ) && 'forbidden' === $forbidden['error'] );
$GLOBALS['rw_caps']['manage_woocommerce'] = true;
$_POST['rwgcm_cache_safe_price_mode'] = 'not-a-mode';
$saved = RWGCM_Cache_Safe_Price::process_save_mode_request();
assert_true( 'an unknown mode is stored as auto and purges', ! empty( $saved['ok'] ) && 'auto' === $saved['mode'] && 'auto' === RWGCM_Cache_Safe_Price::mode() && 'cache_mode' === RWGCM_Cache_Purge::last_purge()['reason'] );

$class   = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-cache-safe-price.php' );
$js      = file_get_contents( dirname( __DIR__ ) . '/assets/js/cache-safe-price.js' );
$boot    = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-plugin.php' );
$readme  = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$plugin  = file_get_contents( dirname( __DIR__ ) . '/reactwoo-geo-commerce.php' );
$price   = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-product-price.php' );
$perm_at = is_string( $class ) ? strpos( $class, 'function rest_permission' ) : false;
$next_at = is_string( $class ) ? strpos( $class, 'function rest_prices' ) : false;
$perm    = ( false !== $perm_at && false !== $next_at ) ? substr( $class, $perm_at, $next_at - $perm_at ) : '';
assert_true( 'the price renderer still does not read the vary cookie', is_string( $price ) && false === strpos( $price, '$_COOKIE' ) && false === strpos( $price, 'nocache_headers' ) );
assert_true(
	'the endpoint is public, nonce-free, and sends no-store',
	false !== strpos( $perm, 'return true;' )
		&& false === strpos( $perm, 'wp_verify_nonce' )
		&& is_string( $class )
		&& false !== strpos( $class, 'no-store' )
		&& false !== strpos( $class, 'litespeed_vary_cookies' )
		&& false !== strpos( $class, 'X-LiteSpeed-Vary' )
);
assert_true( 'the browser script does not send a REST nonce', is_string( $js ) && false === strpos( $js, 'X-WP-Nonce' ) && false === strpos( $js, 'wp_rest' ) && false !== strpos( $js, "cache: 'no-store'" ) && false !== strpos( $js, 'delete query.country' ) );
assert_true( 'the plugin boots cache-safe prices', is_string( $boot ) && false !== strpos( $boot, 'RWGCM_Cache_Safe_Price::init' ) );
assert_true(
	'the readme documents AJAX limits',
	is_string( $readme )
		&& false !== strpos( $readme, 'Cart, checkout' )
		&& false !== strpos( $readme, 'woocommerce_available_variation' )
		&& false !== strpos( $readme, 'Store API' )
		&& false !== strpos( $readme, 'Cache-Control: no-store' )
		&& false !== strpos( $readme, 'Stable tag: 0.8.0' )
);
assert_true(
	'plugin header and constant are 0.8.0',
	is_string( $plugin ) && false !== strpos( $plugin, 'Version: 0.8.0' ) && false !== strpos( $plugin, "define( 'RWGCM_VERSION', '0.8.0' )" )
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
