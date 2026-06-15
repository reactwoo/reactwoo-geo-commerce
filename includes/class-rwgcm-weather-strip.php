<?php
/**
 * Visitor shopping-weather strip (compact storefront display).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders current visitor weather facets as a compact strip.
 */
class RWGCM_Weather_Strip {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'rwgcm_weather_strip', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'rwgcm-weather-strip',
			RWGCM_URL . 'assets/css/weather-strip.css',
			array(),
			RWGCM_VERSION
		);
	}

	/**
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return self::render(
			array(
				'class'    => isset( $atts['class'] ) ? (string) $atts['class'] : '',
				'fallback' => isset( $atts['fallback'] ) ? (string) $atts['fallback'] : 'hide',
				'link'     => isset( $atts['link'] ) ? (string) $atts['link'] : '',
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Display args.
	 * @return string
	 */
	public static function render( array $args = array() ) {
		$fallback = isset( $args['fallback'] ) ? sanitize_key( (string) $args['fallback'] ) : 'hide';
		$class    = isset( $args['class'] ) ? sanitize_html_class( (string) $args['class'] ) : '';
		$facets   = RWGCM_Weather_Affinity::get_visitor_facets();

		if ( empty( $facets ) ) {
			return 'hide' === $fallback ? '' : '<div class="rwgcm-weather-strip rwgcm-weather-strip--empty' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '"><span class="rwgcm-weather-strip__label">' . esc_html__( 'Weather unavailable', 'reactwoo-geo-commerce' ) . '</span></div>';
		}

		wp_enqueue_style( 'rwgcm-weather-strip' );

		$labels = array();
		foreach ( $facets as $slug ) {
			$labels[] = RWGCM_Weather_Affinity::format_facet_value_label( $slug );
		}

		$classes = array( 'rwgcm-weather-strip' );
		if ( $class ) {
			$classes[] = $class;
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<span class="rwgcm-weather-strip__label">' . esc_html__( 'Good weather for', 'reactwoo-geo-commerce' ) . '</span>';
		$html .= '<span class="rwgcm-weather-strip__facets">';
		foreach ( $labels as $i => $label ) {
			$html .= '<span class="rwgcm-weather-strip__facet">' . esc_html( $label ) . '</span>';
		}
		$html .= '</span></div>';

		$link = self::resolve_link_url( $args );
		if ( '' !== $link ) {
			$html = '<a class="rwgcm-weather-strip__link" href="' . esc_url( $link ) . '">' . $html . '</a>';
		}

		return $html;
	}

	/**
	 * Resolve strip link target from args or merchandising default.
	 *
	 * @param array<string, mixed> $args Display args (`link` = none|shop|custom URL).
	 * @return string URL or empty.
	 */
	public static function resolve_link_url( array $args = array() ) {
		$link = isset( $args['link'] ) ? trim( (string) $args['link'] ) : '';
		if ( '' === $link ) {
			$custom_default = RWGCM_Settings::get_weather_strip_link_custom_url();
			if ( '' !== $custom_default ) {
				return $custom_default;
			}
			$link = RWGCM_Settings::get_weather_strip_link_mode();
		}
		if ( '' === $link || 'none' === sanitize_key( $link ) ) {
			return '';
		}
		if ( 'shop' === sanitize_key( $link ) ) {
			if ( function_exists( 'wc_get_page_id' ) ) {
				$shop_id = (int) wc_get_page_id( 'shop' );
				if ( $shop_id > 0 ) {
					$url = get_permalink( $shop_id );
					if ( is_string( $url ) && '' !== $url ) {
						return $url;
					}
				}
			}
			return '';
		}
		if ( filter_var( $link, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $link );
		}
		/**
		 * Filter weather strip link URL when link mode is not none/shop.
		 *
		 * @param string               $url  Resolved URL (empty by default).
		 * @param string               $link Link mode or raw URL from args.
		 * @param array<string, mixed> $args Display args.
		 */
		return (string) apply_filters( 'rwgcm_weather_strip_link_url', '', $link, $args );
	}
}
