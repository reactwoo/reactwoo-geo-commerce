<?php
/**
 * Validates generic Geo Commerce rule payloads.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes rules for storage and evaluation.
 */
class RWGCM_Rule_Sanitizer {

	/**
	 * @param array<string, mixed> $rule Raw rule.
	 * @return array<string, mixed>|null
	 */
	public static function sanitize_rule( array $rule ) {
		$status = isset( $rule['status'] ) ? sanitize_key( (string) $rule['status'] ) : 'active';
		if ( ! in_array( $status, array( 'active', 'draft', 'disabled' ), true ) ) {
			$status = 'active';
		}
		$label = isset( $rule['label'] ) ? sanitize_text_field( (string) $rule['label'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$label = mb_substr( $label, 0, 191 );
		} else {
			$label = substr( $label, 0, 191 );
		}
		$priority = isset( $rule['priority'] ) ? (int) $rule['priority'] : 100;
		$priority = max( 0, min( 999999, $priority ) );

		$scope = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
		$stype = isset( $scope['type'] ) ? sanitize_key( (string) $scope['type'] ) : 'global';
		if ( ! in_array( $stype, array( 'global', 'product_category', 'product', 'cart' ), true ) ) {
			$stype = 'global';
		}
		$sids = isset( $scope['ids'] ) && is_array( $scope['ids'] ) ? array_map( 'absint', $scope['ids'] ) : array();
		$sids = array_values( array_filter( array_unique( $sids ) ) );

		$conditions = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		$match      = isset( $conditions['match'] ) ? sanitize_key( (string) $conditions['match'] ) : 'all';
		if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
			$match = 'all';
		}
		$items = isset( $conditions['items'] ) && is_array( $conditions['items'] ) ? $conditions['items'] : array();
		$clean_items = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$t = isset( $it['target'] ) ? sanitize_key( (string) $it['target'] ) : '';
			if ( '' === $t ) {
				continue;
			}
			$op = isset( $it['operator'] ) ? sanitize_key( (string) $it['operator'] ) : 'is';
			if ( class_exists( 'RWGC_Target_Operators', false ) && ! RWGC_Target_Operators::is_valid( $op ) ) {
				$op = 'is';
			} elseif ( ! class_exists( 'RWGC_Target_Operators', false ) && ! in_array( $op, array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains', 'greater_than', 'less_than', 'between' ), true ) ) {
				$op = 'is';
			}
			$clean_items[] = array(
				'target'   => $t,
				'operator' => $op,
				'value'    => isset( $it['value'] ) ? $it['value'] : null,
			);
			if ( count( $clean_items ) >= 40 ) {
				break;
			}
		}

		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		$clean_actions = array();
		foreach ( $actions as $act ) {
			if ( ! is_array( $act ) || empty( $act['type'] ) ) {
				continue;
			}
			$type = sanitize_key( (string) $act['type'] );
			$clean_act = RWGCM_Action_Resolver::sanitize_action( $type, $act );
			if ( is_array( $clean_act ) ) {
				$clean_actions[] = $clean_act;
			}
			if ( count( $clean_actions ) >= 20 ) {
				break;
			}
		}

		$meta = isset( $rule['meta'] ) && is_array( $rule['meta'] ) ? $rule['meta'] : array();

		$out = array(
			'id'         => isset( $rule['id'] ) ? absint( $rule['id'] ) : 0,
			'status'     => $status,
			'label'      => $label,
			'priority'   => $priority,
			'scope'      => array(
				'type' => $stype,
				'ids'  => $sids,
			),
			'conditions' => array(
				'match' => $match,
				'items' => $clean_items,
			),
			'actions'    => $clean_actions,
			'meta'       => $meta,
		);

		if ( empty( $clean_actions ) ) {
			return null;
		}

		return $out;
	}
}
