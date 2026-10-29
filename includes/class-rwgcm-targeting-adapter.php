<?php
/**
 * Bridges Geo Commerce legacy condition rows (target + operator + value) to Geo Core portable targeting
 * so evaluation uses {@see RWGC_Rule_Evaluator} and Pro condition hooks.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps stored condition groups to sanitized portable rule sets and evaluates via Geo Core.
 */
class RWGCM_Targeting_Adapter {

	/**
	 * Geo Commerce UI target keys that correspond to portable condition types under different names.
	 *
	 * @var array<string, string>
	 */
	private static $target_to_type = array(
		'google_ads_campaign'       => 'campaign',
		'utm_campaign'              => 'campaign',
		'ga_audience'               => 'audience',
		'google_analytics_audience' => 'audience',
	);

	/**
	 * Whether the legacy group had any non-empty target rows (used when sanitization drops all conditions).
	 *
	 * @param array<string, mixed> $group Legacy group (match + items).
	 * @return bool
	 */
	private static function had_nonempty_targets( array $group ) {
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		foreach ( $items as $it ) {
			if ( is_array( $it ) && ! empty( $it['target'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert context array to snapshot when Geo Core is available.
	 *
	 * @param array<string, mixed> $context Flat snapshot from {@see rwgc_get_context_snapshot()} or resolver.
	 * @return RWGC_Context_Snapshot|null
	 */
	private static function snapshot_from_context( array $context ) {
		if ( ! class_exists( 'RWGC_Context_Snapshot', false ) ) {
			return null;
		}
		return RWGC_Context_Snapshot::from_array( $context );
	}

	/**
	 * Map a commerce condition target key to a portable condition type slug.
	 *
	 * @param string $target Sanitized target key from stored rules.
	 * @return string Empty when unusable.
	 */
	private static function target_key_to_condition_type( $target ) {
		$t = sanitize_key( (string) $target );
		if ( '' === $t ) {
			return '';
		}
		if ( isset( self::$target_to_type[ $t ] ) ) {
			$t = self::$target_to_type[ $t ];
		}
		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return RWGC_Targeting_Rule_Set_Schema::sanitize_condition_type_string( $t );
		}
		return $t;
	}

	/**
	 * Build raw portable rule-set document (pre-sanitize) from a legacy commerce condition group.
	 *
	 * @param array<string, mixed> $group Legacy shape: match + items[] with target, operator, value.
	 * @return array<string, mixed>
	 */
	private static function legacy_group_to_portable_raw( array $group ) {
		$match = isset( $group['match'] ) ? sanitize_key( (string) $group['match'] ) : 'all';
		$match = 'any' === $match ? 'any' : 'all';

		$items       = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();
		$conditions  = array();
		foreach ( $items as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$type = self::target_key_to_condition_type( isset( $it['target'] ) ? $it['target'] : '' );
			if ( '' === $type ) {
				continue;
			}
			$op = isset( $it['operator'] ) ? sanitize_key( (string) $it['operator'] ) : 'in';
			if ( class_exists( 'RWGC_Target_Operators', false ) && ! RWGC_Target_Operators::is_valid( $op ) ) {
				$op = in_array( $op, array( 'is_not', 'not' ), true ) ? 'not_in' : 'in';
			}
			$value = isset( $it['value'] ) ? $it['value'] : null;
			if ( in_array( $op, array( 'in', 'not_in' ), true ) && ! is_array( $value ) ) {
				$value = self::list_value( $value );
			}
			if ( 'weather_facet' === $type && class_exists( 'RWGCM_Weather_Affinity', false ) ) {
				$value = RWGCM_Weather_Affinity::sanitize_facet_list( $value );
				if ( empty( $value ) ) {
					continue;
				}
			}
			$conditions[] = array(
				'type'     => $type,
				'operator' => $op,
				'value'    => $value,
			);
		}

		return array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'any',
			'rules'   => array(
				array(
					'id'         => 'rwgcm_legacy_group',
					'match'      => $match,
					'conditions' => $conditions,
				),
			),
		);
	}

	/**
	 * Evaluate a legacy condition group against the current visitor context using Geo Core’s evaluator when possible.
	 *
	 * @param array<string, mixed> $group   Legacy group (`match` + `items`).
	 * @param array<string, mixed> $context Context snapshot array.
	 * @return bool
	 */
	public static function group_matches( array $group, array $context ) {
		$context  = self::context_with_visitor_country( $context );
		$snapshot = self::snapshot_from_context( $context );

		if ( $snapshot && class_exists( 'RWGC_Rule_Evaluator', false ) && class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			$raw = self::legacy_group_to_portable_raw( $group );
			$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
			if ( null !== $set && RWGC_Rule_Evaluator::matches( $set, $snapshot ) ) {
				return true;
			}
		}

		// The 0.8.0 rule UI stores operator "is" and a country code (or name). Geo Core's
		// portable operators may not treat that as a match, so the commerce rows are checked too.
		return RWGCM_Condition_Evaluator::group_matches( $group, $context );
	}

	/**
	 * The price route knows the visitor country even when a context snapshot has not filled it in yet.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private static function context_with_visitor_country( array $context ) {
		$current = isset( $context['country'] ) && is_scalar( $context['country'] ) ? strtoupper( substr( (string) $context['country'], 0, 2 ) ) : '';
		if ( strlen( $current ) === 2 ) {
			$context['country'] = $current;
			return $context;
		}
		$visitor = '';
		if ( class_exists( 'RWGCM_Pricing_Calc', false ) ) {
			$visitor = strtoupper( substr( (string) RWGCM_Pricing_Calc::get_visitor_country(), 0, 2 ) );
		} elseif ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$visitor = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( strlen( $visitor ) === 2 ) {
			$context['country'] = $visitor;
		}
		return $context;
	}

	/**
	 * @param mixed $value Raw condition value.
	 * @return array<int, string>
	 */
	private static function list_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				foreach ( self::list_value( $item ) as $part ) {
					$out[] = $part;
				}
			}
			return $out;
		}
		$text = trim( (string) $value );
		if ( '' === $text ) {
			return array();
		}
		if ( false !== strpos( $text, ',' ) ) {
			$parts = array();
			foreach ( explode( ',', $text ) as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$parts[] = $part;
				}
			}
			return $parts;
		}
		return array( $text );
	}

	/**
	 * Evaluate a stored commerce rule (optional portable JSON in meta, else legacy condition rows).
	 *
	 * @param array<string, mixed> $rule    Full rule row from {@see RWGCM_Rule_Store}.
	 * @param array<string, mixed> $context Visitor context snapshot.
	 * @return bool
	 */
	public static function rule_matches( array $rule, array $context ) {
		$meta = isset( $rule['meta'] ) && is_array( $rule['meta'] ) ? $rule['meta'] : array();
		if ( ! empty( $meta['use_portable_targeting'] ) && ! empty( $meta['portable_targeting'] ) ) {
			$snapshot = self::snapshot_from_context( $context );
			if ( $snapshot && class_exists( 'RWGC_Rule_Evaluator', false ) && class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
				$set = RWGC_Targeting_Rule_Set_Schema::sanitize( (string) $meta['portable_targeting'] );
				if ( is_array( $set ) ) {
					return RWGC_Rule_Evaluator::matches( $set, $snapshot );
				}
				return false;
			}
		}

		$conds = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		return self::group_matches( $conds, $context );
	}
}
