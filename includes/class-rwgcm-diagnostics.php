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

		$wx_block = is_array( $context ) && ! empty( $context['weather'] ) && is_array( $context['weather'] ) ? $context['weather'] : array();

		$weather = array(
			'connected'        => class_exists( 'RWGCM_Condition_Library', false ) ? RWGCM_Condition_Library::is_weather_available() : false,
			'visitor_facets'   => class_exists( 'RWGCM_Weather_Affinity', false ) ? RWGCM_Weather_Affinity::get_visitor_facets( is_array( $context ) ? $context : null ) : array(),
			'tagged_products'  => class_exists( 'RWGCM_Weather_Affinity', false ) ? RWGCM_Weather_Affinity::count_tagged_products() : 0,
			'boost_shop'       => class_exists( 'RWGCM_Settings', false ) ? RWGCM_Settings::get_weather_catalog_boost_mode( 'shop' ) : 'off',
			'boost_category'   => class_exists( 'RWGCM_Settings', false ) ? RWGCM_Settings::get_weather_catalog_boost_mode( 'category' ) : 'off',
			'boost_collection' => class_exists( 'RWGCM_Settings', false ) ? RWGCM_Settings::get_weather_catalog_boost_mode( 'collection' ) : 'off',
			'location_source'  => isset( $wx_block['location_source'] ) ? (string) $wx_block['location_source'] : '',
			'coordinate_mode'  => class_exists( 'RWGCP_Weather_Service', false ) ? RWGCP_Weather_Service::get_coordinate_mode() : '',
			'air_quality_epa'  => isset( $wx_block['air_quality_epa'] ) ? $wx_block['air_quality_epa'] : null,
			'pollen_index_max' => isset( $wx_block['pollen_index_max'] ) ? $wx_block['pollen_index_max'] : null,
		);

		return array(
			'context_snapshot'       => $context,
			'available_target_keys'  => array_keys( $targets ),
			'use_generic_pricing'    => $use_generic,
			'winning_price_rule'     => $winning,
			'price_adjustment_calc'  => $pa,
			'generic_rule_count'     => RWGCM_DB::rules_table_exists() ? RWGCM_Rule_Store::count_by_status( 'active' ) : 0,
			'legacy_pricing_enabled' => class_exists( 'RWGCM_Pricing_Rules', false ) ? RWGCM_Pricing_Rules::is_enabled() : false,
			'weather_merchandising'  => $weather,
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
