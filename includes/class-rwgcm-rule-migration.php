<?php
/**
 * Migrates legacy option-based pricing rows into generic rules (custom table).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time migration from rwgcm_pricing option rows.
 */
class RWGCM_Rule_Migration {

	const OPTION_DONE = 'rwgcm_pricing_rules_migrated_v1';

	/**
	 * Run migration when the rules table is empty and legacy rows exist.
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_pricing() {
		if ( get_option( self::OPTION_DONE, false ) ) {
			return;
		}
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return;
		}
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $n > 0 ) {
			update_option( self::OPTION_DONE, 1, false );
			return;
		}
		if ( ! class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			return;
		}
		$legacy = RWGCM_Pricing_Rules::get_all();
		$rules  = isset( $legacy['rules'] ) && is_array( $legacy['rules'] ) ? $legacy['rules'] : array();
		if ( empty( $rules ) ) {
			update_option( self::OPTION_DONE, 1, false );
			return;
		}
		$idx = 0;
		foreach ( $rules as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cc = isset( $row['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['country'] ), 0, 2 ) ) : '';
			if ( strlen( $cc ) !== 2 ) {
				continue;
			}
			$cats = isset( $row['category_ids'] ) && is_array( $row['category_ids'] ) ? array_map( 'intval', $row['category_ids'] ) : array();
			$cats = array_values( array_filter( array_unique( $cats ) ) );

			$scope_type = 'global';
			$scope_ids  = array();
			if ( ! empty( $cats ) ) {
				$scope_type = 'product_category';
				$scope_ids  = $cats;
			}

			$type = isset( $row['type'] ) ? (string) $row['type'] : 'percent';
			if ( ! in_array( $type, array( 'percent', 'fixed_line' ), true ) ) {
				$type = 'percent';
			}
			$value = isset( $row['value'] ) ? floatval( $row['value'] ) : 0.0;

			$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %s: country code */
					__( 'Migrated pricing: %s', 'reactwoo-geo-commerce' ),
					$cc
				);
			}

			$active = ! isset( $row['active'] ) || ! empty( $row['active'] );

			$rule = array(
				'label'    => $label,
				'status'   => $active ? 'active' : 'draft',
				'priority' => 1000 - min( $idx, 900 ),
				'scope'    => array(
					'type' => $scope_type,
					'ids'  => $scope_ids,
				),
				'conditions' => array(
					'match' => 'all',
					'items' => array(
						array(
							'target'   => 'country',
							'operator' => 'is',
							'value'    => $cc,
						),
					),
				),
				'actions' => array(
					array(
						'type'  => 'price_adjustment',
						'mode'  => $type,
						'value' => $value,
					),
				),
				'meta' => array(
					'source'     => 'migrated_legacy_pricing',
					'created_at' => current_time( 'mysql', true ),
				),
			);

			RWGCM_Rule_Store::insert_rule( $rule );
			++$idx;
		}

		update_option( self::OPTION_DONE, 1, false );
	}
}
