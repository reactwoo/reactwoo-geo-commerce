<?php
/**
 * Rule Tester rendered product impacts from Geo Commerce rules.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports commerce rule outcomes for products discovered on tester content.
 */
class RWGCM_Rule_Tester_Rendered_Impacts {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_rule_tester_collect_rendered_impacts', array( __CLASS__, 'collect' ), 20, 4 );
	}

	/**
	 * @param array<int,array<string,mixed>> $impacts         Existing impacts.
	 * @param array<string,mixed>            $tester_context  Tester context.
	 * @param int                            $rule_id         Selected visibility rule ID.
	 * @param array<string,mixed>            $content         Content selector.
	 * @return array<int,array<string,mixed>>
	 */
	public static function collect( $impacts, $tester_context, $rule_id, $content ) {
		if ( ! class_exists( 'RWGCM_Rule_Evaluator', false ) || ! function_exists( 'wc_get_product' ) ) {
			return is_array( $impacts ) ? $impacts : array();
		}

		$rule_id = absint( $rule_id );
		$discovery = isset( $tester_context['discovery'] ) && is_array( $tester_context['discovery'] )
			? $tester_context['discovery']
			: ( class_exists( 'RWGC_Rule_Tester_Rendered_Impacts', false )
				? RWGC_Rule_Tester_Rendered_Impacts::discover_product_sources( is_array( $content ) ? $content : array() )
				: array( 'sources' => array() ) );
		$sources = isset( $discovery['sources'] ) && is_array( $discovery['sources'] ) ? $discovery['sources'] : array();
		if ( empty( $sources ) ) {
			return is_array( $impacts ) ? $impacts : array();
		}

		$context = self::commerce_context( $tester_context );
		$out     = is_array( $impacts ) ? $impacts : array();
		$seen    = array();
		foreach ( $out as $row ) {
			if ( is_array( $row ) && ! empty( $row['product_id'] ) ) {
				$key = absint( $row['product_id'] ) . ':' . sanitize_key( (string) ( $row['outcome'] ?? '' ) );
				$seen[ $key ] = true;
			}
		}

		$visibility_portable = $rule_id > 0 ? self::visibility_rule_portable( $rule_id ) : '';

		foreach ( $sources as $source_row ) {
			if ( ! is_array( $source_row ) ) {
				continue;
			}
			$product_id = absint( $source_row['product_id'] ?? 0 );
			if ( $product_id <= 0 ) {
				continue;
			}
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$matches = RWGCM_Rule_Evaluator::get_matching_rules(
				RWGCM_Rule_Store::get_all_rules(),
				$context,
				$product
			);
			if ( empty( $matches ) ) {
				continue;
			}

			foreach ( $matches as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				if ( $rule_id > 0 && ! self::commerce_rule_relates_to_visibility_rule( $rule, $rule_id, $visibility_portable ) ) {
					continue;
				}
				$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
				foreach ( $actions as $action ) {
					if ( ! is_array( $action ) || empty( $action['type'] ) ) {
						continue;
					}
					$impact = self::impact_from_action( $action, $rule, $product_id, $source_row, $rule_id );
					if ( null === $impact ) {
						continue;
					}
					$key = $product_id . ':' . sanitize_key( (string) ( $impact['outcome'] ?? '' ) ) . ':' . sanitize_key( (string) ( $impact['source'] ?? '' ) );
					if ( ! empty( $seen[ $key ] ) ) {
						continue;
					}
					$out[]        = $impact;
					$seen[ $key ] = true;
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $tester_context Tester context.
	 * @return array<string,mixed>
	 */
	private static function commerce_context( array $tester_context ) {
		$ctx = isset( $tester_context['context'] ) && is_array( $tester_context['context'] ) ? $tester_context['context'] : array();
		if ( class_exists( 'RWGC_Visibility_Rule_Preview', false ) ) {
			$snap = RWGC_Visibility_Rule_Preview::snapshot_from_scenario( $ctx );
			if ( $snap instanceof RWGC_Context_Snapshot ) {
				return $snap->to_array();
			}
		}
		return $ctx;
	}

	/**
	 * @param int $rule_id Visibility rule post ID.
	 * @return string Normalized portable JSON.
	 */
	private static function visibility_rule_portable( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			return '';
		}
		$raw = (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
		$set = json_decode( $raw, true );
		return is_array( $set ) ? (string) wp_json_encode( $set ) : '';
	}

	/**
	 * @param array<string,mixed> $rule                Commerce rule.
	 * @param int                 $visibility_rule_id  Selected visibility rule.
	 * @param string              $visibility_portable Normalized portable JSON.
	 * @return bool
	 */
	private static function commerce_rule_relates_to_visibility_rule( array $rule, $visibility_rule_id, $visibility_portable ) {
		$meta = isset( $rule['meta'] ) && is_array( $rule['meta'] ) ? $rule['meta'] : array();
		if ( ! empty( $meta['use_portable_targeting'] ) && ! empty( $meta['portable_targeting'] ) && '' !== $visibility_portable ) {
			$set = json_decode( (string) $meta['portable_targeting'], true );
			if ( is_array( $set ) && (string) wp_json_encode( $set ) === $visibility_portable ) {
				return true;
			}
		}
		// Commerce rules without portable parity are not tied to the selected visibility rule.
		return false;
	}

	/**
	 * @param array<string,mixed> $action      Commerce action.
	 * @param array<string,mixed> $rule        Commerce rule.
	 * @param int                 $product_id  Product ID.
	 * @param array<string,mixed> $source_row  Discovery source row.
	 * @param int                 $rule_id     Selected visibility rule ID.
	 * @return array<string,mixed>|null
	 */
	private static function impact_from_action( array $action, array $rule, $product_id, array $source_row, $rule_id ) {
		$type = sanitize_key( (string) ( $action['type'] ?? '' ) );
		$label = isset( $rule['label'] ) ? (string) $rule['label'] : (string) ( $rule['id'] ?? '' );

		if ( 'product_visibility' === $type ) {
			$mode = isset( $action['mode'] ) ? sanitize_key( (string) $action['mode'] ) : 'show';
			$hide = 'hide' === $mode;
			return array(
				'target_type'  => 'product',
				'source'       => 'geo_commerce',
				'source_label' => (string) ( $source_row['source_label'] ?? __( 'Geo Commerce', 'reactwoo-geo-commerce' ) ),
				'product_id'   => $product_id,
				'product_name' => get_the_title( $product_id ) ?: (string) $product_id,
				'rule_id'      => isset( $rule['id'] ) ? absint( $rule['id'] ) : 0,
				'rule_label'   => $label,
				'outcome'      => $hide ? 'hidden' : 'visible',
				'reason'       => $hide
					? __( 'This product was removed from the rendered shortcode output by a Geo Commerce product visibility rule.', 'reactwoo-geo-commerce' )
					: __( 'This product remains visible because a Geo Commerce visibility rule applies a show outcome.', 'reactwoo-geo-commerce' ),
			);
		}

		if ( 'price_adjustment' === $type ) {
			return array(
				'target_type'  => 'product',
				'source'       => 'geo_commerce',
				'source_label' => (string) ( $source_row['source_label'] ?? __( 'Geo Commerce', 'reactwoo-geo-commerce' ) ),
				'product_id'   => $product_id,
				'product_name' => get_the_title( $product_id ) ?: (string) $product_id,
				'rule_id'      => isset( $rule['id'] ) ? absint( $rule['id'] ) : 0,
				'rule_label'   => $label,
				'outcome'      => 'price_changed',
				'reason'       => __( 'A Geo Commerce pricing rule would change this product price for the simulated visitor.', 'reactwoo-geo-commerce' ),
			);
		}

		$display_types = array( 'product_badge', 'badge_override', 'product_notice', 'product_overlay', 'title_override' );
		if ( in_array( $type, $display_types, true ) ) {
			return array(
				'target_type'  => 'product',
				'source'       => 'geo_commerce',
				'source_label' => (string) ( $source_row['source_label'] ?? __( 'Geo Commerce', 'reactwoo-geo-commerce' ) ),
				'product_id'   => $product_id,
				'product_name' => get_the_title( $product_id ) ?: (string) $product_id,
				'rule_id'      => isset( $rule['id'] ) ? absint( $rule['id'] ) : 0,
				'rule_label'   => $label,
				'outcome'      => str_replace( 'product_', '', $type ),
				'reason'       => __( 'A Geo Commerce display rule would apply a storefront overlay for the simulated visitor.', 'reactwoo-geo-commerce' ),
			);
		}

		unset( $rule_id );
		return null;
	}
}
