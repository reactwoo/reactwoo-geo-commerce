<?php
/**
 * Elementor Atomic (V4) twins of the classic Geo Commerce widgets.
 *
 * Classic widgets stay registered. These types are separate so saved classic
 * content is unchanged. The canvas paints Twig; the published page uses the
 * same PHP renderers as the classic widgets.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Atomic weather widgets and the editor preview route.
 */
class RWGCM_Elementor_Atomic {

	/**
	 * @return void
	 */
	public static function init() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		// Priority 20: Elementor 4.3 registers its own atomic widgets at 10, and the
		// base class is not loaded yet when that first callback starts.
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ), 20 );
		add_action( 'elementor/elements/elements_registered', array( __CLASS__, 'register_widgets_later' ), 20 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_preview' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_preview' ) );
		if ( function_exists( 'did_action' ) && did_action( 'rest_api_init' ) ) {
			self::register_rest();
		}
		self::register_widgets_now();
	}

	/**
	 * Register atomic widgets when elementor/widgets/register has already fired.
	 * Inactive atomic experiments return before any atomic class is loaded.
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
	 * Elementor 4.3 keeps the atomic base class unloaded until something autoloads it.
	 * The V4 experiment is the gate; the class is loaded only after that is on.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! self::atomic_experiment_on() ) {
			return false;
		}
		return class_exists( '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base', true );
	}

	/**
	 * @return bool
	 */
	private static function atomic_experiment_on() {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! $plugin || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) ) {
			return false;
		}
		if ( $plugin->experiments->is_feature_active( 'e_atomic_elements' ) ) {
			return true;
		}
		if ( class_exists( '\Elementor\Modules\AtomicWidgets\Module', true )
			&& method_exists( '\Elementor\Modules\AtomicWidgets\Module', 'is_active' )
			&& \Elementor\Modules\AtomicWidgets\Module::is_active()
		) {
			return true;
		}
		return (bool) $plugin->experiments->is_feature_active( 'e_opt_in_v4' );
	}

	/**
	 * Later hook used by Elementor 4.3 after atomic elements exist.
	 *
	 * @param object $elements_manager Elements manager.
	 * @return void
	 */
	public static function register_widgets_later( $elements_manager ) {
		unset( $elements_manager );
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
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets_manager ) {
		static $registered = false;
		if ( $registered || ! self::is_active() || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		$registered = true;
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic-weather-products.php';
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic-weather-strip.php';
		require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic-product-price.php';
		$widgets_manager->register( new RWGCM_Elementor_Atomic_Weather_Products() );
		$widgets_manager->register( new RWGCM_Elementor_Atomic_Weather_Strip() );
		$widgets_manager->register( new RWGCM_Elementor_Atomic_Product_Price() );
	}

	/**
	 * @return void
	 */
	public static function register_rest() {
		register_rest_route(
			'rwgcm/v1',
			'/elementor-preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => array( __CLASS__, 'preview' ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function preview( $request ) {
		$widget   = sanitize_key( (string) $request->get_param( 'widget' ) );
		$settings = $request->get_param( 'settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$html = '';
		if ( 'weather-products' === $widget && class_exists( 'RWGCM_Weather_Products_Display', false ) ) {
			$html = RWGCM_Weather_Products_Display::render( self::products_args( $settings ) );
		} elseif ( 'weather-strip' === $widget && class_exists( 'RWGCM_Weather_Strip', false ) ) {
			$html = RWGCM_Weather_Strip::render( self::strip_args( $settings ) );
		} elseif ( 'product-price' === $widget && class_exists( 'RWGCM_Product_Price', false ) ) {
			$html = RWGCM_Product_Price::render( $settings );
		}
		return rest_ensure_response(
			array(
				'html' => is_string( $html ) ? $html : '',
			)
		);
	}

	/**
	 * @return void
	 */
	public static function enqueue_preview() {
		if ( class_exists( 'RWGCM_Weather_Products_Display', false ) ) {
			RWGCM_Weather_Products_Display::register_assets();
		}
		if ( class_exists( 'RWGCM_Weather_Strip', false ) ) {
			RWGCM_Weather_Strip::register_assets();
		}
		if ( class_exists( 'RWGCM_Product_Price', false ) ) {
			RWGCM_Product_Price::register_assets();
		}
		wp_enqueue_style( 'rwgcm-weather-products' );
		wp_enqueue_style( 'rwgcm-weather-strip' );
		wp_enqueue_style( 'rwgcm-product-price' );
		wp_register_script(
			'rwgcm-elementor-atomic-preview',
			RWGCM_URL . 'assets/js/rwgcm-elementor-atomic-preview.js',
			array(),
			RWGCM_VERSION,
			true
		);
		wp_localize_script(
			'rwgcm-elementor-atomic-preview',
			'rwgcmAtomicPreview',
			array(
				'rest'   => rest_url( 'rwgcm/v1/elementor-preview' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'styles' => array(
					RWGCM_URL . 'assets/css/weather-products.css?ver=' . rawurlencode( RWGCM_VERSION ),
					RWGCM_URL . 'assets/css/weather-strip.css?ver=' . rawurlencode( RWGCM_VERSION ),
					RWGCM_URL . 'assets/css/product-price.css?ver=' . rawurlencode( RWGCM_VERSION ),
				),
			)
		);
		wp_enqueue_script( 'rwgcm-elementor-atomic-preview' );
	}

	/**
	 * @param array<string, mixed> $settings Atomic or preview settings.
	 * @return array<string, mixed>
	 */
	public static function products_args( $settings ) {
		$orderby = self::one_of( self::scalar_string( $settings, 'orderby', 'relevance' ), array( 'relevance', 'date', 'menu_order' ), 'relevance' );
		$fallback = self::one_of( self::scalar_string( $settings, 'fallback', 'hide' ), array( 'hide', 'category', 'message' ), 'hide' );
		$unavailable = self::one_of( self::scalar_string( $settings, 'weather_unavailable', 'hide' ), array( 'hide', 'category', 'message' ), 'hide' );
		$limit = (int) self::scalar_string( $settings, 'limit', '8' );
		$columns = (int) self::scalar_string( $settings, 'columns', '4' );
		return array(
			'title'               => self::scalar_string( $settings, 'title', '' ),
			'limit'               => max( 1, min( 24, $limit ) ),
			'columns'             => max( 1, min( 6, $columns ) ),
			'category'            => self::scalar_string( $settings, 'category', '' ),
			'ids'                 => self::scalar_string( $settings, 'ids', '' ),
			'orderby'             => $orderby,
			'fallback'            => $fallback,
			'fallback_category'   => self::scalar_string( $settings, 'fallback_category', '' ),
			'fallback_message'    => self::scalar_string( $settings, 'fallback_message', '' ),
			'weather_unavailable' => $unavailable,
			'class'               => 'rwgcm-elementor-weather-products',
		);
	}

	/**
	 * @param array<string, mixed> $settings Atomic or preview settings.
	 * @return array<string, string>
	 */
	public static function strip_args( $settings ) {
		return array(
			'fallback' => self::one_of( self::scalar_string( $settings, 'fallback', 'hide' ), array( 'hide', 'message' ), 'hide' ),
			'link'     => self::one_of( self::scalar_string( $settings, 'link', '' ), array( '', 'none', 'shop' ), '' ),
			'class'    => 'rwgcm-elementor-weather-strip',
		);
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Key.
	 * @param string               $default  Default.
	 * @return string
	 */
	private static function scalar_string( $settings, $key, $default ) {
		if ( ! is_array( $settings ) || ! array_key_exists( $key, $settings ) ) {
			return $default;
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( (string) $value );
		}
		return $default;
	}

	/**
	 * @param string        $value   Candidate.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string        $default Default.
	 * @return string
	 */
	private static function one_of( $value, $allowed, $default ) {
		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
