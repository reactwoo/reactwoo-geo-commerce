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
 * Product data tab fields for weather facet tagging.
 */
class RWGCM_Admin_Product_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_product_fields' ), 25 );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_product_editor' ) );
	}

	/**
	 * @return void
	 */
	public static function render_product_fields() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return;
		}

		$weather_on = RWGCM_Condition_Library::is_weather_available();
		$facets     = RWGCM_Weather_Affinity::get_facet_definitions();
		$selected   = array();
		global $post;
		if ( $post instanceof WP_Post ) {
			$selected = RWGCM_Weather_Affinity::get_product_facets( (int) $post->ID );
			$cat_hints = RWGCM_Weather_Affinity::get_product_category_facets( (int) $post->ID );
		} else {
			$cat_hints = array();
		}

		echo '<div class="options_group rwgcm-product-weather">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Good for this weather', 'reactwoo-geo-commerce' ) . '</strong></p>';

		if ( ! $weather_on ) {
			echo '<p class="form-field description">';
			echo esc_html__( 'Connect GeoCore Pro weather to tag products and use weather conditions in commerce rules.', 'reactwoo-geo-commerce' );
			if ( class_exists( 'RWGCP_Weather_Service', false ) ) {
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ) . '">' . esc_html__( 'Weather settings', 'reactwoo-geo-commerce' ) . '</a>';
			}
			echo '</p></div>';
			return;
		}

		echo '<p class="form-field description">' . esc_html__( 'Choose which shopping weather types this product suits. Used for weather-based rules, catalog boost, and product widgets.', 'reactwoo-geo-commerce' ) . '</p>';

		if ( ! empty( $cat_hints ) ) {
			echo '<p class="form-field description rwgcm-category-weather-hints">';
			echo esc_html__( 'Category defaults:', 'reactwoo-geo-commerce' ) . ' ';
			echo esc_html( RWGCM_Weather_Affinity::format_facet_value_label( implode( ',', $cat_hints ) ) );
			echo ' <button type="button" class="button-link" id="rwgcm-apply-category-weather" data-facets="' . esc_attr( implode( ',', $cat_hints ) ) . '">';
			esc_html_e( 'Apply to product', 'reactwoo-geo-commerce' );
			echo '</button></p>';
		}

		foreach ( $facets as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue;
			}
			$slug    = (string) $row['slug'];
			$label   = isset( $row['label'] ) ? (string) $row['label'] : $slug;
			$checked = in_array( $slug, $selected, true );
			echo '<p class="form-field rwgcm-product-weather-facet">';
			echo '<label for="rwgcm_weather_facet_' . esc_attr( $slug ) . '">';
			echo '<input type="checkbox" name="rwgcm_weather_facets[]" id="rwgcm_weather_facet_' . esc_attr( $slug ) . '" value="' . esc_attr( $slug ) . '" ' . checked( $checked, true, false ) . ' /> ';
			echo esc_html( $label );
			echo '</label></p>';
		}

		self::render_preview_panel( $facets );

		echo '</div>';
	}

	/**
	 * Live / simulated visitor weather match preview (admin only, not saved).
	 *
	 * @param array<int, array{slug: string, label: string}> $facets Facet definitions.
	 * @return void
	 */
	private static function render_preview_panel( array $facets ) {
		$visitor = RWGCM_Weather_Affinity::get_visitor_facets();
		echo '<div class="rwgcm-weather-preview" style="margin-top:12px;padding:12px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;">';
		echo '<p class="form-field" style="margin:0 0 8px;"><strong>' . esc_html__( 'Storefront preview', 'reactwoo-geo-commerce' ) . '</strong></p>';
		if ( ! empty( $visitor ) ) {
			echo '<p class="description" style="margin:0 0 10px;">';
			echo esc_html__( 'Current visitor weather:', 'reactwoo-geo-commerce' ) . ' ';
			echo esc_html( RWGCM_Weather_Affinity::format_facet_value_label( implode( ',', $visitor ) ) );
			echo '</p>';
		} else {
			echo '<p class="description" style="margin:0 0 10px;">' . esc_html__( 'Visitor weather is unavailable — simulate facets below or warm the weather cache in GeoCore Pro.', 'reactwoo-geo-commerce' ) . '</p>';
		}
		echo '<p class="form-field" style="margin:0 0 8px;">';
		echo '<label><input type="checkbox" id="rwgcm-weather-preview-simulate" value="1" ' . checked( empty( $visitor ), true, false ) . ' /> ';
		echo esc_html__( 'Simulate visitor weather', 'reactwoo-geo-commerce' );
		echo '</label></p>';
		$simulate_visible = empty( $visitor ) ? '' : ' style="display:none;"';
		echo '<div class="rwgcm-weather-preview-simulate-fields"' . $simulate_visible . '>';
		foreach ( $facets as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue;
			}
			$slug  = (string) $row['slug'];
			$label = isset( $row['label'] ) ? (string) $row['label'] : $slug;
			echo '<label style="display:inline-block;margin:0 12px 6px 0;">';
			echo '<input type="checkbox" name="rwgcm_preview_visitor_facets[]" value="' . esc_attr( $slug ) . '" /> ';
			echo esc_html( $label );
			echo '</label>';
		}
		echo '</div>';
		echo '<p id="rwgcm-weather-preview-status" class="description" style="margin:10px 0 0;"></p>';
		echo '<style>.rwgcm-weather-preview--match{color:#007017;font-weight:600}.rwgcm-weather-preview--nomatch{color:#8a2424}</style>';
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
		wp_enqueue_script(
			'rwgcm-product-weather-preview',
			RWGCM_URL . 'assets/js/product-weather-preview.js',
			array( 'jquery' ),
			RWGCM_VERSION,
			true
		);

		$labels = array();
		foreach ( RWGCM_Weather_Affinity::get_facet_definitions() as $row ) {
			if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
				$labels[ (string) $row['slug'] ] = isset( $row['label'] ) ? (string) $row['label'] : (string) $row['slug'];
			}
		}

		wp_localize_script(
			'rwgcm-product-weather-preview',
			'rwgcmProductWeatherPreview',
			array(
				'visitorFacets' => RWGCM_Weather_Affinity::get_visitor_facets(),
				'labels'        => $labels,
				'i18n'          => array(
					'noProductFacets' => __( 'Tag at least one weather facet to preview storefront match.', 'reactwoo-geo-commerce' ),
					'noVisitorFacets' => __( 'No visitor weather to compare — enable simulate mode or connect weather.', 'reactwoo-geo-commerce' ),
					'match'           => __( 'Would match storefront for: %s', 'reactwoo-geo-commerce' ),
					'noMatch'         => __( 'No overlap with visitor weather — product would not boost or match weather rules.', 'reactwoo-geo-commerce' ),
				),
			)
		);

		wp_add_inline_script(
			'jquery',
			'(function($){$(\'#rwgcm-apply-category-weather\').on(\'click\',function(){var raw=$(this).data(\'facets\')||\'\';raw.toString().split(\',\').forEach(function(slug){slug=$.trim(slug);if(!slug){return;}$("#rwgcm_weather_facet_"+slug).prop(\'checked\',true).trigger(\'change\');});});})(jQuery);'
		);
	}
}
