<?php
/**
 * Product category default shopping-weather facets.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Term meta on product_cat for category-level facet defaults.
 */
class RWGCM_Category_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'edit_form_fields' ), 10, 2 );
		add_action( 'created_product_cat', array( __CLASS__, 'save_term' ), 10, 2 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_term' ), 10, 2 );
	}

	/**
	 * @return void
	 */
	public static function add_form_fields() {
		self::render_fields( array() );
	}

	/**
	 * @param WP_Term $term Term.
	 * @return void
	 */
	public static function edit_form_fields( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$selected = RWGCM_Weather_Affinity::get_category_facets( (int) $term->term_id );
		echo '<tr class="form-field rwgcm-category-weather"><th scope="row">';
		esc_html_e( 'Default weather facets', 'reactwoo-geo-commerce' );
		echo '</th><td>';
		self::render_checkbox_group( $selected );
		echo '<p class="description">' . esc_html__( 'Suggested defaults for products in this category. Does not auto-apply to products until you tag them or use Suggest / Apply on the product editor.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param string[] $selected Selected slugs.
	 * @return void
	 */
	private static function render_fields( array $selected ) {
		echo '<div class="form-field rwgcm-category-weather">';
		echo '<label>' . esc_html__( 'Default weather facets', 'reactwoo-geo-commerce' ) . '</label>';
		self::render_checkbox_group( $selected );
		echo '<p class="description">' . esc_html__( 'Category-level hints for product tagging and Geo AI suggestions.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '</div>';
	}

	/**
	 * @param string[] $selected Selected slugs.
	 * @return void
	 */
	private static function render_checkbox_group( array $selected ) {
		foreach ( RWGCM_Weather_Affinity::get_facet_definitions() as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue;
			}
			$slug = (string) $row['slug'];
			printf(
				'<label style="display:inline-block;margin:0 12px 6px 0;"><input type="checkbox" name="rwgcm_cat_weather_facets[]" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( isset( $row['label'] ) ? (string) $row['label'] : $slug )
			);
		}
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save_term( $term_id ) {
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		if ( ! isset( $_POST['rwgcm_cat_weather_facets'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			RWGCM_Weather_Affinity::save_category_facets( (int) $term_id, array() );
			return;
		}
		$raw = wp_unslash( $_POST['rwgcm_cat_weather_facets'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		RWGCM_Weather_Affinity::save_category_facets( (int) $term_id, is_array( $raw ) ? $raw : array() );
	}
}
