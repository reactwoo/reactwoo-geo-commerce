<?php
/**
 * Product ↔ visitor shopping-weather facet matching.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads product facet meta and compares to visitor snapshot weather.
 */
class RWGCM_Weather_Affinity {

	const META_KEY = '_rwgcm_weather_facets';

	const TERM_META_KEY = 'rwgcm_weather_facets';

	/**
	 * @var array<string, int>
	 */
	private static $overlap_score_cache = array();

	/**
	 * @var string[]|null
	 */
	private static $visitor_facets_cache = null;

	/**
	 * @var string|null
	 */
	private static $visitor_facets_cache_key = null;

	/**
	 * Facet slug => label for UIs when GeoCore Pro is unavailable.
	 *
	 * @return array<string, string>
	 */
	public static function get_default_facet_labels() {
		return array(
			'wet'   => __( 'Wet / raining', 'reactwoo-geo-commerce' ),
			'dry'   => __( 'Dry', 'reactwoo-geo-commerce' ),
			'hot'   => __( 'Hot', 'reactwoo-geo-commerce' ),
			'cold'  => __( 'Cold', 'reactwoo-geo-commerce' ),
			'mild'  => __( 'Mild / comfortable', 'reactwoo-geo-commerce' ),
			'windy' => __( 'Windy', 'reactwoo-geo-commerce' ),
			'sunny' => __( 'Sunny / bright', 'reactwoo-geo-commerce' ),
			'high_uv' => __( 'High UV / sun protection', 'reactwoo-geo-commerce' ),
			'poor_air' => __( 'Poor air quality', 'reactwoo-geo-commerce' ),
			'high_pollen' => __( 'High pollen / allergies', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * @return array<int, array{slug: string, label: string}>
	 */
	public static function get_facet_definitions() {
		if ( class_exists( 'RWGCP_Weather_Facets', false ) ) {
			return RWGCP_Weather_Facets::get_definitions();
		}
		$out = array();
		foreach ( self::get_default_facet_labels() as $slug => $label ) {
			$out[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * @param mixed $raw Raw meta or POST.
	 * @return string[]
	 */
	public static function sanitize_facet_list( $raw ) {
		if ( class_exists( 'RWGCP_Weather_Facets', false ) ) {
			return RWGCP_Weather_Facets::sanitize_facet_list( $raw );
		}
		$allowed = array_flip( array_keys( self::get_default_facet_labels() ) );
		$items   = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== trim( $raw ) ? explode( ',', $raw ) : array() );
		$out     = array();
		foreach ( $items as $item ) {
			$s = strtolower( trim( (string) $item ) );
			if ( isset( $allowed[ $s ] ) ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function get_product_facets( $product_id ) {
		$pid = absint( $product_id );
		if ( $pid <= 0 ) {
			return array();
		}
		$raw = get_post_meta( $pid, self::META_KEY, true );
		if ( ( '' === $raw || ( is_array( $raw ) && empty( $raw ) ) ) && class_exists( 'RWGC_Product_Meta', false ) ) {
			$alt = get_post_meta( $pid, RWGC_Product_Meta::META_WEATHER_TAGS, true );
			if ( ! empty( $alt ) ) {
				$raw = $alt;
			}
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::sanitize_facet_list( $decoded );
			}
			return self::sanitize_facet_list( explode( ',', $raw ) );
		}
		return self::sanitize_facet_list( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param int        $product_id Product ID.
	 * @param string[]   $facets     Facet slugs.
	 * @return void
	 */
	public static function get_available_facet_slugs() {
		if ( class_exists( 'RWGCP_Weather_Facets', false ) ) {
			return RWGCP_Weather_Facets::get_available_slugs();
		}
		return array_keys( self::get_default_facet_labels() );
	}

	/**
	 * @param int        $product_id Product ID.
	 * @param string[]   $facets     Facet slugs.
	 * @return void
	 */
	public static function save_product_facets( $product_id, array $facets ) {
		$pid = absint( $product_id );
		if ( $pid <= 0 ) {
			return;
		}
		$incoming  = self::sanitize_facet_list( $facets );
		$available = array_flip( self::get_available_facet_slugs() );
		$incoming  = array_values(
			array_filter(
				$incoming,
				static function ( $slug ) use ( $available ) {
					return isset( $available[ $slug ] );
				}
			)
		);
		$existing = self::get_product_facets( $pid );
		$latent   = array_values(
			array_filter(
				$existing,
				static function ( $slug ) use ( $available ) {
					return ! isset( $available[ $slug ] );
				}
			)
		);
		$clean = self::sanitize_facet_list( array_merge( $incoming, $latent ) );
		if ( empty( $clean ) ) {
			delete_post_meta( $pid, self::META_KEY );
			return;
		}
		update_post_meta( $pid, self::META_KEY, wp_json_encode( $clean ) );
	}

	/**
	 * @param int $term_id Product category term ID.
	 * @return string[]
	 */
	public static function get_category_facets( $term_id ) {
		$tid = absint( $term_id );
		if ( $tid <= 0 ) {
			return array();
		}
		$raw = get_term_meta( $tid, self::TERM_META_KEY, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::sanitize_facet_list( $decoded );
			}
			return self::sanitize_facet_list( explode( ',', $raw ) );
		}
		return self::sanitize_facet_list( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * @param int        $term_id Term ID.
	 * @param string[]   $facets  Facet slugs.
	 * @return void
	 */
	public static function save_category_facets( $term_id, array $facets ) {
		$tid = absint( $term_id );
		if ( $tid <= 0 ) {
			return;
		}
		$clean = self::sanitize_facet_list( $facets );
		if ( empty( $clean ) ) {
			delete_term_meta( $tid, self::TERM_META_KEY );
			return;
		}
		update_term_meta( $tid, self::TERM_META_KEY, wp_json_encode( $clean ) );
	}

	/**
	 * Union of category default facets assigned to a product (hints for tagging, not storefront match).
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function get_product_category_facets( $product_id ) {
		$pid = absint( $product_id );
		if ( $pid <= 0 ) {
			return array();
		}
		$term_ids = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $term_ids ) || ! is_array( $term_ids ) ) {
			return array();
		}
		$merged = array();
		foreach ( $term_ids as $term_id ) {
			$merged = array_merge( $merged, self::get_category_facets( (int) $term_id ) );
		}
		return self::sanitize_facet_list( $merged );
	}

	/**
	 * Count products tagged with at least one weather facet.
	 *
	 * @return int
	 */
	public static function count_tagged_products() {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => self::META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * @param array<string, mixed>|null $context Snapshot array.
	 * @return string[]
	 */
	public static function get_visitor_facets( $context = null ) {
		$cache_key = self::visitor_context_cache_key( $context );
		if ( null !== self::$visitor_facets_cache && self::$visitor_facets_cache_key === $cache_key ) {
			return self::$visitor_facets_cache;
		}

		if ( null === $context && function_exists( 'rwgc_get_context_snapshot' ) ) {
			$context = rwgc_get_context_snapshot();
		}
		if ( ! is_array( $context ) || empty( $context['weather'] ) || ! is_array( $context['weather'] ) ) {
			self::$visitor_facets_cache     = array();
			self::$visitor_facets_cache_key = $cache_key;
			return array();
		}
		$wx = $context['weather'];
		if ( empty( $wx['available'] ) ) {
			self::$visitor_facets_cache     = array();
			self::$visitor_facets_cache_key = $cache_key;
			return array();
		}
		if ( ! empty( $wx['facets'] ) && is_array( $wx['facets'] ) ) {
			$facets = self::sanitize_facet_list( $wx['facets'] );
		} elseif ( class_exists( 'RWGCP_Weather_Facets', false ) ) {
			$facets = RWGCP_Weather_Facets::derive( $wx );
		} else {
			$facets = array();
		}

		self::$visitor_facets_cache     = $facets;
		self::$visitor_facets_cache_key = $cache_key;
		return $facets;
	}

	/**
	 * @param array<string, mixed>|null $context Snapshot.
	 * @return string
	 */
	private static function visitor_context_cache_key( $context ) {
		if ( null === $context && function_exists( 'rwgc_get_context_snapshot' ) ) {
			$context = rwgc_get_context_snapshot();
		}
		if ( ! is_array( $context ) ) {
			return 'none';
		}
		$wx = isset( $context['weather'] ) && is_array( $context['weather'] ) ? $context['weather'] : array();
		if ( ! empty( $wx['facets'] ) && is_array( $wx['facets'] ) ) {
			return 'facets:' . implode( ',', self::sanitize_facet_list( $wx['facets'] ) );
		}
		$parts = array(
			isset( $wx['temp_c'] ) ? (string) $wx['temp_c'] : '',
			isset( $wx['precip_mm'] ) ? (string) $wx['precip_mm'] : '',
			isset( $wx['wind_kph'] ) ? (string) $wx['wind_kph'] : '',
			isset( $wx['uv'] ) ? (string) $wx['uv'] : '',
			! empty( $wx['is_day'] ) ? '1' : '0',
		);
		return 'wx:' . implode( '|', $parts );
	}

	/**
	 * Whether a product’s affinity overlaps visitor weather (default: any match).
	 *
	 * @param int                     $product_id Product ID.
	 * @param array<string, mixed>|null $context  Snapshot.
	 * @return bool
	 */
	public static function product_matches_visitor( $product_id, $context = null ) {
		$product_facets = self::get_product_facets( $product_id );
		if ( empty( $product_facets ) ) {
			return false;
		}
		$visitor = self::get_visitor_facets( $context );
		if ( empty( $visitor ) ) {
			return false;
		}
		$score = count( array_intersect( $product_facets, $visitor ) );
		/**
		 * Whether a product matches the visitor’s shopping weather.
		 *
		 * @param bool                   $match         Default: any facet overlap.
		 * @param int                    $product_id    Product ID.
		 * @param string[]               $product_facets Product facets.
		 * @param string[]               $visitor_facets Visitor facets.
		 * @param array<string, mixed>|null $context    Snapshot.
		 */
		return (bool) apply_filters(
			'rwgcm_weather_product_match',
			$score > 0,
			$product_id,
			$product_facets,
			$visitor,
			$context
		);
	}

	/**
	 * Overlap count for catalog sorting (Phase 4).
	 *
	 * @param int                     $product_id Product ID.
	 * @param array<string, mixed>|null $context  Snapshot.
	 * @return int
	 */
	public static function overlap_score( $product_id, $context = null ) {
		$pid = absint( $product_id );
		if ( $pid <= 0 ) {
			return 0;
		}
		$visitor = self::get_visitor_facets( $context );
		if ( empty( $visitor ) ) {
			return 0;
		}
		$cache_key = $pid . ':' . implode( ',', $visitor );
		if ( isset( self::$overlap_score_cache[ $cache_key ] ) ) {
			return (int) self::$overlap_score_cache[ $cache_key ];
		}
		self::batch_overlap_scores( array( $pid ), $context );
		return isset( self::$overlap_score_cache[ $cache_key ] ) ? (int) self::$overlap_score_cache[ $cache_key ] : 0;
	}

	/**
	 * SQL-backed overlap scores for many products in one query (catalog boost / widgets).
	 *
	 * @param int[]                     $product_ids Product IDs.
	 * @param array<string, mixed>|null $context     Snapshot.
	 * @return array<int, int> Scores keyed by product ID.
	 */
	public static function batch_overlap_scores( array $product_ids, $context = null ) {
		$visitor = self::get_visitor_facets( $context );
		if ( empty( $visitor ) ) {
			return array();
		}
		$visitor_key = implode( ',', $visitor );
		$ids         = array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
		$need        = array();
		foreach ( $ids as $pid ) {
			$ck = $pid . ':' . $visitor_key;
			if ( ! isset( self::$overlap_score_cache[ $ck ] ) ) {
				$need[] = $pid;
			}
		}
		if ( empty( $need ) ) {
			$out = array();
			foreach ( $ids as $pid ) {
				$ck = $pid . ':' . $visitor_key;
				$out[ $pid ] = (int) self::$overlap_score_cache[ $ck ];
			}
			return $out;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $need ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			array_merge( array( self::META_KEY ), $need )
		);
		$rows         = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$found        = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['post_id'] ) ) {
					continue;
				}
				$pid    = (int) $row['post_id'];
				$facets = self::decode_meta_facets( isset( $row['meta_value'] ) ? $row['meta_value'] : '' );
				$found[ $pid ] = count( array_intersect( $facets, $visitor ) );
			}
		}
		foreach ( $need as $pid ) {
			$score = isset( $found[ $pid ] ) ? (int) $found[ $pid ] : 0;
			self::$overlap_score_cache[ $pid . ':' . $visitor_key ] = $score;
		}

		$out = array();
		foreach ( $ids as $pid ) {
			$ck          = $pid . ':' . $visitor_key;
			$out[ $pid ] = isset( self::$overlap_score_cache[ $ck ] ) ? (int) self::$overlap_score_cache[ $ck ] : 0;
		}
		return $out;
	}

	/**
	 * Meta query matching products tagged with any of the given facets.
	 *
	 * @param string[] $facets   Facet slugs.
	 * @param string   $relation OR|AND.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_meta_query_for_facets( array $facets, $relation = 'OR' ) {
		$facets = self::sanitize_facet_list( $facets );
		if ( empty( $facets ) ) {
			return array();
		}
		$clauses = array( 'relation' => 'OR' === strtoupper( (string) $relation ) ? 'OR' : 'AND' );
		foreach ( $facets as $slug ) {
			$clauses[] = array(
				'key'     => self::META_KEY,
				'value'   => '"' . $slug . '"',
				'compare' => 'LIKE',
			);
		}
		return $clauses;
	}

	/**
	 * Product IDs tagged with weather meta, optionally filtered by visitor facet overlap.
	 *
	 * @param array<string, mixed> $args {
	 *     @type int    $limit    Max IDs (default 200).
	 *     @type string $category Category slug/ID list.
	 *     @type string[] $visitor_facets When set, require overlap with these facets.
	 * }
	 * @return int[]
	 */
	public static function query_tagged_product_ids( array $args = array() ) {
		$limit   = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 200;
		$visitor = isset( $args['visitor_facets'] ) && is_array( $args['visitor_facets'] )
			? self::sanitize_facet_list( $args['visitor_facets'] )
			: array();

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);

		if ( ! empty( $visitor ) ) {
			$query_args['meta_query'] = self::build_meta_query_for_facets( $visitor, 'OR' );
		}

		$category = isset( $args['category'] ) ? trim( (string) $args['category'] ) : '';
		if ( '' !== $category && taxonomy_exists( 'product_cat' ) ) {
			$terms = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', $category ) ) ) as $part ) {
				if ( is_numeric( $part ) ) {
					$term = get_term( (int) $part, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = (int) $term->term_id;
					}
				} else {
					$term = get_term_by( 'slug', sanitize_title( $part ), 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = (int) $term->term_id;
					}
				}
			}
			if ( ! empty( $terms ) ) {
				$query_args['tax_query'] = array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array_values( array_unique( $terms ) ),
					),
				);
			}
		}

		$q = new WP_Query( $query_args );
		return is_array( $q->posts ) ? array_map( 'intval', $q->posts ) : array();
	}

	/**
	 * @param mixed $raw Meta value.
	 * @return string[]
	 */
	private static function decode_meta_facets( $raw ) {
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::sanitize_facet_list( $decoded );
			}
			return self::sanitize_facet_list( explode( ',', $raw ) );
		}
		return self::sanitize_facet_list( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Human label for stored facet slug(s).
	 *
	 * @param string $value Comma-separated slugs or single slug.
	 * @return string
	 */
	public static function format_facet_value_label( $value ) {
		$labels = self::get_default_facet_labels();
		foreach ( self::get_facet_definitions() as $row ) {
			if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
				$labels[ (string) $row['slug'] ] = isset( $row['label'] ) ? (string) $row['label'] : (string) $row['slug'];
			}
		}
		$parts = self::sanitize_facet_list(
			is_string( $value ) && false !== strpos( $value, ',' )
				? explode( ',', $value )
				: ( is_string( $value ) ? array( $value ) : (array) $value )
		);
		$out   = array();
		foreach ( $parts as $slug ) {
			$out[] = isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
		}
		return implode( ', ', $out );
	}
}
