<?php
/**
 * WooCommerce orders list: visitor country column (HPOS + legacy) and optional sort by that meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin list table column for {@see RWGCM_Order_Geo::META_COUNTRY}.
 */
class RWGCM_Admin_Orders_List {

	const COLUMN = 'rwgcm_visitor_country';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'hpos_columns' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'hpos_cell' ), 10, 2 );
		add_filter( 'woocommerce_shop_order_list_table_sortable_columns', array( __CLASS__, 'hpos_sortable' ) );
		add_filter( 'woocommerce_order_query_args', array( __CLASS__, 'order_query_args' ) );

		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'legacy_columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'legacy_cell' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_sortable_columns', array( __CLASS__, 'legacy_sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'legacy_orderby' ) );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function hpos_columns( $columns ) {
		return self::inject_column( $columns );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function legacy_columns( $columns ) {
		return self::inject_column( $columns );
	}

	/**
	 * @param array<string, string>|mixed $columns Columns.
	 * @return array<string, string>
	 */
	private static function inject_column( $columns ) {
		if ( ! is_array( $columns ) ) {
			return array();
		}
		$new      = array();
		$inserted = false;
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_number' === $key ) {
				$new[ self::COLUMN ] = __( 'Visitor country', 'reactwoo-geo-commerce' );
				$inserted            = true;
			}
		}
		if ( ! $inserted ) {
			$new[ self::COLUMN ] = __( 'Visitor country', 'reactwoo-geo-commerce' );
		}
		return $new;
	}

	/**
	 * @param string     $column Column id.
	 * @param \WC_Order  $order  Order.
	 * @return void
	 */
	public static function hpos_cell( $column, $order ) {
		if ( self::COLUMN !== $column || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		echo esc_html( self::format_cell( $order ) );
	}

	/**
	 * @param string $column  Column id.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function legacy_cell( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}
		$post_id = absint( $post_id );
		$order   = $post_id > 0 ? wc_get_order( $post_id ) : false;
		if ( ! $order ) {
			echo esc_html( '—' );
			return;
		}
		echo esc_html( self::format_cell( $order ) );
	}

	/**
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	private static function format_cell( $order ) {
		$cc = $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true );
		if ( is_string( $cc ) && strlen( $cc ) === 2 ) {
			return strtoupper( $cc );
		}
		return '—';
	}

	/**
	 * HPOS list: make Visitor country sortable (orderby value must match {@see self::COLUMN}).
	 *
	 * @param array<string, string> $sortable Sortable columns.
	 * @return array<string, string>
	 */
	public static function hpos_sortable( $sortable ) {
		if ( ! is_array( $sortable ) ) {
			return array();
		}
		$sortable[ self::COLUMN ] = self::COLUMN;
		return $sortable;
	}

	/**
	 * Legacy CPT list: same sortable registration.
	 *
	 * @param array<string, string> $sortable Sortable columns.
	 * @return array<string, string>
	 */
	public static function legacy_sortable( $sortable ) {
		if ( ! is_array( $sortable ) ) {
			return array();
		}
		$sortable[ self::COLUMN ] = self::COLUMN;
		return $sortable;
	}

	/**
	 * HPOS: map custom orderby to order meta (WooCommerce `wc_get_orders` / COT meta sort).
	 *
	 * @param array<string, mixed> $args Query vars.
	 * @return array<string, mixed>
	 */
	public static function order_query_args( $args ) {
		if ( ! is_admin() || empty( $args['orderby'] ) || self::COLUMN !== $args['orderby'] ) {
			return $args;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin list table GET.
		if ( empty( $_GET['page'] ) || 'wc-orders' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return $args;
		}

		$order = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'ASC';
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$args['orderby'] = 'meta_value';
		$args['order']   = $order;

		$clause = array( 'key' => RWGCM_Order_Geo::META_COUNTRY );
		if ( ! empty( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				$clause,
				$args['meta_query'],
			);
		} else {
			$args['meta_query'] = array( $clause );
		}

		return $args;
	}

	/**
	 * Legacy posts table: sort shop_order by visitor country meta.
	 *
	 * @param \WP_Query $q Query.
	 * @return void
	 */
	public static function legacy_orderby( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', false ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return;
		}
		if ( 'shop_order' !== $q->get( 'post_type' ) ) {
			return;
		}
		if ( self::COLUMN !== $q->get( 'orderby' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin list table GET.
		$dir = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'ASC';
		$dir = in_array( $dir, array( 'ASC', 'DESC' ), true ) ? $dir : 'ASC';

		$q->set( 'meta_key', RWGCM_Order_Geo::META_COUNTRY );
		$q->set( 'orderby', 'meta_value' );
		$q->set( 'order', $dir );
	}
}
