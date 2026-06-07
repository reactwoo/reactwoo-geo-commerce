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
	const OPTION_OVERLAYS_DONE = 'rwgcm_overlays_migrated_v2';

	/**
	 * Run all one-time migrations.
	 *
	 * @return void
	 */
	public static function maybe_migrate_all() {
		self::maybe_migrate_legacy_pricing();
		self::maybe_migrate_legacy_overlays();
	}

	/**
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

	/**
	 * Copy legacy product overlays into unified rules (does not delete overlay rows).
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_overlays() {
		if ( get_option( self::OPTION_OVERLAYS_DONE, false ) ) {
			return;
		}
		if ( ! RWGCM_DB::overlays_table_exists() || ! RWGCM_DB::rules_table_exists() ) {
			return;
		}
		if ( ! class_exists( 'RWGCM_Product_Overlay_Store', false ) ) {
			return;
		}

		$overlays = RWGCM_Product_Overlay_Store::get_all_overlays();
		if ( empty( $overlays ) ) {
			update_option( self::OPTION_OVERLAYS_DONE, 1, false );
			return;
		}

		foreach ( $overlays as $overlay ) {
			if ( ! is_array( $overlay ) ) {
				continue;
			}
			$legacy_id = isset( $overlay['id'] ) ? absint( $overlay['id'] ) : 0;
			if ( $legacy_id <= 0 ) {
				continue;
			}
			if ( self::rule_exists_for_legacy_overlay( $legacy_id ) ) {
				continue;
			}

			$product_id = isset( $overlay['product_id'] ) ? absint( $overlay['product_id'] ) : 0;
			$scope_type = $product_id > 0 ? 'product' : 'global';
			$scope_ids  = $product_id > 0 ? array( $product_id ) : array();

			$actions = self::overlay_overrides_to_actions( isset( $overlay['overrides'] ) && is_array( $overlay['overrides'] ) ? $overlay['overrides'] : array() );
			if ( empty( $actions ) ) {
				continue;
			}

			$conditions = isset( $overlay['conditions'] ) && is_array( $overlay['conditions'] )
				? $overlay['conditions']
				: array( 'match' => 'all', 'items' => array() );

			$label = isset( $overlay['label'] ) ? (string) $overlay['label'] : '';
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %d: legacy overlay ID */
					__( 'Migrated overlay #%d', 'reactwoo-geo-commerce' ),
					$legacy_id
				);
			}

			$rule = array(
				'label'      => $label,
				'status'     => isset( $overlay['status'] ) ? (string) $overlay['status'] : 'active',
				'priority'   => isset( $overlay['priority'] ) ? (int) $overlay['priority'] : 100,
				'scope'      => array(
					'type' => $scope_type,
					'ids'  => $scope_ids,
				),
				'conditions' => $conditions,
				'actions'    => $actions,
				'meta'       => array(
					'source'             => 'migrated_legacy_overlay',
					'legacy_overlay_id'  => $legacy_id,
					'created_at'         => current_time( 'mysql', true ),
				),
			);

			RWGCM_Rule_Store::insert_rule( $rule );
		}

		update_option( self::OPTION_OVERLAYS_DONE, 1, false );
	}

	/**
	 * @param int $legacy_overlay_id Legacy overlay row ID.
	 * @return bool
	 */
	private static function rule_exists_for_legacy_overlay( $legacy_overlay_id ) {
		$rules = RWGCM_Rule_Store::get_all_rules();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['meta'] ) || ! is_array( $rule['meta'] ) ) {
				continue;
			}
			if ( isset( $rule['meta']['legacy_overlay_id'] ) && (int) $rule['meta']['legacy_overlay_id'] === (int) $legacy_overlay_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $overrides Overlay override map.
	 * @return list<array<string, mixed>>
	 */
	private static function overlay_overrides_to_actions( array $overrides ) {
		$map = array(
			'title'             => 'title_override',
			'short_description' => 'short_description_override',
			'description'       => 'description_override',
			'gallery'           => 'gallery_override',
			'badge'             => 'product_badge',
			'cta'               => 'cta_override',
		);
		$actions = array();
		foreach ( $map as $key => $type ) {
			if ( empty( $overrides[ $key ] ) || ! is_array( $overrides[ $key ] ) || empty( $overrides[ $key ]['enabled'] ) ) {
				continue;
			}
			$value = isset( $overrides[ $key ]['value'] ) ? $overrides[ $key ]['value'] : null;
			if ( 'product_badge' === $type ) {
				$actions[] = array(
					'type'  => 'product_badge',
					'text'  => is_scalar( $value ) ? (string) $value : '',
					'style' => 'default',
				);
				continue;
			}
			$actions[] = array(
				'type'    => $type,
				'enabled' => true,
				'value'   => $value,
			);
		}
		return $actions;
	}

	/**
	 * Whether legacy option-based pricing rows still exist.
	 *
	 * @return bool
	 */
	public static function has_legacy_pricing_rows() {
		if ( ! class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			return false;
		}
		$legacy = RWGCM_Pricing_Rules::get_all();
		$rules  = isset( $legacy['rules'] ) && is_array( $legacy['rules'] ) ? $legacy['rules'] : array();
		return ! empty( $rules );
	}

	/**
	 * Whether unmigrated overlay rows remain in the legacy table.
	 *
	 * @return bool
	 */
	public static function has_legacy_overlay_rows() {
		if ( ! RWGCM_DB::overlays_table_exists() || ! class_exists( 'RWGCM_Product_Overlay_Store', false ) ) {
			return false;
		}
		return count( RWGCM_Product_Overlay_Store::get_all_overlays() ) > 0;
	}
}
