<?php
/**
 * Admin / support diagnostics for Geo Commerce + Geo Core context.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects snapshots for the Diagnostics screen.
 */
class RWGCM_Diagnostics {

	/**
	 * @param \WC_Product|null $product Optional product for rule simulation.
	 * @return array<string, mixed>
	 */
	public static function collect( $product = null ) {
		$context = function_exists( 'rwgc_get_context_snapshot' ) ? rwgc_get_context_snapshot() : array();
		$targets = function_exists( 'rwgc_get_available_target_types' ) ? rwgc_get_available_target_types() : array();

		$winning = null;
		$pa      = null;
		if ( is_a( $product, 'WC_Product' ) && class_exists( 'RWGCM_Rule_Evaluator', false ) ) {
			$winning = RWGCM_Rule_Evaluator::get_winning_price_rule( $product, $context );
			$pa      = RWGCM_Rule_Evaluator::get_price_adjustment_for_calc( $winning );
		}

		$use_generic = self::uses_generic_pricing_rules();

		return array(
			'context_snapshot'       => $context,
			'available_target_keys'  => array_keys( $targets ),
			'use_generic_pricing'    => $use_generic,
			'winning_price_rule'     => $winning,
			'price_adjustment_calc'  => $pa,
			'generic_rule_count'     => RWGCM_DB::rules_table_exists() ? RWGCM_Rule_Store::count_by_status( 'active' ) : 0,
			'legacy_pricing_enabled' => class_exists( 'RWGCM_Pricing_Rules', false ) ? RWGCM_Pricing_Rules::is_enabled() : false,
		);
	}

	/**
	 * @return bool
	 */
	public static function uses_generic_pricing_rules() {
		/**
		 * Whether to evaluate cart pricing from generic rules (custom table) instead of legacy rows.
		 *
		 * @param bool|null $use Default: true when active generic rules exist.
		 */
		$default = RWGCM_DB::rules_table_exists() && RWGCM_Rule_Store::count_by_status( 'active' ) > 0;
		$filtered = apply_filters( 'rwgcm_use_generic_pricing_rules', $default );
		return (bool) $filtered;
	}
}
