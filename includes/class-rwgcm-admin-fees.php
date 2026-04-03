<?php
/**
 * Admin: geo cart fee rules (country + label + amount).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submenu under Geo Core for fee rules.
 */
class RWGCM_Admin_Fees {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgcm_save_fees', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_fees' );

		$enabled   = isset( $_POST['rwgcm_fees_enabled'] ) ? 1 : 0;
		$countries = isset( $_POST['rwgcm_fee_country'] ) && is_array( $_POST['rwgcm_fee_country'] ) ? wp_unslash( $_POST['rwgcm_fee_country'] ) : array();
		$names     = isset( $_POST['rwgcm_fee_name'] ) && is_array( $_POST['rwgcm_fee_name'] ) ? wp_unslash( $_POST['rwgcm_fee_name'] ) : array();
		$amounts    = isset( $_POST['rwgcm_fee_amount'] ) && is_array( $_POST['rwgcm_fee_amount'] ) ? wp_unslash( $_POST['rwgcm_fee_amount'] ) : array();
		$tax_matrix = isset( $_POST['rwgcm_fee_taxable'] ) && is_array( $_POST['rwgcm_fee_taxable'] ) ? wp_unslash( $_POST['rwgcm_fee_taxable'] ) : array();
		$tax_class_in = isset( $_POST['rwgcm_fee_tax_class'] ) && is_array( $_POST['rwgcm_fee_tax_class'] ) ? wp_unslash( $_POST['rwgcm_fee_tax_class'] ) : array();

		$rules = array();
		$max   = max( count( $countries ), count( $names ), count( $amounts ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$rules[] = array(
				'country'   => isset( $countries[ $i ] ) ? (string) $countries[ $i ] : '',
				'name'      => isset( $names[ $i ] ) ? (string) $names[ $i ] : '',
				'amount'    => isset( $amounts[ $i ] ) ? (string) $amounts[ $i ] : '0',
				'taxable'   => ! empty( $tax_matrix[ $i ] ),
				'tax_class' => isset( $tax_class_in[ $i ] ) ? (string) $tax_class_in[ $i ] : '',
			);
		}

		$saved = RWGCM_Fee_Rules::sanitize(
			array(
				'enabled' => $enabled,
				'rules'   => $rules,
			)
		);
		update_option( RWGCM_Fee_Rules::OPTION_KEY, $saved, false );

		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-fees&updated=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config       = RWGCM_Fee_Rules::get_all();
		$wc_countries = array();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$wc_countries = WC()->countries->get_countries();
		}
		$tax_class_options = function_exists( 'wc_get_product_tax_class_options' ) ? wc_get_product_tax_class_options() : array( '' => __( 'Standard', 'woocommerce' ) );
		$rwgc_nav_current = 'rwgcm-fees';
		include RWGCM_PATH . 'admin/views/fee-rules.php';
	}
}
