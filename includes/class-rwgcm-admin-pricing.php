<?php
/**
 * Pricing rules admin (WooCommerce country select — no CSV targeting).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submenu under Geo Core for pricing rules.
 */
class RWGCM_Admin_Pricing {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgcm_save_pricing', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_pricing' );

		$enabled = isset( $_POST['rwgcm_pricing_enabled'] ) ? 1 : 0;
		$countries = isset( $_POST['rwgcm_rule_country'] ) && is_array( $_POST['rwgcm_rule_country'] ) ? wp_unslash( $_POST['rwgcm_rule_country'] ) : array();
		$types     = isset( $_POST['rwgcm_rule_type'] ) && is_array( $_POST['rwgcm_rule_type'] ) ? wp_unslash( $_POST['rwgcm_rule_type'] ) : array();
		$values    = isset( $_POST['rwgcm_rule_value'] ) && is_array( $_POST['rwgcm_rule_value'] ) ? wp_unslash( $_POST['rwgcm_rule_value'] ) : array();
		$cat_matrix = isset( $_POST['rwgcm_rule_category_ids'] ) && is_array( $_POST['rwgcm_rule_category_ids'] ) ? wp_unslash( $_POST['rwgcm_rule_category_ids'] ) : array();
		$labels  = isset( $_POST['rwgcm_rule_label'] ) && is_array( $_POST['rwgcm_rule_label'] ) ? wp_unslash( $_POST['rwgcm_rule_label'] ) : array();
		$actives = isset( $_POST['rwgcm_rule_active'] ) && is_array( $_POST['rwgcm_rule_active'] ) ? wp_unslash( $_POST['rwgcm_rule_active'] ) : array();

		$rules = array();
		$max   = max( count( $countries ), count( $types ), count( $values ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$cats = array();
			if ( isset( $cat_matrix[ $i ] ) && is_array( $cat_matrix[ $i ] ) ) {
				$cats = $cat_matrix[ $i ];
			}
			$rules[] = array(
				'country'      => isset( $countries[ $i ] ) ? (string) $countries[ $i ] : '',
				'type'         => isset( $types[ $i ] ) ? (string) $types[ $i ] : 'percent',
				'value'        => isset( $values[ $i ] ) ? (string) $values[ $i ] : '0',
				'category_ids' => $cats,
				'label'        => isset( $labels[ $i ] ) ? (string) $labels[ $i ] : '',
				'active'       => isset( $actives[ $i ] ) && '1' === (string) $actives[ $i ],
			);
		}

		$saved = RWGCM_Pricing_Rules::sanitize(
			array(
				'enabled' => $enabled,
				'rules'   => $rules,
			)
		);
		update_option( RWGCM_Pricing_Rules::OPTION_KEY, $saved, false );

		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-pricing&updated=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = RWGCM_Pricing_Rules::get_all();
		$wc_countries = array();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$wc_countries = WC()->countries->get_countries();
		}
		$product_categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		if ( is_wp_error( $product_categories ) || ! is_array( $product_categories ) ) {
			$product_categories = array();
		}
		$rwgc_nav_current = 'rwgcm-pricing';
		include RWGCM_PATH . 'admin/views/pricing-rules.php';
	}
}
