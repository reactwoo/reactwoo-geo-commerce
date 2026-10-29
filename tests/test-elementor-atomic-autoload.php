<?php
/**
 * Atomic registration autoloads Elementor's base class (run with: php tests/test-elementor-atomic-autoload.php).
 *
 * Elementor 4.3 has not loaded Atomic_Widget_Base when widgets/register begins.
 * class_exists( ..., false ) therefore skips the widget. Autoload must load it
 * after the V4 experiment is on.
 *
 * @package ReactWoo_Geo_Commerce
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $widgets_manager;
		public $experiments;
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'RWGCM_PATH' ) ) {
		define( 'RWGCM_PATH', dirname( __DIR__ ) . '/' );
	}

	$GLOBALS['rw_autoloaded'] = array();
	spl_autoload_register(
		static function ( $class ) {
			if ( 'Elementor\\Modules\\AtomicWidgets\\Elements\\Base\\Atomic_Widget_Base' !== $class ) {
				return;
			}
			$GLOBALS['rw_autoloaded'][] = $class;
			require_once __DIR__ . '/stubs/elementor-atomic-widget-base.php';
		}
	);

	function __( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}

	class RW_Atomic_Manager {
		public $names = array();
		public function register( $widget ) {
			$this->names[] = $widget->get_name();
		}
	}
	class RW_Atomic_Experiments {
		public $on = false;
		public function is_feature_active( $feature ) {
			return $this->on && ( 'e_atomic_elements' === $feature || 'e_opt_in_v4' === $feature );
		}
	}

	$manager = new RW_Atomic_Manager();
	$experiments = new RW_Atomic_Experiments();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = $manager;
	\Elementor\Plugin::$instance->experiments = $experiments;

	require_once RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic.php';

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

	assert_true(
		'the atomic base class is not loaded before the experiment check',
		! class_exists( '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base', false )
	);
	assert_true( 'a switched-off V4 experiment does not autoload atomic classes', ! RWGCM_Elementor_Atomic::is_active() && array() === $GLOBALS['rw_autoloaded'] );
	RWGCM_Elementor_Atomic::register_widgets( $manager );
	assert_true( 'inactive atomic registration adds no widgets', array() === $manager->names );

	$experiments->on = true;
	assert_true( 'V4 on autoloads Atomic_Widget_Base', RWGCM_Elementor_Atomic::is_active() );
	assert_true( 'autoload ran for the atomic base class', in_array( 'Elementor\\Modules\\AtomicWidgets\\Elements\\Base\\Atomic_Widget_Base', $GLOBALS['rw_autoloaded'], true ) );
	RWGCM_Elementor_Atomic::register_widgets( $manager );
	assert_true( 'rwgcm-product-price-atomic registers after the class autoloads', in_array( 'rwgcm-product-price-atomic', $manager->names, true ) );
	assert_true( 'atomic weather widgets register on the same pass', in_array( 'rwgcm-weather-products-atomic', $manager->names, true ) && in_array( 'rwgcm-weather-strip-atomic', $manager->names, true ) );

	$src = file_get_contents( RWGCM_PATH . 'includes/integrations/elementor/class-rwgcm-elementor-atomic.php' );
	assert_true(
		'registration does not use class_exists without autoload for the atomic base',
		is_string( $src ) && false === strpos( $src, "Atomic_Widget_Base', false" )
	);
	assert_true(
		'atomic widgets also register on elementor/elements/elements_registered',
		is_string( $src ) && false !== strpos( $src, 'elementor/elements/elements_registered' ) && false !== strpos( $src, 'e_atomic_elements' )
	);

	echo "\n{$passed} passed, {$failed} failed\n";
	exit( $failed > 0 ? 1 : 0 );
}
