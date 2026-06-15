<?php
/**
 * Product weather tagging helpers — suggest, auto-apply, coverage.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared tagging operations for admin bulk actions and auto-apply.
 */
class RWGCM_Weather_Tagging {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'maybe_auto_apply_category_defaults' ), 15, 2 );
	}

	/**
	 * Suggest facet slugs for a product (Geo AI keywords + category defaults, or category-only fallback).
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function suggest_facets( $product_id ) {
		$pid = absint( $product_id );
		if ( $pid <= 0 ) {
			return array();
		}
		if ( class_exists( 'RWGA_Weather_Facet_Suggester', false ) ) {
			$result = RWGA_Weather_Facet_Suggester::suggest_for_product( $pid );
			if ( is_array( $result ) && ! empty( $result['facets'] ) && is_array( $result['facets'] ) ) {
				return RWGCM_Weather_Affinity::sanitize_facet_list( $result['facets'] );
			}
		}
		return RWGCM_Weather_Affinity::get_product_category_facets( $pid );
	}

	/**
	 * Apply category union defaults when product has no facets and setting is enabled.
	 *
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function maybe_auto_apply_category_defaults( $post_id, $post ) {
		unset( $post );
		if ( ! RWGCM_Settings::is_weather_auto_category_defaults_enabled() ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// User explicitly saved weather checkboxes (including clearing all).
		if ( isset( $_POST['rwgcm_weather_facets'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! empty( RWGCM_Weather_Affinity::get_product_facets( $post_id ) ) ) {
			return;
		}
		$defaults = RWGCM_Weather_Affinity::get_product_category_facets( $post_id );
		if ( empty( $defaults ) ) {
			return;
		}
		RWGCM_Weather_Affinity::save_product_facets( $post_id, $defaults );
	}

	/**
	 * Catalog tagging coverage for admin reporting.
	 *
	 * @return array{total: int, tagged: int, untagged: int, percent: float}
	 */
	public static function get_coverage_stats() {
		$counts = wp_count_posts( 'product' );
		$total  = ( $counts && isset( $counts->publish ) ) ? (int) $counts->publish : 0;
		$tagged = RWGCM_Weather_Affinity::count_tagged_products();
		$tagged = min( $tagged, $total );
		$untagged = max( 0, $total - $tagged );
		$percent  = $total > 0 ? round( ( $tagged / $total ) * 100, 1 ) : 0.0;

		return array(
			'total'    => $total,
			'tagged'   => $tagged,
			'untagged' => $untagged,
			'percent'  => $percent,
		);
	}

	/**
	 * Admin URL to products list filtered to untagged weather facets.
	 *
	 * @return string
	 */
	public static function untagged_products_admin_url() {
		return add_query_arg(
			array(
				'post_type'      => 'product',
				'rwgcm_weather'  => 'untagged',
			),
			admin_url( 'edit.php' )
		);
	}
}
