<?php
/**
 * Rule matching with deterministic ordering (scope, conditions, priority, id).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates stored rules against Geo Core context.
 */
class RWGCM_Rule_Evaluator {

	/**
	 * Scope rank: higher = more specific.
	 *
	 * @param string $type Scope type.
	 * @return int
	 */
	public static function scope_rank( $type ) {
		$t = sanitize_key( (string) $type );
		switch ( $t ) {
			case 'product':
				return 4;
			case 'product_category':
				return 3;
			case 'cart':
				return 2;
			case 'global':
			default:
				return 1;
		}
	}

	/**
	 * @param array<string, mixed> $rule Rule.
	 * @param array<string, mixed> $context Context snapshot.
	 * @return bool
	 */
	public static function matches_rule( array $rule, array $context ) {
		if ( empty( $rule['status'] ) || 'active' !== $rule['status'] ) {
			return false;
		}
		$conds = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		return RWGCM_Condition_Evaluator::group_matches( $conds, $context );
	}

	/**
	 * @param array<string, mixed> $rule Rule.
	 * @param \WC_Product            $product Product.
	 * @return bool
	 */
	public static function scope_matches_product( array $rule, $product ) {
		$scope = isset( $rule['scope'] ) && is_array( $rule['scope'] ) ? $rule['scope'] : array();
		$type  = isset( $scope['type'] ) ? sanitize_key( (string) $scope['type'] ) : 'global';
		$ids   = isset( $scope['ids'] ) && is_array( $scope['ids'] ) ? array_map( 'intval', $scope['ids'] ) : array();
		if ( 'global' === $type ) {
			return true;
		}
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}
		if ( 'product' === $type ) {
			return in_array( (int) $product->get_id(), $ids, true );
		}
		if ( 'product_category' === $type ) {
			if ( empty( $ids ) ) {
				return true;
			}
			$have = $product->get_category_ids();
			$have = array_map( 'intval', $have );
			return count( array_intersect( $ids, $have ) ) > 0;
		}
		if ( 'cart' === $type ) {
			return true;
		}
		return false;
	}

	/**
	 * @param list<array<string, mixed>> $rules Rules.
	 * @param array<string, mixed>       $context Context.
	 * @param \WC_Product|null           $product Product for scope.
	 * @param string|null                $action_type Optional filter (e.g. price_adjustment).
	 * @return list<array<string, mixed>>
	 */
	public static function get_matching_rules( array $rules, array $context, $product = null, $action_type = null ) {
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			if ( null !== $product && ! self::scope_matches_product( $rule, $product ) ) {
				continue;
			}
			if ( null !== $action_type && ! self::rule_has_action_type( $rule, (string) $action_type ) ) {
				continue;
			}
			if ( self::matches_rule( $rule, $context ) ) {
				$out[] = $rule;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $rule Rule.
	 * @param string               $type Action type.
	 * @return bool
	 */
	private static function rule_has_action_type( array $rule, $type ) {
		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		foreach ( $actions as $a ) {
			if ( is_array( $a ) && isset( $a['type'] ) && sanitize_key( (string) $a['type'] ) === sanitize_key( $type ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<array<string, mixed>> $matches Matching rules.
	 * @return array<string, mixed>|null
	 */
	public static function pick_winner( array $matches ) {
		if ( empty( $matches ) ) {
			return null;
		}
		usort( $matches, array( __CLASS__, 'compare_rules' ) );
		return $matches[0];
	}

	/**
	 * Higher sort order = wins (first element after sort).
	 *
	 * @param array<string, mixed> $a Rule a.
	 * @param array<string, mixed> $b Rule b.
	 * @return int
	 */
	public static function compare_rules( array $a, array $b ) {
		$sa = self::scope_rank( isset( $a['scope']['type'] ) ? (string) $a['scope']['type'] : 'global' );
		$sb = self::scope_rank( isset( $b['scope']['type'] ) ? (string) $b['scope']['type'] : 'global' );
		if ( $sa !== $sb ) {
			return $sb <=> $sa;
		}
		$ca = RWGCM_Condition_Evaluator::count_conditions( isset( $a['conditions'] ) && is_array( $a['conditions'] ) ? $a['conditions'] : array() );
		$cb = RWGCM_Condition_Evaluator::count_conditions( isset( $b['conditions'] ) && is_array( $b['conditions'] ) ? $b['conditions'] : array() );
		if ( $ca !== $cb ) {
			return $cb <=> $ca;
		}
		$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
		$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
		if ( $pa !== $pb ) {
			return $pb <=> $pa;
		}
		$ida = isset( $a['id'] ) ? absint( $a['id'] ) : 0;
		$idb = isset( $b['id'] ) ? absint( $b['id'] ) : 0;
		return $ida <=> $idb;
	}

	/**
	 * Winning rule for cart pricing (price_adjustment).
	 *
	 * @param \WC_Product            $product Product.
	 * @param array<string, mixed>   $context Context.
	 * @return array<string, mixed>|null
	 */
	public static function get_winning_price_rule( $product, array $context ) {
		$rules = RWGCM_Rule_Store::get_all_rules();
		$match = self::get_matching_rules( $rules, $context, $product, 'price_adjustment' );
		return self::pick_winner( $match );
	}

	/**
	 * Extract first price_adjustment action from a rule (legacy calc format).
	 *
	 * @param array<string, mixed>|null $rule Rule.
	 * @return array{type: string, value: float}|null
	 */
	public static function get_price_adjustment_for_calc( $rule ) {
		if ( ! is_array( $rule ) ) {
			return null;
		}
		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		foreach ( $actions as $a ) {
			if ( ! is_array( $a ) || empty( $a['type'] ) ) {
				continue;
			}
			if ( 'price_adjustment' !== sanitize_key( (string) $a['type'] ) ) {
				continue;
			}
			$mode = isset( $a['mode'] ) ? sanitize_key( (string) $a['mode'] ) : 'percent';
			if ( ! in_array( $mode, array( 'percent', 'fixed_line' ), true ) ) {
				$mode = 'percent';
			}
			return array(
				'type'  => $mode,
				'value' => isset( $a['value'] ) ? floatval( $a['value'] ) : 0.0,
			);
		}
		return null;
	}
}
