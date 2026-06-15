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
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_dynamic_tags' ) );
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
