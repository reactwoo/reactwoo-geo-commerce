<?php
/**
 * Query products by shopping-weather facet overlap.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared product selection for shortcode, block, and Elementor widget.
 */
class RWGCM_Weather_Product_Query {

	/**
	 * @param array<string, mixed> $args Display args.
	 * @return array<string, mixed>
	 */
	public static function parse_args( $args ) {
		$args = is_array( $args ) ? $args : array();
		$out  = array(
			'limit'              => isset( $args['limit'] ) ? max( 1, min( 48, (int) $args['limit'] ) ) : 8,
			'columns'            => isset( $args['columns'] ) ? max( 1, min( 6, (int) $args['columns'] ) ) : 4,
			'category'           => isset( $args['category'] ) ? sanitize_text_field( (string) $args['category'] ) : '',
			'ids'                => isset( $args['ids'] ) ? sanitize_text_field( (string) $args['ids'] ) : '',
			'orderby'            => isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'relevance',
			'fallback'           => isset( $args['fallback'] ) ? sanitize_key( (string) $args['fallback'] ) : 'hide',
			'fallback_category'  => isset( $args['fallback_category'] ) ? sanitize_text_field( (string) $args['fallback_category'] ) : '',
			'fallback_message'   => isset( $args['fallback_message'] ) ? sanitize_text_field( (string) $args['fallback_message'] ) : '',
			'weather_unavailable'=> isset( $args['weather_unavailable'] ) ? sanitize_key( (string) $args['weather_unavailable'] ) : 'hide',
			'class'              => isset( $args['class'] ) ? sanitize_html_class( (string) $args['class'] ) : '',
			'title'              => isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : '',
		);

		if ( ! in_array( $out['orderby'], array( 'relevance', 'date', 'menu_order' ), true ) ) {
			$out['orderby'] = 'relevance';
		}
		if ( ! in_array( $out['fallback'], array( 'hide', 'category', 'message' ), true ) ) {
			$out['fallback'] = 'hide';
		}
		if ( ! in_array( $out['weather_unavailable'], array( 'hide', 'category', 'message' ), true ) ) {
			$out['weather_unavailable'] = 'hide';
		}

		/**
		 * @param array<string, mixed> $out  Parsed args.
		 * @param array<string, mixed> $args Raw input.
		 */
		return apply_filters( 'rwgcm_weather_products_query_args', $out, $args );
	}

	/**
	 * Whether visitor weather facets are available.
	 *
	 * @return bool
	 */
	public static function weather_available() {
		return ! empty( RWGCM_Weather_Affinity::get_visitor_facets() );
	}

	/**
	 * @param array<string, mixed> $args Parsed args.
	 * @return array{product_ids: int[], mode: string, message: string}
	 */
	public static function resolve( array $args ) {
		$visitor = RWGCM_Weather_Affinity::get_visitor_facets();
		if ( empty( $visitor ) ) {
			return self::resolve_fallback_pool( $args, $args['weather_unavailable'], 'weather_unavailable' );
		}

		$pool = self::candidate_product_ids( $args, $visitor );
		if ( empty( $pool ) ) {
			return self::resolve_fallback_pool( $args, $args['fallback'], 'no_match' );
		}

		$scored = RWGCM_Weather_Affinity::batch_overlap_scores( $pool );
		$scored = array_filter(
			$scored,
			static function ( $score ) {
				return (int) $score > 0;
			}
		);

		if ( empty( $scored ) ) {
			return self::resolve_fallback_pool( $args, $args['fallback'], 'no_match' );
		}

		$ids = self::sort_product_ids( array_keys( $scored ), $scored, $args['orderby'] );
		$ids = array_slice( $ids, 0, $args['limit'] );

		return array(
			'product_ids' => array_map( 'intval', $ids ),
			'mode'        => 'weather',
			'message'     => '',
		);
	}

	/**
	 * @param array<string, mixed> $args Parsed args.
	 * @param string               $mode Fallback mode.
	 * @param string               $reason Internal reason slug.
	 * @return array{product_ids: int[], mode: string, message: string}
	 */
	private static function resolve_fallback_pool( array $args, $mode, $reason ) {
		unset( $reason );
		if ( 'hide' === $mode ) {
			return array(
				'product_ids' => array(),
				'mode'        => 'hidden',
				'message'     => '',
			);
		}

		if ( 'message' === $mode ) {
			$msg = $args['fallback_message'];
			if ( '' === $msg ) {
				$msg = __( 'No weather-matched products right now.', 'reactwoo-geo-commerce' );
			}
			return array(
				'product_ids' => array(),
				'mode'        => 'message',
				'message'     => $msg,
			);
		}

		$cat = '' !== $args['fallback_category'] ? $args['fallback_category'] : $args['category'];
		$ids = self::category_product_ids( $cat, $args['limit'], $args['orderby'] );
		return array(
			'product_ids' => $ids,
			'mode'        => 'fallback_category',
			'message'     => '',
		);
	}

