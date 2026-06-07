<?php
/**
 * Supported Geo Commerce action types (satellite outcomes).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates action payloads by type.
 */
class RWGCM_Action_Resolver {

	/**
	 * Known action type slugs.
	 *
	 * @return string[]
	 */
	public static function known_types() {
		return array(
			'price_adjustment',
			'cart_fee',
			'availability',
			'quantity_rule',
			'title_override',
			'short_description_override',
			'description_override',
			'gallery_override',
			'badge_override',
			'product_badge',
			'product_notice',
			'product_overlay',
			'product_visibility',
			'cta_override',
			'shipping_notice',
			'stock_message',
			'custom_html',
		);
	}

	/**
	 * Action types shown in the unified rule builder.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function builder_action_options() {
		return array(
			'price_adjustment'   => __( 'Price adjustment', 'reactwoo-geo-commerce' ),
			'product_badge'      => __( 'Product badge', 'reactwoo-geo-commerce' ),
			'product_notice'     => __( 'Product notice', 'reactwoo-geo-commerce' ),
			'product_overlay'    => __( 'Product overlay', 'reactwoo-geo-commerce' ),
			'product_visibility' => __( 'Product visibility', 'reactwoo-geo-commerce' ),
			'cta_override'       => __( 'CTA / button change', 'reactwoo-geo-commerce' ),
			'shipping_notice'    => __( 'Shipping notice', 'reactwoo-geo-commerce' ),
			'stock_message'      => __( 'Stock message', 'reactwoo-geo-commerce' ),
			'custom_html'        => __( 'Custom HTML', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * @param string               $type Action type.
	 * @param array<string, mixed> $action Raw action.
	 * @return array<string, mixed>|null
	 */
	public static function sanitize_action( $type, array $action ) {
		$type = sanitize_key( (string) $type );
		switch ( $type ) {
			case 'price_adjustment':
				$mode = isset( $action['mode'] ) ? sanitize_key( (string) $action['mode'] ) : 'percent';
				if ( ! in_array( $mode, array( 'percent', 'fixed_line' ), true ) ) {
					$mode = 'percent';
				}
				$value = isset( $action['value'] ) ? floatval( $action['value'] ) : 0.0;
				if ( 'percent' === $mode ) {
					$value = max( -100.0, min( 500.0, $value ) );
				} else {
					$value = max( -999999.0, min( 999999.0, $value ) );
				}
				return array(
					'type'  => 'price_adjustment',
					'mode'  => $mode,
					'value' => $value,
				);
			case 'cart_fee':
				return array(
					'type'  => 'cart_fee',
					'name'  => isset( $action['name'] ) ? sanitize_text_field( (string) $action['name'] ) : '',
					'value' => isset( $action['value'] ) ? floatval( $action['value'] ) : 0.0,
				);
			case 'availability':
				return array(
					'type'    => 'availability',
					'mode'    => isset( $action['mode'] ) ? sanitize_key( (string) $action['mode'] ) : 'inherit',
					'message' => isset( $action['message'] ) ? sanitize_text_field( (string) $action['message'] ) : '',
				);
			case 'quantity_rule':
				return array(
					'type'     => 'quantity_rule',
					'min'      => isset( $action['min'] ) ? max( 0, (int) $action['min'] ) : 0,
					'max'      => isset( $action['max'] ) ? max( 0, (int) $action['max'] ) : 0,
					'step'     => isset( $action['step'] ) ? max( 1, (int) $action['step'] ) : 1,
				);
			case 'title_override':
			case 'short_description_override':
			case 'description_override':
			case 'gallery_override':
			case 'badge_override':
			case 'cta_override':
				return array(
					'type'    => $type,
					'enabled' => ! empty( $action['enabled'] ),
					'value'   => isset( $action['value'] ) ? $action['value'] : null,
				);
			case 'product_badge':
				return array(
					'type'  => 'product_badge',
					'text'  => isset( $action['text'] ) ? sanitize_text_field( (string) $action['text'] ) : '',
					'style' => isset( $action['style'] ) ? sanitize_key( (string) $action['style'] ) : 'default',
				);
			case 'product_notice':
			case 'shipping_notice':
			case 'stock_message':
				return array(
					'type' => $type,
					'text' => isset( $action['text'] ) ? sanitize_text_field( (string) $action['text'] ) : '',
				);
			case 'product_overlay':
				return array(
					'type'    => 'product_overlay',
					'field'   => isset( $action['field'] ) ? sanitize_key( (string) $action['field'] ) : 'title',
					'enabled' => ! empty( $action['enabled'] ),
					'value'   => isset( $action['value'] ) ? $action['value'] : '',
				);
			case 'product_visibility':
				$mode = isset( $action['mode'] ) ? sanitize_key( (string) $action['mode'] ) : 'show';
				if ( ! in_array( $mode, array( 'show', 'hide' ), true ) ) {
					$mode = 'show';
				}
				return array(
					'type' => 'product_visibility',
					'mode' => $mode,
				);
			case 'custom_html':
				return array(
					'type'  => 'custom_html',
					'value' => isset( $action['value'] ) ? wp_kses_post( (string) $action['value'] ) : '',
				);
			default:
				return null;
		}
	}
}
