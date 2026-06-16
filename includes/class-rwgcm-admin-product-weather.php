<?php
/**
 * WooCommerce product editor — shopping weather affinity.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weather facet fields inside the GeoCore product data tab.
 */
class RWGCM_Admin_Product_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'unregister_legacy_general_tab' ), 100 );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_product_editor' ) );
	}

	/**
	 * Stop legacy builds from rendering weather fields on the General tab.
	 *
	 * @return void
	 */
	public static function unregister_legacy_general_tab() {
		remove_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_product_fields' ), 25 );
		remove_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_weather_section' ), 25 );
	}

	/**
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public static function render_weather_section( $post_id ) {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}

		$weather_on = RWGCM_Condition_Library::is_weather_available();
		$facets     = RWGCM_Weather_Affinity::get_facet_definitions();
		$post_id    = absint( $post_id );
		$selected   = $post_id > 0 ? RWGCM_Weather_Affinity::get_product_facets( $post_id ) : array();
		$cat_hints  = $post_id > 0 ? RWGCM_Weather_Affinity::get_product_category_facets( $post_id ) : array();

		if ( ! $weather_on ) {
			echo '<p class="description">';
			echo esc_html__( 'Connect GeoCore Pro weather for live visitor data. You can still tag products now for catalog boost and widgets when weather is enabled.', 'reactwoo-geo-commerce' );
			if ( class_exists( 'RWGCP_Weather_Service', false ) ) {
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ) . '">' . esc_html__( 'Weather settings', 'reactwoo-geo-commerce' ) . '</a>';
			}
			echo '</p>';
		}

		if ( ! empty( $cat_hints ) ) {
			echo '<p class="description rwgcm-category-weather-hints">';
			echo esc_html__( 'Category defaults:', 'reactwoo-geo-commerce' ) . ' ';
			echo esc_html( RWGCM_Weather_Affinity::format_facet_value_label( implode( ',', $cat_hints ) ) );
			echo ' <button type="button" class="button-link" id="rwgcm-apply-category-weather" data-facets="' . esc_attr( implode( ',', $cat_hints ) ) . '">';
			esc_html_e( 'Apply to product', 'reactwoo-geo-commerce' );
			echo '</button></p>';
		}

		echo '<div class="rwgc-product-weather-grid">';
		foreach ( $facets as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue;
			}
			$slug    = (string) $row['slug'];
			$label   = isset( $row['label'] ) ? (string) $row['label'] : $slug;
			$checked = in_array( $slug, $selected, true );
			printf(
				'<label class="rwgc-product-weather-grid__item" for="rwgcm_weather_facet_%1$s"><input type="checkbox" name="rwgcm_weather_facets[]" id="rwgcm_weather_facet_%1$s" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $slug ),
				checked( $checked, true, false ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/**
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_product( $post_id, $post ) {
		unset( $post );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['rwgcm_weather_facets'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC product save.
			RWGCM_Weather_Affinity::save_product_facets( (int) $post_id, array() );
			return;
		}
		$raw = wp_unslash( $_POST['rwgcm_weather_facets'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = is_array( $raw ) ? $raw : array();
		RWGCM_Weather_Affinity::save_product_facets( (int) $post_id, $raw );
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_product_editor( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			'(function($){$(\'#rwgcm-apply-category-weather\').on(\'click\',function(){var raw=$(this).data(\'facets\')||\'\';raw.toString().split(\',\').forEach(function(slug){slug=$.trim(slug);if(!slug){return;}$("#rwgcm_weather_facet_"+slug).prop(\'checked\',true).trigger(\'change\');});});})(jQuery);'
		);
	}
}
