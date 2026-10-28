<?php
/**
 * Admin gateway list fallback (run with: php tests/test-gateway-fallback.php).
 *
 * A gateway that only appears on woocommerce_payment_gateways is listed on
 * Payments by country, and the screen can warn that checkout will not offer it.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Count.
	 * @param string $domain Domain.
	 * @return string
	 */
	function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $tag Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) { // phpcs:ignore
		if ( 'woocommerce_payment_gateways' === $tag && isset( $GLOBALS['rwgcm_test_gateway_filter'] ) ) {
			return $GLOBALS['rwgcm_test_gateway_filter'];
		}
		unset( $tag );
		return $value;
	}
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * Stand-in for the WooCommerce gateway base class.
	 */
	class WC_Payment_Gateway { // phpcs:ignore
		/**
		 * @var string
		 */
		public $id = '';

		/**
		 * @var bool|string
		 */
		public $enabled = 'yes';

		/**
		 * @return string
		 */
		public function get_method_title() {
			return '';
		}
	}
}

/**
 * Gateway already present in WooCommerce's registry.
 */
class RWGCM_Test_Registered_Gateway extends WC_Payment_Gateway {

	/**
	 * @var int
	 */
	public static $constructed = 0;

	/**
	 * @return void
	 */
	public function __construct() {
		++self::$constructed;
		$this->id      = 'bacs';
		$this->enabled = 'yes';
	}

	/**
	 * @return string
	 */
	public function get_method_title() {
		return 'Direct bank transfer';
	}
}

/**
 * Gateway added only through the payment-gateways filter.
 */
class RWGCM_Test_Late_Gateway extends WC_Payment_Gateway {

	/**
	 * @var int
	 */
	public static $constructed = 0;

	/**
	 * @return void
	 */
	public function __construct() {
		++self::$constructed;
		$this->id      = 'paystack';
		$this->enabled = false;
	}

	/**
	 * @return string
	 */
	public function get_method_title() {
		return 'Paystack';
	}
}

/**
 * Constructor failure must not take down the admin list.
 */
class RWGCM_Test_Throwing_Gateway extends WC_Payment_Gateway {

	/**
	 * @var int
	 */
	public static $constructed = 0;

	/**
	 * @return void
	 */
	public function __construct() {
		++self::$constructed;
		throw new RuntimeException( 'boom' );
	}
}

/**
 * Not a payment gateway.
 */
class RWGCM_Test_Not_A_Gateway {

	/**
	 * @var string
	 */
	public $id = 'nope';
}

/**
 * Gateway supplied as an object on the filter.
 */
class RWGCM_Test_Object_Gateway extends WC_Payment_Gateway {

	/**
	 * @return void
	 */
	public function __construct() {
		$this->id      = 'square';
		$this->enabled = 'yes';
	}

	/**
	 * @return string
	 */
	public function get_method_title() {
		return 'Square';
	}
}

/**
 * WooCommerce gateway controller stand-in.
 */
class RWGCM_Test_Gateway_Controller {

	/**
	 * @var array<string, object>
	 */
	public $gateways = array();

	/**
	 * @return array<string, object>
	 */
	public function payment_gateways() {
		return $this->gateways;
	}
}

/**
 * WooCommerce stand-in.
 */
class RWGCM_Test_WC {

	/**
	 * @var RWGCM_Test_Gateway_Controller
	 */
	public $controller;

