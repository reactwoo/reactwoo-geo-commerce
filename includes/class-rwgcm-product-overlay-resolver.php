<?php
/**
 * Picks winning product overlay for a product + context (future: merge strategies).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overlay resolution (minimal v1: first matching overlay by priority order from store).
 */
class RWGCM_Product_Overlay_Resolver {

	/**
	 * @param int                    $product_id Product ID.
	 * @param array<string, mixed>   $context Context snapshot.
	 * @return array<string, mixed>|null
	 */
	public static function resolve( $product_id, array $context ) {
		$overlays = RWGCM_Product_Overlay_Store::get_overlays_for_product( $product_id );
		foreach ( $overlays as $ov ) {
			if ( ! is_array( $ov ) ) {
				continue;
			}
			$conds = isset( $ov['conditions'] ) && is_array( $ov['conditions'] ) ? $ov['conditions'] : array();
			if ( RWGCM_Condition_Evaluator::group_matches( $conds, $context ) ) {
				return $ov;
			}
		}
		return null;
	}
}
