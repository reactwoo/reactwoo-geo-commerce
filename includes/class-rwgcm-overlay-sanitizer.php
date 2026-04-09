<?php
/**
 * Sanitizes product overlay payloads (conditions + field overrides).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes overlay rows for storage and display.
 */
class RWGCM_Overlay_Sanitizer {

	/**
	 * @param array<string, mixed> $data Raw payload.
	 * @return array<string, mixed>|null
	 */
	public static function sanitize( array $data ) {
		$product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		if ( $product_id <= 0 ) {
			return null;
		}

		$label = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : '';
		if ( function_exists( 'mb_substr' ) ) {
			$label = mb_substr( $label, 0, 191 );
		} else {
			$label = substr( $label, 0, 191 );
		}

		$status = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'active';
		if ( ! in_array( $status, array( 'active', 'draft', 'disabled' ), true ) ) {
			$status = 'active';
		}

		$priority = isset( $data['priority'] ) ? (int) $data['priority'] : 100;
		$priority   = max( 0, min( 999999, $priority ) );

		$match = isset( $data['conditions']['match'] ) ? sanitize_key( (string) $data['conditions']['match'] ) : 'all';
		if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
			$match = 'all';
		}
		$items = isset( $data['conditions']['items'] ) && is_array( $data['conditions']['items'] ) ? $data['conditions']['items'] : array();
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

		$raw_ov = isset( $data['overrides'] ) && is_array( $data['overrides'] ) ? $data['overrides'] : array();
		$overrides = self::sanitize_overrides( $raw_ov );

		$meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();

		return array(
			'product_id' => $product_id,
			'label'      => $label,
			'status'     => $status,
			'priority'   => $priority,
			'conditions' => array(
				'match' => $match,
				'items' => $clean_items,
			),
			'overrides'  => $overrides,
			'meta'       => $meta,
		);
	}

	/**
	 * @param array<string, mixed> $raw Raw overrides block.
	 * @return array<string, array{enabled: bool, value: mixed}>
	 */
	private static function sanitize_overrides( array $raw ) {
		$keys = array( 'title', 'short_description', 'description', 'gallery', 'badge', 'cta' );
		$out  = array();
		foreach ( $keys as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				continue;
			}
			$block = $raw[ $key ];
			$en    = ! empty( $block['enabled'] );
			if ( 'gallery' === $key ) {
				$ids = isset( $block['value'] ) ? $block['value'] : array();
				if ( is_string( $ids ) ) {
					$ids = array_filter( array_map( 'absint', explode( ',', $ids ) ) );
				} elseif ( is_array( $ids ) ) {
					$ids = array_map( 'absint', $ids );
				} else {
					$ids = array();
				}
				$ids = array_values( array_filter( array_unique( $ids ) ) );
				if ( count( $ids ) > 50 ) {
					$ids = array_slice( $ids, 0, 50 );
				}
				$out[ $key ] = array(
					'enabled' => $en,
					'value'   => $ids,
				);
				continue;
			}
			$val = isset( $block['value'] ) ? $block['value'] : '';
			if ( 'title' === $key || 'badge' === $key || 'cta' === $key ) {
				$val = sanitize_text_field( (string) $val );
			} else {
				$val = wp_kses_post( (string) $val );
			}
			$out[ $key ] = array(
				'enabled' => $en,
				'value'   => $val,
			);
		}
		return $out;
	}
}
