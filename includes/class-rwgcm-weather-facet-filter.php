<?php
/**
 * Category/shop facet filter chips driven by visitor weather.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode + query arg filter for weather-tagged products.
 */
class RWGCM_Weather_Facet_Filter {

	const QUERY_ARG = 'rwgcm_wf';

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			add_action( 'woocommerce_product_query', array( __CLASS__, 'on_product_query' ), 20, 2 );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'rwgcm_weather_filter', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'rwgcm-weather-facet-filter',
			RWGCM_URL . 'assets/css/weather-facet-filter.css',
			array(),
			RWGCM_VERSION
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'class'    => '',
				'fallback' => 'hide',
				'show_all' => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'rwgcm_weather_filter'
		);

		$facets = RWGCM_Weather_Affinity::get_visitor_facets();
		if ( empty( $facets ) ) {
			return 'hide' === sanitize_key( (string) $atts['fallback'] ) ? '' : '<div class="rwgcm-weather-filter rwgcm-weather-filter--empty"><span>' . esc_html__( 'Weather unavailable', 'reactwoo-geo-commerce' ) . '</span></div>';
		}

		wp_enqueue_style( 'rwgcm-weather-facet-filter' );

		$current = self::get_active_facet();
		$base    = remove_query_arg( self::QUERY_ARG );
		$class   = sanitize_html_class( (string) $atts['class'] );
		$classes = array( 'rwgcm-weather-filter' );
		if ( $class ) {
			$classes[] = $class;
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<span class="rwgcm-weather-filter__label">' . esc_html__( 'Shop for today\'s weather', 'reactwoo-geo-commerce' ) . '</span>';
		$html .= '<span class="rwgcm-weather-filter__chips">';

		if ( ! empty( $atts['show_all'] ) && '0' !== (string) $atts['show_all'] ) {
			$all_url = $base;
			$all_cls = '' === $current ? ' is-active' : '';
			$html   .= '<a class="rwgcm-weather-filter__chip' . esc_attr( $all_cls ) . '" href="' . esc_url( $all_url ) . '">' . esc_html__( 'All', 'reactwoo-geo-commerce' ) . '</a>';
		}

		foreach ( $facets as $slug ) {
			$url   = add_query_arg( self::QUERY_ARG, $slug, $base );
			$label = RWGCM_Weather_Affinity::format_facet_value_label( $slug );
			$active = ( $current === $slug ) ? ' is-active' : '';
			$html .= '<a class="rwgcm-weather-filter__chip' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		$html .= '</span></div>';
		return $html;
	}

	/**
	 * @return string Active facet slug or empty.
	 */
	public static function get_active_facet() {
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$raw = sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed = array_flip( array_column( RWGCM_Weather_Affinity::get_facet_definitions(), 'slug' ) );
		return isset( $allowed[ $raw ] ) ? $raw : '';
	}

	/**
	 * @param WP_Query $q        Query.
	 * @param mixed    $wc_query WC_Query.
	 * @return void
	 */
	public static function on_product_query( $q, $wc_query ) {
		unset( $wc_query );
		if ( ! $q instanceof WP_Query ) {
			return;
		}
		$facet = self::get_active_facet();
		if ( '' === $facet ) {
			return;
		}
		$meta_query = $q->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => RWGCM_Weather_Affinity::META_KEY,
			'value'   => '"' . $facet . '"',
			'compare' => 'LIKE',
		);
		$q->set( 'meta_query', $meta_query );
	}
}
