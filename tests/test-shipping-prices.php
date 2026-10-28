<?php
/**
 * Per-currency shipping (0.5.0): fields, read-time swap, priced decision,
 * rate tagging, hiding, guard and overview (run with: php tests/test-shipping-prices.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['rw_currency'] = 'GBP';
$GLOBALS['rw_opts']     = array( 'woocommerce_currency' => 'GBP' );
$GLOBALS['rw_filters']  = array();
$GLOBALS['rw_admin']    = false;
function add_filter() {} // phpcs:ignore
function add_action() {} // phpcs:ignore
function did_action( $h ) { unset( $h ); return 1; } // phpcs:ignore
function __( $s, $d = '' ) { unset( $d ); return $s; } // phpcs:ignore
function apply_filters( $tag, $value, ...$args ) { return isset( $GLOBALS['rw_filters'][ $tag ] ) ? call_user_func( $GLOBALS['rw_filters'][ $tag ], $value, ...$args ) : $value; } // phpcs:ignore
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['rw_opts'] ) ? $GLOBALS['rw_opts'][ $k ] : $d; } // phpcs:ignore
function get_woocommerce_currency() { return $GLOBALS['rw_currency']; } // phpcs:ignore
function wp_json_encode( $v ) { return json_encode( $v ); } // phpcs:ignore
function is_admin() { return $GLOBALS['rw_admin']; } // phpcs:ignore
function wp_doing_ajax() { return false; } // phpcs:ignore
function wp_doing_cron() { return false; } // phpcs:ignore

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-shipping-prices.php';

$passed = 0;
$failed = 0;
function assert_true( $label, $condition ) { // phpcs:ignore
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$label}\n"; return; }
	++$failed; echo "FAIL: {$label}\n";
}
$S = 'RWGCM_Shipping_Prices';

// --- Fields -----------------------------------------------------------
$native = array(
	'title' => array( 'title' => 'Title', 'type' => 'text' ),
	'cost'  => array( 'title' => 'Cost', 'type' => 'text', 'sanitize_callback' => 'rw_sanitize' ),
);
$f = $S::add_fields( 'flat_rate', $native, array( 'ZAR', 'EUR' ) );
assert_true( 'flat rate gets a heading', isset( $f['rwgcm_currency_prices'] ) && 'title' === $f['rwgcm_currency_prices']['type'] );
assert_true( 'flat rate gets Cost (ZAR) and Cost (EUR)', 'Cost (ZAR)' === $f['rwgcm_cost_ZAR']['title'] && 'Cost (EUR)' === $f['rwgcm_cost_EUR']['title'] );
assert_true( 'fields reuse WooCommerce sanitize_callback (formula validation)', 'rw_sanitize' === $f['rwgcm_cost_ZAR']['sanitize_callback'] );
assert_true( 'fields default blank with a "not offered" placeholder', '' === $f['rwgcm_cost_ZAR']['default'] && false !== strpos( $f['rwgcm_cost_ZAR']['placeholder'], 'not offered in ZAR' ) );
assert_true( 'currency field has a visible helper and a decimal keypad', false === $f['rwgcm_cost_ZAR']['desc_tip'] && '' !== $f['rwgcm_cost_ZAR']['description'] && 'decimal' === $f['rwgcm_cost_ZAR']['custom_attributes']['inputmode'] );
assert_true( 'native fields untouched and first', array( 'title', 'cost' ) === array_slice( array_keys( $f ), 0, 2 ) && $native['cost'] === $f['cost'] );
$f = $S::add_fields( 'free_shipping', array( 'min_amount' => array( 'type' => 'price' ) ), array( 'ZAR' ) );
assert_true( 'free shipping gets Minimum order amount (ZAR)', 'Minimum order amount (ZAR)' === $f['rwgcm_min_amount_ZAR']['title'] );
assert_true( 'free-shipping minimum has a helper under the field', false === $f['rwgcm_min_amount_ZAR']['desc_tip'] && false !== strpos( $f['rwgcm_min_amount_ZAR']['description'], 'store currency' ) && 'decimal' === $f['rwgcm_min_amount_ZAR']['custom_attributes']['inputmode'] );
$class_fields = array(
	'cost'           => array( 'title' => 'Cost', 'type' => 'text', 'sanitize_callback' => 'rw_sanitize' ),
	'class_cost_12'  => array( 'title' => '"Heavy" shipping class cost', 'type' => 'text', 'sanitize_callback' => 'rw_sanitize' ),
	'no_class_cost'  => array( 'title' => 'No shipping class cost', 'type' => 'text', 'sanitize_callback' => 'rw_sanitize' ),
);
$cf = $S::add_fields( 'flat_rate', $class_fields, array( 'ZAR' ) );
assert_true( 'class cost field key and label', isset( $cf['rwgcm_class_cost_12_ZAR'] ) && false !== strpos( $cf['rwgcm_class_cost_12_ZAR']['title'], 'Heavy' ) && false !== strpos( $cf['rwgcm_class_cost_12_ZAR']['title'], 'ZAR' ) );
assert_true( 'no-class cost field', 'rwgcm_no_class_cost_ZAR' === $S::manual_component_key( 'no_class_cost', 'ZAR' ) && isset( $cf['rwgcm_no_class_cost_ZAR'] ) && 'rw_sanitize' === $cf['rwgcm_class_cost_12_ZAR']['sanitize_callback'] );
assert_true( 'class cost helper says a blank keeps the flat rate in the store currency', false !== strpos( $cf['rwgcm_class_cost_12_ZAR']['description'], 'stays in the store currency' ) );
assert_true( 'native class cost field is not replaced', $class_fields['class_cost_12'] === $cf['class_cost_12'] );
$f = $S::add_fields( 'local_pickup', array( 'cost' => array() ), array( 'ZAR' ) );
assert_true( 'local pickup gets Cost (ZAR)', isset( $f['rwgcm_cost_ZAR'] ) );
assert_true( 'unsupported method unchanged', array( 'x' => 1 ) === $S::add_fields( 'royal_mail', array( 'x' => 1 ), array( 'ZAR' ) ) );
assert_true( 'no extra currencies: unchanged', array( 'cost' => array() ) === $S::add_fields( 'flat_rate', array( 'cost' => array() ), array() ) );
assert_true( 'non-array fields pass through', 'x' === $S::add_fields( 'flat_rate', 'x', array( 'ZAR' ) ) );
assert_true( 'extra currencies exclude the store currency', ! in_array( 'GBP', $S::extra_currencies( 'GBP' ), true ) && in_array( 'ZAR', $S::extra_currencies( 'GBP' ), true ) );
assert_true( 'field keys', 'rwgcm_cost_ZAR' === $S::field_key( 'flat_rate', 'zar' ) && 'rwgcm_min_amount_ZAR' === $S::field_key( 'free_shipping', 'ZAR' ) && '' === $S::field_key( 'table_rate', 'ZAR' ) );

// --- Swap ---------------------------------------------------------------
$set = array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150 + 5 * [qty]', 'rwgcm_cost_EUR' => '' );
assert_true( 'swap: ZAR returns the ZAR formula string unchanged', '150 + 5 * [qty]' === $S::swapped_value( 'flat_rate', $set, '10', 'ZAR', 'GBP' ) );
assert_true( 'swap: blank EUR keeps the store value', '10' === $S::swapped_value( 'flat_rate', $set, '10', 'EUR', 'GBP' ) );
assert_true( 'swap: store currency keeps the store value', '10' === $S::swapped_value( 'flat_rate', $set, '10', 'GBP', 'GBP' ) );
assert_true( 'swap: free shipping minimum', '1000' === $S::swapped_value( 'free_shipping', array( 'min_amount' => '60', 'rwgcm_min_amount_ZAR' => ' 1000 ' ), '60', 'ZAR', 'GBP' ) );
$partial = array( 'cost' => '10', 'rwgcm_cost_ZAR' => '230', 'class_cost_12' => '5' );
assert_true( 'swap: a missing class amount does not swap the base cost', '10' === $S::swapped_value( 'flat_rate', $partial, '10', 'ZAR', 'GBP', 'cost' ) );
assert_true( 'swap: a missing class amount does not swap the class cost', '5' === $S::swapped_value( 'flat_rate', $partial, '5', 'ZAR', 'GBP', 'class_cost_12' ) );

class RW_Method { public $id; public $instance_settings; public function __construct( $id, $s ) { $this->id = $id; $this->instance_settings = $s; } } // phpcs:ignore
$m = new RW_Method( 'flat_rate', $set );
$GLOBALS['rw_currency'] = 'ZAR';
assert_true( 'filter_instance_option: storefront ZAR cart reads the ZAR cost', '150 + 5 * [qty]' === $S::filter_instance_option( '10', 'cost', $m ) );
assert_true( 'filter_instance_option: other keys untouched', 'Flat' === $S::filter_instance_option( 'Flat', 'title', $m ) );
assert_true( 'filter_instance_option: free shipping cost key is not swapped', '0' === $S::filter_instance_option( '0', 'cost', new RW_Method( 'free_shipping', array( 'rwgcm_min_amount_ZAR' => '1' ) ) ) );
$GLOBALS['rw_admin'] = true;
assert_true( 'filter_instance_option: wp-admin never sees the swapped value', '10' === $S::filter_instance_option( '10', 'cost', $m ) );
$GLOBALS['rw_admin'] = false;
define( 'REST_REQUEST', true );
$_SERVER['REQUEST_URI'] = '/wp-json/wc/v3/shipping/zones/1/methods';
assert_true( 'filter_instance_option: REST export (wc/v3) never sees the swapped value', '10' === $S::filter_instance_option( '10', 'cost', $m ) );
$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/cart';
assert_true( 'filter_instance_option: Store API cart is swapped', '150 + 5 * [qty]' === $S::filter_instance_option( '10', 'cost', $m ) );
$GLOBALS['rw_currency'] = 'GBP';
assert_true( 'filter_instance_option: GBP cart keeps the GBP cost', '10' === $S::filter_instance_option( '10', 'cost', $m ) );

// --- Method status ------------------------------------------------------
$st = function ( $id, $s, $c = 'ZAR' ) use ( $S ) { return $S::method_status( $id, $s, $c, 'GBP' ); };
assert_true( 'flat rate with ZAR cost is priced', $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150' ) )['priced'] );
assert_true( 'flat rate without ZAR cost is not priced', 'missing' === $st( 'flat_rate', array( 'cost' => '10' ) )['reason'] );
assert_true( 'flat rate with class costs and no ZAR class amount stays unpriced', 'class_costs' === $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'class_cost_12' => '5' ) )['reason'] );
assert_true( 'flat rate with only no_class_cost and no ZAR amount stays unpriced', 'class_costs' === $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'no_class_cost' => '2' ) )['reason'] );
assert_true( 'blank class cost fields do not count', $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'class_cost_12' => '', 'no_class_cost' => '' ) )['priced'] );
assert_true( 'a zero class cost does not need a ZAR amount', $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'class_cost_12' => '0' ) )['priced'] );
$full_class = array( 'cost' => '10', 'rwgcm_cost_ZAR' => '230', 'class_cost_12' => '5', 'rwgcm_class_cost_12_ZAR' => '40', 'no_class_cost' => '2', 'rwgcm_no_class_cost_ZAR' => '15' );
assert_true( 'flat rate is priced when every class cost has a ZAR amount', $st( 'flat_rate', $full_class )['priced'] );
assert_true( 'priced class-cost label names the ZAR base and the class amounts', '230 + class 40, 15' === $st( 'flat_rate', $full_class )['amount'] );
assert_true( 'one missing class amount keeps the method unpriced', 'class_costs' === $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '230', 'class_cost_12' => '5', 'rwgcm_class_cost_12_ZAR' => '40', 'no_class_cost' => '2' ) )['reason'] );
assert_true( 'class amount without a base amount is not priced', 'missing' === $st( 'flat_rate', array( 'cost' => '10', 'class_cost_12' => '5', 'rwgcm_class_cost_12_ZAR' => '40' ) )['reason'] );
assert_true( 'swap: complete class amounts swap the base and the class cost', '230' === $S::swapped_value( 'flat_rate', $full_class, '10', 'ZAR', 'GBP', 'cost' ) && '40' === $S::swapped_value( 'flat_rate', $full_class, '5', 'ZAR', 'GBP', 'class_cost_12' ) && '15' === $S::swapped_value( 'flat_rate', $full_class, '2', 'ZAR', 'GBP', 'no_class_cost' ) );
$before_swap = $full_class;
$GLOBALS['rw_currency'] = 'ZAR';
$cm = new RW_Method( 'flat_rate', $full_class );
assert_true( 'filter reads the ZAR class cost only when every component is priced', '40' === $S::filter_instance_option( '5', 'class_cost_12', $cm ) && '230' === $S::filter_instance_option( '10', 'cost', $cm ) );
$pm = new RW_Method( 'flat_rate', $partial );
assert_true( 'filter does not swap any component while a class amount is missing', '10' === $S::filter_instance_option( '10', 'cost', $pm ) && '5' === $S::filter_instance_option( '5', 'class_cost_12', $pm ) );
$GLOBALS['rw_currency'] = 'GBP';
$S::swapped_value( 'flat_rate', $full_class, '10', 'ZAR', 'GBP', 'cost' );
assert_true( 'swap does not write the instance settings', $before_swap === $full_class );
assert_true( 'free flat rate (cost 0) is priced in any currency', 'zero' === $st( 'flat_rate', array( 'cost' => '0' ) )['reason'] );
assert_true( 'free shipping coupon-only is priced', 'coupon_only' === $st( 'free_shipping', array( 'requires' => 'coupon', 'min_amount' => '60' ) )['reason'] );
assert_true( 'free shipping with no requirement is priced', 'no_minimum' === $st( 'free_shipping', array( 'requires' => '' ) )['reason'] );
foreach ( array( 'min_amount', 'either', 'both' ) as $req ) {
	assert_true( "free shipping requires={$req} without ZAR minimum is not priced", ! $st( 'free_shipping', array( 'requires' => $req, 'min_amount' => '60' ) )['priced'] );
	assert_true( "free shipping requires={$req} with ZAR minimum is priced", $st( 'free_shipping', array( 'requires' => $req, 'min_amount' => '60', 'rwgcm_min_amount_ZAR' => '1000' ) )['priced'] );
}
assert_true( 'free shipping min 0 needs no ZAR minimum', $st( 'free_shipping', array( 'requires' => 'min_amount', 'min_amount' => '0' ) )['priced'] );
assert_true( 'local pickup with ZAR cost is priced', $st( 'local_pickup', array( 'cost' => '3', 'rwgcm_cost_ZAR' => '30' ) )['priced'] );
assert_true( 'free local pickup is priced', $st( 'local_pickup', array( 'cost' => '' ) )['priced'] );
assert_true( 'local pickup GBP cost only is not priced', ! $st( 'local_pickup', array( 'cost' => '3' ) )['priced'] );
assert_true( 'carrier / other method is never priced', 'unsupported' === $st( 'royal_mail', array( 'rwgcm_cost_ZAR' => '1' ) )['reason'] );
assert_true( 'everything is priced in the store currency', $st( 'royal_mail', array(), 'GBP' )['priced'] );

// --- Zone decision ------------------------------------------------------
$flat_zar  = array( 'id' => 'flat_rate', 'settings' => array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150' ) );
$free_zar  = array( 'id' => 'free_shipping', 'settings' => array( 'requires' => 'min_amount', 'min_amount' => '60', 'rwgcm_min_amount_ZAR' => '1000' ) );
$pickup    = array( 'id' => 'local_pickup', 'settings' => array( 'cost' => '3' ) );
$carrier   = array( 'id' => 'royal_mail', 'settings' => array() );
$coupon    = array( 'id' => 'free_shipping', 'settings' => array( 'requires' => 'coupon' ) );
assert_true( 'zone: all priced -> priced', $S::zones_priced( array( array( $flat_zar, $free_zar ) ), 'ZAR', 'GBP', false ) );
assert_true( 'zone: one unpriced method -> GBP by default', ! $S::zones_priced( array( array( $flat_zar, $pickup ) ), 'ZAR', 'GBP', false ) );
assert_true( 'zone: carrier present -> GBP by default', ! $S::zones_priced( array( array( $flat_zar, $carrier ) ), 'ZAR', 'GBP', false ) );
assert_true( 'zone: coupon-only free shipping counts as priced', $S::zones_priced( array( array( $flat_zar, $coupon ) ), 'ZAR', 'GBP', false ) );
assert_true( 'zone: hide on, one priced -> priced', $S::zones_priced( array( array( $flat_zar, $pickup, $carrier ) ), 'ZAR', 'GBP', true ) );
assert_true( 'zone: hide on, none priced -> GBP (never hide everything)', ! $S::zones_priced( array( array( $pickup, $carrier ) ), 'ZAR', 'GBP', true ) );
assert_true( 'multi-package: every package must be priced', ! $S::zones_priced( array( array( $flat_zar ), array( $pickup ) ), 'ZAR', 'GBP', false ) );
assert_true( 'multi-package: both priced', $S::zones_priced( array( array( $flat_zar ), array( $free_zar ) ), 'ZAR', 'GBP', false ) );
assert_true( 'zone with no enabled methods does not block', $S::zones_priced( array( array(), array( $flat_zar ) ), 'ZAR', 'GBP', false ) );
assert_true( 'store currency: always priced', $S::zones_priced( array( array( $carrier ) ), 'GBP', 'GBP', false ) );

// --- shipping_priced filter contract ---------------------------------------
$seen = null;
$GLOBALS['rw_filters']['rwgcm_price_book_shipping_priced'] = function ( $v, $cur, $pk ) use ( &$seen ) { $seen = array( $v, $cur, $pk ); return $v; };
RWGCM_Price_Book_Currency::shipping_priced( 'ZAR', array( array( 'destination' => array( 'country' => 'ZA' ) ) ) );
assert_true( 'filter receives (computed, currency, packages)', is_array( $seen ) && false === $seen[0] && 'ZAR' === $seen[1] && 'ZA' === $seen[2][0]['destination']['country'] );
$GLOBALS['rw_filters']['rwgcm_price_book_shipping_priced'] = function () { return true; };
assert_true( 'filter can still force true (0.4.5 contract)', true === RWGCM_Price_Book_Currency::shipping_priced( 'ZAR', array() ) );
unset( $GLOBALS['rw_filters']['rwgcm_price_book_shipping_priced'] );
assert_true( 'no packages: not priced by default', false === RWGCM_Price_Book_Currency::shipping_priced( 'ZAR', array() ) );

// --- Hide setting -------------------------------------------------------
assert_true( 'hide unpriced is off by default', false === $S::hide_unpriced() );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'yes';
assert_true( 'hide unpriced on via option', true === $S::hide_unpriced() );
$GLOBALS['rw_filters']['rwgcm_shipping_hide_unpriced'] = function () { return false; };
assert_true( 'rwgcm_shipping_hide_unpriced filter overrides', false === $S::hide_unpriced() );
unset( $GLOBALS['rw_filters']['rwgcm_shipping_hide_unpriced'] );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'no';

// --- Rate tagging and hiding ----------------------------------------------
class RW_Rate { public $mid; public $inst; public $meta = array(); public function __construct( $m, $i ) { $this->mid = $m; $this->inst = $i; } public function get_method_id() { return $this->mid; } public function get_instance_id() { return $this->inst; } public function add_meta_data( $k, $v ) { $this->meta[ $k ] = $v; } } // phpcs:ignore
assert_true( 'rate currency: priced -> active', 'ZAR' === $S::rate_currency( true, false, 'ZAR', 'GBP' ) );
assert_true( 'rate currency: unpriced -> store currency', 'GBP' === $S::rate_currency( false, false, 'ZAR', 'GBP' ) );
assert_true( 'rate currency: unpriced but declared by the site filter -> active', 'ZAR' === $S::rate_currency( false, true, 'ZAR', 'GBP' ) );
$GLOBALS['rw_opts']['woocommerce_flat_rate_1_settings']    = array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150' );
$GLOBALS['rw_opts']['woocommerce_local_pickup_4_settings'] = array( 'cost' => '3' );
$GLOBALS['rw_currency']                                    = 'ZAR';
$rates = array( 'flat_rate:1' => new RW_Rate( 'flat_rate', 1 ), 'local_pickup:4' => new RW_Rate( 'local_pickup', 4 ) );
$out   = $S::filter_package_rates( $rates, 'not-a-package' );
assert_true( 'tagging: ZAR-priced flat rate tagged ZAR', 'ZAR' === $out['flat_rate:1']->meta['rwgcm_priced_currency'] );
assert_true( 'tagging: unpriced pickup tagged GBP', 'GBP' === $out['local_pickup:4']->meta['rwgcm_priced_currency'] );
assert_true( 'tagging: hide off keeps every rate', 2 === count( $out ) );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'yes';
$out = $S::filter_package_rates( array( 'flat_rate:1' => new RW_Rate( 'flat_rate', 1 ), 'local_pickup:4' => new RW_Rate( 'local_pickup', 4 ) ), 'not-a-package' );
assert_true( 'hiding: unpriced pickup removed in ZAR', array( 'flat_rate:1' ) === array_keys( $out ) );
$out = $S::filter_package_rates( array( 'local_pickup:4' => new RW_Rate( 'local_pickup', 4 ) ), 'not-a-package' );
assert_true( 'hiding: never removes every rate', array( 'local_pickup:4' ) === array_keys( $out ) );
$GLOBALS['rw_currency'] = 'GBP';
$out = $S::filter_package_rates( array( 'flat_rate:1' => new RW_Rate( 'flat_rate', 1 ), 'local_pickup:4' => new RW_Rate( 'local_pickup', 4 ) ), 'not-a-package' );
assert_true( 'GBP cart: nothing hidden, everything tagged GBP', 2 === count( $out ) && 'GBP' === $out['local_pickup:4']->meta['rwgcm_priced_currency'] && 'GBP' === $out['flat_rate:1']->meta['rwgcm_priced_currency'] );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'no';
assert_true( 'non-array rates pass through', 'x' === $S::filter_package_rates( 'x', array() ) );

// --- Guard ------------------------------------------------------------------
assert_true( 'guard: GBP order passes whatever the lines say', $S::shipping_lines_ok( array( array( 'total' => 5, 'meta' => '' ) ), 'GBP', 'GBP' ) );
assert_true( 'guard: ZAR order with ZAR line passes', $S::shipping_lines_ok( array( array( 'total' => 150, 'meta' => 'ZAR' ) ), 'ZAR', 'GBP' ) );
assert_true( 'guard: ZAR order with GBP line refused', ! $S::shipping_lines_ok( array( array( 'total' => 10, 'meta' => 'GBP' ) ), 'ZAR', 'GBP' ) );
assert_true( 'guard: ZAR order with untagged line refused', ! $S::shipping_lines_ok( array( array( 'total' => 10, 'meta' => '' ) ), 'ZAR', 'GBP' ) );
assert_true( 'guard: one bad line out of two refused', ! $S::shipping_lines_ok( array( array( 'total' => 150, 'meta' => 'ZAR' ), array( 'total' => 0, 'meta' => 'GBP' ) ), 'ZAR', 'GBP' ) );
assert_true( 'guard: ZAR order without shipping lines passes', $S::shipping_lines_ok( array(), 'ZAR', 'GBP' ) );

// --- Address-change notice ---------------------------------------------------
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } } // phpcs:ignore
$rp = new ReflectionProperty( 'RWGCM_Price_Book_Currency', 'shipping_fallback' );
$rp->setAccessible( true );
$rp->setValue( null, '' );
assert_true( 'no fallback: no notice text', '' === $S::current_notice() );
assert_true( 'no fallback: empty wrapper is still rendered (fragment target)', '<div class="rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--checkout"></div>' === $S::notice_html( '', 'checkout' ) );
$rp->setValue( null, 'ZAR' );
assert_true( 'fallback: notice text from the current state (no session, no one-shot)', false !== strpos( $S::current_notice(), 'Prices are shown in GBP' ) && false !== strpos( $S::current_notice(), 'priced in ZAR' ) );
assert_true( 'fallback: stays on every render (persistent)', $S::current_notice() === $S::current_notice() );
$rp->setValue( null, 'GBP' );
assert_true( 'fallback equal to the store currency is cleared', '' === $S::current_notice() );
$GLOBALS['rw_currency'] = 'ZAR';
$rp->setValue( null, 'ZAR' );
assert_true( 'notice is cleared when the cart is already in the visitor currency', '' === $S::current_notice() );
$GLOBALS['rw_currency'] = 'GBP';
$rp->setValue( null, 'ZAR' );
$h = $S::notice_html( $S::current_notice(), 'checkout' );
assert_true( 'fallback: one woocommerce-info inside one wrapper', 1 === substr_count( $h, 'woocommerce-info' ) && 1 === substr_count( $h, 'rwgcm-shipping-currency-notice--checkout' ) );
assert_true( 'cart wrapper class', false !== strpos( $S::notice_html( 'x', 'cart' ), 'rwgcm-shipping-currency-notice--cart' ) );
$cart_row = $S::notice_html( $S::current_notice(), 'cart' );
assert_true(
	'cart notice is one totals row and not a WooCommerce notice',
	1 === substr_count( $cart_row, 'rwgcm-shipping-currency-notice--cart' )
		&& 0 === strpos( $cart_row, '<tr ' )
		&& false === strpos( $cart_row, 'woocommerce-info' )
		&& false === strpos( $cart_row, 'woocommerce-message' )
		&& false === strpos( $cart_row, 'woocommerce-error' )
		&& false !== strpos( $cart_row, 'Prices are shown in GBP' )
);
assert_true( 'cart notice markup is absent when there is nothing to say', '' === $S::notice_html( '', 'cart' ) );
assert_true( 'notice text is escaped', false === strpos( $S::notice_html( '<b>x</b>' ), '<b>' ) );
$fr = $S::filter_checkout_fragments( array( '.woocommerce-checkout-review-order-table' => 'T' ) );
assert_true( 'fragments: wrapper replaced in place by its own selector', isset( $fr['.rwgcm-shipping-currency-notice--checkout'] ) && false !== strpos( $fr['.rwgcm-shipping-currency-notice--checkout'], 'Prices are shown' ) && 'T' === $fr['.woocommerce-checkout-review-order-table'] );
$rp->setValue( null, '' );
$fr = $S::filter_checkout_fragments( array() );
assert_true( 'fragments: back to ZAR empties the wrapper (notice disappears)', false === strpos( $fr['.rwgcm-shipping-currency-notice--checkout'], 'woocommerce-info' ) );
assert_true( 'fragments: non-array passes through', 'x' === $S::filter_checkout_fragments( 'x' ) );
$GLOBALS['rw_admin'] = true;
$rp->setValue( null, 'ZAR' );
assert_true( 'admin context: no notice', '' === $S::current_notice() );
$GLOBALS['rw_admin'] = false;
$rp->setValue( null, '' );
assert_true( 'cart notice disappears when the cart is priced in the visitor currency', '' === $S::notice_html( $S::current_notice(), 'cart' ) );
$GLOBALS['rw_currency'] = 'ZAR';
$hz = $S::filter_cart_hash( 'abc' );
$GLOBALS['rw_currency'] = 'GBP';
$hg = $S::filter_cart_hash( 'abc' );
assert_true( 'cart hash keeps the WooCommerce hash and adds the active currency', 0 === strpos( $hz, 'abc|rwgcm:ZAR' ) && 'abc|rwgcm:GBP' === $hg && $hz !== $hg );
$frags = $S::filter_cart_fragments( array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">x</div>' ) );
assert_true(
	'mini-cart fragment records the active currency',
	isset( $frags['span.rwgcm-mini-cart-currency'] )
		&& false !== strpos( $frags['span.rwgcm-mini-cart-currency'], 'data-currency="GBP"' )
		&& false !== strpos( $frags['div.widget_shopping_cart_content'], 'data-currency="GBP"' )
);
$GLOBALS['rw_currency'] = 'ZAR';
$frags = $S::filter_cart_fragments( $frags );
assert_true( 'mini-cart fragment follows a currency switch', false !== strpos( $frags['div.widget_shopping_cart_content'], 'data-currency="ZAR"' ) && 1 === substr_count( $frags['div.widget_shopping_cart_content'], 'rwgcm-mini-cart-currency' ) );
assert_true( 'cart fragments: non-array passes through', 'x' === $S::filter_cart_fragments( 'x' ) );
$kept = $S::append_header_cart_fragments(
	array( 'a.cart-contents' => '<a class="cart-contents">theme</a>' ),
	'GBP',
	'£23.00',
	'1'
);
assert_true( 'theme header fragment is left as the theme rendered it', '<a class="cart-contents">theme</a>' === $kept['a.cart-contents'] );
assert_true( 'missing header cart link is added for the active currency', isset( $kept['.site-header-cart a.cart-contents'] ) && false !== strpos( $kept['.site-header-cart a.cart-contents'], 'data-rwgcm-currency="GBP"' ) && false !== strpos( $kept['.site-header-cart a.cart-contents'], '£23.00' ) );
$GLOBALS['rw_filters']['rwgcm_header_cart_fragment_selectors'] = function ( $selectors ) {
	$selectors[] = '.header-cart-amount';
	return $selectors;
};
$added = $S::append_header_cart_fragments( array(), 'ZAR', 'R499.00', '1' );
unset( $GLOBALS['rw_filters']['rwgcm_header_cart_fragment_selectors'] );
assert_true(
	'filterable header selector gets an amount element in the active currency',
	isset( $added['.header-cart-amount'] )
		&& false !== strpos( $added['.header-cart-amount'], 'class="header-cart-amount"' )
		&& false !== strpos( $added['.header-cart-amount'], 'data-rwgcm-currency="ZAR"' )
		&& false !== strpos( $added['.header-cart-amount'], 'R499.00' )
		&& 'cart-contents' === $S::fragment_class_from_selector( '.site-header-cart a.cart-contents' )
);
$script = $S::cart_fragment_refresh_script();
assert_true(
	'cart refresh binds the totals events on document.body and posts get_refreshed_fragments',
	false !== strpos( $script, '$(document.body).on(\'updated_cart_totals updated_shipping_method updated_wc_div\',arm)' )
		&& false !== strpos( $script, 'form.woocommerce-shipping-calculator' )
		&& false !== strpos( $script, 'get_refreshed_fragments' )
		&& false !== strpos( $script, "$.ajax({url:endpoint(),type:'POST'" )
		&& false === strpos( $script, "trigger('wc_fragment_refresh')" )
);
$submit_at = strpos( $script, 'form.woocommerce-shipping-calculator' );
$submit_fn = false !== $submit_at ? substr( $script, $submit_at, 180 ) : '';
assert_true( 'calculator submit does not send before the country is saved', false !== strpos( $submit_fn, 'has not saved the shipping country' ) && false === strpos( $submit_fn, 'arm()' ) );
assert_true( 'a fragment response does not re-arm the debounce', false !== strpos( $script, "url.indexOf('get_refreshed_fragments')!==-1){return;}" ) );
assert_true( 'a switch during an in-flight refresh queues another send', false !== strpos( $script, 'if(inflight){again=true;return;}' ) && false !== strpos( $script, 'if(again){arm();}' ) );
assert_true( 'cart refresh is debounced, not a one-shot flag', false !== strpos( $script, 'clearTimeout' ) && false !== strpos( $script, 'setTimeout' ) && false === strpos( $script, 'var fired' ) && false === strpos( $script, 'var done' ) );
assert_true( 'a currency change drops cached wc_fragments sessionStorage', false !== strpos( $script, "key.indexOf('wc_fragments')" ) && false !== strpos( $script, 'sessionStorage.removeItem' ) && false !== strpos( $script, 'data-rwgcm-currency' ) );
assert_true( 'totals carry the active currency for the fragment script', false !== strpos( $S::cart_currency_marker_html( 'ZAR' ), 'data-rwgcm-currency="ZAR"' ) && false === strpos( $S::cart_currency_marker_html( 'GBP' ), 'woocommerce-info' ) );
assert_true( 'notice copy', 'Prices are shown in GBP because shipping to United Kingdom isn’t priced in ZAR.' === $S::fallback_notice( 'ZAR', 'GBP', 'United Kingdom' ) );
assert_true( 'notice copy without a country', false !== strpos( $S::fallback_notice( 'ZAR', 'GBP', '' ), 'this address' ) );

// --- Overview -----------------------------------------------------------------
$zones = array(
	array( 'id' => 1, 'name' => 'South Africa', 'methods' => array( $flat_zar + array( 'title' => 'Flat', 'enabled' => true ), $free_zar + array( 'title' => 'Free', 'enabled' => true ), $pickup + array( 'title' => 'Collect', 'enabled' => false ) ) ),
	array( 'id' => 2, 'name' => 'UK', 'methods' => array( array( 'id' => 'flat_rate', 'settings' => array( 'cost' => '5' ), 'title' => 'UK', 'enabled' => true ) ) ),
	array( 'id' => 0, 'name' => 'Rest', 'methods' => array() ),
);
$ov = $S::overview( $zones, array( 'ZAR', 'EUR' ), 'GBP', false );
assert_true( 'overview: ZA zone priced in ZAR (disabled pickup ignored)', true === $ov[0]['verdicts']['ZAR'] );
assert_true( 'overview: ZA zone not priced in EUR', false === $ov[0]['verdicts']['EUR'] );
assert_true( 'overview: UK zone stays GBP for ZAR', false === $ov[1]['verdicts']['ZAR'] );
assert_true( 'overview: empty zone has no verdict', null === $ov[2]['verdicts']['ZAR'] );
assert_true( 'overview: cell shows the ZAR cost', 'ZAR 150' === $ov[0]['rows'][0]['cells']['ZAR']['label'] );
assert_true( 'overview: free shipping cell shows the minimum', 'minimum ZAR 1000' === $ov[0]['rows'][1]['cells']['ZAR']['label'] );
assert_true( 'overview: missing reason', 'no EUR amount' === $ov[0]['rows'][0]['cells']['EUR']['label'] );
assert_true( 'overview: disabled method flagged', false === $ov[0]['rows'][2]['enabled'] );
assert_true( 'reason labels for carrier and class costs', 'carrier, store currency only' === $S::reason_label( array( 'priced' => false, 'reason' => 'unsupported', 'amount' => '' ), 'ZAR' ) && 'class costs need a ZAR amount' === $S::reason_label( array( 'priced' => false, 'reason' => 'class_costs', 'amount' => '' ), 'ZAR' ) );

// --- computed_priced against zones (WC_Shipping_Zones stand-in) -----------------
if ( true ) { // Conditional so the class is not hoisted above the earlier "no WooCommerce" checks.
	class RW_ZoneMethod { public $id; public $instance_id; public $enabled = 'yes'; public function __construct( $id, $i ) { $this->id = $id; $this->instance_id = $i; } public function get_method_title() { return $this->id; } } // phpcs:ignore
	class RW_Zone { public $m; public function __construct( $m ) { $this->m = $m; } public function get_shipping_methods( $enabled_only = false ) { unset( $enabled_only ); return $this->m; } } // phpcs:ignore
	class WC_Shipping_Zones { public static $calls = 0; public static function get_zone_matching_package( $p ) { ++self::$calls; return 'ZA' === $p['destination']['country'] ? new RW_Zone( array( new RW_ZoneMethod( 'flat_rate', 1 ), new RW_ZoneMethod( 'free_shipping', 2 ) ) ) : new RW_Zone( array( new RW_ZoneMethod( 'flat_rate', 3 ) ) ); } } // phpcs:ignore
}
$GLOBALS['rw_opts']['woocommerce_free_shipping_2_settings'] = array( 'requires' => 'min_amount', 'min_amount' => '60', 'rwgcm_min_amount_ZAR' => '1000' );
$GLOBALS['rw_opts']['woocommerce_flat_rate_3_settings']     = array( 'cost' => '5' );
$za = array( 'destination' => array( 'country' => 'ZA', 'state' => 'GP', 'postcode' => '2000' ) );
$gb = array( 'destination' => array( 'country' => 'GB', 'state' => '', 'postcode' => 'SW1A 1AA' ) );
$S::reset_memo();
assert_true( 'computed: ZA package (flat R150 + free min R1000) is priced in ZAR', true === $S::computed_priced( 'ZAR', array( $za ) ) );
assert_true( 'computed: GB package (flat GBP only) is not priced in ZAR', false === $S::computed_priced( 'ZAR', array( $gb ) ) );
assert_true( 'computed: ZA + GB packages -> not priced', false === $S::computed_priced( 'ZAR', array( $za, $gb ) ) );
assert_true( 'computed: GBP always priced', true === $S::computed_priced( 'GBP', array( $gb ) ) );
assert_true( 'computed: no packages -> not priced', false === $S::computed_priced( 'ZAR', array() ) );
$before = WC_Shipping_Zones::$calls;
$S::computed_priced( 'ZAR', array( $za ) );
$S::computed_priced( 'ZAR', array( $za ) );
assert_true( 'computed: zone lookups are memoized per destination', $before === WC_Shipping_Zones::$calls );
assert_true( 'shipping_priced default now comes from the zones', true === RWGCM_Price_Book_Currency::shipping_priced( 'ZAR', array( $za ) ) && false === RWGCM_Price_Book_Currency::shipping_priced( 'ZAR', array( $gb ) ) );
$GLOBALS['rw_opts']['woocommerce_free_shipping_2_settings'] = array( 'requires' => 'min_amount', 'min_amount' => '60' );
$S::reset_memo();
assert_true( 'computed: blanking the ZAR free-shipping minimum makes the ZA zone fall back', false === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'yes';
assert_true( 'computed: ...unless hiding is on (flat rate still priced)', true === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'no';
$GLOBALS['rw_currency'] = 'ZAR';
$GLOBALS['rw_filters']['rwgcm_price_book_shipping_priced'] = function ( $v, $c ) { return 'ZAR' === $c ? true : $v; };
$out = $S::filter_package_rates( array( 'flat_rate:3' => new RW_Rate( 'flat_rate', 3 ) ), $gb );
assert_true( 'site filter forcing ZAR: GBP-only rate tagged ZAR with source=filter (0.4.5 contract kept)', 'ZAR' === $out['flat_rate:3']->meta['rwgcm_priced_currency'] && 'filter' === $out['flat_rate:3']->meta['rwgcm_priced_source'] );
unset( $GLOBALS['rw_filters']['rwgcm_price_book_shipping_priced'] );
$out = $S::filter_package_rates( array( 'flat_rate:3' => new RW_Rate( 'flat_rate', 3 ) ), $gb );
assert_true( 'without the site filter the same rate is tagged GBP', 'GBP' === $out['flat_rate:3']->meta['rwgcm_priced_currency'] && ! isset( $out['flat_rate:3']->meta['rwgcm_priced_source'] ) );
$GLOBALS['rw_currency'] = 'GBP';

// --- Class costs and block pickup (phase 1) -----------------------------------
$zone = array(
	'id'          => 4,
	'locations'   => array( array( 'code' => 'ZA', 'type' => 'country' ) ),
	'instance_id' => 9,
	'settings'    => array( 'cost' => '10', 'class_cost_12' => '5', 'title' => 'Flat' ),
);
$zone_before = $zone;
$saved = $zone['settings'];
$saved['rwgcm_cost_ZAR'] = '230';
$saved['rwgcm_class_cost_12_ZAR'] = '80';
assert_true( 'admin save of ZAR fields adds only rwgcm keys', '10' === $saved['cost'] && '5' === $saved['class_cost_12'] && 'Flat' === $saved['title'] && '230' === $saved['rwgcm_cost_ZAR'] && '80' === $saved['rwgcm_class_cost_12_ZAR'] );
assert_true( 'zone id, locations and instance id are unchanged', 4 === $zone['id'] && $zone_before['locations'] === $zone['locations'] && 9 === $zone['instance_id'] && $zone_before['settings'] === $zone['settings'] );

$locations = array(
	array( 'name' => 'Cape Town', 'enabled' => true, 'cost' => '15' ),
	array( 'id' => 'loc-jhb', 'name' => 'Johannesburg', 'enabled' => true ),
	array( 'name' => 'Closed', 'enabled' => false ),
);
$locations_before = $locations;
$GLOBALS['rw_opts']['pickup_location_pickup_locations'] = $locations;
$GLOBALS['rw_opts']['woocommerce_pickup_location_settings'] = array( 'enabled' => 'yes', 'cost' => '10' );
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array( '0' => array( 'ZAR' => '80' ), 'loc-jhb' => array( 'ZAR' => '90' ), 'gone' => array( 'EUR' => '12' ) );
$model = $S::pickup_admin_model( $locations, '10', $GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] );
assert_true( 'pickup cards keep the GBP location cost', '15' === $model['cards'][0]['gbp_cost'] && '10' === $model['cards'][1]['gbp_cost'] );
assert_true( 'explicit location id is used and the index is not guessed onto it', '0' === $model['cards'][0]['id'] && 'loc-jhb' === $model['cards'][1]['id'] );
assert_true( 'a regenerated id is an orphan and is not copied', 1 === count( $model['orphans'] ) && 'gone' === $model['orphans'][0]['id'] );
assert_true( 'reading the cards does not change the location list', $locations_before === $locations && $locations_before === $GLOBALS['rw_opts']['pickup_location_pickup_locations'] );
assert_true( 'GBP method cost is unchanged', '10' === $GLOBALS['rw_opts']['woocommerce_pickup_location_settings']['cost'] );
$merged = $S::merge_pickup_costs(
	$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ],
	array( '0' => array( 'ZAR' => '200' ), 'loc-jhb' => array( 'ZAR' => '' ) ),
	array( '0', 'loc-jhb' ),
	array( 'gone' ),
	array( 'ZAR', 'EUR' )
);
assert_true( 'merge writes the side option only', array( '0' => array( 'ZAR' => '200' ) ) === $merged );
assert_true( 'merge does not rewrite locations or the GBP cost', $locations_before === $GLOBALS['rw_opts']['pickup_location_pickup_locations'] && '10' === $GLOBALS['rw_opts']['woocommerce_pickup_location_settings']['cost'] && '15' === $GLOBALS['rw_opts']['pickup_location_pickup_locations'][0]['cost'] );
assert_true( 'a non-numeric pickup amount is rejected', '' === $S::sanitize_pickup_amount( '10 * [qty]' ) && '230.50' === $S::sanitize_pickup_amount( '230,50' ) );
assert_true( 'ids that were not on the form are kept', array( 'kept' => array( 'ZAR' => '5' ) ) === $S::merge_pickup_costs( array( 'kept' => array( 'ZAR' => '5' ) ), array(), array(), array(), array( 'ZAR' ) ) );

assert_true( 'pickup with no ZAR amount is not priced', 'missing' === $st( 'pickup_location', array( 'cost' => '10', 'location_id' => '0', 'pickup_costs' => array() ) )['reason'] );
assert_true( 'pickup with a ZAR amount is priced', $st( 'pickup_location', array( 'cost' => '10', 'location_id' => '0', 'pickup_costs' => array( '0' => array( 'ZAR' => '80' ) ) ) )['priced'] );
assert_true( 'a zero GBP pickup cost is priced with no amount', 'zero' === $st( 'pickup_location', array( 'cost' => '0', 'location_id' => '0', 'pickup_costs' => array() ) )['reason'] );
$GLOBALS['rw_currency'] = 'ZAR';
class RW_Pickup_Method { public $id = 'pickup_location'; } // phpcs:ignore
$rate_args = array( 'id' => 'pickup_location:0', 'label' => 'Pickup', 'cost' => '10' );
$swapped_args = $S::filter_add_rate_args( $rate_args, new RW_Pickup_Method() );
assert_true( 'pickup rate cost is the manual ZAR amount', '80' === $swapped_args['cost'] );
assert_true( 'applying the ZAR amount does not change the location cost', '15' === $GLOBALS['rw_opts']['pickup_location_pickup_locations'][0]['cost'] && '10' === $GLOBALS['rw_opts']['woocommerce_pickup_location_settings']['cost'] );
$explicit = $S::filter_add_rate_args( array( 'id' => 'pickup_location:1', 'label' => 'Pickup', 'cost' => '10' ), new RW_Pickup_Method() );
assert_true( 'a location id on the row is the cost key, not the array index', '90' === $explicit['cost'] );
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array();
$kept_gbp = $S::filter_add_rate_args( array( 'id' => 'pickup_location:0', 'label' => 'Pickup', 'cost' => '10' ), new RW_Pickup_Method() );
assert_true( 'a pickup rate with no manual amount keeps the GBP cost', '10' === $kept_gbp['cost'] );
$GLOBALS['rw_admin'] = true;
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array( '0' => array( 'ZAR' => '80' ) );
assert_true( 'wp-admin does not swap the pickup cost', '10' === $S::filter_add_rate_args( array( 'id' => 'pickup_location:0', 'cost' => '10' ), new RW_Pickup_Method() )['cost'] );
$GLOBALS['rw_admin'] = false;
$GLOBALS['rw_currency'] = 'GBP';
assert_true( 'a GBP cart does not swap the pickup cost', '10' === $S::filter_add_rate_args( array( 'id' => 'pickup_location:0', 'cost' => '10' ), new RW_Pickup_Method() )['cost'] );

$GLOBALS['rw_opts']['woocommerce_free_shipping_2_settings'] = array( 'requires' => 'min_amount', 'min_amount' => '60', 'rwgcm_min_amount_ZAR' => '1000' );
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array();
$S::reset_memo();
assert_true( 'enabled pickup with no ZAR amount keeps a priced zone in GBP', false === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array( '0' => array( 'ZAR' => '80' ), 'loc-jhb' => array( 'ZAR' => '90' ) );
$S::reset_memo();
assert_true( 'enabled pickup is priced when every enabled location has a ZAR amount', true === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts']['woocommerce_pickup_location_settings']['enabled'] = 'no';
$S::reset_memo();
assert_true( 'disabled block pickup does not block the currency', true === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts']['woocommerce_pickup_location_settings']['enabled'] = 'yes';
$GLOBALS['rw_opts']['pickup_location_pickup_locations'][0]['cost'] = '0';
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array();
$S::reset_memo();
assert_true( 'a zero cost on the location needs no amount; the other location still does', false === $S::computed_priced( 'ZAR', array( $za ) ) );
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array( '0' => array( 'ZAR' => '80' ), 'loc-jhb' => array( 'ZAR' => '90' ) );
$GLOBALS['rw_currency'] = 'ZAR';
class RW_Pickup_Rate { public $mid; public $rid; public $meta = array(); public function __construct( $rid ) { $this->mid = 'pickup_location'; $this->rid = $rid; } public function get_method_id() { return $this->mid; } public function get_instance_id() { return 0; } public function get_id() { return $this->rid; } public function add_meta_data( $k, $v ) { $this->meta[ $k ] = $v; } } // phpcs:ignore
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'yes';
$GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] = array( '0' => array( 'ZAR' => '80' ) );
$S::reset_memo();
$out = $S::filter_package_rates(
	array(
		'flat_rate:1' => new RW_Rate( 'flat_rate', 1 ),
		'pickup_location:0' => new RW_Pickup_Rate( 'pickup_location:0' ),
		'pickup_location:1' => new RW_Pickup_Rate( 'pickup_location:1' ),
	),
	$za
);
assert_true( 'priced pickup location is tagged ZAR', isset( $out['pickup_location:0'] ) && 'ZAR' === $out['pickup_location:0']->meta['rwgcm_priced_currency'] );
assert_true( 'hide unpriced removes the location that has no ZAR amount and keeps the priced one', ! isset( $out['pickup_location:1'] ) && isset( $out['flat_rate:1'] ) );
$GLOBALS['rw_opts'][ $S::OPTION_HIDE_UNPRICED ] = 'no';
$GLOBALS['rw_currency'] = 'GBP';
unset( $GLOBALS['rw_opts']['woocommerce_pickup_location_settings'], $GLOBALS['rw_opts']['pickup_location_pickup_locations'], $GLOBALS['rw_opts'][ $S::OPTION_PICKUP_COSTS ] );
$S::reset_memo();

$block_js = file_get_contents( dirname( __DIR__ ) . '/assets/js/shipping-currency-notice.js' );
assert_true(
	'checkout block notice is a live region bound to the store API string',
	is_string( $block_js )
		&& false !== strpos( $block_js, 'shipping_currency_notice' )
		&& false !== strpos( $block_js, "role: 'status'" )
		&& false !== strpos( $block_js, 'rwgcm-shipping-currency-notice--block' )
);
assert_true( 'checkout block notice renders nothing when the string is empty', false !== strpos( $block_js, 'if ( ! notice )' ) && false !== strpos( $block_js, 'return null' ) );
$picker_js = file_get_contents( dirname( __DIR__ ) . '/admin/js/rwgcm-shipping-currency.js' );
assert_true( 'currency select changes the column and does not save', is_string( $picker_js ) && false !== strpos( $picker_js, 'rwgcm-shipping-currency' ) && false !== strpos( $picker_js, 'addEventListener' ) && false === strpos( $picker_js, 'fetch(' ) && false === strpos( $picker_js, '.submit(' ) );

// --- Wiring (source) ------------------------------------------------------------
$src   = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-shipping-prices.php' );
$plug  = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-plugin.php' );
$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-admin.php' );
$guard = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-store-api.php' );
$cur   = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php' );
assert_true( 'hooks instance form fields for the 3 methods', false !== strpos( $src, "'woocommerce_shipping_instance_form_fields_' . \$id" ) );
assert_true( 'swaps on woocommerce_shipping_{id}_instance_option', false !== strpos( $src, "'woocommerce_shipping_' . \$id . '_instance_option'" ) );
assert_true( 'swap is gated on forces_store_currency()', false !== strpos( $src, 'RWGCM_Price_Book_Currency::forces_store_currency() || ! function_exists' ) );
assert_true( 'tags rates before rwgcm_package_rates (priority 50 < 99)', false !== strpos( $src, "'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 50, 2" ) );
assert_true( 'hide setting change flushes the rate cache', false !== strpos( $src, "'update_option_' . self::OPTION_HIDE_UNPRICED" ) && false !== strpos( $src, "get_transient_version( 'shipping', true )" ) );
assert_true( 'Store API extension data registered', false !== strpos( $src, "'namespace'       => 'rwgcm'" ) );
assert_true( 'no one-shot wc_add_notice / session state any more', false === strpos( $src, 'wc_add_notice' ) && false === strpos( $src, 'SESSION_FALLBACK' ) );
assert_true( 'checkout: rendered on page load and in update_order_review fragments', false !== strpos( $src, "'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_notice' )" ) && false !== strpos( $src, "'woocommerce_update_order_review_fragments', array( __CLASS__, 'filter_checkout_fragments' )" ) );
assert_true( 'cart: rendered inside the totals table, not as a WooCommerce notice', false !== strpos( $src, "'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'render_cart_notice' )" ) && false === strpos( $src, 'woocommerce_before_cart_totals' ) );
assert_true( 'mini-cart cache key includes the active currency', false !== strpos( $src, "'woocommerce_cart_hash', array( __CLASS__, 'filter_cart_hash' )" ) && false !== strpos( $src, "'woocommerce_add_to_cart_fragments', array( __CLASS__, 'filter_cart_fragments' )" ) );
assert_true( 'cart refresh script is printed in the footer on every cart view', false !== strpos( $src, "'wp_footer', array( __CLASS__, 'print_cart_fragment_refresh_script' )" ) && false !== strpos( $src, "array( 'jquery' )" ) && false !== strpos( $src, 'rwgcm-cart-fragments' ) );
assert_true( 'header fragments use the filterable selector list', false !== strpos( $src, 'rwgcm_header_cart_fragment_selectors' ) && false !== strpos( $src, 'append_header_cart_fragments' ) );
assert_true( 'calculator persists the customer session before the response is flushed', false !== strpos( $cur, "'woocommerce_calculated_shipping', array( __CLASS__, 'persist_customer_session' )" ) );
assert_true( 'Store API extension data unchanged', false !== strpos( $src, "'shipping_currency_notice'   => '' === \$now['state'] ? ''" ) );
assert_true( 'plugin loads and inits RWGCM_Shipping_Prices', false !== strpos( $plug, 'class-rwgcm-shipping-prices.php' ) && false !== strpos( $plug, 'RWGCM_Shipping_Prices::init();' ) );
assert_true( 'guard uses shipping line meta', false !== strpos( $guard, 'RWGCM_Shipping_Prices::shipping_lines_ok( $lines' ) && false === strpos( $guard, 'shipping_priced(' ) );
assert_true( 'currency resolver does not call WC_Cart::get_shipping_packages during totals', false === strpos( $cur, '->get_shipping_packages()' ) );
assert_true( 'admin: Shipping tab and overview', false !== strpos( $admin, 'function render_shipping_overview' ) && false !== strpos( $admin, "'shipping' => array( __( 'Shipping'" ) );
assert_true( 'admin: overview reuses the sticky matrix layout', false !== strpos( $admin, 'rwgcm-gateway-matrix rwgcm-shipping-matrix' ) );
assert_true( 'admin: hide setting saved only with its own present field', false !== strpos( $admin, "if ( isset( \$_POST['rwgcm_shipping_settings_present'] ) ) {\n\t\t\tupdate_option( RWGCM_Shipping_Prices::OPTION_HIDE_UNPRICED" ) );
assert_true( 'admin: pickup costs save from their own present field', false !== strpos( $admin, "if ( isset( \$_POST['rwgcm_pickup_costs_present'] ) )" ) && false !== strpos( $admin, 'RWGCM_Shipping_Prices::OPTION_PICKUP_COSTS' ) );
assert_true( 'admin: currency select and pickup cards', false !== strpos( $admin, 'rwgcm-shipping-currency' ) && false !== strpos( $admin, 'function render_pickup_cards' ) && false !== strpos( $admin, 'price-books-shipping' ) );
assert_true( 'location list and GBP pickup settings are never updated', false === strpos( $src . $admin, "update_option( 'pickup_location_pickup_locations'" ) && false === strpos( $src . $admin, "update_option( 'woocommerce_pickup_location_settings'" ) && false === strpos( $src, 'woocommerce_shipping_zone_methods' ) );
assert_true( 'block notice script is registered for the checkout block', false !== strpos( $src, 'function register_checkout_block' ) && false !== strpos( $src, 'shipping-currency-notice.js' ) );
$modal_css = file_get_contents( dirname( __DIR__ ) . '/admin/css/rwgcm-shipping-modal.css' );
$admin_css = file_get_contents( dirname( __DIR__ ) . '/admin/css/rwgcm-admin.css' );
assert_true( 'method modal fields are full width from 782px down', is_string( $modal_css ) && false !== strpos( $modal_css, 'max-width: 782px' ) && false !== strpos( $modal_css, 'width: 100%' ) );
assert_true( 'shipping tab shows one currency column below 782px and stacks pickup cards', is_string( $admin_css ) && false !== strpos( $admin_css, '.rwgcm-currency-col.is-selected' ) && false !== strpos( $admin_css, 'flex-direction: column' ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
