<?php
/**
 * Resolves which price adjustment applies: generic rules (Geo Core targets) or legacy rows.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges {@see RWGCM_Pricing_Calc} with the new rule engine.
 */
class RWGCM_Pricing_Resolution {

	/**
	 * Whether cart/catalog geo pricing should run.
	 *
	 * @return bool
	 */
	public static function is_pricing_effective() {
		if ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() ) {
			return true;
		}
		return class_exists( 'RWGCM_Pricing_Rules', false ) && RWGCM_Pricing_Rules::is_enabled();
	}

	/**
	 * Legacy-shaped adjustment array for {@see RWGCM_Pricing_Calc::compute_adjusted()} or null.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return array{type: string, value: float}|null
	 */
	public static function find_price_adjustment( $product ) {
		if ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() ) {
			if ( ! class_exists( 'RWGCM_Rule_Evaluator', false ) || ! function_exists( 'rwgc_get_context_snapshot' ) ) {
				return null;
			}
			$ctx = rwgc_get_context_snapshot();
			$win = RWGCM_Rule_Evaluator::get_winning_price_rule( $product, is_array( $ctx ) ? $ctx : array() );
			return RWGCM_Rule_Evaluator::get_price_adjustment_for_calc( $win );
		}
		$country = RWGCM_Pricing_Calc::get_visitor_country();
		return RWGCM_Pricing_Rules::find_matching_rule( $country, $product );
	}
}
