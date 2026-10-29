<?php
/**
 * Elementor widget registration (run with: php tests/test-elementor-widgets.php).
 *
 * Commerce boots after Elementor has already fired elementor/loaded. Widgets,
 * including the weather widgets, must still register. Atomic elements register
 * only while the atomic experiment is on.
 *
 * @package ReactWoo_Geo_Commerce
 */

namespace Elementor {
	class Widget_Base {
		public function __construct( $data = array(), $args = null ) {
			unset( $data, $args );
		}
	}
	class Controls_Manager {
		const TEXT   = 'text';
		const NUMBER = 'number';
		const SELECT = 'select';
	}
	class Plugin {
		public static $instance;
		public $widgets_manager;
		public $experiments;
		public $dynamic_tags;
	}
}

namespace Elementor\Modules\AtomicWidgets\Elements\Base {
	trait Has_Template {
	}
	class Atomic_Widget_Base {
		public function __construct() {
		}
		public function get_name() {
			return static::get_element_type();
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'RWGCM_PATH' ) ) {
		define( 'RWGCM_PATH', dirname( __DIR__ ) . '/' );
	}

	$GLOBALS['rw_actions'] = array();
	$GLOBALS['rw_did']     = array(
		'elementor/loaded'           => 1,
		'elementor/widgets/register' => 1,
	);

	function __( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { // phpcs:ignore
		unset( $priority, $args );
		$GLOBALS['rw_actions'][ $hook ][] = $callback;
	}
	function did_action( $hook ) { // phpcs:ignore
		return isset( $GLOBALS['rw_did'][ $hook ] ) ? (int) $GLOBALS['rw_did'][ $hook ] : 0;
	}

	class RW_Elementor_Manager {
		public $names = array();
		public function register( $widget ) {
			$this->names[] = $widget->get_name();
		}
	}
	class RW_Elementor_Experiments {
		public $atomic = false;
		public function is_feature_active( $feature ) {
			return $this->atomic && ( 'e_atomic_elements' === $feature || 'e_opt_in_v4' === $feature );
		}
	}

	$manager = new RW_Elementor_Manager();
	$experiments = new RW_Elementor_Experiments();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = $manager;
	\Elementor\Plugin::$instance->experiments = $experiments;

	require_once RWGCM_PATH . 'includes/class-rwgcm-plugin.php';
	RWGCM_Plugin::boot_elementor();
	RWGCM_Plugin::boot_elementor();

	$passed = 0;
	$failed = 0;
	function assert_true( $label, $condition ) { // phpcs:ignore
		global $passed, $failed;
		if ( $condition ) {
			++$passed;
			echo "PASS: {$label}\n";
			return;
		}
		++$failed;
		echo "FAIL: {$label}\n";
	}

	$names = \Elementor\Plugin::$instance->widgets_manager->names;
	assert_true( 'booting after elementor/loaded registers the weather products widget', in_array( 'rwgcm-weather-products', $names, true ) );
	assert_true( 'booting after elementor/loaded registers the weather strip widget', in_array( 'rwgcm-weather-strip', $names, true ) );
	assert_true( 'booting after elementor/loaded registers the product price widget', in_array( 'rwgcm-product-price', $names, true ) );
	assert_true( 'atomic widgets stay unregistered while the experiment is off', ! in_array( 'rwgcm-product-price-atomic', $names, true ) && ! in_array( 'rwgcm-weather-products-atomic', $names, true ) );
	assert_true( 'atomic active check is false without the experiment', ! RWGCM_Elementor_Atomic::is_active() );
	assert_true( 'the editor preview route is hooked for later rest_api_init', isset( $GLOBALS['rw_actions']['rest_api_init'] ) );
	assert_true( 'classic widgets stay hooked for a later elementor/widgets/register', isset( $GLOBALS['rw_actions']['elementor/widgets/register'] ) );

	$before = count( $names );
	RWGCM_Elementor_Atomic::register_widgets( $manager );
	assert_true( 'calling atomic registration while inactive adds nothing', count( $manager->names ) === $before );

	$experiments->atomic = true;
	assert_true( 'atomic active check is true when the experiment is on', RWGCM_Elementor_Atomic::is_active() );
	RWGCM_Elementor_Atomic::register_widgets( $manager );
	assert_true( 'atomic weather products register when the experiment is on', in_array( 'rwgcm-weather-products-atomic', $manager->names, true ) );
	assert_true( 'atomic weather strip registers when the experiment is on', in_array( 'rwgcm-weather-strip-atomic', $manager->names, true ) );
	assert_true( 'atomic product price registers when the experiment is on', in_array( 'rwgcm-product-price-atomic', $manager->names, true ) );

	$experiments->atomic = false;
	$after = count( $manager->names );
	RWGCM_Elementor_Atomic::register_widgets( $manager );
	assert_true( 'turning the atomic experiment off does not fatal and adds nothing', count( $manager->names ) === $after && ! RWGCM_Elementor_Atomic::is_active() );

	$plugin = file_get_contents( RWGCM_PATH . 'includes/class-rwgcm-plugin.php' );
	assert_true(
		'plugin boots Elementor when elementor/loaded has already fired',
		is_string( $plugin ) && false !== strpos( $plugin, "did_action( 'elementor/loaded' )" ) && false !== strpos( $plugin, 'self::boot_elementor()' )
	);

	echo "\n{$passed} passed, {$failed} failed\n";
	exit( $failed > 0 ? 1 : 0 );
}
