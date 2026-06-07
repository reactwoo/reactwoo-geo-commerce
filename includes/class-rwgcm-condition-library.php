<?php
/**
 * Guided condition field library for Geo Commerce rules.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps commerce-friendly condition metadata to Geo Core target keys for evaluation.
 */
class RWGCM_Condition_Library {

	/**
	 * Human-readable group labels keyed by group slug.
	 *
	 * @return array<string, string>
	 */
	public static function get_groups() {
		return array(
			'visitor_location' => __( 'Visitor Location', 'reactwoo-geo-commerce' ),
			'woocommerce'      => __( 'WooCommerce Product', 'reactwoo-geo-commerce' ),
			'cart_checkout'      => __( 'Cart / Checkout', 'reactwoo-geo-commerce' ),
			'customer'           => __( 'Customer', 'reactwoo-geo-commerce' ),
			'device_session'     => __( 'Device / Session', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * Commerce condition field definitions.
	 *
	 * Each field stores `target` (Geo Core key) for evaluation compatibility.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields() {
		$fields = array(
			'visitor.country' => array(
				'id'           => 'visitor.country',
				'target'       => 'country',
				'label'        => __( 'Country', 'reactwoo-geo-commerce' ),
				'group'        => 'visitor_location',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'country_select',
				'value_source' => 'countries',
			),
			'visitor.region' => array(
				'id'           => 'visitor.region',
				'target'       => 'region',
				'label'        => __( 'Region / State', 'reactwoo-geo-commerce' ),
				'group'        => 'visitor_location',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' ),
				'value_type'   => 'text',
				'value_source' => '',
			),
			'visitor.city' => array(
				'id'           => 'visitor.city',
				'target'       => 'city',
				'label'        => __( 'City', 'reactwoo-geo-commerce' ),
				'group'        => 'visitor_location',
				'operators'    => array( 'is', 'is_not', 'contains', 'not_contains' ),
				'value_type'   => 'text',
				'value_source' => '',
			),
			'visitor.currency' => array(
				'id'           => 'visitor.currency',
				'target'       => 'currency',
				'label'        => __( 'Currency', 'reactwoo-geo-commerce' ),
				'group'        => 'visitor_location',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'currency_select',
				'value_source' => 'currencies',
			),
			'product.category' => array(
				'id'           => 'product.category',
				'target'       => 'product_category',
				'label'        => __( 'Product category', 'reactwoo-geo-commerce' ),
				'group'        => 'woocommerce',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'product_cat_select',
				'value_source' => 'product_categories',
			),
			'product.tag' => array(
				'id'           => 'product.tag',
				'target'       => 'product_tag',
				'label'        => __( 'Product tag', 'reactwoo-geo-commerce' ),
				'group'        => 'woocommerce',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'product_tag_select',
				'value_source' => 'product_tags',
			),
			'product.price' => array(
				'id'           => 'product.price',
				'target'       => 'product_price',
				'label'        => __( 'Product price', 'reactwoo-geo-commerce' ),
				'group'        => 'woocommerce',
				'operators'    => array( 'is', 'greater_than', 'less_than', 'between' ),
				'value_type'   => 'numeric',
				'value_source' => 'currency',
			),
			'product.stock_status' => array(
				'id'           => 'product.stock_status',
				'target'       => 'stock_status',
				'label'        => __( 'Stock status', 'reactwoo-geo-commerce' ),
				'group'        => 'woocommerce',
				'operators'    => array( 'is', 'is_not' ),
				'value_type'   => 'select',
				'value_source' => 'stock_statuses',
			),
			'cart.subtotal' => array(
				'id'           => 'cart.subtotal',
				'target'       => 'cart_subtotal',
				'label'        => __( 'Cart subtotal', 'reactwoo-geo-commerce' ),
				'group'        => 'cart_checkout',
				'operators'    => array( 'greater_than', 'less_than', 'between', 'is' ),
				'value_type'   => 'numeric',
				'value_source' => 'currency',
			),
			'cart.item_count' => array(
				'id'           => 'cart.item_count',
				'target'       => 'cart_item_count',
				'label'        => __( 'Cart item count', 'reactwoo-geo-commerce' ),
				'group'        => 'cart_checkout',
				'operators'    => array( 'is', 'greater_than', 'less_than' ),
				'value_type'   => 'numeric',
				'value_source' => '',
			),
			'cart.shipping_country' => array(
				'id'           => 'cart.shipping_country',
				'target'       => 'shipping_country',
				'label'        => __( 'Shipping country', 'reactwoo-geo-commerce' ),
				'group'        => 'cart_checkout',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'country_select',
				'value_source' => 'countries',
			),
			'cart.billing_country' => array(
				'id'           => 'cart.billing_country',
				'target'       => 'billing_country',
				'label'        => __( 'Billing country', 'reactwoo-geo-commerce' ),
				'group'        => 'cart_checkout',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'country_select',
				'value_source' => 'countries',
			),
			'customer.logged_in' => array(
				'id'           => 'customer.logged_in',
				'target'       => 'logged_in',
				'label'        => __( 'Logged-in status', 'reactwoo-geo-commerce' ),
				'group'        => 'customer',
				'operators'    => array( 'is' ),
				'value_type'   => 'boolean_select',
				'value_source' => 'logged_in_status',
			),
			'customer.role' => array(
				'id'           => 'customer.role',
				'target'       => 'user_role',
				'label'        => __( 'User role', 'reactwoo-geo-commerce' ),
				'group'        => 'customer',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'role_select',
				'value_source' => 'wp_roles',
			),
			'customer.segment' => array(
				'id'           => 'customer.segment',
				'target'       => 'customer_segment',
				'label'        => __( 'Customer group / segment', 'reactwoo-geo-commerce' ),
				'group'        => 'customer',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'text',
				'value_source' => '',
			),
			'session.device_type' => array(
				'id'           => 'session.device_type',
				'target'       => 'device_type',
				'label'        => __( 'Device type', 'reactwoo-geo-commerce' ),
				'group'        => 'device_session',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_type'   => 'select',
				'value_source' => 'device_types',
			),
			'session.language' => array(
				'id'           => 'session.language',
				'target'       => 'language',
				'label'        => __( 'Browser language', 'reactwoo-geo-commerce' ),
				'group'        => 'device_session',
				'operators'    => array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' ),
				'value_type'   => 'text',
				'value_source' => '',
			),
			'session.utm_campaign' => array(
				'id'           => 'session.utm_campaign',
				'target'       => 'utm_campaign',
				'label'        => __( 'UTM campaign', 'reactwoo-geo-commerce' ),
				'group'        => 'device_session',
				'operators'    => array( 'is', 'is_not', 'contains', 'not_contains' ),
				'value_type'   => 'text',
				'value_source' => '',
			),
			'session.returning_visitor' => array(
				'id'           => 'session.returning_visitor',
				'target'       => 'returning_visitor',
				'label'        => __( 'Returning visitor', 'reactwoo-geo-commerce' ),
				'group'        => 'device_session',
				'operators'    => array( 'is' ),
				'value_type'   => 'boolean_select',
				'value_source' => 'yes_no',
			),
			'session.new_visitor' => array(
				'id'           => 'session.new_visitor',
				'target'       => 'new_visitor',
				'label'        => __( 'New visitor', 'reactwoo-geo-commerce' ),
				'group'        => 'device_session',
				'operators'    => array( 'is' ),
				'value_type'   => 'boolean_select',
				'value_source' => 'yes_no',
			),
		);

		/**
		 * Filter commerce condition library fields.
		 *
		 * @param array<string, array<string, mixed>> $fields Field definitions.
		 */
		return apply_filters( 'rwgcm_condition_library_fields', $fields );
	}

	/**
	 * Resolve field definition by stored target key or field id.
	 *
	 * @param string $key Target key or field id.
	 * @return array<string, mixed>|null
	 */
	public static function get_field_by_key( $key ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			return null;
		}
		foreach ( self::get_fields() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( isset( $field['id'] ) && sanitize_key( (string) $field['id'] ) === $key ) {
				return $field;
			}
			if ( isset( $field['target'] ) && sanitize_key( (string) $field['target'] ) === $key ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Operator labels for UI.
	 *
	 * @return array<string, string>
	 */
	public static function get_operator_labels() {
		return array(
			'is'             => __( 'is', 'reactwoo-geo-commerce' ),
			'is_not'         => __( 'is not', 'reactwoo-geo-commerce' ),
			'in'             => __( 'is one of', 'reactwoo-geo-commerce' ),
			'not_in'         => __( 'is not one of', 'reactwoo-geo-commerce' ),
			'contains'       => __( 'contains', 'reactwoo-geo-commerce' ),
			'not_contains'   => __( 'does not contain', 'reactwoo-geo-commerce' ),
			'greater_than'   => __( 'greater than', 'reactwoo-geo-commerce' ),
			'less_than'      => __( 'less than', 'reactwoo-geo-commerce' ),
			'between'        => __( 'between', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * Value option sources for JS-driven selectors.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_value_sources() {
		$sources = array(
			'countries' => array(),
			'currencies' => array(),
			'product_categories' => array(),
			'product_tags' => array(),
			'wp_roles' => array(),
			'logged_in_status' => array(
				'1' => __( 'Logged in', 'reactwoo-geo-commerce' ),
				'0' => __( 'Logged out', 'reactwoo-geo-commerce' ),
			),
			'yes_no' => array(
				'1' => __( 'Yes', 'reactwoo-geo-commerce' ),
				'0' => __( 'No', 'reactwoo-geo-commerce' ),
			),
			'stock_statuses' => array(
				'instock'     => __( 'In stock', 'reactwoo-geo-commerce' ),
				'outofstock'  => __( 'Out of stock', 'reactwoo-geo-commerce' ),
				'onbackorder' => __( 'On backorder', 'reactwoo-geo-commerce' ),
			),
			'device_types' => array(
				'desktop' => __( 'Desktop', 'reactwoo-geo-commerce' ),
				'mobile'  => __( 'Mobile', 'reactwoo-geo-commerce' ),
				'tablet'  => __( 'Tablet', 'reactwoo-geo-commerce' ),
			),
		);

		if ( class_exists( 'RWGC_Countries', false ) ) {
			$sources['countries'] = RWGC_Countries::get_options();
		}
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$sources['currencies'] = RWGC_Countries::get_currency_options();
		}

		$cat_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( ! is_wp_error( $cat_terms ) && is_array( $cat_terms ) ) {
			foreach ( $cat_terms as $term ) {
				if ( is_object( $term ) && isset( $term->term_id, $term->name ) ) {
					$sources['product_categories'][ (string) (int) $term->term_id ] = (string) $term->name;
				}
			}
		}

		$tag_terms = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( ! is_wp_error( $tag_terms ) && is_array( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				if ( is_object( $term ) && isset( $term->term_id, $term->name ) ) {
					$sources['product_tags'][ (string) (int) $term->term_id ] = (string) $term->name;
				}
			}
		}

		global $wp_roles;
		if ( isset( $wp_roles ) && is_object( $wp_roles ) && ! empty( $wp_roles->roles ) ) {
			foreach ( $wp_roles->roles as $slug => $role ) {
				$sources['wp_roles'][ sanitize_key( (string) $slug ) ] = isset( $role['name'] ) ? (string) $role['name'] : (string) $slug;
			}
		}

		/**
		 * Filter value sources for condition builder selectors.
		 *
		 * @param array<string, array<string, string>> $sources Value sources.
		 */
		return apply_filters( 'rwgcm_condition_value_sources', $sources );
	}

	/**
	 * Resolve a human-readable label for a stored condition value.
	 *
	 * @param array<string, mixed> $condition Condition row.
	 * @return string
	 */
	public static function resolve_value_label( array $condition ) {
		if ( ! empty( $condition['label'] ) ) {
			return (string) $condition['label'];
		}

		$field = self::get_field_by_key( isset( $condition['field'] ) ? (string) $condition['field'] : ( isset( $condition['target'] ) ? (string) $condition['target'] : '' ) );
		$value = isset( $condition['value'] ) ? $condition['value'] : '';
		if ( null === $field ) {
			return is_scalar( $value ) ? (string) $value : '';
		}

		$sources = self::get_value_sources();
		$source  = isset( $field['value_source'] ) ? (string) $field['value_source'] : '';

		if ( 'country_select' === ( $field['value_type'] ?? '' ) && is_string( $value ) && class_exists( 'RWGC_Countries', false ) ) {
			$countries = RWGC_Countries::get_options();
			$code      = strtoupper( substr( $value, 0, 2 ) );
			return isset( $countries[ $code ] ) ? (string) $countries[ $code ] : $code;
		}

		if ( '' !== $source && isset( $sources[ $source ] ) && is_scalar( $value ) ) {
			$key = (string) $value;
			if ( isset( $sources[ $source ][ $key ] ) ) {
				return (string) $sources[ $source ][ $key ];
			}
		}

		if ( 'boolean_select' === ( $field['value_type'] ?? '' ) ) {
			return '1' === (string) $value || true === $value || 'yes' === (string) $value
				? __( 'Yes', 'reactwoo-geo-commerce' )
				: __( 'No', 'reactwoo-geo-commerce' );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
