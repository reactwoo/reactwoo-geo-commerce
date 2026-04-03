<?php
/**
 * Stored geo pricing rules (deterministic; optional ReactWoo license not required for this feature).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option: rwgcm_pricing — enabled flag + country rules.
 */
class RWGCM_Pricing_Rules {

	const OPTION_KEY = 'rwgcm_pricing';

	/**
	 * @return array{enabled: bool, rules: list<array{country: string, type: string, value: float, category_ids: list<int>}>}
	 */
	public static function get_all() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return self::sanitize( $raw );
	}

	/**
	 * @param array<string, mixed> $data Raw option.
	 * @return array{enabled: bool, rules: list<array{country: string, type: string, value: float, category_ids: list<int>}>}
	 */
	public static function sanitize( $data ) {
		$enabled = ! empty( $data['enabled'] );
		$rules   = isset( $data['rules'] ) && is_array( $data['rules'] ) ? $data['rules'] : array();
		$out     = array();
		foreach ( $rules as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cc = isset( $row['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['country'] ), 0, 2 ) ) : '';
			if ( '' === $cc || strlen( $cc ) !== 2 ) {
				continue;
			}
			if ( ! self::is_allowed_country( $cc ) ) {
				continue;
			}
			$type = isset( $row['type'] ) ? (string) $row['type'] : 'percent';
			if ( ! in_array( $type, array( 'percent', 'fixed_line' ), true ) ) {
				$type = 'percent';
			}
			$value = isset( $row['value'] ) ? floatval( $row['value'] ) : 0.0;
			if ( 'percent' === $type ) {
				$value = max( -100.0, min( 500.0, $value ) );
			} else {
				$value = max( -999999.0, min( 999999.0, $value ) );
			}
			$cat_ids = self::sanitize_category_ids( isset( $row['category_ids'] ) ? $row['category_ids'] : array() );
			$out[]   = array(
				'country'      => $cc,
				'type'         => $type,
				'value'        => $value,
				'category_ids' => $cat_ids,
			);
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return array(
			'enabled' => $enabled,
			'rules'   => $out,
		);
	}

	/**
	 * @param string $iso2 Two-letter code.
	 * @return bool
	 */
	private static function is_allowed_country( $iso2 ) {
		if ( ! function_exists( 'WC' ) ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $iso2 );
		}
		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->countries ) || ! is_object( $wc->countries ) ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $iso2 );
		}
		$countries = $wc->countries->get_countries();
		return is_array( $countries ) && isset( $countries[ $iso2 ] );
	}

	/**
	 * @param array<int|string, mixed> $ids Raw term IDs.
	 * @return list<int>
	 */
	private static function sanitize_category_ids( $ids ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $tid ) {
			$tid = absint( $tid );
			if ( $tid <= 0 ) {
				continue;
			}
			$term = get_term( $tid, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = (int) $term->term_id;
			}
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return array_values( array_unique( array_map( 'intval', $out ) ) );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$all = self::get_all();
		return ! empty( $all['enabled'] ) && ! empty( $all['rules'] );
	}

	/**
	 * First list-order rule matching country and optional product categories (empty category_ids = all products).
	 *
	 * @param string       $country_iso2 Uppercase ISO2.
	 * @param \WC_Product $product       Cart line product.
	 * @return array{country: string, type: string, value: float, category_ids: list<int>}|null
	 */
	public static function find_matching_rule( $country_iso2, $product ) {
		$country_iso2 = strtoupper( substr( sanitize_text_field( (string) $country_iso2 ), 0, 2 ) );
		if ( strlen( $country_iso2 ) !== 2 ) {
			return null;
		}
		$all = self::get_all();
		if ( empty( $all['enabled'] ) || empty( $all['rules'] ) ) {
			return null;
		}
		foreach ( $all['rules'] as $rule ) {
			if ( ! isset( $rule['country'] ) || $rule['country'] !== $country_iso2 ) {
				continue;
			}
			$cat_ids = isset( $rule['category_ids'] ) && is_array( $rule['category_ids'] ) ? $rule['category_ids'] : array();
			if ( ! empty( $cat_ids ) ) {
				if ( ! is_a( $product, 'WC_Product' ) || ! self::product_in_categories( $product, $cat_ids ) ) {
					continue;
				}
			}
			return $rule;
		}
		return null;
	}

	/**
	 * @param \WC_Product $product Product.
	 * @param list<int>   $cat_ids Term IDs (product_cat).
	 * @return bool
	 */
	private static function product_in_categories( $product, $cat_ids ) {
		$want = array_map( 'intval', $cat_ids );
		$have = $product->get_category_ids();
		$have = array_map( 'intval', $have );
		return count( array_intersect( $want, $have ) ) > 0;
	}
}
