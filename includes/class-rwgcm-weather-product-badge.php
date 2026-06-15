<?php
/**
 * Storefront badge from product weather meta (independent of display rules).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints a loop/single badge when product facets overlap visitor weather.
 */
class RWGCM_Weather_Product_Badge {

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'maybe_print_badge_loop' ), 8 );
		add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'maybe_print_badge_single' ), 7 );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'rwgcm-weather-product-badge',
			RWGCM_URL . 'assets/css/weather-product-badge.css',
			array(),
			RWGCM_VERSION
		);
	}

	/**
	 * @return void
	 */
	public static function maybe_print_badge_loop() {
		global $product;
		self::print_badge( $product );
	}

	/**
	 * @return void
	 */
	public static function maybe_print_badge_single() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		self::print_badge( $product );
	}

	/**
	 * @param \WC_Product|null $product Product.
	 * @return void
	 */
	private static function print_badge( $product ) {
		if ( ! RWGCM_Settings::is_weather_meta_badge_enabled() ) {
			return;
		}
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$pid = (int) $product->get_id();
		if ( $product->is_type( 'variation' ) ) {
			$pid = (int) $product->get_parent_id();
		}
		if ( $pid <= 0 || ! RWGCM_Weather_Affinity::product_matches_visitor( $pid ) ) {
			return;
		}
		$text = self::badge_text( $pid );
		if ( '' === $text ) {
			return;
		}
		wp_enqueue_style( 'rwgcm-weather-product-badge' );
		echo '<span class="rwgcm-weather-meta-badge">' . esc_html( $text ) . '</span>';
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private static function badge_text( $product_id ) {
		$product_facets = RWGCM_Weather_Affinity::get_product_facets( $product_id );
		$visitor        = RWGCM_Weather_Affinity::get_visitor_facets();
		$overlap        = array_values( array_intersect( $product_facets, $visitor ) );
		if ( empty( $overlap ) ) {
			return '';
		}
		$labels = array();
		foreach ( $overlap as $slug ) {
			$labels[] = RWGCM_Weather_Affinity::format_facet_value_label( $slug );
		}
		$template = RWGCM_Settings::get_weather_meta_badge_text();
		if ( '' !== $template && false !== strpos( $template, '{facets}' ) ) {
			return str_replace( '{facets}', implode( ', ', $labels ), $template );
		}
		return sprintf(
			/* translators: %s: comma-separated shopping weather labels */
			__( 'Good for %s', 'reactwoo-geo-commerce' ),
			implode( ', ', $labels )
		);
	}
}
