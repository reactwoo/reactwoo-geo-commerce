<?php
/**
 * Generic rules admin (Geo Core targets + custom table).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD UI for wp_{prefix}rwgcm_rules.
 */
class RWGCM_Admin_Rules {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgcm_save_generic_rule', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_rwgcm_delete_generic_rule', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_generic_rule' );

		$rule = self::rule_array_from_post();
		if ( empty( $rule ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_error=1' ) );
			exit;
		}

		$rule_id = isset( $rule['id'] ) ? absint( $rule['id'] ) : 0;
		if ( $rule_id > 0 ) {
			$ok = RWGCM_Rule_Store::update_rule( $rule_id, $rule );
			$url = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=' . $rule_id . '&updated=' . ( $ok ? '1' : '0' ) );
		} else {
			unset( $rule['id'] );
			$new_id = RWGCM_Rule_Store::insert_rule( $rule );
			$ok     = $new_id > 0;
			$url    = $ok
				? admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=' . (int) $new_id . '&updated=1' )
				: admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_error=1' );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		$rule_id = isset( $_GET['rule_id'] ) ? absint( wp_unslash( $_GET['rule_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $rule_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-pricing' ) );
			exit;
		}
		check_admin_referer( 'rwgcm_delete_rule_' . $rule_id );

		RWGCM_Rule_Store::delete_rule( $rule_id );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-pricing&deleted=1' ) );
		exit;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function rule_array_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in handle_save.
		$id = isset( $_POST['rwgcm_rule_id'] ) ? absint( wp_unslash( $_POST['rwgcm_rule_id'] ) ) : 0;

		$label = isset( $_POST['rwgcm_rule_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_rule_label'] ) ) : '';
		if ( '' === $label ) {
			$label = __( 'Untitled rule', 'reactwoo-geo-commerce' );
		}

		$status = isset( $_POST['rwgcm_rule_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_rule_status'] ) ) : 'active';
		if ( ! in_array( $status, array( 'active', 'draft', 'disabled' ), true ) ) {
			$status = 'active';
		}

		$priority = isset( $_POST['rwgcm_rule_priority'] ) ? absint( wp_unslash( $_POST['rwgcm_rule_priority'] ) ) : 100;

		$scope_type = isset( $_POST['rwgcm_scope_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_scope_type'] ) ) : 'global';
		if ( ! in_array( $scope_type, array( 'global', 'product_category', 'product', 'cart' ), true ) ) {
			$scope_type = 'global';
		}

		$scope_ids = array();
		if ( 'product_category' === $scope_type && isset( $_POST['rwgcm_scope_cats'] ) && is_array( $_POST['rwgcm_scope_cats'] ) ) {
			foreach ( wp_unslash( $_POST['rwgcm_scope_cats'] ) as $tid ) {
				$scope_ids[] = absint( $tid );
			}
			$scope_ids = array_values( array_filter( array_unique( $scope_ids ) ) );
		}
		if ( 'product' === $scope_type && isset( $_POST['rwgcm_scope_product'] ) ) {
			$pid = absint( wp_unslash( $_POST['rwgcm_scope_product'] ) );
			if ( $pid > 0 ) {
				$scope_ids = array( $pid );
			}
		}

		$match = isset( $_POST['rwgcm_conditions_match'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_conditions_match'] ) ) : 'all';
		if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
			$match = 'all';
		}

		$items = array();
		if ( isset( $_POST['rwgcm_cond_target'] ) && is_array( $_POST['rwgcm_cond_target'] ) ) {
			$targets   = wp_unslash( $_POST['rwgcm_cond_target'] );
			$operators = isset( $_POST['rwgcm_cond_operator'] ) && is_array( $_POST['rwgcm_cond_operator'] ) ? wp_unslash( $_POST['rwgcm_cond_operator'] ) : array();
			$values    = isset( $_POST['rwgcm_cond_value'] ) && is_array( $_POST['rwgcm_cond_value'] ) ? wp_unslash( $_POST['rwgcm_cond_value'] ) : array();
			foreach ( $targets as $i => $target_raw ) {
				$target = sanitize_key( (string) $target_raw );
				if ( '' === $target ) {
					continue;
				}
				$op = isset( $operators[ $i ] ) ? sanitize_key( (string) $operators[ $i ] ) : 'is';
				$val = isset( $values[ $i ] ) ? sanitize_text_field( (string) $values[ $i ] ) : '';
				$items[] = array(
					'target'   => $target,
					'operator' => $op,
					'value'    => $val,
				);
			}
		}

		$mode = isset( $_POST['rwgcm_pa_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_pa_mode'] ) ) : 'percent';
		if ( ! in_array( $mode, array( 'percent', 'fixed_line' ), true ) ) {
			$mode = 'percent';
		}
		$pav = isset( $_POST['rwgcm_pa_value'] ) ? floatval( wp_unslash( $_POST['rwgcm_pa_value'] ) ) : 0.0;

		$rule = array(
			'id'         => $id,
			'label'      => $label,
			'status'     => $status,
			'priority'   => $priority,
			'scope'      => array(
				'type' => $scope_type,
				'ids'  => $scope_ids,
			),
			'conditions' => array(
				'match' => $match,
				'items' => $items,
			),
			'actions'    => array(
				array(
					'type'  => 'price_adjustment',
					'mode'  => $mode,
					'value' => $pav,
				),
			),
			'meta'       => array(
				'source' => 'admin_generic_rules',
			),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $rule;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! RWGCM_DB::rules_table_exists() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>';
			esc_html_e( 'Rules table is missing. Reload wp-admin or re-activate Geo Commerce to run the database installer.', 'reactwoo-geo-commerce' );
			echo '</p></div></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = isset( $_GET['rwgcm_edit'] ) ? wp_unslash( $_GET['rwgcm_edit'] ) : '';

		if ( 'new' === $edit ) {
			self::render_edit( null );
			return;
		}
		if ( is_numeric( $edit ) && (int) $edit > 0 ) {
			$loaded = RWGCM_Rule_Store::get_rule( (int) $edit );
			if ( null === $loaded ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_error=notfound' ) );
				exit;
			}
			self::render_edit( $loaded );
			return;
		}

		self::render_list();
	}

	/**
	 * @param array<string, mixed>|null $rule Rule or null for new.
	 * @return void
	 */
	private static function render_edit( $rule ) {
		$is_new = null === $rule;
		if ( $is_new ) {
			$rule = array();
		} elseif ( ! is_array( $rule ) ) {
			return;
		}
		$wc_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 200,
			)
		);
		if ( is_wp_error( $wc_cats ) || ! is_array( $wc_cats ) ) {
			$wc_cats = array();
		}

		if ( class_exists( 'RWGC_Target_Registry', false ) ) {
			RWGC_Target_Registry::init();
		}
		$target_defs = function_exists( 'rwgc_get_target_types' ) ? rwgc_get_target_types() : array();
		$operators   = class_exists( 'RWGC_Target_Operators', false ) ? RWGC_Target_Operators::all() : array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' );

		$rwgc_nav_current = 'rwgcm-pricing';
		include RWGCM_PATH . 'admin/views/rules-generic-edit.php';
	}

	/**
	 * @return void
	 */
	private static function render_list() {
		$rules            = RWGCM_Rule_Store::get_all_rules();
		$rwgc_nav_current = 'rwgcm-pricing';
		include RWGCM_PATH . 'admin/views/rules-generic-list.php';
	}
}