	/**
	 * @return RWGCM_Test_Gateway_Controller
	 */
	public function payment_gateways() {
		return $this->controller;
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * @return RWGCM_Test_WC
	 */
	function WC() { // phpcs:ignore
		return $GLOBALS['rwgcm_test_wc'];
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-availability.php';

$passed = 0;
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $condition Condition.
 * @return void
 */
function assert_true( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

$registered = new RWGCM_Test_Registered_Gateway();
RWGCM_Test_Registered_Gateway::$constructed = 0;

$controller             = new RWGCM_Test_Gateway_Controller();
$controller->gateways   = array( 'bacs' => $registered );
$wc                     = new RWGCM_Test_WC();
$wc->controller         = $controller;
$GLOBALS['rwgcm_test_wc'] = $wc;

$duplicate = new RWGCM_Test_Late_Gateway();
RWGCM_Test_Late_Gateway::$constructed = 0;
RWGCM_Test_Throwing_Gateway::$constructed = 0;

$GLOBALS['rwgcm_test_gateway_filter'] = array(
	'RWGCM_Test_Registered_Gateway',
	'RWGCM_Test_Late_Gateway',
	'RWGCM_Test_Throwing_Gateway',
	'RWGCM_Test_Not_A_Gateway',
	'Not_A_Real_Class',
	'',
	new RWGCM_Test_Object_Gateway(),
	$duplicate,
);

$choices = RWGCM_Gateway_Availability::gateway_choices();
$missing = RWGCM_Gateway_Availability::last_unregistered_gateway_ids();

assert_true(
	'a gateway that exists only on the filter is listed',
	isset( $choices['paystack'] ) && 'Paystack' === $choices['paystack']['title'] && false === $choices['paystack']['enabled']
);
assert_true(
	'an object on the filter is listed when its id is not registered',
	isset( $choices['square'] ) && 'Square' === $choices['square']['title'] && true === $choices['square']['enabled']
);
assert_true( 'a gateway already in the registry stays listed', isset( $choices['bacs'] ) && true === $choices['bacs']['enabled'] );
assert_true( 'a gateway whose constructor throws is skipped', ! isset( $choices['nope'] ) && 1 === RWGCM_Test_Throwing_Gateway::$constructed );
assert_true( 'a class already in the registry is not constructed again', 0 === RWGCM_Test_Registered_Gateway::$constructed );
assert_true( 'the missing class is constructed once', 1 === RWGCM_Test_Late_Gateway::$constructed );
assert_true(
	'fallback ids are the ones missing from WooCommerce',
	array( 'paystack', 'square' ) === array_values( $missing )
);
assert_true(
	'one missing gateway warns that checkout cannot offer it',
	'WooCommerce did not register Paystack (paystack); it will not show at checkout until that is fixed.' === RWGCM_Gateway_Availability::unregistered_checkout_notice( array( 'paystack' ), $choices )
);
assert_true(
	'several missing gateways are named in one warning',
	'WooCommerce did not register Paystack (paystack), Square (square); they will not show at checkout until that is fixed.' === RWGCM_Gateway_Availability::unregistered_checkout_notice( $missing, $choices )
);
assert_true( 'no fallback means no checkout warning', '' === RWGCM_Gateway_Availability::unregistered_checkout_notice( array(), $choices ) );

$already = RWGCM_Gateway_Availability::unregistered_gateway_instances(
	array(
		'bacs'     => $registered,
		'paystack' => $duplicate,
	)
);
assert_true(
	'an id WooCommerce already holds is not instantiated for the admin list',
	! isset( $already['paystack'] ) && isset( $already['square'] ) && ! isset( $already['bacs'] )
);

$GLOBALS['rwgcm_test_gateway_filter'] = array( 'RWGCM_Test_Registered_Gateway' );
$again                                = RWGCM_Gateway_Availability::gateway_choices();
assert_true(
	'a later list with no fallback clears the warning ids',
	isset( $again['bacs'] ) && ! isset( $again['paystack'] ) && array() === RWGCM_Gateway_Availability::last_unregistered_gateway_ids()
);

$availability = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-availability.php' );
$filter_start = is_string( $availability ) ? strpos( $availability, 'function filter_gateways' ) : false;
$filter_end   = is_string( $availability ) ? strpos( $availability, 'function should_restrict' ) : false;
$filter_body  = ( is_string( $availability ) && false !== $filter_start && false !== $filter_end ) ? substr( $availability, $filter_start, $filter_end - $filter_start ) : '';
assert_true( 'checkout filtering does not use the admin fallback', false === strpos( $filter_body, 'unregistered_gateway' ) );

$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-admin.php' );
assert_true(
	'the payments screen prints the fallback warning',
	is_string( $admin ) && false !== strpos( $admin, 'notice notice-warning' ) && false !== strpos( $admin, 'unregistered_checkout_notice' ) && false !== strpos( $admin, 'rwgcm-gateway-summary' )
);

$css = file_get_contents( dirname( __DIR__ ) . '/admin/css/rwgcm-admin.css' );
assert_true(
	'the country column stays visible while the gateway table scrolls',
	is_string( $css ) && false !== strpos( $css, '.rwgcm-gateway-matrix table th:first-child' ) && false !== strpos( $css, 'position: sticky' )
);

$plugin = file_get_contents( dirname( __DIR__ ) . '/reactwoo-geo-commerce.php' );
$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
assert_true(
	'the plugin version is 0.6.0',
	is_string( $plugin ) && false !== strpos( $plugin, 'Version: 0.6.0' ) && false !== strpos( $plugin, "define( 'RWGCM_VERSION', '0.6.0' )" )
);
assert_true( 'the readme stable tag is 0.6.0', is_string( $readme ) && false !== strpos( $readme, 'Stable tag: 0.6.0' ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
