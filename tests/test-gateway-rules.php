<?php
/**
 * Payment rules never add or require an unavailable gateway; classic and blocks agree
 * (run with: php tests/test-gateway-rules.php).
 *
 * Live 0.4.5 config: revolut_cc for GBP/EUR, paystack for ZAR/USD, ZA -> paystack only,
 * Paystack disabled in WooCommerce.
 *
 * @package ReactWoo_Geo_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['rw'] = array( 'currency' => 'ZAR', 'billing' => 'ZA', 'opts' => array() );
function __( $t, $d = 'default' ) { unset( $d ); return $t; } // phpcs:ignore
function _n( $s, $p, $n, $d = 'default' ) { unset( $d ); return 1 === $n ? $s : $p; } // phpcs:ignore
function apply_filters( $tag, $value ) { unset( $tag ); return $value; } // phpcs:ignore
function add_filter() {} // phpcs:ignore
function add_action() {} // phpcs:ignore
function did_action() { return 1; } // phpcs:ignore
function is_checkout() { return true; } // phpcs:ignore
function is_order_received_page() { return false; } // phpcs:ignore
function is_admin() { return false; } // phpcs:ignore
function wp_unslash( $v ) { return $v; } // phpcs:ignore
function sanitize_key( $v ) { return strtolower( (string) $v ); } // phpcs:ignore
function get_current_user_id() { return 0; } // phpcs:ignore
function rwgc_get_visitor_country() { return 'ZA'; } // phpcs:ignore
function get_woocommerce_currency() { return $GLOBALS['rw']['currency']; } // phpcs:ignore
function get_option( $k, $d = false ) { // phpcs:ignore
	$o = array_merge(
		array(
			'woocommerce_currency'     => 'GBP',
			'rwgcm_gateway_rules'      => array( 'countries' => array( 'ZA' => array( 'paystack' ) ), 'groups' => array() ),
			'rwgcm_gateway_currencies' => array( 'revolut_cc' => array( 'GBP', 'EUR' ), 'paystack' => array( 'ZAR', 'USD' ) ),
		),
		$GLOBALS['rw']['opts']
	);
	return isset( $o[ $k ] ) ? $o[ $k ] : $d;
}
/** Customer whose billing WooCommerce shows (0.4.5 defaults it to geo). */
class RW_Cust { public function get_billing_country() { return $GLOBALS['rw']['billing']; } } // phpcs:ignore
function WC() { static $wc = null; if ( null === $wc ) { $wc = new stdClass(); $wc->customer = new RW_Cust(); $wc->session = null; $wc->cart = null; } return $wc; } // phpcs:ignore

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-availability.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-currency.php';

$passed = 0;
$failed = 0;
function assert_true( $label, $condition ) { // phpcs:ignore
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$label}\n"; return; }
	++$failed; echo "FAIL: {$label}\n";
}
/** Both Commerce filters in WooCommerce order (priority 20, then 30). */
function rw_offer( array $available ) { // phpcs:ignore
	return array_keys( RWGCM_Gateway_Currency::filter_gateways( RWGCM_Gateway_Availability::filter_gateways( $available ) ) );
}
$revolut  = new stdClass();
$paystack = new stdClass();
$bacs     = new stdClass();
// WooCommerce's available list: Paystack disabled, so it is not in it.
$available = array( 'revolut_cc' => $revolut );

// Pure rule application.
$r = RWGCM_Gateway_Availability::apply_country_rule( $available, array( 'paystack' ) );
assert_true( 'BUG A: a rule naming only an unavailable gateway never adds it', ! isset( $r['gateways']['paystack'] ) );
assert_true( 'BUG A: unsatisfiable rule falls back to the available list', array( 'revolut_cc' ) === array_keys( $r['gateways'] ) && $r['fallback'] );
$r = RWGCM_Gateway_Availability::apply_country_rule( array( 'revolut_cc' => $revolut, 'paystack' => $paystack ), array( 'paystack' ) );
assert_true( 'rule still narrows when its gateway is available', array( 'paystack' ) === array_keys( $r['gateways'] ) && ! $r['fallback'] );
$r = RWGCM_Gateway_Availability::apply_country_rule( $available, null );
assert_true( 'no rule keeps the list', array( 'revolut_cc' ) === array_keys( $r['gateways'] ) && ! $r['fallback'] );
$r = RWGCM_Gateway_Availability::apply_country_rule( array(), array( 'paystack' ) );
assert_true( 'empty available list stays empty (nothing is invented)', array() === $r['gateways'] );

