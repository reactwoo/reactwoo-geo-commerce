<?php
/**
 * Cache purge service (run with: php tests/test-cache-purge.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

namespace Elementor\Core\Files\CSS {

	/**
	 * Stand-in for Elementor's per-post CSS file.
	 */
	class Post {
		/** @var int[] */
		public static $updated = array();

		/** @var int */
		public $id;

		/**
		 * @param int $post_id Post id.
		 */
		public function __construct( $post_id ) {
			$this->id = (int) $post_id;
		}

		/**
		 * @return void
		 */
		public function update() {
			self::$updated[] = $this->id;
		}
	}
}

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'RWGCM_UNIT_TESTS' ) ) {
		define( 'RWGCM_UNIT_TESTS', true );
	}

	$GLOBALS['rw_options']      = array();
	$GLOBALS['rw_meta']         = array();
	$GLOBALS['rw_actions']      = array();
	$GLOBALS['rw_hook_args']    = array();
	$GLOBALS['rw_filters']      = array();
	$GLOBALS['rw_did']          = array();
	$GLOBALS['rw_calls']        = array();
	$GLOBALS['rw_plugin_flags'] = null;
	$GLOBALS['rw_active_files'] = array();
	$GLOBALS['rw_products']     = array();
	$GLOBALS['rw_parent']       = array();
	$GLOBALS['rw_terms']        = array();
	$GLOBALS['rw_catalog_ids']  = array( 10, 11, 12 );
	$GLOBALS['rw_caps']         = array();
	$GLOBALS['rw_nonces']       = array();
	$GLOBALS['rw_purged']       = 0;

	function __( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
	function esc_html( $text ) { // phpcs:ignore
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
	function wp_unslash( $value ) { // phpcs:ignore
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
	function wp_json_encode( $value ) { // phpcs:ignore
		return json_encode( $value );
	}
	function is_wp_error( $thing ) { // phpcs:ignore
		unset( $thing );
		return false;
	}
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore
		unset( $priority );
		$GLOBALS['rw_actions'][ $hook ][]   = $callback;
		$GLOBALS['rw_hook_args'][ $hook ] = (int) $accepted_args;
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
	function has_action( $hook, $callback = false ) { // phpcs:ignore
		unset( $callback );
		return empty( $GLOBALS['rw_actions'][ $hook ] ) ? false : 10;
	}
	function do_action( $hook ) { // phpcs:ignore
		$args = func_get_args();
		$payload = array_slice( $args, 1 );
		$GLOBALS['rw_did'][ $hook ][] = $payload;
		if ( empty( $GLOBALS['rw_actions'][ $hook ] ) ) {
			return;
		}
		foreach ( $GLOBALS['rw_actions'][ $hook ] as $callback ) {
			call_user_func_array( $callback, $payload );
		}
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
		do_action( 'updated_option', $key, $old, $value );
		return true;
	}
	function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore
		unset( $single );
		if ( isset( $GLOBALS['rw_meta'][ $id ][ $key ] ) ) {
			return $GLOBALS['rw_meta'][ $id ][ $key ];
		}
		return '';
	}
	function get_permalink( $id ) { // phpcs:ignore
		return 'https://example.test/?p=' . (int) $id;
	}
	function wp_get_post_parent_id( $id ) { // phpcs:ignore
		return isset( $GLOBALS['rw_parent'][ $id ] ) ? (int) $GLOBALS['rw_parent'][ $id ] : 0;
	}
	function wc_get_product( $id ) { // phpcs:ignore
		return isset( $GLOBALS['rw_products'][ $id ] ) ? $GLOBALS['rw_products'][ $id ] : null;
	}
	function wc_get_page_id( $page ) { // phpcs:ignore
		return 'shop' === $page ? 99 : 0;
	}
	function get_the_terms( $id, $taxonomy ) { // phpcs:ignore
		if ( 'product_cat' !== $taxonomy || empty( $GLOBALS['rw_terms'][ $id ] ) ) {
			return false;
		}
		return $GLOBALS['rw_terms'][ $id ];
	}
	function get_terms( $args = array() ) { // phpcs:ignore
		unset( $args );
		return array( (object) array( 'term_id' => 7, 'taxonomy' => 'product_cat' ) );
	}
	function get_term_link( $term ) { // phpcs:ignore
		$id = is_object( $term ) && isset( $term->term_id ) ? (int) $term->term_id : 0;
		return 'https://example.test/product-category/' . $id . '/';
	}
	function get_posts( $args = array() ) { // phpcs:ignore
		$type = isset( $args['post_type'] ) ? $args['post_type'] : '';
		$flat = is_array( $type ) ? implode( ',', $type ) : (string) $type;
		if ( false === strpos( $flat, 'product' ) ) {
			return array();
		}
		return $GLOBALS['rw_catalog_ids'];
	}
	function wp_is_post_revision( $id ) { // phpcs:ignore
		return 8 === (int) $id;
	}
	function wp_is_post_autosave( $id ) { // phpcs:ignore
		return 9 === (int) $id;
	}
	function is_plugin_active( $file ) { // phpcs:ignore
		return ! empty( $GLOBALS['rw_active_files'][ $file ] );
	}
	function current_user_can( $cap ) { // phpcs:ignore
		return ! empty( $GLOBALS['rw_caps'][ $cap ] );
	}
	function wp_verify_nonce( $nonce, $action ) { // phpcs:ignore
		return isset( $GLOBALS['rw_nonces'][ $action ] ) && (string) $GLOBALS['rw_nonces'][ $action ] === (string) $nonce;
	}
	function wp_die( $message = '', $title = '', $args = array() ) { // phpcs:ignore
		unset( $title );
		$code = 500;
		if ( is_array( $args ) && isset( $args['response'] ) ) {
			$code = (int) $args['response'];
		}
		$GLOBALS['rw_die'] = array(
			'message' => $message,
			'code'    => $code,
		);
		throw new RuntimeException( 'wp_die' );
	}
	function rocket_clean_post( $id ) { // phpcs:ignore
		$GLOBALS['rw_calls']['rocket_clean_post'][] = (int) $id;
	}
	function rocket_clean_files( $urls ) { // phpcs:ignore
		$GLOBALS['rw_calls']['rocket_clean_files'][] = $urls;
	}
	function w3tc_flush_post( $id ) { // phpcs:ignore
		$GLOBALS['rw_calls']['w3tc_flush_post'][] = (int) $id;
	}
	function w3tc_flush_url( $url ) { // phpcs:ignore
		$GLOBALS['rw_calls']['w3tc_flush_url'][] = (string) $url;
	}
	function wpsc_delete_post_cache( $id ) { // phpcs:ignore
		$GLOBALS['rw_calls']['wpsc_delete_post_cache'][] = (int) $id;
	}

	/**
	 * Minimal product for parent and variation lookups.
	 */
	class RW_Purge_Product {
		/** @var int */
		public $id;
		/** @var int */
		public $parent;
		/** @var int[] */
		public $children;

		/**
		 * @param int   $id       Id.
		 * @param int   $parent   Parent id.
		 * @param int[] $children Child ids.
		 */
		public function __construct( $id, $parent, $children = array() ) {
			$this->id       = (int) $id;
			$this->parent   = (int) $parent;
			$this->children = $children;
		}
		public function get_parent_id() { // phpcs:ignore
			return $this->parent;
		}
		public function get_children() { // phpcs:ignore
			return $this->children;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
	require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-rules.php';
	require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-adapters.php';
	require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-purge.php';

	add_filter(
		'rwgcm_active_cache_plugins',
		static function () {
			return $GLOBALS['rw_plugin_flags'];
		}
	);

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

	/**
	 * @param int[] $ids Ids.
	 * @return int[]
	 */
	function sorted_ids( $ids ) {
		$ids = array_map( 'intval', (array) $ids );
		sort( $ids );
		return $ids;
	}

	$GLOBALS['rw_products'][10] = new RW_Purge_Product( 10, 0, array( 11, 12 ) );
	$GLOBALS['rw_products'][11] = new RW_Purge_Product( 11, 10, array() );
	$GLOBALS['rw_products'][12] = new RW_Purge_Product( 12, 10, array() );
	$GLOBALS['rw_parent'][11]   = 10;
	$GLOBALS['rw_parent'][12]   = 10;
	$shoes                      = (object) array( 'term_id' => 7, 'taxonomy' => 'product_cat' );
	$GLOBALS['rw_terms'][10]    = array( $shoes );
	$GLOBALS['rw_terms'][11]    = array( $shoes );
	$GLOBALS['rw_meta'][50]     = array(
		'_elementor_edit_mode' => 'builder',
		'_elementor_data'      => '{"widgetType":"rwgcm-product-price-atomic"}',
	);

	RWGCM_Cache_Purge::init();

	assert_true( 'price book meta hook accepts four arguments', 4 === $GLOBALS['rw_hook_args']['updated_post_meta'] && 4 === $GLOBALS['rw_hook_args']['added_post_meta'] && 4 === $GLOBALS['rw_hook_args']['deleted_post_meta'] );
	assert_true( 'option hook accepts the option name', 3 === $GLOBALS['rw_hook_args']['updated_option'] && 2 === $GLOBALS['rw_hook_args']['added_option'] && 1 === $GLOBALS['rw_hook_args']['deleted_option'] );
	assert_true( 'exchange-rate action is registered', ! empty( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) );
	assert_true( 'usage index updates on save_post', ! empty( $GLOBALS['rw_actions']['save_post'] ) );

	$family = RWGCM_Cache_Purge::related_product_ids( 11 );
	assert_true( 'a variation purge includes the parent and sibling variations', sorted_ids( $family ) === array( 10, 11, 12 ) );

	$GLOBALS['rw_options'][ RWGCM_Cache_Purge::INDEX_OPTION ] = array(
		50 => array(
			'sources'  => array( 'shortcode' ),
			'products' => array( 10 ),
		),
	);

	$result = RWGCM_Cache_Purge::on_post_meta( 1, 11, '_rwgcm_price_book', array( 'GBP' => array( 'regular' => '10' ) ) );
	unset( $result );
	$logged = RWGCM_Cache_Purge::last_purge();
	$context_posts = array();
	if ( ! empty( $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['post_ids'] ) ) {
		$context_posts = $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['post_ids'];
	}
	assert_true( 'price book save purges the family, shop, and price-element posts', sorted_ids( $context_posts ) === array( 10, 11, 12, 50, 99 ) );
	$urls = isset( $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['urls'] ) ? $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['urls'] : array();
	assert_true( 'price book save purges the category archive and the product URL', in_array( 'https://example.test/product-category/7/', $urls, true ) && in_array( 'https://example.test/?p=11', $urls, true ) && in_array( 'https://example.test/?p=99', $urls, true ) );
	assert_true( 'the purge log stores the time and counts', isset( $logged['time'], $logged['counts']['posts'], $logged['counts']['urls'] ) && (int) $logged['time'] > 0 && 5 === (int) $logged['counts']['posts'] && 'price_book' === $logged['reason'] );
	assert_true( 'rwgcm_cache_purged fired for the price book', isset( $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['reason'] ) && 'price_book' === $GLOBALS['rw_did']['rwgcm_cache_purged'][0][0]['reason'] );
	assert_true(
		'inactive cache plugins are not called just because the function exists',
		empty( $GLOBALS['rw_calls']['rocket_clean_post'] ) && empty( $GLOBALS['rw_calls']['w3tc_flush_post'] ) && empty( $GLOBALS['rw_calls']['wpsc_delete_post_cache'] ) && empty( $GLOBALS['rw_did']['litespeed_purge_post'] ) && 0 === (int) $logged['counts']['litespeed']
	);
	assert_true( 'Elementor CSS is left alone while Elementor is inactive', 0 === (int) $logged['counts']['elementor_css'] && array() === \Elementor\Core\Files\CSS\Post::$updated );

	$unrelated_time = (int) $logged['time'];
	RWGCM_Cache_Purge::on_updated_option( 'blogname', 'Old', 'New' );
	assert_true( 'an unrelated option does not purge', (int) RWGCM_Cache_Purge::last_purge()['time'] === $unrelated_time );

	$GLOBALS['rw_active_files']['wp-rocket/wp-rocket.php'] = true;
	$GLOBALS['rw_calls'] = array();
	RWGCM_Cache_Purge::purge_product( 11, 'price_book' );
	assert_true( 'WP Rocket is purged when the plugin is active', ! empty( $GLOBALS['rw_calls']['rocket_clean_post'] ) && in_array( 11, $GLOBALS['rw_calls']['rocket_clean_post'], true ) );
	unset( $GLOBALS['rw_active_files']['wp-rocket/wp-rocket.php'] );
	$GLOBALS['rw_calls'] = array();
	RWGCM_Cache_Purge::purge_product( 11, 'price_book' );
	assert_true( 'turning the plugin off stops the purge call', empty( $GLOBALS['rw_calls']['rocket_clean_post'] ) );

	$GLOBALS['rw_plugin_flags'] = array(
		'litespeed'  => true,
		'rocket'     => true,
		'w3tc'       => true,
		'supercache' => true,
	);
	$GLOBALS['rw_did']   = array();
	$GLOBALS['rw_calls'] = array();
	RWGCM_Cache_Purge::purge_product( 11, 'price_book' );
	$ls_posts = array();
	foreach ( (array) ( isset( $GLOBALS['rw_did']['litespeed_purge_post'] ) ? $GLOBALS['rw_did']['litespeed_purge_post'] : array() ) as $call ) {
		$ls_posts[] = (int) $call[0];
	}
	$ls_urls = array();
	foreach ( (array) ( isset( $GLOBALS['rw_did']['litespeed_purge_url'] ) ? $GLOBALS['rw_did']['litespeed_purge_url'] : array() ) as $call ) {
		$ls_urls[] = (string) $call[0];
	}
	assert_true( 'LiteSpeed purges each post id', sorted_ids( $ls_posts ) === array( 10, 11, 12, 50, 99 ) );
	assert_true( 'LiteSpeed purges the category URL', in_array( 'https://example.test/product-category/7/', $ls_urls, true ) );
	assert_true( 'WP Rocket, W3 Total Cache, and WP Super Cache purge the product post', in_array( 11, $GLOBALS['rw_calls']['rocket_clean_post'], true ) && in_array( 11, $GLOBALS['rw_calls']['w3tc_flush_post'], true ) && in_array( 11, $GLOBALS['rw_calls']['wpsc_delete_post_cache'], true ) );
	assert_true( 'archive URLs reach Rocket and W3 Total Cache', ! empty( $GLOBALS['rw_calls']['rocket_clean_files'] ) && in_array( 'https://example.test/product-category/7/', $GLOBALS['rw_calls']['w3tc_flush_url'], true ) );

	$GLOBALS['rw_plugin_flags']['cloudflare'] = true;
	add_action( 'cloudflare_purge_by_url', static function () {} );
	$GLOBALS['rw_did']['cloudflare_purge_by_url'] = array();
	RWGCM_Cache_Purge::purge_product( 11, 'price_book' );
	$cf_product = count( $GLOBALS['rw_did']['cloudflare_purge_by_url'] );
	assert_true( 'Cloudflare URL purge runs for a price book when the hook is registered', $cf_product > 0 && empty( $GLOBALS['rw_did']['cloudflare_purge_everything'] ) );
	$cf_before = $cf_product;
	add_action( 'cloudflare_purge_everything', static function () {} );
	$manual = RWGCM_Cache_Purge::purge_all( 'manual' );
	assert_true( 'a full purge uses Cloudflare purge-everything once', isset( $GLOBALS['rw_did']['cloudflare_purge_everything'] ) && 1 === count( $GLOBALS['rw_did']['cloudflare_purge_everything'] ) );
	assert_true( 'purge-everything does not also fire a URL purge for every archive', count( $GLOBALS['rw_did']['cloudflare_purge_by_url'] ) === $cf_before );
	assert_true( 'manual purge reason is manual and includes catalog products', 'manual' === $manual['reason'] && in_array( 10, $manual['post_ids'], true ) && in_array( 12, $manual['post_ids'], true ) );

	$rules = RWGCM_Cache_Purge::on_updated_option( 'rwgcm_pricing', array(), array( 'enabled' => true ) );
	unset( $rules );
	assert_true( 'saving rwgcm_pricing purges', 'pricing_rules' === RWGCM_Cache_Purge::last_purge()['reason'] );
	RWGCM_Cache_Purge::on_added_option( 'rwgcm_exchange_rates', array( 'ZAR' => 20 ) );
	assert_true( 'saving exchange rates purges', 'exchange_rates' === RWGCM_Cache_Purge::last_purge()['reason'] );
	RWGCM_Cache_Purge::on_exchange_rates_saved( array( 'EUR' => 1.1 ) );
	assert_true( 'rwgcm_exchange_rates_saved purges', 'exchange_rates' === RWGCM_Cache_Purge::last_purge()['reason'] );
	RWGCM_Cache_Purge::reset_exchange_purge_guard();
	$exchange_before = 0;
	foreach ( (array) ( isset( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) ? $GLOBALS['rw_did']['rwgcm_cache_purged'] : array() ) as $purged_call ) {
		if ( isset( $purged_call[0]['reason'] ) && 'exchange_rates' === $purged_call[0]['reason'] ) {
			++$exchange_before;
		}
	}
	RWGCM_Cache_Purge::on_updated_option( 'rwgcm_exchange_rates', array( 'ZAR' => '22.1' ), array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.2' ) ) );
	RWGCM_Cache_Purge::on_added_option( 'rwgcm_exchange_rates', array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.2' ) ) );
	RWGCM_Cache_Purge::on_deleted_option( 'rwgcm_exchange_rates' );
	RWGCM_Cache_Purge::on_exchange_rates_saved( array( 'ZAR' => '22.2' ) );
	$exchange_after = 0;
	foreach ( (array) ( isset( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) ? $GLOBALS['rw_did']['rwgcm_cache_purged'] : array() ) as $purged_call ) {
		if ( isset( $purged_call[0]['reason'] ) && 'exchange_rates' === $purged_call[0]['reason'] ) {
			++$exchange_after;
		}
	}
	assert_true( 'one save purges exchange rates once', 1 === ( $exchange_after - $exchange_before ) );
	RWGCM_Cache_Purge::reset_pricing_purge_guard();
	$pricing_before = isset( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) ? count( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) : 0;
	$product_rule   = array(
		'scope'   => array( 'type' => 'product', 'ids' => array( 11 ) ),
		'actions' => array( array( 'type' => 'price_adjustment', 'mode' => 'percent', 'value' => -15 ) ),
	);
	RWGCM_Cache_Purge::on_pricing_rule_changed( $product_rule );
	RWGCM_Cache_Purge::on_pricing_rule_changed( $product_rule );
	$pricing_events = array_slice( (array) $GLOBALS['rw_did']['rwgcm_cache_purged'], $pricing_before );
	assert_true( 'saving a product pricing rule purges once', 1 === count( $pricing_events ) );
	$scoped_posts = isset( $pricing_events[0][0]['post_ids'] ) ? $pricing_events[0][0]['post_ids'] : array();
	assert_true( 'a product-scoped rule purges that product and its parent', in_array( 11, $scoped_posts, true ) && in_array( 10, $scoped_posts, true ) );
	assert_true( 'the scoped purge reason is pricing_rules', isset( $pricing_events[0][0]['reason'] ) && 'pricing_rules' === $pricing_events[0][0]['reason'] );
	RWGCM_Cache_Purge::reset_pricing_purge_guard();
	$badge_before = count( $GLOBALS['rw_did']['rwgcm_cache_purged'] );
	RWGCM_Cache_Purge::on_pricing_rule_changed(
		array(
			'scope'   => array( 'type' => 'global', 'ids' => array() ),
			'actions' => array( array( 'type' => 'product_badge', 'text' => 'Hi' ) ),
		)
	);
	assert_true( 'a badge-only rule does not purge prices', count( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) === $badge_before );
	RWGCM_Cache_Purge::reset_pricing_purge_guard();
	$global_rule = array(
		'scope'   => array( 'type' => 'global', 'ids' => array() ),
		'actions' => array( array( 'type' => 'price_adjustment', 'mode' => 'percent', 'value' => -15 ) ),
	);
	RWGCM_Cache_Purge::on_pricing_rule_changed( $global_rule );
	$global_event = $GLOBALS['rw_did']['rwgcm_cache_purged'][ count( $GLOBALS['rw_did']['rwgcm_cache_purged'] ) - 1 ][0];
	$global_posts = isset( $global_event['post_ids'] ) && is_array( $global_event['post_ids'] ) ? $global_event['post_ids'] : array();
	assert_true( 'a global pricing rule purges the catalog', isset( $global_event['reason'] ) && 'pricing_rules' === $global_event['reason'] && in_array( 12, $global_posts, true ) && in_array( 10, $global_posts, true ) );
	RWGCM_Cache_Purge::on_deleted_option( 'rwgcm_pricing' );
	assert_true( 'deleting pricing rules purges', 'pricing_rules' === RWGCM_Cache_Purge::last_purge()['reason'] );

	if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
		define( 'ELEMENTOR_VERSION', '3.24.0' );
	}
	\Elementor\Core\Files\CSS\Post::$updated = array();
	$css_purge = RWGCM_Cache_Purge::purge_product( 11, 'price_book' );
	assert_true( 'Elementor CSS is regenerated for the price-element post', in_array( 50, \Elementor\Core\Files\CSS\Post::$updated, true ) && (int) $css_purge['counts']['elementor_css'] >= 1 );
	assert_true( 'Elementor CSS is not regenerated for a product without Elementor data', ! in_array( 11, \Elementor\Core\Files\CSS\Post::$updated, true ) );

	$detected = RWGCM_Cache_Purge::detect_usage(
		'[rwgcm_price product_id="10"] <!-- wp:rwgcm/product-price {"product_id":11} /-->',
		'{"widgetType":"rwgcm-product-price"} {"widgetType":"rwgcm-product-price-atomic"}'
	);
	assert_true(
		'usage detection sees the shortcode, block, and both Elementor widgets',
		in_array( 'shortcode', $detected['sources'], true ) && in_array( 'block', $detected['sources'], true ) && in_array( 'elementor', $detected['sources'], true ) && in_array( 'elementor_atomic', $detected['sources'], true ) && sorted_ids( $detected['products'] ) === array( 10, 11 )
	);
	assert_true( 'plain content is not indexed', array() === RWGCM_Cache_Purge::detect_usage( 'Hello', '' )['sources'] );

	$post = (object) array(
		'post_content' => '[rwgcm_price product_id="10"] <!-- wp:rwgcm/product-price {"product_id":11} /-->',
		'post_status'  => 'publish',
	);
	do_action( 'save_post', 50, $post );
	$index = RWGCM_Cache_Purge::get_index();
	assert_true( 'save_post records the price element on the post', isset( $index[50]['sources'] ) && in_array( 'shortcode', $index[50]['sources'], true ) && in_array( 'elementor_atomic', $index[50]['sources'], true ) );
	do_action( 'save_post', 8, $post );
	assert_true( 'revisions are not indexed', ! isset( RWGCM_Cache_Purge::get_index()[8] ) );
	do_action( 'save_post', 9, $post );
	assert_true( 'autosaves are not indexed', ! isset( RWGCM_Cache_Purge::get_index()[9] ) );
	$GLOBALS['rw_meta'][50]['_elementor_data'] = '';
	$post->post_content = 'No price here';
	do_action( 'save_post', 50, $post );
	assert_true( 'removing the price element drops the post from the index', ! isset( RWGCM_Cache_Purge::get_index()[50] ) );
	$GLOBALS['rw_options'][ RWGCM_Cache_Purge::INDEX_OPTION ] = array( 50 => array( 'sources' => array( 'block' ), 'products' => array() ) );
	RWGCM_Cache_Purge::on_delete_post( 50 );
	assert_true( 'deleting a post drops it from the index', ! isset( RWGCM_Cache_Purge::get_index()[50] ) );

	$GLOBALS['rw_caps'] = array();
	$_POST = array( '_wpnonce' => 'ok' );
	$GLOBALS['rw_nonces']['rwgcm_clear_geo_cache'] = 'ok';
	$denied = RWGCM_Cache_Purge::process_clear_cache_request();
	assert_true( 'Clear Geo cache requires manage_woocommerce', empty( $denied['ok'] ) && 'forbidden' === $denied['error'] );
	$GLOBALS['rw_caps']['manage_woocommerce'] = true;
	$_POST['_wpnonce'] = 'wrong';
	$bad_nonce = RWGCM_Cache_Purge::process_clear_cache_request();
	assert_true( 'Clear Geo cache requires the nonce', empty( $bad_nonce['ok'] ) && 'nonce' === $bad_nonce['error'] );
	$_POST['_wpnonce'] = 'ok';
	$cleared = RWGCM_Cache_Purge::process_clear_cache_request();
	assert_true( 'a valid Clear Geo cache request purges', ! empty( $cleared['ok'] ) && isset( $cleared['purge']['reason'] ) && 'manual' === $cleared['purge']['reason'] );

	$GLOBALS['rw_caps'] = array();
	$_POST['_wpnonce']  = 'ok';
	$died               = false;
	try {
		RWGCM_Cache_Purge::handle_clear_cache();
	} catch ( RuntimeException $e ) {
		$died = 'wp_die' === $e->getMessage();
	}
	assert_true( 'a refused Clear Geo cache request for capability is HTTP 403', $died && isset( $GLOBALS['rw_die']['code'] ) && 403 === (int) $GLOBALS['rw_die']['code'] );
	$GLOBALS['rw_caps']['manage_woocommerce'] = true;
	$_POST['_wpnonce']                        = 'wrong';
	$died                                     = false;
	try {
		RWGCM_Cache_Purge::handle_clear_cache();
	} catch ( RuntimeException $e ) {
		$died = 'wp_die' === $e->getMessage();
	}
	assert_true( 'a refused Clear Geo cache request for nonce is HTTP 403', $died && isset( $GLOBALS['rw_die']['code'] ) && 403 === (int) $GLOBALS['rw_die']['code'] );

	$loops = 0;
	add_action(
		'rwgcm_cache_purged',
		static function () use ( &$loops ) {
			++$loops;
			if ( $loops < 5 ) {
				RWGCM_Cache_Purge::purge_all( 'manual' );
			}
		}
	);
	RWGCM_Cache_Purge::purge_all( 'manual' );
	assert_true( 'a purge does not re-enter itself', 1 === $loops );

	$adapters = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-cache-adapters.php' );
	$purge    = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-cache-purge.php' );
	$boot     = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-plugin.php' );
	$settings = file_get_contents( dirname( __DIR__ ) . '/admin/views/settings-commerce.php' );
	$readme   = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
	assert_true(
		'adapters name every cache plugin and stay behind an active check',
		is_string( $adapters )
			&& false !== strpos( $adapters, 'LSCWP_V' )
			&& false !== strpos( $adapters, 'litespeed_purge_post' )
			&& false !== strpos( $adapters, 'litespeed_purge_url' )
			&& false !== strpos( $adapters, 'WP_ROCKET_VERSION' )
			&& false !== strpos( $adapters, 'rocket_clean_post' )
			&& false !== strpos( $adapters, 'w3tc_flush_post' )
			&& false !== strpos( $adapters, 'wpsc_delete_post_cache' )
			&& false !== strpos( $adapters, 'CLOUDFLARE_PLUGIN_DIR' )
			&& false !== strpos( $adapters, 'cloudflare_purge_by_url' )
			&& false !== strpos( $adapters, 'plugin_active' )
	);
	assert_true( 'the purge service fires rwgcm_cache_purged', is_string( $purge ) && false !== strpos( $purge, "do_action( 'rwgcm_cache_purged'" ) && false !== strpos( $purge, 'manage_woocommerce' ) );
	assert_true( 'the plugin boots the purge service', is_string( $boot ) && false !== strpos( $boot, 'RWGCM_Cache_Purge::init' ) );
	assert_true(
		'settings shows Clear Geo cache behind manage_woocommerce and a nonce',
		is_string( $settings ) && false !== strpos( $settings, 'Clear Geo cache' ) && false !== strpos( $settings, 'rwgcm_clear_geo_cache' ) && false !== strpos( $settings, 'manage_woocommerce' )
	);
	assert_true(
		'the readme documents purge limits',
		is_string( $readme ) && false !== strpos( $readme, 'wpsc_delete_post_cache' ) && false !== strpos( $readme, 'rwgcm_cache_purged' ) && false !== strpos( $readme, 'rwgcm_exchange_rates_saved' )
	);

	echo "\n{$passed} passed, {$failed} failed\n";
	exit( $failed > 0 ? 1 : 0 );
}
