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
assert_true( 'native fields untouched and first', array( 'title', 'cost' ) === array_slice( array_keys( $f ), 0, 2 ) && $native['cost'] === $f['cost'] );
$f = $S::add_fields( 'free_shipping', array( 'min_amount' => array( 'type' => 'price' ) ), array( 'ZAR' ) );
assert_true( 'free shipping gets Minimum order amount (ZAR)', 'Minimum order amount (ZAR)' === $f['rwgcm_min_amount_ZAR']['title'] );
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
assert_true( 'flat rate with class costs is not priced in 0.5.0', 'class_costs' === $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'class_cost_12' => '5' ) )['reason'] );
assert_true( 'flat rate with only no_class_cost is not priced', 'class_costs' === $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'no_class_cost' => '2' ) )['reason'] );
assert_true( 'blank class cost fields do not count', $st( 'flat_rate', array( 'cost' => '10', 'rwgcm_cost_ZAR' => '150', 'class_cost_12' => '', 'no_class_cost' => '' ) )['priced'] );
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
$script = $S::cart_fragment_refresh_script();
assert_true(
	'cart refresh listens for every totals event and the calculator submit',
	false !== strpos( $script, "trigger('wc_fragment_refresh')" )
		&& false !== strpos( $script, 'updated_cart_totals' )
		&& false !== strpos( $script, 'updated_shipping_method' )
		&& false !== strpos( $script, 'updated_wc_div' )
		&& false !== strpos( $script, 'form.woocommerce-shipping-calculator' )
);
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
assert_true( 'reason labels for carrier and class costs', false !== strpos( $S::reason_label( array( 'priced' => false, 'reason' => 'unsupported', 'amount' => '' ), 'ZAR' ), 'carrier' ) && false !== strpos( $S::reason_label( array( 'priced' => false, 'reason' => 'class_costs', 'amount' => '' ), 'ZAR' ), 'class' ) );

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
assert_true( 'cart refresh script is printed in the footer on every cart view', false !== strpos( $src, "'wp_footer', array( __CLASS__, 'print_cart_fragment_refresh_script' )" ) );
assert_true( 'Store API extension data unchanged', false !== strpos( $src, "'shipping_currency_notice'   => '' === \$now['state'] ? ''" ) );
assert_true( 'plugin loads and inits RWGCM_Shipping_Prices', false !== strpos( $plug, 'class-rwgcm-shipping-prices.php' ) && false !== strpos( $plug, 'RWGCM_Shipping_Prices::init();' ) );
assert_true( 'guard uses shipping line meta', false !== strpos( $guard, 'RWGCM_Shipping_Prices::shipping_lines_ok( $lines' ) && false === strpos( $guard, 'shipping_priced(' ) );
assert_true( 'currency resolver does not call WC_Cart::get_shipping_packages during totals', false === strpos( $cur, '->get_shipping_packages()' ) );
assert_true( 'admin: Shipping tab and overview', false !== strpos( $admin, 'function render_shipping_overview' ) && false !== strpos( $admin, "'shipping' => array( __( 'Shipping'" ) );
assert_true( 'admin: overview reuses the sticky matrix layout', false !== strpos( $admin, 'rwgcm-gateway-matrix rwgcm-shipping-matrix' ) );
assert_true( 'admin: hide setting saved only with its own present field', false !== strpos( $admin, "if ( isset( \$_POST['rwgcm_shipping_settings_present'] ) ) {\n\t\t\tupdate_option( RWGCM_Shipping_Prices::OPTION_HIDE_UNPRICED" ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