// End to end: ZA guest, ZAR cart, classic checkout, no billing typed (WooCommerce shows ZA).
$GLOBALS['rw']['currency'] = 'ZAR';
$GLOBALS['rw']['billing']  = 'ZA';
assert_true( 'BUG A: ZAR checkout never offers disabled Paystack', ! in_array( 'paystack', rw_offer( $available ), true ) );
assert_true( 'BUG A: ZAR checkout does not fall back to Revolut (GBP/EUR only)', array() === rw_offer( $available ) );
assert_true( 'BUG A: classic shows a clear ZAR message when nothing can take ZAR', false !== strpos( (string) RWGCM_Gateway_Currency::filter_no_methods_message( 'WC default' ), 'ZAR' ) );
assert_true( 'ZAR checkout with Paystack enabled offers only Paystack', array( 'paystack' ) === rw_offer( array( 'revolut_cc' => $revolut, 'paystack' => $paystack ) ) );

// BUG B: GBP cart (mixed or shipped) with a ZA address.
$GLOBALS['rw']['currency'] = 'GBP';
assert_true( 'BUG B: GBP cart + ZA address offers Revolut (rule unsatisfiable, currency allows GBP)', array( 'revolut_cc' ) === rw_offer( $available ) );
RWGCM_Gateway_Currency::filter_gateways( array( 'revolut_cc' => $revolut ) );
assert_true( 'no ZAR message when something is offered', 'WC default' === RWGCM_Gateway_Currency::filter_no_methods_message( 'WC default' ) );
assert_true( 'BUG B: GBP cart + ZA address with Paystack enabled (ZAR/USD only) still offers Revolut', array( 'revolut_cc' ) === rw_offer( array( 'revolut_cc' => $revolut, 'paystack' => $paystack ) ) );
$r = RWGCM_Gateway_Availability::apply_country_rule( array( 'revolut_cc' => $revolut, 'paystack' => $paystack ), array( 'paystack' ), array( 'revolut_cc' => $revolut ) );
assert_true( 'rule whose gateways cannot take the cart currency falls back to the currency-eligible list', array( 'revolut_cc' ) === array_keys( $r['gateways'] ) && $r['fallback'] );
$GLOBALS['rw']['billing'] = 'GB';
assert_true( 'GB customer GBP: Revolut', array( 'revolut_cc' ) === rw_offer( $available ) );

// Consistency: the rule country comes from the same place for classic and Store API.
$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-availability.php' );
assert_true( 'BUG B: customer billing fallback is no longer limited to Store API cart routes', false === strpos( $src, "if ( '' === \$country && \$store_api_cart )" ) );
$GLOBALS['rw']['billing']  = 'ZA';
$GLOBALS['rw']['currency'] = 'ZAR';
assert_true( 'classic page with no typed billing applies the ZA rule like blocks do', array( 'paystack' ) === rw_offer( array( 'revolut_cc' => $revolut, 'paystack' => $paystack, 'bacs' => $bacs ) ) );

