<?php
/**
 * Product overlays admin (canonical product + Geo Core conditions).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD UI for wp_{prefix}rwgcm_product_overlays.
 */
class RWGCM_Admin_Overlays {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgcm_save_overlay', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_rwgcm_delete_overlay', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_overlay' );

		$payload = self::overlay_from_post();
		if ( null === $payload ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_error=1' ) );
			exit;
		}

		$id = isset( $payload['_id'] ) ? absint( $payload['_id'] ) : 0;
		unset( $payload['_id'] );

		if ( $id > 0 ) {
			$ok = RWGCM_Product_Overlay_Store::update_overlay( $id, $payload );
			$url = admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_overlay_edit=' . $id . '&updated=' . ( $ok ? '1' : '0' ) );
		} else {
			$new_id = RWGCM_Product_Overlay_Store::insert_overlay( $payload );
			$ok     = $new_id > 0;
			$url    = $ok
				? admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_overlay_edit=' . (int) $new_id . '&updated=1' )
				: admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_error=1' );
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
		$oid = isset( $_GET['overlay_id'] ) ? absint( wp_unslash( $_GET['overlay_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $oid <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-product-overlays' ) );
			exit;
		}
		check_admin_referer( 'rwgcm_delete_overlay_' . $oid );

		RWGCM_Product_Overlay_Store::delete_overlay( $oid );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-product-overlays&deleted=1' ) );
		exit;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function overlay_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in handle_save.
		$id = isset( $_POST['rwgcm_overlay_id'] ) ? absint( wp_unslash( $_POST['rwgcm_overlay_id'] ) ) : 0;

		$product_id = isset( $_POST['rwgcm_overlay_product_id'] ) ? absint( wp_unslash( $_POST['rwgcm_overlay_product_id'] ) ) : 0;
		if ( $product_id > 0 && function_exists( 'wc_get_product' ) && ! wc_get_product( $product_id ) ) {
			return null;
		}

		$label = isset( $_POST['rwgcm_overlay_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_overlay_label'] ) ) : '';
		if ( '' === $label ) {
			$label = __( 'Product overlay', 'reactwoo-geo-commerce' );
		}

		$status = isset( $_POST['rwgcm_overlay_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_overlay_status'] ) ) : 'active';
		if ( ! in_array( $status, array( 'active', 'draft', 'disabled' ), true ) ) {
			$status = 'active';
		}

		$priority = isset( $_POST['rwgcm_overlay_priority'] ) ? absint( wp_unslash( $_POST['rwgcm_overlay_priority'] ) ) : 100;

		$match = isset( $_POST['rwgcm_overlay_conditions_match'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgcm_overlay_conditions_match'] ) ) : 'all';
		if ( ! in_array( $match, array( 'all', 'any' ), true ) ) {
			$match = 'all';
		}
		$items = array();
		if ( isset( $_POST['rwgcm_overlay_cond_target'] ) && is_array( $_POST['rwgcm_overlay_cond_target'] ) ) {
			$targets   = wp_unslash( $_POST['rwgcm_overlay_cond_target'] );
			$operators = isset( $_POST['rwgcm_overlay_cond_operator'] ) && is_array( $_POST['rwgcm_overlay_cond_operator'] ) ? wp_unslash( $_POST['rwgcm_overlay_cond_operator'] ) : array();
			$values    = isset( $_POST['rwgcm_overlay_cond_value'] ) && is_array( $_POST['rwgcm_overlay_cond_value'] ) ? wp_unslash( $_POST['rwgcm_overlay_cond_value'] ) : array();
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

		$overrides = array();
		if ( ! empty( $_POST['rwgcm_ov_title_enabled'] ) ) {
			$overrides['title'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_ov_title'] ) ) : '',
			);
		}
		if ( ! empty( $_POST['rwgcm_ov_short_enabled'] ) ) {
			$overrides['short_description'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_short'] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_ov_short'] ) ) : '',
			);
		}
		if ( ! empty( $_POST['rwgcm_ov_desc_enabled'] ) ) {
			$overrides['description'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_desc'] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_ov_desc'] ) ) : '',
			);
		}
		if ( ! empty( $_POST['rwgcm_ov_gallery_enabled'] ) ) {
			$overrides['gallery'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_gallery'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_ov_gallery'] ) ) : '',
			);
		}
		if ( ! empty( $_POST['rwgcm_ov_badge_enabled'] ) ) {
			$overrides['badge'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_badge'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgcm_ov_badge'] ) ) : '',
			);
		}
		if ( ! empty( $_POST['rwgcm_ov_cta_enabled'] ) ) {
			$overrides['cta'] = array(
				'enabled' => true,
				'value'   => isset( $_POST['rwgcm_ov_cta'] ) ? wp_kses_post( wp_unslash( (string) $_POST['rwgcm_ov_cta'] ) ) : '',
			);
		}

		$out = array(
			'product_id' => $product_id,
			'label'      => $label,
			'status'     => $status,
			'priority'   => $priority,
			'conditions' => array(
				'match' => $match,
				'items' => $items,
			),
			'overrides'  => $overrides,
			'meta'       => array(
				'source' => 'admin_overlays',
			),
			'_id'        => $id,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $out;
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>';
			esc_html_e( 'Overlay table is missing. Re-activate Geo Commerce or load wp-admin once to install tables.', 'reactwoo-geo-commerce' );
			echo '</p></div></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = isset( $_GET['rwgcm_overlay_edit'] ) ? wp_unslash( $_GET['rwgcm_overlay_edit'] ) : '';

		if ( 'new' === $edit ) {
			self::render_edit( null );
			return;
		}
		if ( is_numeric( $edit ) && (int) $edit > 0 ) {
			$loaded = RWGCM_Product_Overlay_Store::get_overlay( (int) $edit );
			if ( null === $loaded ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_error=notfound' ) );
				exit;
			}
			self::render_edit( $loaded );
			return;
		}

		self::render_list();
	}

	/**
	 * @param array<string, mixed>|null $overlay Overlay row.
	 * @return void
	 */
	private static function render_edit( $overlay ) {
		$is_new = null === $overlay;
		if ( $is_new ) {
			$overlay = array();
		} elseif ( ! is_array( $overlay ) ) {
			return;
		}

		if ( class_exists( 'RWGC_Target_Registry', false ) ) {
			RWGC_Target_Registry::init();
		}
		$target_defs = function_exists( 'rwgc_get_target_types' ) ? rwgc_get_target_types() : array();
		$operators   = class_exists( 'RWGC_Target_Operators', false ) ? RWGC_Target_Operators::all() : array( 'is', 'is_not' );

		$rwgc_nav_current = 'rwgcm-product-overlays';
		include RWGCM_PATH . 'admin/views/overlays-edit.php';
	}

	/**
	 * @return void
	 */
	private static function render_list() {
		$overlays         = RWGCM_Product_Overlay_Store::get_all_for_admin();
		$rwgc_nav_current = 'rwgcm-product-overlays';
		include RWGCM_PATH . 'admin/views/overlays-list.php';
	}
}
