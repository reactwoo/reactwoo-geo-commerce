<?php
/**
 * Reorder shop/category product loops by weather facet overlap.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boost or filter native WooCommerce catalog queries using visitor weather.
 */
class RWGCM_Catalog_Weather_Boost {

	/**
	 * @var string[]|null
	 */
	private static $visitor_facets = null;

	/**
	 * @var array<string, mixed>|null
	 */
	private static $snapshot = null;

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_action( 'woocommerce_product_query', array( __CLASS__, 'on_product_query' ), 25, 2 );
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'on_query_loop_block' ), 25, 3 );
	}

	/**
	 * WooCommerce Product Collection / query-loop blocks.
	 *
	 * @param array<string, mixed> $query Query vars.
	 * @param WP_Block             $block Block instance.
	 * @param int                  $page  Page number.
	 * @return array<string, mixed>
	 */
	public static function on_query_loop_block( $query, $block, $page ) {
		unset( $page );
		if ( ! is_array( $query ) || ! $block instanceof WP_Block ) {
			return $query;
		}
		$name = isset( $block->name ) ? (string) $block->name : '';
		if ( 'woocommerce/product-collection' !== $name ) {
			return $query;
		}
		if ( ! RWGCM_Weather_Product_Query::weather_available() ) {
			return $query;
		}
		if ( 'off' === self::get_mode_for_surface( 'collection' ) ) {
			return $query;
		}
		if ( ! apply_filters( 'rwgcm_weather_catalog_boost_enabled', true, null, 'collection' ) ) {
			return $query;
		}
		$query['rwgcm_weather_boost']      = true;
		$query['rwgcm_weather_boost_mode'] = self::get_mode_for_surface( 'collection' );
		return $query;
	}

	/**
	 * @param WP_Query $q        Query.
	 * @param mixed    $wc_query WC_Query instance.
	 * @return void
	 */
	public static function on_product_query( $q, $wc_query ) {
		unset( $wc_query );
		if ( ! $q instanceof WP_Query ) {
			return;
		}
		$surface = self::resolve_query_surface( $q );
		if ( null === $surface ) {
			return;
		}
		if ( ! self::is_enabled_for_query( $q ) ) {
			return;
		}
		$mode = self::get_mode_for_surface( $surface );
		if ( 'off' === $mode ) {
			return;
		}
		$q->set( 'rwgcm_weather_boost', true );
		$q->set( 'rwgcm_weather_boost_mode', $mode );
		add_filter( 'posts_results', array( __CLASS__, 'reorder_posts' ), 25, 2 );
	}

	/**
	 * @param WP_Query $q Query.
	 * @return bool
	 */
	private static function is_enabled_for_query( $q ) {
		if ( ! $q instanceof WP_Query || $q->get( 'rwgcm_weather_boost' ) ) {
			return false;
		}
		$surface = self::resolve_query_surface( $q );
		if ( null === $surface ) {
			return false;
		}
		if ( ! apply_filters( 'rwgcm_weather_catalog_boost_enabled', true, $q, $surface ) ) {
			return false;
		}
		if ( ! RWGCM_Weather_Product_Query::weather_available() ) {
			return false;
		}
		return 'off' !== self::get_mode_for_surface( $surface );
	}

	/**
	 * @return string|null shop|category|collection
	 */
	private static function resolve_catalog_surface() {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return 'category';
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return 'shop';
		}
		return null;
	}

	/**
	 * @param WP_Query $q Query.
	 * @return string|null
	 */
	private static function resolve_query_surface( $q ) {
		$surface = self::resolve_catalog_surface();
		if ( null !== $surface ) {
			return $surface;
		}
		if ( ! $q instanceof WP_Query ) {
			return null;
		}
		$query_id = sanitize_key( (string) $q->get( 'query_id', '' ) );
		if ( '' !== $query_id && ( false !== strpos( $query_id, 'product-collection' ) || false !== strpos( $query_id, 'product-query' ) ) ) {
			return 'collection';
		}
		return null;
	}

	/**
	 * @param string $surface shop|category|collection
	 * @return string
	 */
	private static function get_mode_for_surface( $surface ) {
		if ( class_exists( 'RWGCM_Settings', false ) ) {
			return RWGCM_Settings::get_weather_catalog_boost_mode( $surface );
		}
		return 'boost';
	}

	/**
	 * @return string[]
	 */
	private static function visitor_facets() {
		if ( null === self::$visitor_facets ) {
			self::$visitor_facets = RWGCM_Weather_Affinity::get_visitor_facets();
		}
		return self::$visitor_facets;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function snapshot() {
		if ( null === self::$snapshot && function_exists( 'rwgc_get_context_snapshot' ) ) {
			$ctx = rwgc_get_context_snapshot();
			self::$snapshot = is_array( $ctx ) ? $ctx : array();
		}
		return is_array( self::$snapshot ) ? self::$snapshot : null;
	}

	/**
	 * @param WP_Post[] $posts Posts for current page.
	 * @param WP_Query  $query Query.
	 * @return WP_Post[]
	 */
	public static function reorder_posts( $posts, $query ) {
		if ( ! $query instanceof WP_Query || ! $query->get( 'rwgcm_weather_boost' ) ) {
			return $posts;
		}

		remove_filter( 'posts_results', array( __CLASS__, 'reorder_posts' ), 25 );

		$mode = sanitize_key( (string) $query->get( 'rwgcm_weather_boost_mode' ) );
		if ( ! in_array( $mode, array( 'boost', 'filter' ), true ) ) {
			return $posts;
		}

		if ( empty( self::visitor_facets() ) || ! is_array( $posts ) || empty( $posts ) ) {
			return $posts;
		}

		$post_ids = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && 'product' === $post->post_type ) {
				$post_ids[] = (int) $post->ID;
			}
		}
		if ( ! empty( $post_ids ) ) {
			RWGCM_Weather_Affinity::batch_overlap_scores( $post_ids, self::snapshot() );
		}

		$matched   = array();
		$unmatched = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
				$unmatched[] = $post;
				continue;
			}
			$score = RWGCM_Weather_Affinity::overlap_score( (int) $post->ID, self::snapshot() );
			if ( $score > 0 ) {
				$matched[] = array(
					'post'  => $post,
					'score' => $score,
				);
			} elseif ( 'boost' === $mode ) {
				$unmatched[] = $post;
			}
		}

		if ( empty( $matched ) ) {
			return 'filter' === $mode ? array() : $posts;
		}

		usort(
			$matched,
			static function ( $a, $b ) {
				$sa = isset( $a['score'] ) ? (int) $a['score'] : 0;
				$sb = isset( $b['score'] ) ? (int) $b['score'] : 0;
				if ( $sa === $sb ) {
					return 0;
				}
				return $sb <=> $sa;
			}
		);

		$ordered = array();
		foreach ( $matched as $row ) {
			$ordered[] = $row['post'];
		}
		if ( 'boost' === $mode ) {
			$ordered = array_merge( $ordered, $unmatched );
		}

		/**
		 * Catalog posts after weather boost/filter ordering.
		 *
		 * @param WP_Post[] $ordered Reordered posts.
		 * @param WP_Post[] $posts   Original posts.
		 * @param string    $mode    boost|filter.
		 * @param WP_Query  $query   Query.
		 */
		return apply_filters( 'rwgcm_weather_catalog_boost_posts', $ordered, $posts, $mode, $query );
	}
}