	/**
	 * @param array<string, mixed> $args    Parsed args.
	 * @param string[]             $visitor Visitor facets.
	 * @return int[]
	 */
	private static function candidate_product_ids( array $args, array $visitor = array() ) {
		$manual = self::parse_id_list( $args['ids'] );
		if ( ! empty( $manual ) ) {
			if ( empty( $visitor ) ) {
				return $manual;
			}
			$scored = RWGCM_Weather_Affinity::batch_overlap_scores( $manual );
			$out    = array();
			foreach ( $scored as $pid => $score ) {
				if ( $score > 0 ) {
					$out[] = (int) $pid;
				}
			}
			return $out;
		}

		$pool_limit = max( (int) $args['limit'] * 8, 80 );
		$pool_limit = min( 500, $pool_limit );

		return RWGCM_Weather_Affinity::query_tagged_product_ids(
			array(
				'limit'          => $pool_limit,
				'category'       => $args['category'],
				'visitor_facets' => $visitor,
			)
		);
	}

	/**
	 * @param string $category Category slug, ID, or comma list.
	 * @return string[]|int[]
	 */
	private static function category_tax_query( $category ) {
		$category = trim( (string) $category );
		if ( '' === $category ) {
			return array();
		}
		$parts = array_map( 'trim', explode( ',', $category ) );
		$slugs = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( is_numeric( $part ) ) {
				$term = get_term( (int) $part, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			} else {
				$slugs[] = sanitize_title( $part );
			}
		}
		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * @param string $category Category slug or ID.
	 * @param int    $limit    Max products.
	 * @param string $orderby  Order mode.
	 * @return int[]
	 */
	private static function category_product_ids( $category, $limit, $orderby ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}
		$query_args = array(
			'status'  => 'publish',
			'limit'   => max( 1, (int) $limit ),
			'return'  => 'ids',
			'orderby' => 'menu_order' === $orderby ? 'menu_order' : ( 'date' === $orderby ? 'date' : 'menu_order' ),
			'order'   => 'DESC',
		);
		$tax = self::category_tax_query( $category );
		if ( ! empty( $tax ) ) {
			$query_args['category'] = $tax;
		}
		$ids = wc_get_products( $query_args );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * @param string $raw Comma-separated IDs.
	 * @return int[]
	 */
	private static function parse_id_list( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}
		$out = array();
		foreach ( explode( ',', $raw ) as $part ) {
			$id = absint( trim( $part ) );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param int[]             $ids    Product IDs.
	 * @param array<int, int>   $scores Overlap scores keyed by product ID.
	 * @param string            $orderby Sort mode.
	 * @return int[]
	 */
	private static function sort_product_ids( array $ids, array $scores, $orderby ) {
		if ( 'relevance' === $orderby ) {
			usort(
				$ids,
				static function ( $a, $b ) use ( $scores ) {
					$sa = isset( $scores[ $a ] ) ? (int) $scores[ $a ] : 0;
					$sb = isset( $scores[ $b ] ) ? (int) $scores[ $b ] : 0;
					if ( $sa === $sb ) {
						return $a <=> $b;
					}
					return $sb <=> $sa;
				}
			);
			return $ids;
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			return $ids;
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'include' => $ids,
				'limit'   => count( $ids ),
			)
		);
		if ( ! is_array( $products ) ) {
			return $ids;
		}

		usort(
			$products,
			static function ( $a, $b ) use ( $orderby ) {
				if ( ! is_a( $a, 'WC_Product' ) || ! is_a( $b, 'WC_Product' ) ) {
					return 0;
				}
				if ( 'date' === $orderby ) {
					return strtotime( $b->get_date_created() ? $b->get_date_created()->date( 'Y-m-d H:i:s' ) : '0' )
						<=> strtotime( $a->get_date_created() ? $a->get_date_created()->date( 'Y-m-d H:i:s' ) : '0' );
				}
				return (int) $a->get_menu_order() <=> (int) $b->get_menu_order();
			}
		);

		$sorted = array();
		foreach ( $products as $product ) {
			if ( is_a( $product, 'WC_Product' ) ) {
				$sorted[] = (int) $product->get_id();
			}
		}
		return $sorted;
	}
}
