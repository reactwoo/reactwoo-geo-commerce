<?php
/**
 * Evaluates condition groups against a Geo Core context snapshot.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Consumes flat context arrays from {@see rwgc_get_context_snapshot()}.
 */
class RWGCM_Condition_Evaluator {

	/**
	 * @param array{match?: string, items?: array<int, array<string, mixed>>} $group Condition group.
	 * @param array<string, mixed>                                           $context Snapshot.
	 * @return bool
	 */
	public static function group_matches( array $group, array $context ) {
		$match = isset( $group['match'] ) ? sanitize_key( (string) $group['match'] ) : 'all';
		if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
			$match = 'all';
		}
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		if ( empty( $items ) ) {
			return true;
		}
		$results = array();
		foreach ( $items as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$results[] = self::condition_matches( $cond, $context );
		}
		if ( empty( $results ) ) {
			return true;
		}
		if ( 'any' === $match ) {
			return in_array( true, $results, true );
		}
		foreach ( $results as $r ) {
			if ( true !== $r ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $condition Single condition.
	 * @param array<string, mixed> $context   Context.
	 * @return bool
	 */
	public static function condition_matches( array $condition, array $context ) {
		$target = isset( $condition['target'] ) ? sanitize_key( (string) $condition['target'] ) : '';
		if ( '' === $target ) {
			return false;
		}
		$op = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'is';
		if ( class_exists( 'RWGC_Target_Operators', false ) && ! RWGC_Target_Operators::is_valid( $op ) ) {
			return false;
		}
		$expected = isset( $condition['value'] ) ? $condition['value'] : null;
		$actual   = self::get_value( $target, $context );
		if ( class_exists( 'RWGC_Target_Operators', false ) ) {
			return RWGC_Target_Operators::evaluate( $actual, $op, $expected );
		}
		return (string) $actual === (string) $expected;
	}

	/**
	 * @param string               $target Target key.
	 * @param array<string, mixed> $context Context.
	 * @return mixed
	 */
	private static function get_value( $target, array $context ) {
		if ( isset( $context[ $target ] ) ) {
			return $context[ $target ];
		}
		if ( isset( $context['custom'] ) && is_array( $context['custom'] ) && isset( $context['custom'][ $target ] ) ) {
			return $context['custom'][ $target ];
		}
		if ( function_exists( 'rwgc_get_context_value' ) ) {
			return rwgc_get_context_value( $target );
		}
		return null;
	}

	/**
	 * Count conditions for specificity scoring.
	 *
	 * @param array<string, mixed> $group Group.
	 * @return int
	 */
	public static function count_conditions( array $group ) {
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		return count( $items );
	}
}
