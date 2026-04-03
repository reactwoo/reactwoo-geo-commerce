<?php
/**
 * Geo Commerce — wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo Commerce.
 */
class RWGCM_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
		add_action( 'admin_post_rwgcm_save_dashboard', array( __CLASS__, 'handle_save_dashboard' ) );
		add_filter( 'rwgc_inner_nav_items', array( __CLASS__, 'filter_inner_nav_items' ), 12, 1 );
	}

	/**
	 * Add Geo Commerce screens to the shared Geo Core inner nav.
	 *
	 * @param array $items Page slug => label.
	 * @return array
	 */
	public static function filter_inner_nav_items( $items ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		return array_merge(
			$items,
			array(
				'rwgcm-dashboard' => __( 'Geo Commerce', 'reactwoo-geo-commerce' ),
				'rwgcm-pricing'   => __( 'Commerce pricing', 'reactwoo-geo-commerce' ),
				'rwgcm-fees'      => __( 'Commerce fees', 'reactwoo-geo-commerce' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function handle_save_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_dashboard' );
		$store = isset( $_POST['rwgcm_store_utm'] ) ? 'yes' : 'no';
		update_option( RWGCM_Attribution::OPTION_STORE_UTM, $store, false );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-dashboard&updated=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'rwgc-dashboard',
			__( 'Geo Commerce', 'reactwoo-geo-commerce' ),
			__( 'Geo Commerce', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sample = array();
		if ( function_exists( 'rwgc_get_visitor_data' ) ) {
			$sample = rwgc_get_visitor_data();
		}
		$rwgcm_pricing_status = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		if ( class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			$p = RWGCM_Pricing_Rules::get_all();
			$rwgcm_pricing_status['enabled']    = ! empty( $p['enabled'] );
			$rwgcm_pricing_status['rule_count'] = isset( $p['rules'] ) && is_array( $p['rules'] ) ? count( $p['rules'] ) : 0;
		}
		$rwgcm_fee_status = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		if ( class_exists( 'RWGCM_Fee_Rules', false ) ) {
			$f = RWGCM_Fee_Rules::get_all();
			$rwgcm_fee_status['enabled']    = ! empty( $f['enabled'] );
			$rwgcm_fee_status['rule_count'] = isset( $f['rules'] ) && is_array( $f['rules'] ) ? count( $f['rules'] ) : 0;
		}
		$rwgc_nav_current = 'rwgcm-dashboard';
		include RWGCM_PATH . 'admin/views/dashboard.php';
	}
}
