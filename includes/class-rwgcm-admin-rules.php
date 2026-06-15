<?php
/**
 * Admin CRUD for generic commerce rules (unified Rules model).
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
		if ( ! in_array( $scope_type, array( 'global', 'product_category', 'product', 'cart', 'checkout', 'site' ), true ) ) {
			$scope_type = 'global';
		}
		if ( 'checkout' === $scope_type || 'site' === $scope_type ) {
			$scope_type = 'cart';
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

		$items = self::conditions_from_post();

		$actions = self::actions_from_post();

		$use_portable = ! empty( $_POST['rwgcm_use_portable_targeting'] );
		$portable_raw = '';
		if ( isset( $_POST['rwgcm_portable_targeting'] ) ) {
			$portable_raw = wp_unslash( (string) $_POST['rwgcm_portable_targeting'] );
			$portable_raw = wp_check_invalid_utf8( $portable_raw, true );
		}

		$existing_meta = array();
		if ( $id > 0 ) {
			$existing = RWGCM_Rule_Store::get_rule( $id );
			if ( is_array( $existing ) && ! empty( $existing['meta'] ) && is_array( $existing['meta'] ) ) {
				$existing_meta = $existing['meta'];
			}
		}

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
			'actions'    => $actions,
			'meta'       => array_merge(
				$existing_meta,
				array(
					'source'                 => 'admin_generic_rules',
					'use_portable_targeting' => $use_portable,
					'portable_targeting'     => $portable_raw,
				)
			),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $rule;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function conditions_from_post() {
		$items = array();
		if ( ! isset( $_POST['rwgcm_cond_field'] ) || ! is_array( $_POST['rwgcm_cond_field'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $items;
		}

		$fields    = wp_unslash( $_POST['rwgcm_cond_field'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$operators = isset( $_POST['rwgcm_cond_operator'] ) && is_array( $_POST['rwgcm_cond_operator'] ) ? wp_unslash( $_POST['rwgcm_cond_operator'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$values    = isset( $_POST['rwgcm_cond_value'] ) && is_array( $_POST['rwgcm_cond_value'] ) ? wp_unslash( $_POST['rwgcm_cond_value'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$labels    = isset( $_POST['rwgcm_cond_label'] ) && is_array( $_POST['rwgcm_cond_label'] ) ? wp_unslash( $_POST['rwgcm_cond_label'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		foreach ( $fields as $i => $field_raw ) {
			$field_id = sanitize_key( (string) $field_raw );
			if ( '' === $field_id ) {
				continue;
			}
			$def = RWGCM_Condition_Library::get_field_by_key( $field_id );
			if ( null === $def || empty( $def['target'] ) ) {
				continue;
			}
			$op = isset( $operators[ $i ] ) ? sanitize_key( (string) $operators[ $i ] ) : 'is';
			$val = isset( $values[ $i ] ) ? $values[ $i ] : '';
			if ( 'facet_multi_select' === ( $def['value_type'] ?? '' ) ) {
				$val = is_array( $val ) ? $val : ( is_string( $val ) ? explode( ',', $val ) : array() );
				$val = RWGCM_Weather_Affinity::sanitize_facet_list( $val );
				if ( empty( $val ) ) {
					continue;
				}
				$val = implode( ',', $val );
			} else {
				$val = sanitize_text_field( (string) $val );
				if ( '' === $val ) {
					continue;
				}
			}
			$row = array(
				'field'    => $field_id,
				'target'   => sanitize_key( (string) $def['target'] ),
				'operator' => $op,
				'value'    => $val,
			);
			$label = isset( $labels[ $i ] ) ? sanitize_text_field( (string) $labels[ $i ] ) : '';
			if ( '' === $label ) {
				$label = RWGCM_Condition_Library::resolve_value_label( $row );
			}
			if ( '' !== $label ) {
				$row['label'] = $label;
			}
			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function actions_from_post() {
		$actions = array();
		if ( ! isset( $_POST['rwgcm_action_type'] ) || ! is_array( $_POST['rwgcm_action_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $actions;
		}

		$types = wp_unslash( $_POST['rwgcm_action_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $types as $i => $type_raw ) {
			$type = sanitize_key( (string) $type_raw );
			if ( '' === $type ) {
				continue;
			}
			$action = array( 'type' => $type );
			switch ( $type ) {
				case 'price_adjustment':
					$mode = isset( $_POST['rwgcm_action_pa_mode'][ $i ] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_action_pa_mode'][ $i ] ) ) : 'percent'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( ! in_array( $mode, array( 'percent', 'fixed_line' ), true ) ) {
						$mode = 'percent';
					}
					$action['mode']  = $mode;
					$action['value'] = isset( $_POST['rwgcm_action_pa_value'][ $i ] ) ? floatval( wp_unslash( $_POST['rwgcm_action_pa_value'][ $i ] ) ) : 0.0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					break;
				case 'product_badge':
				case 'product_notice':
				case 'shipping_notice':
				case 'stock_message':
					$action['text'] = isset( $_POST['rwgcm_action_text'][ $i ] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_action_text'][ $i ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					if ( 'product_badge' === $type ) {
						$action['style'] = isset( $_POST['rwgcm_action_badge_style'][ $i ] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_action_badge_style'][ $i ] ) ) : 'default'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
					break;
				case 'product_overlay':
					$action['field']   = isset( $_POST['rwgcm_action_overlay_field'][ $i ] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_action_overlay_field'][ $i ] ) ) : 'title'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$action['enabled'] = true;
					$action['value']   = isset( $_POST['rwgcm_action_overlay_value'][ $i ] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_action_overlay_value'][ $i ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					break;
				case 'product_visibility':
					$mode = isset( $_POST['rwgcm_action_visibility_mode'][ $i ] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_action_visibility_mode'][ $i ] ) ) : 'show'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$action['mode'] = in_array( $mode, array( 'show', 'hide' ), true ) ? $mode : 'show';
					break;
				case 'cta_override':
					$action['enabled'] = true;
					$action['value']   = isset( $_POST['rwgcm_action_cta_value'][ $i ] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_action_cta_value'][ $i ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					break;
				case 'custom_html':
					$action['value'] = isset( $_POST['rwgcm_action_html_value'][ $i ] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_action_html_value'][ $i ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
					break;
				default:
					continue 2;
			}
			$sanitized = RWGCM_Action_Resolver::sanitize_action( $type, $action );
			if ( is_array( $sanitized ) ) {
				$actions[] = $sanitized;
			}
		}

		return $actions;
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

		$condition_fields = RWGCM_Condition_Library::get_fields_for_ui();
		$condition_groups = RWGCM_Condition_Library::get_groups_for_ui();
		$operator_labels  = RWGCM_Condition_Library::get_operator_labels();
		$value_sources    = RWGCM_Condition_Library::get_value_sources();
		$action_options   = RWGCM_Action_Resolver::builder_action_options();

		$rwgc_nav_current = 'rwgcm-pricing';
		include RWGCM_PATH . 'admin/views/rules-generic-edit.php';
	}

	/**
	 * @return void
	 */
	private static function render_list() {
		$rules            = RWGCM_Rule_Store::get_all_rules();
		$filter           = isset( $_GET['rwgcm_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwgcm_filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'pricing' === $filter ) {
			$rules = array_values(
				array_filter(
					$rules,
					static function ( $rule ) {
						if ( ! is_array( $rule ) || empty( $rule['actions'] ) ) {
							return false;
						}
						foreach ( $rule['actions'] as $action ) {
							if ( is_array( $action ) && isset( $action['type'] ) && 'price_adjustment' === $action['type'] ) {
								return true;
							}
						}
						return false;
					}
				)
			);
		} elseif ( 'display' === $filter ) {
			$rules = array_values(
				array_filter(
					$rules,
					static function ( $rule ) {
						if ( ! is_array( $rule ) || empty( $rule['actions'] ) ) {
							return false;
						}
						foreach ( $rule['actions'] as $action ) {
							if ( ! is_array( $action ) || empty( $action['type'] ) ) {
								continue;
							}
							$type = sanitize_key( (string) $action['type'] );
							if ( 'price_adjustment' !== $type && 'cart_fee' !== $type ) {
								return true;
							}
						}
						return false;
					}
				)
			);
		}
		$rwgc_nav_current = 'rwgcm-pricing';
		include RWGCM_PATH . 'admin/views/rules-generic-list.php';
	}
}
