<?php
/**
 * Front-end rendering for weather product loops.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML output shared by shortcode, block, and Elementor.
 */
class RWGCM_Weather_Products_Display {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'rwgcm-weather-products',
			RWGCM_URL . 'assets/css/weather-products.css',
			array(),
			RWGCM_VERSION
		);
	}

	/**
	 * @param array<string, mixed> $raw_args Shortcode/block attributes.
	 * @return string
	 */
	public static function render( $raw_args ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$args   = RWGCM_Weather_Product_Query::parse_args( $raw_args );
		$result = RWGCM_Weather_Product_Query::resolve( $args );

		if ( 'message' === $result['mode'] && '' !== $result['message'] ) {
			wp_enqueue_style( 'rwgcm-weather-products' );
			return '<div class="rwgcm-weather-products rwgcm-weather-products--message' . ( $args['class'] ? ' ' . esc_attr( $args['class'] ) : '' ) . '"><p class="rwgcm-weather-products__empty">' . esc_html( $result['message'] ) . '</p></div>';
		}

		if ( empty( $result['product_ids'] ) ) {
			return '';
		}

		wp_enqueue_style( 'rwgcm-weather-products' );

		$classes = array( 'rwgcm-weather-products', 'woocommerce', 'columns-' . (int) $args['columns'] );
		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}

		ob_start();
		$template = RWGCM_PATH . 'templates/weather-products-loop.php';
		if ( is_readable( $template ) ) {
			include $template;
		} else {
			self::render_inline_loop( $args, $result['product_ids'] );
		}
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $args Parsed args.
	 * @param int[]                $product_ids Product IDs.
	 * @return void
	 */
	private static function render_inline_loop( array $args, array $product_ids ) {
		$classes = array( 'rwgcm-weather-products', 'woocommerce', 'columns-' . (int) $args['columns'] );
		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( '' !== $args['title'] ) {
			echo '<h2 class="rwgcm-weather-products__title">' . esc_html( $args['title'] ) . '</h2>';
		}
		echo '<ul class="products columns-' . esc_attr( (string) (int) $args['columns'] ) . '">';
		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wc_get_template_part( 'content', 'product' );
		}
		echo '</ul></div>';
	}
}
