<?php
/**
 * Admin-only pricing/fee preview (no cart side effects).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plain-language simulator output for rule builders.
 */
class RWGCM_Simulator {

	/**
	 * @param string             $country_iso2 ISO2.
	 * @param array<int, int>    $category_ids Product_cat term IDs.
	 * @param float              $base_price   Catalog base unit price.
	 * @return array{rule: array<string, mixed>|null, adjusted: float, explanation: string}
	 */
	public static function pricing_preview( $country_iso2, $category_ids, $base_price ) {
		$base = (float) $base_price;
		if ( ! class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			return array(
				'rule'         => null,
				'adjusted'     => $base,
				'explanation'  => __( 'Pricing rules are not loaded.', 'reactwoo-geo-commerce' ),
			);
		}
		$rule = RWGCM_Pricing_Rules::find_matching_rule_for_categories( $country_iso2, $category_ids );
		if ( null === $rule ) {
			return array(
				'rule'         => null,
				'adjusted'     => $base,
				'explanation'  => __( 'No matching rule — base price unchanged.', 'reactwoo-geo-commerce' ),
			);
		}
		if ( ! class_exists( 'RWGCM_Pricing_Calc', false ) ) {
			return array(
				'rule'        => $rule,
				'adjusted'    => $base,
				'explanation' => __( 'Could not compute adjustment.', 'reactwoo-geo-commerce' ),
			);
		}
		$adj = RWGCM_Pricing_Calc::compute_adjusted( $base, $rule );
		$exp = self::explain_pricing_rule( $rule, $base, $adj );
		return array(
			'rule'        => $rule,
			'adjusted'    => $adj,
			'explanation' => $exp,
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule row.
	 * @param float                  $base Base price.
	 * @param float                  $adj  Adjusted price.
	 * @return string
	 */
	public static function explain_pricing_rule( $rule, $base, $adj ) {
		if ( ! is_array( $rule ) ) {
			return '';
		}
		$cc = isset( $rule['country'] ) ? (string) $rule['country'] : '';
		$type = isset( $rule['type'] ) && 'fixed_line' === $rule['type'] ? 'fixed_line' : 'percent';
		$val  = isset( $rule['value'] ) ? (float) $rule['value'] : 0.0;
		$cats = isset( $rule['category_ids'] ) && is_array( $rule['category_ids'] ) ? $rule['category_ids'] : array();
		$cat_note = '';
		if ( ! empty( $cats ) ) {
			$cat_note = __( 'limited to selected categories', 'reactwoo-geo-commerce' );
		} else {
			$cat_note = __( 'all products in country', 'reactwoo-geo-commerce' );
		}
		if ( 'fixed_line' === $type ) {
			/* translators: 1: country, 2: fixed amount, 3: base, 4: result, 5: category note */
			return sprintf(
				__( 'For visitors in %1$s (%5$s): add %2$s per unit to base %3$s → %4$s.', 'reactwoo-geo-commerce' ),
				$cc,
				wc_format_decimal( $val, wc_get_price_decimals() ),
				wc_format_decimal( $base, wc_get_price_decimals() ),
				wc_format_decimal( $adj, wc_get_price_decimals() ),
				$cat_note
			);
		}
		/* translators: 1: country, 2: percent, 3: base, 4: result, 5: category note */
		return sprintf(
			__( 'For visitors in %1$s (%5$s): adjust price by %2$s%% — base %3$s → %4$s.', 'reactwoo-geo-commerce' ),
			$cc,
			wc_format_decimal( $val, 2 ),
			wc_format_decimal( $base, wc_get_price_decimals() ),
			wc_format_decimal( $adj, wc_get_price_decimals() ),
			$cat_note
		);
	}

	/**
	 * Human-readable one-line summary for a pricing rule row (admin cards).
	 *
	 * @param array<string, mixed> $rule Rule row (after sanitize).
	 * @return string
	 */
	public static function summarize_pricing_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return '';
		}
		$cc = isset( $rule['country'] ) ? (string) $rule['country'] : '';
		$type = isset( $rule['type'] ) && 'fixed_line' === $rule['type'] ? 'fixed_line' : 'percent';
		$val  = isset( $rule['value'] ) ? (float) $rule['value'] : 0.0;
		$cats = isset( $rule['category_ids'] ) && is_array( $rule['category_ids'] ) ? $rule['category_ids'] : array();
		$cn   = '';
		if ( ! empty( $cats ) && function_exists( 'get_term' ) ) {
			$names = array();
			foreach ( array_slice( $cats, 0, 3 ) as $tid ) {
				$t = get_term( (int) $tid, 'product_cat' );
				if ( $t && ! is_wp_error( $t ) ) {
					$names[] = $t->name;
				}
			}
			if ( ! empty( $names ) ) {
				$cn = implode( ', ', $names );
			}
		}
		if ( 'fixed_line' === $type ) {
			if ( '' !== $cn ) {
				/* translators: 1: country, 2: amount, 3: category list */
				return sprintf( __( 'In %1$s: add %2$s per unit for categories: %3$s.', 'reactwoo-geo-commerce' ), $cc, wc_format_decimal( $val, wc_get_price_decimals() ), $cn );
			}
			/* translators: 1: country, 2: amount */
			return sprintf( __( 'In %1$s: add %2$s per unit to all products.', 'reactwoo-geo-commerce' ), $cc, wc_format_decimal( $val, wc_get_price_decimals() ) );
		}
		if ( '' !== $cn ) {
			/* translators: 1: country, 2: percent, 3: categories */
			return sprintf( __( 'In %1$s: change price by %2$s%% for categories: %3$s.', 'reactwoo-geo-commerce' ), $cc, wc_format_decimal( $val, 2 ), $cn );
		}
		/* translators: 1: country, 2: percent */
		return sprintf( __( 'In %1$s: change all catalog prices by %2$s%%.', 'reactwoo-geo-commerce' ), $cc, wc_format_decimal( $val, 2 ) );
	}

	/**
	 * @param string $country_iso2 ISO2.
	 * @return list<array<string, mixed>>
	 */
	public static function fee_rows_for_country( $country_iso2 ) {
		if ( ! class_exists( 'RWGCM_Fee_Rules', false ) ) {
			return array();
		}
		return RWGCM_Fee_Rules::get_rows_for_country( $country_iso2 );
	}

	/**
	 * @param array<string, mixed> $rule Fee rule row.
	 * @return string
	 */
	public static function summarize_fee_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return '';
		}
		$cc = isset( $rule['country'] ) ? (string) $rule['country'] : '';
		$name = isset( $rule['name'] ) ? (string) $rule['name'] : '';
		$amt  = isset( $rule['amount'] ) ? (float) $rule['amount'] : 0.0;
		$tax  = ! empty( $rule['taxable'] );
		/* translators: 1: country, 2: fee label, 3: amount, 4: taxable yes/no */
		return sprintf(
			__( 'In %1$s: fee “%2$s” of %3$s (%4$s).', 'reactwoo-geo-commerce' ),
			$cc,
			$name,
			wc_format_decimal( $amt, wc_get_price_decimals() ),
			$tax ? __( 'taxable', 'reactwoo-geo-commerce' ) : __( 'non-taxable', 'reactwoo-geo-commerce' )
		);
	}
}
