<?php
/**
 * Elementor integration bootstrap.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Geo Commerce Elementor widgets.
 */
class RWGCM_Elementor {

	/**
	 * @return void
	 */
	public static function init() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_dynamic_tags' ) );
		self::register_widgets_now();
		self::register_dynamic_tags_now();
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic.php';
		RWGCM_Elementor_Atomic::init();
	}

	/**
	 * elementor/widgets/register may already have fired.
	 *
	 * @return void
	 */
	private static function register_widgets_now() {
		if ( ! function_exists( 'did_action' ) || ! did_action( 'elementor/widgets/register' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! is_object( $plugin ) || empty( $plugin->widgets_manager ) || ! is_object( $plugin->widgets_manager ) ) {
			return;
		}
		self::register_widgets( $plugin->widgets_manager );
	}

	/**
	 * @return void
	 */
	private static function register_dynamic_tags_now() {
		if ( ! function_exists( 'did_action' ) || ! did_action( 'elementor/dynamic_tags/register' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! is_object( $plugin ) || empty( $plugin->dynamic_tags ) || ! is_object( $plugin->dynamic_tags ) ) {
			return;
		}
		self::register_dynamic_tags( $plugin->dynamic_tags );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ) {
		if ( ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-weather-products.php';
		$widgets_manager->register( new RWGCM_Elementor_Weather_Products() );
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-weather-strip.php';
		$widgets_manager->register( new RWGCM_Elementor_Weather_Strip() );
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-product-price.php';
		$widgets_manager->register( new RWGCM_Elementor_Product_Price() );
	}

	/**
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags Dynamic tags manager.
	 * @return void
	 */
	public static function register_dynamic_tags( $dynamic_tags ) {
		if ( ! is_object( $dynamic_tags ) || ! method_exists( $dynamic_tags, 'register' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag', false ) ) {
			return;
		}
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-visitor-weather-facets.php';
		$dynamic_tags->register( new RWGCM_Elementor_Visitor_Weather_Facets() );
	}
}
