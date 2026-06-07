<?php
/**
 * Human-readable rule summaries for admin and logs.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds plain-English descriptions of commerce rules.
 */
class RWGCM_Rule_Summary {

	/**
	 * Action type labels for summaries.
	 *
	 * @return array<string, string>
	 */
	public static function get_action_labels() {
		return array(
			'price_adjustment'           => __( 'price adjustment', 'reactwoo-geo-commerce' ),
			'product_badge'              => __( 'product badge', 'reactwoo-geo-commerce' ),
			'badge_override'               => __( 'product badge', 'reactwoo-geo-commerce' ),
			'product_notice'             => __( 'product notice', 'reactwoo-geo-commerce' ),
			'product_overlay'            => __( 'product overlay', 'reactwoo-geo-commerce' ),
			'title_override'             => __( 'title override', 'reactwoo-geo-commerce' ),
			'short_description_override' => __( 'short description override', 'reactwoo-geo-commerce' ),
			'description_override'       => __( 'description override', 'reactwoo-geo-commerce' ),
			'gallery_override'           => __( 'gallery override', 'reactwoo-geo-commerce' ),
			'product_visibility'         => __( 'product visibility', 'reactwoo-geo-commerce' ),
			'cta_override'               => __( 'CTA change', 'reactwoo-geo-commerce' ),
			'shipping_notice'            => __( 'shipping notice', 'reactwoo-geo-commerce' ),
			'stock_message'              => __( 'stock message', 'reactwoo-geo-commerce' ),
			'custom_html'                => __( 'custom HTML', 'reactwoo-geo-commerce' ),
			'cart_fee'                   => __( 'cart fee', 'reactwoo-geo-commerce' ),
			'availability'               => __( 'availability change', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * Build a full rule summary sentence.
	 *
	 * @param array<string, mixed> $rule Rule payload.
	 * @return string
	 */
	public static function summarize_rule( array $rule ) {
		$conditions = self::summarize_conditions( $rule );
		$actions    = self::summarize_actions( $rule );

		if ( '' === $conditions && '' === $actions ) {
			return __( 'Empty rule.', 'reactwoo-geo-commerce' );
		}
		if ( '' === $conditions ) {
			/* translators: %s: action phrase */
			return sprintf( __( 'Always %s.', 'reactwoo-geo-commerce' ), $actions );
		}
		if ( '' === $actions ) {
			/* translators: %s: condition phrase */
			return sprintf( __( 'If %s, no actions configured.', 'reactwoo-geo-commerce' ), $conditions );
		}
		/* translators: 1: condition phrase, 2: action phrase */
		return sprintf( __( 'If %1$s, %2$s.', 'reactwoo-geo-commerce' ), $conditions, $actions );
	}

	/**
	 * @param array<string, mixed> $rule Rule.
	 * @return string
	 */
	public static function summarize_conditions( array $rule ) {
		$group = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		$match = isset( $group['match'] ) ? (string) $group['match'] : 'all';
		$items = isset( $group['items'] ) && is_array( $group['items'] ) ? $group['items'] : array();

		if ( ! empty( $rule['meta']['use_portable_targeting'] ) && ! empty( $rule['meta']['portable_targeting'] ) ) {
			return __( 'visibility rules match', 'reactwoo-geo-commerce' );
		}

		$parts = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['target'] ) ) {
				continue;
			}
			$phrase = self::summarize_condition_row( $item );
			if ( '' !== $phrase ) {
				$parts[] = $phrase;
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$join = 'any' === $match
			? __( ' or ', 'reactwoo-geo-commerce' )
			: __( ' and ', 'reactwoo-geo-commerce' );

		return implode( $join, $parts );
	}

	/**
	 * @param array<string, mixed> $condition Condition row.
	 * @return string
	 */
	public static function summarize_condition_row( array $condition ) {
		$field = RWGCM_Condition_Library::get_field_by_key(
			isset( $condition['field'] ) ? (string) $condition['field'] : ( isset( $condition['target'] ) ? (string) $condition['target'] : '' )
		);
		$field_label = $field && isset( $field['label'] ) ? (string) $field['label'] : ( isset( $condition['target'] ) ? (string) $condition['target'] : '' );
		$op          = isset( $condition['operator'] ) ? (string) $condition['operator'] : 'is';
		$op_labels   = RWGCM_Condition_Library::get_operator_labels();
		$op_label    = isset( $op_labels[ $op ] ) ? $op_labels[ $op ] : $op;
		$value_label = RWGCM_Condition_Library::resolve_value_label( $condition );

		if ( '' === $value_label ) {
			return sprintf(
				/* translators: 1: field label, 2: operator */
				__( 'visitor %1$s %2$s', 'reactwoo-geo-commerce' ),
				strtolower( $field_label ),
				$op_label
			);
		}

		return sprintf(
			/* translators: 1: field label, 2: operator, 3: value label */
			__( 'visitor %1$s %2$s %3$s', 'reactwoo-geo-commerce' ),
			strtolower( $field_label ),
			$op_label,
			$value_label
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule.
	 * @return string
	 */
	public static function summarize_actions( array $rule ) {
		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		if ( empty( $actions ) ) {
			return '';
		}

		$labels = self::get_action_labels();
		$parts  = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				continue;
			}
			$type = sanitize_key( (string) $action['type'] );
			$part = self::summarize_action( $type, $action, $labels );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		if ( 1 === count( $parts ) ) {
			return $parts[0];
		}

		$last = array_pop( $parts );
		/* translators: %s: final action phrase */
		return sprintf(
			__( '%1$s and %2$s', 'reactwoo-geo-commerce' ),
			implode( __( ', ', 'reactwoo-geo-commerce' ), $parts ),
			$last
		);
	}

	/**
	 * @param string               $type   Action type.
	 * @param array<string, mixed> $action Action payload.
	 * @param array<string, string> $labels Label map.
	 * @return string
	 */
	private static function summarize_action( $type, array $action, array $labels ) {
		switch ( $type ) {
			case 'price_adjustment':
				$mode  = isset( $action['mode'] ) ? (string) $action['mode'] : 'percent';
				$value = isset( $action['value'] ) ? floatval( $action['value'] ) : 0.0;
				if ( 'fixed_line' === $mode ) {
					/* translators: %s: amount */
					return sprintf( __( 'adjust the product price by %s per unit', 'reactwoo-geo-commerce' ), (string) $value );
				}
				if ( $value >= 0 ) {
					/* translators: %s: percentage */
					return sprintf( __( 'increase the product price by %s%%', 'reactwoo-geo-commerce' ), (string) $value );
				}
				/* translators: %s: percentage (absolute) */
				return sprintf( __( 'decrease the product price by %s%%', 'reactwoo-geo-commerce' ), (string) abs( $value ) );

			case 'product_badge':
			case 'badge_override':
				$text = isset( $action['text'] ) ? (string) $action['text'] : ( isset( $action['value'] ) ? (string) $action['value'] : '' );
				if ( '' === $text ) {
					return __( 'show a product badge', 'reactwoo-geo-commerce' );
				}
				/* translators: %s: badge text */
				return sprintf( __( 'show the badge "%s"', 'reactwoo-geo-commerce' ), $text );

			case 'product_notice':
				$text = isset( $action['text'] ) ? (string) $action['text'] : '';
				if ( '' === $text ) {
					return __( 'show a product notice', 'reactwoo-geo-commerce' );
				}
				/* translators: %s: notice text */
				return sprintf( __( 'show the notice "%s"', 'reactwoo-geo-commerce' ), $text );

			case 'cta_override':
				return __( 'change the product CTA', 'reactwoo-geo-commerce' );

			case 'product_visibility':
				$mode = isset( $action['mode'] ) ? (string) $action['mode'] : 'show';
				return 'hide' === $mode
					? __( 'hide the product', 'reactwoo-geo-commerce' )
					: __( 'show the product', 'reactwoo-geo-commerce' );

			case 'shipping_notice':
				$text = isset( $action['text'] ) ? (string) $action['text'] : '';
				if ( '' === $text ) {
					return __( 'show a shipping notice', 'reactwoo-geo-commerce' );
				}
				/* translators: %s: notice text */
				return sprintf( __( 'show the shipping notice "%s"', 'reactwoo-geo-commerce' ), $text );

			case 'stock_message':
				$text = isset( $action['text'] ) ? (string) $action['text'] : '';
				if ( '' === $text ) {
					return __( 'show a stock message', 'reactwoo-geo-commerce' );
				}
				/* translators: %s: message text */
				return sprintf( __( 'show the stock message "%s"', 'reactwoo-geo-commerce' ), $text );

			case 'custom_html':
				return __( 'output custom HTML', 'reactwoo-geo-commerce' );

			default:
				return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
		}
	}

	/**
	 * Short action tags for list tables.
	 *
	 * @param array<string, mixed> $rule Rule.
	 * @return string[]
	 */
	public static function get_action_tags( array $rule ) {
		$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
		$labels  = self::get_action_labels();
		$tags    = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				continue;
			}
			$type = sanitize_key( (string) $action['type'] );
			$tags[] = isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
		}
		return array_values( array_unique( $tags ) );
	}
}
