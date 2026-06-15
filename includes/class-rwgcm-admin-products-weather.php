<?php
/**
 * Product list column, bulk edit, and quick edit for weather facets.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce products admin list enhancements.
 */
class RWGCM_Admin_Products_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_action( 'woocommerce_product_bulk_edit_start', array( __CLASS__, 'bulk_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'bulk_edit_save' ) );
		add_action( 'woocommerce_product_quick_edit_start', array( __CLASS__, 'quick_edit_fields' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'quick_edit_save' ) );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'quick_edit_script' ) );
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_untagged_products' ) );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_notices' ) );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['rwgcm_weather'] = __( 'Weather', 'reactwoo-geo-commerce' );
			}
		}
		if ( ! isset( $new['rwgcm_weather'] ) ) {
			$new['rwgcm_weather'] = __( 'Weather', 'reactwoo-geo-commerce' );
		}
		return $new;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'rwgcm_weather' !== $column ) {
			return;
		}
		$facets = RWGCM_Weather_Affinity::get_product_facets( $post_id );
		if ( empty( $facets ) ) {
			echo '<span class="rwgcm-weather-col rwgcm-weather-col--empty">&mdash;</span>';
			return;
		}
		echo '<span class="rwgcm-weather-col" data-facets="' . esc_attr( implode( ',', $facets ) ) . '">' . esc_html( RWGCM_Weather_Affinity::format_facet_value_label( implode( ',', $facets ) ) ) . '</span>';
	}

	/**
	 * @return void
	 */
	public static function bulk_edit_fields() {
		$facets = RWGCM_Weather_Affinity::get_facet_definitions();
		if ( empty( $facets ) ) {
			return;
		}
		?>
		<div class="inline-edit-group rwgcm-bulk-weather">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Weather facets', 'reactwoo-geo-commerce' ); ?></span>
				<select name="rwgcm_bulk_weather_mode">
					<option value=""><?php esc_html_e( '— No change —', 'reactwoo-geo-commerce' ); ?></option>
					<option value="set"><?php esc_html_e( 'Set to', 'reactwoo-geo-commerce' ); ?></option>
					<option value="merge"><?php esc_html_e( 'Add to existing', 'reactwoo-geo-commerce' ); ?></option>
					<option value="clear"><?php esc_html_e( 'Clear all', 'reactwoo-geo-commerce' ); ?></option>
				</select>
			</label>
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Facets', 'reactwoo-geo-commerce' ); ?></span>
				<input type="text" name="rwgcm_bulk_weather_facets" class="text" placeholder="<?php esc_attr_e( 'wet, windy', 'reactwoo-geo-commerce' ); ?>" />
			</label>
		</div>
		<?php
	}

	/**
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	public static function bulk_edit_save( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce bulk edit.
		$mode = isset( $_REQUEST['rwgcm_bulk_weather_mode'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['rwgcm_bulk_weather_mode'] ) ) : '';
		if ( '' === $mode ) {
			return;
		}
		$pid = (int) $product->get_id();
		if ( 'clear' === $mode ) {
			RWGCM_Weather_Affinity::save_product_facets( $pid, array() );
			return;
		}
		$raw = isset( $_REQUEST['rwgcm_bulk_weather_facets'] ) ? wp_unslash( (string) $_REQUEST['rwgcm_bulk_weather_facets'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$incoming = RWGCM_Weather_Affinity::sanitize_facet_list( explode( ',', $raw ) );
		if ( empty( $incoming ) ) {
			return;
		}
		if ( 'merge' === $mode ) {
			$incoming = array_values( array_unique( array_merge( RWGCM_Weather_Affinity::get_product_facets( $pid ), $incoming ) ) );
		}
		RWGCM_Weather_Affinity::save_product_facets( $pid, $incoming );
	}

	/**
	 * @return void
	 */
	public static function quick_edit_fields() {
		?>
		<div class="inline-edit-group rwgcm-quick-weather">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Weather facets', 'reactwoo-geo-commerce' ); ?></span>
				<input type="text" name="rwgcm_quick_weather_facets" class="text rwgcm-quick-weather-facets" placeholder="<?php esc_attr_e( 'wet, cold', 'reactwoo-geo-commerce' ); ?>" />
			</label>
		</div>
		<?php
	}

	/**
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	public static function quick_edit_save( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce quick edit.
		if ( ! array_key_exists( 'rwgcm_quick_weather_facets', $_REQUEST ) ) {
			return;
		}
		$raw = wp_unslash( (string) $_REQUEST['rwgcm_quick_weather_facets'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		RWGCM_Weather_Affinity::save_product_facets( (int) $product->get_id(), explode( ',', $raw ) );
	}

	/**
	 * Populate quick edit from list column data attributes.
	 *
	 * @return void
	 */
	public static function quick_edit_script() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		(function ($) {
			'use strict';
			var $wpList = $('#the-list');
			$wpList.on('click', '.editinline', function () {
				var $row = $(this).closest('tr');
				var $col = $row.find('.column-rwgcm_weather .rwgcm-weather-col');
				var facets = $col.length ? ($col.data('facets') || '') : '';
				setTimeout(function () {
					$('input.rwgcm-quick-weather-facets').val(facets);
				}, 50);
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public static function register_bulk_actions( $actions ) {
		$actions['rwgcm_suggest_weather'] = __( 'Suggest weather facets', 'reactwoo-geo-commerce' );
		$actions['rwgcm_apply_category_weather'] = __( 'Apply category weather defaults', 'reactwoo-geo-commerce' );
		return $actions;
	}

	/**
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action slug.
	 * @param int[]  $post_ids    Product IDs.
	 * @return string
	 */
	public static function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( ! in_array( $action, array( 'rwgcm_suggest_weather', 'rwgcm_apply_category_weather' ), true ) ) {
			return $redirect_to;
		}
		if ( ! current_user_can( 'edit_products' ) ) {
			return $redirect_to;
		}
		$updated = 0;
		$skipped = 0;
		foreach ( (array) $post_ids as $post_id ) {
			$pid = absint( $post_id );
			if ( $pid <= 0 || ! current_user_can( 'edit_post', $pid ) ) {
				continue;
			}
			if ( 'rwgcm_apply_category_weather' === $action ) {
				$facets = RWGCM_Weather_Affinity::get_product_category_facets( $pid );
			} else {
				$facets = RWGCM_Weather_Tagging::suggest_facets( $pid );
			}
			if ( empty( $facets ) ) {
				++$skipped;
				continue;
			}
			RWGCM_Weather_Affinity::save_product_facets( $pid, $facets );
			++$updated;
		}
		return add_query_arg(
			array(
				'rwgcm_bulk_weather' => sanitize_key( $action ),
				'rwgcm_updated'      => $updated,
				'rwgcm_skipped'      => $skipped,
			),
			$redirect_to
		);
	}

	/**
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public static function filter_untagged_products( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['rwgcm_weather'] ) || 'untagged' !== sanitize_key( wp_unslash( (string) $_GET['rwgcm_weather'] ) ) ) {
			return;
		}
		$meta_query = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => RWGCM_Weather_Affinity::META_KEY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => RWGCM_Weather_Affinity::META_KEY,
				'value'   => '',
				'compare' => '=',
			),
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * @return void
	 */
	public static function bulk_action_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['rwgcm_bulk_weather'] ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		$action  = sanitize_key( wp_unslash( (string) $_GET['rwgcm_bulk_weather'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = isset( $_GET['rwgcm_updated'] ) ? absint( $_GET['rwgcm_updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped = isset( $_GET['rwgcm_skipped'] ) ? absint( $_GET['rwgcm_skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$label   = 'rwgcm_apply_category_weather' === $action
			? __( 'Applied category weather defaults to %1$d product(s). %2$d skipped (no category defaults).', 'reactwoo-geo-commerce' )
			: __( 'Suggested weather facets for %1$d product(s). %2$d skipped (no suggestions).', 'reactwoo-geo-commerce' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( $label, $updated, $skipped ) ) . '</p></div>';
	}
}
