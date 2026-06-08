<?php
/**
 * Geo Commerce rows for Geo Core AI site intelligence snapshot.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends compact `geo_commerce` metadata via {@see rwgc_ai_snapshot_payload}.
 */
class RWGCM_AI_Snapshot {

	const MAX_RULE_ROWS = 40;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_ai_snapshot_payload', array( __CLASS__, 'append_commerce_metadata' ), 20, 2 );
		add_filter( 'rwgc_ai_snapshot_relationships', array( __CLASS__, 'append_commerce_relationships' ), 20, 1 );
	}

	/**
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @param array<string, mixed> $context Builder context.
	 * @return array<string, mixed>
	 */
	public static function append_commerce_metadata( array $payload, array $context = array() ) {
		unset( $context );

		if ( ! class_exists( 'RWGC_AI_Snapshot_Schema', false ) ) {
			return $payload;
		}

		$rules_active = class_exists( 'RWGCM_Rule_Store', false ) && RWGCM_DB::rules_table_exists()
			? RWGCM_Rule_Store::count_by_status( 'active' )
			: 0;
		$rules_draft = class_exists( 'RWGCM_Rule_Store', false ) && RWGCM_DB::rules_table_exists()
			? RWGCM_Rule_Store::count_by_status( 'draft' )
			: 0;
		$legacy_overlays = 0;
		if ( RWGCM_DB::overlays_table_exists() && class_exists( 'RWGCM_Product_Overlay_Store', false ) ) {
			$legacy_overlays = (int) count( RWGCM_Product_Overlay_Store::get_all_overlays() );
		}

		$rules = self::collect_rules();
		$block = array(
			'active'              => true,
			'version'             => defined( 'RWGCM_VERSION' ) ? (string) RWGCM_VERSION : '',
			'woocommerce_active'  => class_exists( 'WooCommerce', false ),
			'counts'              => array(
				'rules_active'      => $rules_active,
				'rules_draft'       => $rules_draft,
				'legacy_overlays'   => $legacy_overlays,
				'rules_by_action'   => self::count_action_types( $rules ),
			),
			'rules'               => $rules,
		);

		/**
		 * Filter Geo Commerce block appended to the Geo AI site intelligence snapshot.
		 *
		 * @param array<string, mixed> $block   Commerce metadata block.
		 * @param array<string, mixed> $payload Full snapshot before normalization.
		 */
		$block = apply_filters( 'rwgcm_ai_snapshot_block', $block, $payload );

		$payload['geo_commerce'] = is_array( $block ) ? $block : array();

		return $payload;
	}

	/**
	 * @param array<int, array<string, mixed>> $rels Existing relationship rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function append_commerce_relationships( array $rels ) {
		if ( ! class_exists( 'RWGCM_Rule_Store', false ) || ! RWGCM_DB::rules_table_exists() ) {
			return $rels;
		}

		foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['id'] ) ) {
				continue;
			}
			$rule_id = (string) $rule['id'];
			$scope   = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
			$stype   = isset( $scope['type'] ) ? sanitize_key( (string) $scope['type'] ) : 'global';
			$ids     = isset( $scope['ids'] ) && is_array( $scope['ids'] ) ? $scope['ids'] : array();

			if ( 'product' === $stype ) {
				foreach ( array_slice( $ids, 0, 5 ) as $pid ) {
					$pid = absint( $pid );
					if ( $pid <= 0 ) {
						continue;
					}
					$rels[] = array(
						'type'      => 'targets',
						'from_type' => 'commerce_rule',
						'from_id'   => $rule_id,
						'to_type'   => 'product',
						'to_id'     => (string) $pid,
						'meta'      => array(
							'status' => isset( $rule['status'] ) ? sanitize_key( (string) $rule['status'] ) : '',
						),
					);
				}
			}
		}

		return $rels;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_rules() {
		if ( ! class_exists( 'RWGCM_Rule_Store', false ) || ! RWGCM_DB::rules_table_exists() ) {
			return array();
		}

		$rows = array();
		foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$scope = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
			$conds = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
			$rows[] = array(
				'id'                  => isset( $rule['id'] ) ? sanitize_text_field( (string) $rule['id'] ) : '',
				'label'               => isset( $rule['label'] ) ? sanitize_text_field( (string) $rule['label'] ) : '',
				'status'              => isset( $rule['status'] ) ? sanitize_key( (string) $rule['status'] ) : 'draft',
				'scope_type'          => isset( $scope['type'] ) ? sanitize_key( (string) $scope['type'] ) : 'global',
				'scope_product_count' => isset( $scope['ids'] ) && is_array( $scope['ids'] ) ? count( $scope['ids'] ) : 0,
				'condition_count'     => class_exists( 'RWGCM_Condition_Evaluator', false )
					? RWGCM_Condition_Evaluator::count_conditions( $conds )
					: 0,
				'action_types'        => self::extract_action_types( $rule ),
			);
			if ( count( $rows ) >= self::MAX_RULE_ROWS ) {
				break;
			}
		}

		return $rows;
	}

	/**
	 * @param array<string, mixed> $rule Rule row.
	 * @return array<int, string>
	 */
	private static function extract_action_types( array $rule ) {
		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		$types   = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				continue;
			}
			$types[] = sanitize_key( (string) $action['type'] );
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $rules Compact rule rows.
	 * @return array<string, int>
	 */
	private static function count_action_types( array $rules ) {
		$counts = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['action_types'] ) || ! is_array( $rule['action_types'] ) ) {
				continue;
			}
			foreach ( $rule['action_types'] as $type ) {
				$type = sanitize_key( (string) $type );
				if ( '' === $type ) {
					continue;
				}
				$counts[ $type ] = isset( $counts[ $type ] ) ? (int) $counts[ $type ] + 1 : 1;
			}
		}
		ksort( $counts );
		return $counts;
	}
}