// Admin warning.
$choices = array(
	'revolut_cc' => array( 'title' => 'Revolut', 'enabled' => true ),
	'paystack'   => array( 'title' => 'Paystack', 'enabled' => false ),
	'bacs'       => array( 'title' => 'Bank', 'enabled' => false ),
);
$rules = array( 'countries' => array( 'ZA' => array( 'paystack' ) ), 'groups' => array( 'eu' => array( 'ghost' ) ) );
$lists = array( 'revolut_cc' => array( 'GBP', 'EUR' ), 'paystack' => array( 'ZAR', 'USD' ) );
$w = RWGCM_Gateway_Availability::disabled_rule_warning( $rules, $lists, $choices, array( 'paystack' => false ) );
assert_true( 'admin warning names disabled Paystack and where it is used', false !== strpos( $w, 'Paystack (paystack, disabled in WooCommerce) in ZA, ZAR/USD' ) );
assert_true( 'admin warning names an unregistered gateway', false !== strpos( $w, 'ghost (ghost, not registered in WooCommerce) in eu' ) );
assert_true( 'admin warning skips enabled gateways', false === strpos( $w, 'Revolut' ) );
$w = RWGCM_Gateway_Availability::disabled_rule_warning( $rules, $lists, $choices, array( 'paystack' => true ) );
assert_true( 'no warning for Paystack saved as enabled (only off for the GBP store currency)', false === strpos( $w, 'Paystack' ) );
assert_true( 'no warning when every referenced gateway is enabled', '' === RWGCM_Gateway_Availability::disabled_rule_warning( array( 'countries' => array( 'GB' => array( 'revolut_cc' ) ) ), array(), $choices ) );
// 0.4.8: currency-list-only references are not warnings.
$choices2 = array(
	'revolut_hosted_checkout' => array( 'title' => 'Revolut Hosted Checkout', 'enabled' => false ),
	'paystack'                => array( 'title' => 'Paystack', 'enabled' => false ),
	'cheque'                  => array( 'title' => 'Cheque', 'enabled' => false ),
	'bacs'                    => array( 'title' => 'Bank', 'enabled' => true ),
);
$rules2 = array( 'countries' => array( 'ZA' => array( 'cheque' ) ), 'groups' => array() );
$lists2 = array( 'revolut_hosted_checkout' => array( 'GBP', 'EUR' ), 'paystack' => array( 'ZAR' ), 'bacs' => array( 'GBP' ), 'gone' => array( 'USD' ), 'cheque' => array( 'ZAR' ) );
$saved2 = array( 'revolut_hosted_checkout' => false, 'paystack' => true, 'cheque' => false, 'bacs' => true );
$w2 = RWGCM_Gateway_Availability::disabled_rule_warning( $rules2, $lists2, $choices2, $saved2 );
assert_true( 'warning still names a disabled gateway in a country rule', false !== strpos( $w2, 'Cheque (cheque, disabled in WooCommerce) in ZA, ZAR' ) );
assert_true( 'warning omits a disabled gateway that is only in a currency list', false === strpos( $w2, 'Revolut' ) && false === strpos( $w2, 'gone' ) );
assert_true( 'no warning at all when only currency lists reference disabled gateways', '' === RWGCM_Gateway_Availability::disabled_rule_warning( array(), $lists2, $choices2, $saved2 ) );
$i2 = RWGCM_Gateway_Availability::disabled_list_info( $rules2, $lists2, $choices2, $saved2 );
assert_true( 'soft info line for a disabled list-only gateway', in_array( 'Revolut Hosted Checkout is disabled in WooCommerce; its currency list has no effect.', $i2, true ) );
assert_true( 'soft info line for an unregistered list-only gateway', in_array( 'gone is not registered in WooCommerce; its currency list has no effect.', $i2, true ) );
assert_true( 'no info line for a rule gateway, an enabled one, or a saved-enabled one', 2 === count( $i2 ) );
assert_true( 'no info lines with no lists', array() === RWGCM_Gateway_Availability::disabled_list_info( $rules2, array(), $choices2, $saved2 ) );
assert_true( 'currency hint only for saved-enabled runtime-disabled', RWGCM_Gateway_Availability::explain_disabled_gateway( 'paystack', array( 'ZAR' ), true ) );
assert_true( 'no currency hint for a plainly disabled gateway with a list', ! RWGCM_Gateway_Availability::explain_disabled_gateway( 'revolut_hosted_checkout', array( 'GBP', 'EUR' ), false ) );
assert_true( 'no currency hint for plainly disabled Paystack', ! RWGCM_Gateway_Availability::explain_disabled_gateway( 'paystack', array(), false ) );
$admin_src = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-admin.php' );
assert_true( 'admin passes the saved enabled flag to the hint', false !== strpos( $admin_src, 'explain_disabled_gateway( $id, $saved_codes, ! empty( $saved_on[ $id ] ) )' ) );
assert_true( 'admin shows list info as notice-info, not a warning', false !== strpos( $admin_src, 'notice notice-info inline rwgcm-disabled-list-info' ) );
assert_true( 'admin says disabled in WooCommerce for plainly disabled gateways', false !== strpos( $admin_src, "'%s is disabled in WooCommerce.'" ) );
$GLOBALS['rw']['opts'] = array( 'woocommerce_paystack_settings' => array( 'enabled' => 'yes' ), 'woocommerce_bacs_settings' => array( 'enabled' => 'no' ) );
$flags = RWGCM_Gateway_Availability::saved_enabled_flags( array( 'paystack', 'bacs', 'none' ) );
assert_true( 'saved enabled flags read woocommerce_{id}_settings', true === $flags['paystack'] && false === $flags['bacs'] && false === $flags['none'] );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
