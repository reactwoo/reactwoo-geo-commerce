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
		if ( '' === $target && isset( $condition['field'] ) ) {
			$target = sanitize_key( str_replace( '.', '_', (string) $condition['field'] ) );
			if ( 'visitor_country' === $target ) {
				$target = 'country';
			}
		}
		if ( '' === $target ) {
			return false;
		}
		$op = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'is';
		$expected = isset( $condition['value'] ) ? $condition['value'] : null;
		$actual   = self::get_value( $target, $context );
		$commerce = self::commerce_operator( $op, $actual, $expected, $condition );
		if ( null !== $commerce ) {
			return $commerce;
		}
		if ( class_exists( 'RWGC_Target_Operators', false ) && RWGC_Target_Operators::is_valid( $op ) ) {
			return RWGC_Target_Operators::evaluate( $actual, $op, $expected );
		}
		return self::in_list( $actual, $expected, $condition );
	}

	/**
	 * Operators the rule UI saves. Null lets Geo Core handle anything else.
	 *
	 * @param string               $op        Operator.
	 * @param mixed                $actual    Context value.
	 * @param mixed                $expected  Stored value.
	 * @param array<string, mixed> $condition Condition row, for a stored country label.
	 * @return bool|null
	 */
	private static function commerce_operator( $op, $actual, $expected, array $condition ) {
		if ( ! in_array( $op, array( 'is', 'equals', 'eq', 'is_not', 'not', 'in', 'not_in' ), true ) ) {
			return null;
		}
		$hit = self::in_list( $actual, $expected, $condition );
		if ( in_array( $op, array( 'is_not', 'not', 'not_in' ), true ) ) {
			return ! $hit;
		}
		return $hit;
	}

	/**
	 * @param mixed                $actual    Context value.
	 * @param mixed                $expected  Stored value.
	 * @param array<string, mixed> $condition Condition row.
	 * @return bool
	 */
	private static function in_list( $actual, $expected, array $condition ) {
		$want = self::tokens( $expected );
		if ( isset( $condition['label'] ) && is_scalar( $condition['label'] ) ) {
			$want = array_merge( $want, self::tokens( $condition['label'] ) );
		}
		$have = self::tokens( $actual );
		if ( empty( $want ) || empty( $have ) ) {
			return false;
		}
		return count( array_intersect( $have, $want ) ) > 0;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return array<int, string>
	 */
	private static function tokens( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				$out = array_merge( $out, self::tokens( $item ) );
			}
			return array_values( array_unique( $out ) );
		}
		$text = trim( (string) $value );
		if ( '' === $text ) {
			return array();
		}
		$parts = false !== strpos( $text, ',' ) ? explode( ',', $text ) : array( $text );
		$out   = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^[A-Za-z]{2}$/', $part ) ) {
				$out[] = strtoupper( $part );
				continue;
			}
			$code = self::country_name_code( $part );
			$out[] = '' !== $code ? $code : strtolower( $part );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Store a country condition as ISO codes. A name such as "South Africa" becomes ZA.
	 * Lists and comma-separated values are normalized the same way. Unknown text is kept.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function normalize_country_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				$normalized = self::normalize_country_value( $item );
				if ( is_array( $normalized ) ) {
					foreach ( $normalized as $part ) {
						$out[] = $part;
					}
				} elseif ( is_string( $normalized ) && '' !== $normalized ) {
					$out[] = $normalized;
				}
			}
			return array_values( $out );
		}
		$text = trim( (string) $value );
		if ( '' === $text ) {
			return '';
		}
		if ( false !== strpos( $text, ',' ) ) {
			$parts = array();
			foreach ( explode( ',', $text ) as $part ) {
				$part = self::normalize_country_value( $part );
				if ( is_string( $part ) && '' !== $part ) {
					$parts[] = $part;
				}
			}
			return implode( ',', $parts );
		}
		if ( preg_match( '/^[A-Za-z]{2}$/', $text ) ) {
			return strtoupper( $text );
		}
		$code = self::country_name_code( $text );
		return '' !== $code ? $code : $text;
	}

	/**
	 * @param string $name Country name.
	 * @return string ISO2 or empty.
	 */
	private static function country_name_code( $name ) {
		$name = strtolower( trim( $name ) );
		if ( class_exists( 'RWGC_Countries', false ) && method_exists( 'RWGC_Countries', 'get_options' ) ) {
			foreach ( RWGC_Countries::get_options() as $code => $label ) {
				if ( strtolower( (string) $label ) === $name ) {
					return strtoupper( (string) $code );
				}
			}
		}
		$fallback = array(
			'south africa'              => 'ZA',
			'united kingdom'            => 'GB',
			'united states'             => 'US',
			'united states of america'  => 'US',
			'germany'                   => 'DE',
		);
		return isset( $fallback[ $name ] ) ? $fallback[ $name ] : '';
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
