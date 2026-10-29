<?php
/**
 * Purge cached pages when geo prices change.
 *
 * Fires for the price book meta (_rwgcm_price_book), the rwgcm_pricing option,
 * exchange-rate options (rwgcm_exchange_rates, filter rwgcm_exchange_rate_options),
 * and the rwgcm_exchange_rates_saved action.
 *
 * Targets are the product with its parent and variations, the shop page, product
 * category archives, and posts recorded in the price-element usage index.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache purge service.
 */
class RWGCM_Cache_Purge {

	const INDEX_OPTION = 'rwgcm_price_usage_index';

	const LOG_OPTION = 'rwgcm_last_cache_purge';

	const EXCHANGE_OPTION = 'rwgcm_exchange_rates';

	/**
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Exchange-rate saves update several options and fire an action. One request purges once.
	 *
	 * @var bool
	 */
	private static $exchange_purged = false;

	/**
	 * While set, an exchange-rate option write does not purge. Used when a refresh stores the same rates.
	 *
	 * @var int
	 */
	private static $silence_exchange = 0;

	/**
	 * Pricing-rule saves purge once per request.
	 *
	 * @var bool
	 */
	private static $pricing_purged = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'added_post_meta', array( __CLASS__, 'on_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_post_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_post_meta' ), 10, 4 );
		add_action( 'updated_option', array( __CLASS__, 'on_updated_option' ), 10, 3 );
		add_action( 'added_option', array( __CLASS__, 'on_added_option' ), 10, 2 );
		add_action( 'deleted_option', array( __CLASS__, 'on_deleted_option' ), 10, 1 );
		add_action( 'rwgcm_exchange_rates_saved', array( __CLASS__, 'on_exchange_rates_saved' ), 10, 1 );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ), 10, 1 );
		add_action( 'admin_post_rwgcm_clear_geo_cache', array( __CLASS__, 'handle_clear_cache' ) );
	}

	/**
	 * @param mixed  $meta_id    Meta id or list of ids.
	 * @param int    $object_id  Post id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$object_id = (int) $object_id;
		$key       = (string) $meta_key;
		if ( '_elementor_data' === $key || '_elementor_edit_mode' === $key ) {
			self::reindex_post( $object_id );
			return;
		}
		if ( self::is_price_book_key( $key ) ) {
			self::purge_product( $object_id, 'price_book' );
		}
	}

	/**
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function on_updated_option( $option, $old_value, $value ) {
		unset( $old_value, $value );
		self::maybe_purge_option( (string) $option );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public static function on_added_option( $option, $value ) {
		unset( $value );
		self::maybe_purge_option( (string) $option );
	}

	/**
	 * @param string $option Option name.
	 * @return void
	 */
	public static function on_deleted_option( $option ) {
		self::maybe_purge_option( (string) $option );
	}

	/**
	 * @param mixed $payload Optional rate payload from the saver. Unused.
	 * @return array<string, mixed>
	 */
	public static function on_exchange_rates_saved( $payload = null ) {
		unset( $payload );
		return self::purge_exchange_once();
	}

	/**
	 * Ignore exchange-rate option writes until the matching call with false.
	 * A cron refresh uses this when the stored rates did not change.
	 *
	 * @param bool $on True to silence, false to restore one level.
	 * @return void
	 */
	public static function silence_exchange_purge( $on ) {
		if ( $on ) {
			++self::$silence_exchange;
			return;
		}
		if ( self::$silence_exchange > 0 ) {
			--self::$silence_exchange;
		}
	}

	/**
	 * Clear the once-per-request exchange purge guard. Tests use this between cases.
	 *
	 * @return void
	 */
	public static function reset_exchange_purge_guard() {
		self::$exchange_purged  = false;
		self::$silence_exchange = 0;
	}

	/**
	 * Clear the once-per-request pricing-rule purge guard. Tests use this between cases.
	 *
	 * @return void
	 */
	public static function reset_pricing_purge_guard() {
		self::$pricing_purged = false;
	}

	/**
	 * A generic pricing rule was saved, enabled, disabled, or deleted.
	 * One request purges once. A product scope purges those products. Anything
	 * wider, or a scope we cannot resolve, purges the catalog.
	 *
	 * @param array<string, mixed>|null $rule     New rule, or null on delete.
	 * @param array<string, mixed>|null $previous Previous rule.
	 * @return array<string, mixed>
	 */
	public static function on_pricing_rule_changed( $rule, $previous = null ) {
		if ( ! self::rule_adjusts_price( $rule ) && ! self::rule_adjusts_price( $previous ) ) {
			return array();
		}
		return self::purge_pricing_once( self::pricing_rule_product_ids( $rule, $previous ) );
	}

	/**
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    Post object.
	 * @return void
	 */
	public static function on_save_post( $post_id, $post = null ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( is_object( $post ) && isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
			return;
		}
		self::reindex_post( $post_id, $post );
	}

	/**
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_delete_post( $post_id ) {
		$index = self::get_index();
		unset( $index[ (int) $post_id ], $index[ (string) $post_id ] );
		self::save_index( $index );
	}

	/**
	 * Admin "Clear Geo cache" handler. Requires manage_woocommerce and a nonce.
	 *
	 * @return void
	 */
	public static function handle_clear_cache() {
		$result = self::process_clear_cache_request();
		if ( empty( $result['ok'] ) ) {
			$message = ( isset( $result['error'] ) && 'forbidden' === $result['error'] )
				? __( 'You need permission to manage WooCommerce to clear the geo cache.', 'reactwoo-geo-commerce' )
				: __( 'The link you followed has expired.', 'reactwoo-geo-commerce' );
			wp_die( esc_html( $message ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-settings&rwgcm_cache_cleared=1' ) );
		exit;
	}

	/**
	 * @return array{ok: bool, error?: string, purge?: array<string, mixed>}
	 */
	public static function process_clear_cache_request() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return array(
				'ok'    => false,
				'error' => 'forbidden',
			);
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';
		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'rwgcm_clear_geo_cache' ) ) {
			return array(
				'ok'    => false,
				'error' => 'nonce',
			);
		}
		return array(
			'ok'    => true,
			'purge' => self::purge_all( 'manual' ),
		);
	}

	/**
	 * Purge one product family plus shop, its categories, and price-element posts.
	 *
	 * @param int    $product_id Product or variation id.
	 * @param string $reason     price_book or another caller reason.
	 * @return array<string, mixed>
	 */
	public static function purge_product( $product_id, $reason = 'price_book' ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return array();
		}
		$family = self::related_product_ids( $product_id );
		$posts  = $family;
		$shop   = self::shop_page_id();
		if ( $shop > 0 ) {
			$posts[] = $shop;
		}
		$posts = array_merge( $posts, self::usage_post_ids() );
		$urls  = array_merge( self::archive_urls( $family ), self::permalinks( $posts ) );
		return self::execute( $posts, $urls, (string) $reason, false );
	}

	/**
	 * Purge every catalog product, the shop, all category archives, and price-element posts.
	 *
	 * @param string $reason manual, pricing_rules, or exchange_rates.
	 * @return array<string, mixed>
	 */
	public static function purge_all( $reason = 'manual' ) {
		$posts = self::catalog_product_ids();
		$shop  = self::shop_page_id();
		if ( $shop > 0 ) {
			$posts[] = $shop;
		}
		$posts = array_merge( $posts, self::usage_post_ids() );
		$urls  = array_merge( self::archive_urls( array() ), self::permalinks( $posts ) );
		return self::execute( $posts, $urls, (string) $reason, true );
	}

	/**
	 * Product id, its parent, and its variations.
	 *
	 * @param int $product_id Product or variation id.
	 * @return int[]
	 */
	public static function related_product_ids( $product_id ) {
		$ids    = array( (int) $product_id );
		$parent = function_exists( 'wp_get_post_parent_id' ) ? (int) wp_get_post_parent_id( $product_id ) : 0;
		if ( $parent > 0 ) {
			$ids[] = $parent;
		}
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( is_object( $product ) && method_exists( $product, 'get_parent_id' ) ) {
				$from_product = (int) $product->get_parent_id();
				if ( $from_product > 0 ) {
					$parent = $from_product;
					$ids[]  = $from_product;
				}
			}
			if ( is_object( $product ) && $parent <= 0 && method_exists( $product, 'get_children' ) ) {
				foreach ( (array) $product->get_children() as $child ) {
					$ids[] = (int) $child;
				}
			}
			if ( $parent > 0 ) {
				$parent_product = wc_get_product( $parent );
				if ( is_object( $parent_product ) && method_exists( $parent_product, 'get_children' ) ) {
					foreach ( (array) $parent_product->get_children() as $child ) {
						$ids[] = (int) $child;
					}
				}
			}
		}
		return self::id_list( $ids );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_index() {
		$index = get_option( self::INDEX_OPTION, array() );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function last_purge() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Option names that store exchange rates.
	 *
	 * @return string[]
	 */
	public static function exchange_rate_options() {
		$keys     = array( self::EXCHANGE_OPTION );
		$filtered = apply_filters( 'rwgcm_exchange_rate_options', $keys );
		if ( ! is_array( $filtered ) ) {
			return $keys;
		}
		$out = array();
		foreach ( $filtered as $key ) {
			$key = sanitize_key( (string) $key );
			if ( '' !== $key ) {
				$out[] = $key;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Detect [rwgcm_price], the rwgcm/product-price block, and the Elementor price widgets.
	 *
	 * @param string $content   Post content.
	 * @param string $elementor Elementor JSON, when the post has any.
	 * @return array{sources: string[], products: int[]}
	 */
	public static function detect_usage( $content, $elementor = '' ) {
		$blob     = (string) $content . "\n" . (string) $elementor;
		$sources  = array();
		$products = array();
		if ( false !== strpos( $blob, '[rwgcm_price' ) ) {
			$sources[] = 'shortcode';
		}
		if ( false !== strpos( $blob, 'wp:rwgcm/product-price' ) ) {
			$sources[] = 'block';
		}
		if ( false !== strpos( $blob, 'rwgcm-product-price-atomic' ) ) {
			$sources[] = 'elementor_atomic';
		}
		if ( preg_match( '/rwgcm-product-price(?!-atomic)/', $blob ) ) {
			$sources[] = 'elementor';
		}
		if ( preg_match_all( '/(?:product_id|variation_id)"?\s*[=:]\s*["\']?(\d+)/', $blob, $found ) ) {
			foreach ( $found[1] as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$products[] = $id;
				}
			}
		}
		return array(
			'sources'  => array_values( array_unique( $sources ) ),
			'products' => self::id_list( $products ),
		);
	}

	/**
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    Post, when already loaded.
	 * @return void
	 */
	public static function reindex_post( $post_id, $post = null ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		$content = '';
		if ( is_object( $post ) && isset( $post->post_content ) ) {
			$content = (string) $post->post_content;
		} elseif ( function_exists( 'get_post' ) ) {
			$loaded = get_post( $post_id );
			if ( is_object( $loaded ) && isset( $loaded->post_content ) ) {
				$content = (string) $loaded->post_content;
			}
		}
		$elementor = '';
		if ( function_exists( 'get_post_meta' ) ) {
			$raw = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_string( $raw ) ) {
				$elementor = $raw;
			} elseif ( is_array( $raw ) ) {
				$encoded   = function_exists( 'wp_json_encode' ) ? wp_json_encode( $raw ) : json_encode( $raw );
				$elementor = is_string( $encoded ) ? $encoded : '';
			}
		}
		$usage = self::detect_usage( $content, $elementor );
		$index = self::get_index();
		if ( empty( $usage['sources'] ) ) {
			unset( $index[ $post_id ], $index[ (string) $post_id ] );
		} else {
			$index[ $post_id ] = $usage;
		}
		self::save_index( $index );
	}

	/**
	 * Regenerate Elementor CSS for one post built with Elementor.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function regenerate_elementor_css( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! self::elementor_active() || ! self::post_built_with_elementor( $post_id ) ) {
			return false;
		}
		if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return false;
		}
		try {
			$css = new \Elementor\Core\Files\CSS\Post( $post_id );
			if ( ! is_object( $css ) || ! method_exists( $css, 'update' ) ) {
				return false;
			}
			$css->update();
			return true;
		} catch ( Exception $e ) {
			unset( $e );
			return false;
		}
	}

	/**
	 * @return bool
	 */
	public static function elementor_active() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin', false );
	}

	/**
	 * @param string $option Option name.
	 * @return void
	 */
	private static function maybe_purge_option( $option ) {
		if ( self::is_ignored_option( $option ) ) {
			return;
		}
		if ( self::is_pricing_option( $option ) ) {
			self::purge_pricing_once( null );
			return;
		}
		if ( in_array( $option, self::exchange_rate_options(), true ) ) {
			if ( self::$silence_exchange > 0 ) {
				return;
			}
			self::purge_exchange_once();
		}
	}

	/**
	 * @param int[]|null $product_ids Null purges the whole catalog.
	 * @return array<string, mixed>
	 */
	private static function purge_pricing_once( $product_ids ) {
		if ( self::$pricing_purged ) {
			return self::last_purge();
		}
		if ( null === $product_ids ) {
			$result = self::purge_all( 'pricing_rules' );
		} else {
			$result = self::purge_product_ids( $product_ids, 'pricing_rules' );
		}
		if ( empty( $result['skipped'] ) ) {
			self::$pricing_purged = true;
		}
		return $result;
	}

	/**
	 * @param int[]  $product_ids Product ids.
	 * @param string $reason      Reason.
	 * @return array<string, mixed>
	 */
	public static function purge_product_ids( array $product_ids, $reason ) {
		$posts = array();
		foreach ( $product_ids as $product_id ) {
			$posts = array_merge( $posts, self::related_product_ids( $product_id ) );
		}
		$posts = self::id_list( $posts );
		if ( empty( $posts ) ) {
			return self::purge_all( $reason );
		}
		$shop = self::shop_page_id();
		if ( $shop > 0 ) {
			$posts[] = $shop;
		}
		$posts = array_merge( $posts, self::usage_post_ids() );
		$urls  = array_merge( self::archive_urls( $posts ), self::permalinks( $posts ) );
		return self::execute( $posts, $urls, (string) $reason, false );
	}

	/**
	 * @param array<string, mixed>|null $rule     Rule.
	 * @param array<string, mixed>|null $previous Previous rule.
	 * @return int[]|null Null means the whole catalog.
	 */
	private static function pricing_rule_product_ids( $rule, $previous ) {
		$ids = self::scope_product_ids( $rule );
		if ( null === $ids ) {
			return null;
		}
		$old = self::scope_product_ids( $previous );
		if ( null === $old ) {
			return null;
		}
		return self::id_list( array_merge( $ids, $old ) );
	}

	/**
	 * @param array<string, mixed>|null $rule Rule.
	 * @return int[]|null
	 */
	private static function scope_product_ids( $rule ) {
		if ( ! is_array( $rule ) ) {
			return array();
		}
		$scope = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
		$type  = isset( $scope['type'] ) ? (string) $scope['type'] : 'global';
		$ids   = isset( $scope['ids'] ) && is_array( $scope['ids'] ) ? $scope['ids'] : array();
		if ( 'product' === $type && ! empty( $ids ) ) {
			return array_map( 'intval', $ids );
		}
		if ( 'product_category' === $type && ! empty( $ids ) ) {
			return self::products_in_categories( $ids );
		}
		return null;
	}

	/**
	 * @param array<int, mixed> $term_ids Category ids.
	 * @return int[]|null Null when the product list cannot be resolved.
	 */
	private static function products_in_categories( array $term_ids ) {
		if ( ! function_exists( 'get_posts' ) ) {
			return null;
		}
		$terms = array();
		foreach ( $term_ids as $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id > 0 ) {
				$terms[] = $term_id;
			}
		}
		if ( empty( $terms ) ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'nopaging'               => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
			)
		);
		return is_array( $posts ) ? self::id_list( $posts ) : null;
	}

	/**
	 * @param array<string, mixed>|null $rule Rule.
	 * @return bool
	 */
	private static function rule_adjusts_price( $rule ) {
		if ( ! is_array( $rule ) || empty( $rule['actions'] ) || ! is_array( $rule['actions'] ) ) {
			return false;
		}
		foreach ( $rule['actions'] as $action ) {
			if ( is_array( $action ) && isset( $action['type'] ) && 'price_adjustment' === $action['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function purge_exchange_once() {
		if ( self::$exchange_purged ) {
			return self::last_purge();
		}
		$result = self::purge_all( 'exchange_rates' );
		if ( empty( $result['skipped'] ) ) {
			self::$exchange_purged = true;
		}
		return $result;
	}

	/**
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function is_ignored_option( $option ) {
		$ignored = array( self::INDEX_OPTION, self::LOG_OPTION, 'rwgcm_cache_safe_price_mode' );
		return in_array( $option, $ignored, true );
	}

	/**
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function is_pricing_option( $option ) {
		if ( class_exists( 'RWGCM_Pricing_Rules', false ) && RWGCM_Pricing_Rules::OPTION_KEY === $option ) {
			return true;
		}
		return 'rwgcm_pricing' === $option;
	}

	/**
	 * @param string $key Meta key.
	 * @return bool
	 */
	private static function is_price_book_key( $key ) {
		if ( class_exists( 'RWGCM_Price_Book', false ) ) {
			return RWGCM_Price_Book::META_KEY === $key;
		}
		return '_rwgcm_price_book' === $key;
	}

	/**
	 * @param int[]  $posts            Post ids.
	 * @param string[] $urls           URLs.
	 * @param string $reason           Reason.
	 * @param bool   $allow_everything When true, a Cloudflare purge-everything hook replaces per-URL calls.
	 * @return array<string, mixed>
	 */
	private static function execute( $posts, $urls, $reason, $allow_everything ) {
		if ( self::$busy ) {
			return array(
				'skipped' => true,
				'reason'  => $reason,
			);
		}
		self::$busy = true;
		try {
			$post_result = class_exists( 'RWGCM_Cache_Adapters', false )
				? RWGCM_Cache_Adapters::purge_posts( $posts )
				: array( array(), self::id_list( $posts ) );
			$url_result  = class_exists( 'RWGCM_Cache_Adapters', false )
				? RWGCM_Cache_Adapters::purge_urls( $urls )
				: array( array(), array() );
			$post_ids    = isset( $post_result[1] ) ? $post_result[1] : array();
			$urls        = isset( $url_result[1] ) ? $url_result[1] : array();
			$counts      = self::merge_counts(
				isset( $post_result[0] ) ? $post_result[0] : array(),
				isset( $url_result[0] ) ? $url_result[0] : array()
			);
			$counts['posts']         = count( $post_ids );
			$counts['urls']          = count( $urls );
			$counts['elementor_css'] = 0;
			foreach ( $post_ids as $post_id ) {
				if ( self::regenerate_elementor_css( $post_id ) ) {
					$counts['elementor_css']++;
				}
			}
			if ( class_exists( 'RWGCM_Cache_Adapters', false ) ) {
				$used_everything = false;
				if ( $allow_everything && RWGCM_Cache_Adapters::purge_cloudflare_everything() ) {
					$counts['cloudflare']++;
					$used_everything = true;
				}
				if ( ! $used_everything ) {
					foreach ( $urls as $url ) {
						if ( RWGCM_Cache_Adapters::purge_cloudflare_url( $url ) ) {
							$counts['cloudflare']++;
						}
					}
				}
			}
			$time    = time();
			$context = array(
				'reason'   => $reason,
				'time'     => $time,
				'time_gmt' => gmdate( 'c', $time ),
				'post_ids' => $post_ids,
				'urls'     => $urls,
				'counts'   => $counts,
			);
			update_option(
				self::LOG_OPTION,
				array(
					'reason'   => $reason,
					'time'     => $time,
					'time_gmt' => $context['time_gmt'],
					'counts'   => $counts,
				),
				false
			);
			do_action( 'rwgcm_cache_purged', $context );
			return $context;
		} finally {
			self::$busy = false;
		}
	}

	/**
	 * @param array<string, int> $left  Counts.
	 * @param array<string, int> $right Counts.
	 * @return array<string, int>
	 */
	private static function merge_counts( $left, $right ) {
		$base = class_exists( 'RWGCM_Cache_Adapters', false ) ? RWGCM_Cache_Adapters::blank_counts() : array();
		foreach ( array( $left, $right ) as $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			foreach ( $part as $key => $value ) {
				$base[ $key ] = ( isset( $base[ $key ] ) ? (int) $base[ $key ] : 0 ) + (int) $value;
			}
		}
		return $base;
	}

	/**
	 * @param int[] $ids Ids.
	 * @return int[]
	 */
	private static function id_list( $ids ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	/**
	 * @return int[]
	 */
	private static function usage_post_ids() {
		$ids = array();
		foreach ( self::get_index() as $post_id => $row ) {
			unset( $row );
			$ids[] = (int) $post_id;
		}
		return self::id_list( $ids );
	}

	/**
	 * @return int
	 */
	private static function shop_page_id() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}
		$id = (int) wc_get_page_id( 'shop' );
		return $id > 0 ? $id : 0;
	}

	/**
	 * @return string
	 */
	private static function shop_url() {
		$id = self::shop_page_id();
		if ( $id <= 0 ) {
			return '';
		}
		return self::permalink( $id );
	}

	/**
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function permalink( $post_id ) {
		if ( ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( $post_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param int[] $post_ids Post ids.
	 * @return string[]
	 */
	private static function permalinks( $post_ids ) {
		$urls = array();
		foreach ( self::id_list( $post_ids ) as $post_id ) {
			$url = self::permalink( $post_id );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Category archive URLs. An empty id list means every product category.
	 * The shop URL is always included when the shop page exists.
	 *
	 * @param int[] $product_ids Product ids, or empty for every category.
	 * @return string[]
	 */
	private static function archive_urls( $product_ids ) {
		$urls = array();
		$shop = self::shop_url();
		if ( '' !== $shop ) {
			$urls[] = $shop;
		}
		$terms = array();
		$ids   = self::id_list( $product_ids );
		if ( empty( $ids ) ) {
			$terms = self::terms_for( 0 );
		} else {
			foreach ( $ids as $id ) {
				$terms = array_merge( $terms, self::terms_for( $id ) );
			}
		}
		foreach ( $terms as $term ) {
			$url = self::term_url( $term );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param int $product_id Product id, or 0 for every product_cat term.
	 * @return array<int, object|array<string, mixed>>
	 */
	private static function terms_for( $product_id ) {
		$product_id = (int) $product_id;
		$terms      = array();
		if ( $product_id > 0 && function_exists( 'get_the_terms' ) ) {
			$terms = get_the_terms( $product_id, 'product_cat' );
		} elseif ( $product_id <= 0 && function_exists( 'get_terms' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			return array();
		}
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * @param object|array<string, mixed> $term Term.
	 * @return string
	 */
	private static function term_url( $term ) {
		if ( ! function_exists( 'get_term_link' ) ) {
			return '';
		}
		$link = get_term_link( $term );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $link ) ) {
			return '';
		}
		return is_string( $link ) ? $link : '';
	}

	/**
	 * @return int[]
	 */
	private static function catalog_product_ids() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'              => array( 'product', 'product_variation' ),
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'nopaging'               => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return is_array( $ids ) ? self::id_list( $ids ) : array();
	}

	/**
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function post_built_with_elementor( $post_id ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		if ( 'builder' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return true;
		}
		$data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $data ) ) {
			return '' !== $data;
		}
		return is_array( $data ) && ! empty( $data );
	}

	/**
	 * @param array<int|string, mixed> $index Index.
	 * @return void
	 */
	private static function save_index( $index ) {
		update_option( self::INDEX_OPTION, $index, false );
	}
}
