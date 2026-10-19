<?php
/**
 * Checkout country defaults and billing-vs-geo currency (run with: php tests/test-checkout-country.php).
 *
 * Classic checkout for a guest from a Johannesburg IP: WooCommerce pre-fills
 * billing GB (store base). That default must not flip the cart to GBP.
 *
 * @package ReactWoo_Geo_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['rw'] = array();
function rw_reset() { // phpcs:ignore
	$GLOBALS['rw'] = array( 'geo' => 'ZA', 'user' => 0, 'meta' => array(), 'session' => array(), 'cart' => array(), 'allowed' => array( 'ZA' => 'South Africa', 'GB' => 'United Kingdom', 'DE' => 'Germany' ), 'books' => array() );
	$_POST = array();
}
rw_reset();

function __( $t, $d = 'default' ) { unset( $d ); return $t; } // phpcs:ignore
function apply_filters( $tag, $value ) { unset( $tag ); return $value; } // phpcs:ignore
function add_filter() {} // phpcs:ignore
function add_action() {} // phpcs:ignore
function did_action() { return 1; } // phpcs:ignore
function is_checkout() { return true; } // phpcs:ignore
function is_order_received_page() { return false; } // phpcs:ignore
function wp_unslash( $v ) { return $v; } // phpcs:ignore
function rwgc_get_visitor_country() { return $GLOBALS['rw']['geo']; } // phpcs:ignore
function get_current_user_id() { return $GLOBALS['rw']['user']; } // phpcs:ignore
function get_user_meta( $id, $k, $s = false ) { unset( $s ); return isset( $GLOBALS['rw']['meta'][ $id ][ $k ] ) ? $GLOBALS['rw']['meta'][ $id ][ $k ] : ''; } // phpcs:ignore
function get_post_meta( $id, $k, $s = false ) { unset( $k, $s ); return isset( $GLOBALS['rw']['books'][ $id ] ) ? $GLOBALS['rw']['books'][ $id ] : ''; } // phpcs:ignore
function get_option( $k, $d = false ) { // phpcs:ignore
	if ( isset( $GLOBALS['rw']['opts'] ) && array_key_exists( $k, $GLOBALS['rw']['opts'] ) ) {
		return $GLOBALS['rw']['opts'][ $k ];
	}
	$o = array(
		'woocommerce_currency'               => 'GBP',
		'rwgcm_price_book_country_map'       => array( 'ZA' => 'ZAR', 'GB' => 'GBP', 'DE' => 'EUR', 'US' => 'USD' ),
		'rwgcm_price_book_fallback_currency' => '',
	);
	return isset( $o[ $k ] ) ? $o[ $k ] : $d;
}
function wp_json_encode( $v ) { return json_encode( $v ); } // phpcs:ignore
/** WC stand-ins. */
class RW_Session { public $saved = 0; public function get( $k ) { return isset( $GLOBALS['rw']['session'][ $k ] ) ? $GLOBALS['rw']['session'][ $k ] : null; } public function set( $k, $v ) { $GLOBALS['rw']['session'][ $k ] = $v; } public function save_data() { ++$this->saved; } } // phpcs:ignore
class RW_Cart { public function get_cart() { return $GLOBALS['rw']['cart']; } public function needs_shipping() { return ! empty( $GLOBALS['rw']['ship'] ); } } // phpcs:ignore
class RW_Countries { public function get_allowed_countries() { return $GLOBALS['rw']['allowed']; } public function get_shipping_countries() { return $GLOBALS['rw']['allowed']; } } // phpcs:ignore
/** Guest customer pre-filled from the store base country, as WooCommerce does. */
class RW_Customer { // phpcs:ignore
	public function get_billing_country() { return 'GB'; }
	public function get_shipping_country() { return isset( $GLOBALS['rw']['ship_country'] ) ? (string) $GLOBALS['rw']['ship_country'] : ''; }
	public function get_shipping_state() { return ''; }
	public function get_shipping_postcode() { return ''; }
	public function get_shipping_city() { return ''; }
	public function get_shipping_address() { return ''; }
	public function get_shipping_address_2() { return ''; }
}
class RW_P { public $id; public function __construct( $id ) { $this->id = $id; } public function get_id() { return $this->id; } } // phpcs:ignore
function WC() { // phpcs:ignore
	static $wc = null;
	if ( null === $wc ) {
		$wc            = new stdClass();
		$wc->session   = new RW_Session();
		$wc->cart      = new RW_Cart();
		$wc->countries = new RW_Countries();
		$wc->customer  = new RW_Customer();
	}
	return $wc;
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-shipping-prices.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-store-api.php';

$passed = 0;
$failed = 0;
function assert_true( $label, $condition ) { // phpcs:ignore
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$label}\n"; return; }
	++$failed; echo "FAIL: {$label}\n";
}
function rw_currency() { return RWGCM_Price_Book_Currency::filter_currency( 'GBP' ); } // phpcs:ignore
function rw_cart( array $ids ) { // phpcs:ignore
	$GLOBALS['rw']['cart'] = array();
	foreach ( $ids as $id ) {
		$GLOBALS['rw']['cart'][ 'k' . $id ] = array( 'data' => new RW_P( $id ), 'quantity' => 1 );
	}
}
function rw_books() { // phpcs:ignore
	$GLOBALS['rw']['books'][3655] = RWGCM_Price_Book::sanitize_book( array( 'ZAR' => array( 'regular' => '699' ), 'GBP' => array( 'regular' => '39' ) ) );
	$GLOBALS['rw']['books'][3495] = RWGCM_Price_Book::sanitize_book( array( 'ZAR' => array( 'regular' => '1499' ), 'GBP' => array( 'regular' => '79' ) ) );
}

// Pure helpers.
$allowed = array( 'ZA' => 'South Africa', 'GB' => 'United Kingdom' );
assert_true( 'default country: allowed geo wins', 'ZA' === RWGCM_Price_Book::default_checkout_country( 'ZA', $allowed, 'GB' ) );
assert_true( 'default country: geo not sold to keeps WC default', 'GB' === RWGCM_Price_Book::default_checkout_country( 'BR', $allowed, 'GB' ) );
assert_true( 'default country: unknown geo keeps WC default', 'GB' === RWGCM_Price_Book::default_checkout_country( '', $allowed, 'GB' ) );
assert_true( 'default country: list form of allowed countries works', 'ZA' === RWGCM_Price_Book::default_checkout_country( 'za', array( 'ZA', 'GB' ), 'GB' ) );
assert_true( 'chosen billing: nothing chosen is empty', '' === RWGCM_Price_Book::chosen_billing_country( '', '', '', true ) );
assert_true( 'chosen billing: posted wins over account', 'GB' === RWGCM_Price_Book::chosen_billing_country( 'GB', 'ZA', 'DE', true ) );
assert_true( 'chosen billing: account used on checkout', 'GB' === RWGCM_Price_Book::chosen_billing_country( '', '', 'GB', true ) );
assert_true( 'chosen billing: account ignored on catalog', '' === RWGCM_Price_Book::chosen_billing_country( '', '', 'GB', false ) );

// 1. ZA guest, nothing chosen: checkout pre-fills ZA, cart stays ZAR.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
assert_true( 'ZA guest: billing pre-fill is ZA, not WC default GB', 'ZA' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'billing_country' ) );
assert_true( 'ZA guest: shipping pre-fill is ZA', 'ZA' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'shipping_country' ) );
$loc = RWGCM_Price_Book_Currency::filter_default_location( array( 'country' => 'GB', 'state' => '' ) );
assert_true( 'ZA guest: default customer location is ZA', 'ZA' === $loc['country'] && '' === $loc['state'] );
assert_true( 'ZA guest at checkout with no billing chosen: ZAR', 'ZAR' === rw_currency() );
// update_order_review posts the (now ZA) pre-filled form value.
$_POST = array( 'post_data' => 'billing_country=ZA&billing_first_name=' );
assert_true( 'ZA guest: update_order_review with pre-filled ZA keeps ZAR', 'ZAR' === rw_currency() );

// 2. The old live bug: WC customer object says GB but nobody chose it.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
assert_true( 'ZA guest: a defaulted customer GB (not posted or remembered) does not flip to GBP', 'ZAR' === rw_currency() );

// 3. ZA guest deliberately picks GB.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
$_POST = array( 'billing_country' => 'GB' );
assert_true( 'ZA guest who picks GB: GBP', 'GBP' === rw_currency() );
$_POST = array();
assert_true( 'ZA guest who picked GB: remembered on the next request', 'GBP' === rw_currency() );
assert_true( 'ZA guest who picked GB: pre-fill no longer overridden', 'GB' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'billing_country' ) );
// Store API update-customer path.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
$req = new class() { public function get_param( $k ) { return 'billing_address' === $k ? array( 'country' => 'GB' ) : null; } }; // phpcs:ignore
RWGCM_Price_Book_Currency::remember_store_api_billing( null, $req );
assert_true( 'Store API cart update-customer to GB: GBP', 'GBP' === rw_currency() );

// 4. Logged-in customer with saved GB billing.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
$GLOBALS['rw']['user'] = 7; $GLOBALS['rw']['meta'][7] = array( 'billing_country' => 'GB' );
assert_true( 'logged-in saved GB: GBP', 'GBP' === rw_currency() );
assert_true( 'logged-in saved GB: pre-fill untouched', 'GB' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'billing_country' ) );
// Logged in without a saved country behaves like a guest.
$GLOBALS['rw']['meta'][7] = array();
assert_true( 'logged-in without saved country: ZAR', 'ZAR' === rw_currency() );

// 5. Geo country not sold to.
rw_reset(); rw_books(); rw_cart( array( 3655 ) );
$GLOBALS['rw']['geo'] = 'BR';
assert_true( 'geo not in allowed countries: WC default billing', 'GB' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'billing_country' ) );
$loc = RWGCM_Price_Book_Currency::filter_default_location( array( 'country' => 'GB', 'state' => 'LND' ) );
assert_true( 'geo not in allowed countries: WC default location kept', 'GB' === $loc['country'] && 'LND' === $loc['state'] );
$GLOBALS['rw']['geo'] = 'ZA'; $GLOBALS['rw']['allowed'] = array( 'GB' => 'United Kingdom' );
assert_true( 'ZA not sold to: WC default billing', 'GB' === RWGCM_Price_Book_Currency::filter_default_checkout_country( 'GB', 'billing_country' ) );

// 6. Mixed cart stays GBP for a ZA guest, and the order guard agrees.
rw_reset(); rw_books(); rw_cart( array( 3655, 2893 ) );
$cur = rw_currency();
assert_true( 'ZA guest mixed cart (booked + unbooked): GBP', 'GBP' === $cur );
assert_true( 'guard agrees with mixed cart currency', RWGCM_Price_Book_Store_Api::lines_priced_in( array( 3655, 2893 ), $cur, 'GBP', array( 'RWGCM_Price_Book_Store_Api', 'line_priced_in' ) ) );
rw_cart( array( 3655, 3495 ) );
$cur = rw_currency();
assert_true( 'ZA guest two booked items: ZAR', 'ZAR' === $cur );
assert_true( 'guard agrees with all-ZAR cart currency', RWGCM_Price_Book_Store_Api::lines_priced_in( array( 3655, 3495 ), $cur, 'GBP', array( 'RWGCM_Price_Book_Store_Api', 'line_priced_in' ) ) );

// 7. Shipping: rates are store-currency amounts, so a cart that needs shipping stays GBP.
assert_true( 'shipping helper: base currency always supported', RWGCM_Price_Book::shipping_supports_currency( 'GBP', 'GBP', true, false ) );
assert_true( 'shipping helper: ZAR + needs shipping + not priced -> unsupported', ! RWGCM_Price_Book::shipping_supports_currency( 'ZAR', 'GBP', true, false ) );
assert_true( 'shipping helper: ZAR + virtual cart -> supported', RWGCM_Price_Book::shipping_supports_currency( 'ZAR', 'GBP', false, false ) );
assert_true( 'shipping helper: ZAR + shipping priced via filter -> supported', RWGCM_Price_Book::shipping_supports_currency( 'ZAR', 'GBP', true, true ) );
rw_reset(); rw_books(); rw_cart( array( 3655 ) ); $GLOBALS['rw']['ship'] = true;
assert_true( 'ZA guest, ZAR-priced physical item needing shipping: GBP (no R-labelled GBP shipping)', 'GBP' === rw_currency() );
$GLOBALS['rw']['ship'] = false;
assert_true( 'ZA guest, ZAR-priced item, no shipping needed: ZAR', 'ZAR' === rw_currency() );
rw_cart( array() ); $GLOBALS['rw']['ship'] = true;
assert_true( 'empty cart still shows the geo currency (catalog)', 'ZAR' === rw_currency() );

/** Order stand-in with shipping. */
class RW_ShipLine { public $t; public $m; public function __construct( $t, $m ) { $this->t = $t; $this->m = $m; } public function get_total() { return $this->t; } public function get_meta( $k ) { return 'rwgcm_priced_currency' === $k ? $this->m : ''; } } // phpcs:ignore
class RW_ShipOrder { public $c; public $s; public $m; public function __construct( $c, $s, $m = '' ) { $this->c = $c; $this->s = $s; $this->m = $m; } public function get_currency() { return $this->c; } public function get_shipping_total() { return $this->s; } public function get_items( $t ) { if ( 'shipping' === $t ) { return (float) $this->s > 0 || '' !== $this->m ? array( new RW_ShipLine( (float) $this->s, $this->m ) ) : array(); } return array( new class() { public function get_product_id() { return 3655; } public function get_variation_id() { return 0; } public function get_meta( $k ) { unset( $k ); return ''; } } ); } } // phpcs:ignore
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'ZAR', '73.23' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: ZAR order with store-currency shipping is refused', $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'ZAR', '0' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: ZAR order without shipping passes', ! $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'GBP', '19.90' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: GBP order with shipping passes', ! $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'ZAR', '150', 'ZAR' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard 0.5.0: ZAR order whose shipping line is priced in ZAR passes', ! $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'ZAR', '10', 'GBP' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard 0.5.0: ZAR order with a GBP-priced shipping line is refused', $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_ShipOrder( 'ZAR', '0', 'GBP' ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard 0.5.0: a free line priced in GBP (unpriced free-shipping minimum) is refused too', $threw );

// 8. Fragment refresh uses the customer shipping country the calculator saved,
// not the geo visitor country. ZA shipping is priced in ZAR; GB shipping is not.
class RW_DestMethod { public $id; public $instance_id; public $enabled = 'yes'; public function __construct( $id, $i ) { $this->id = $id; $this->instance_id = $i; } public function get_method_title() { return $this->id; } } // phpcs:ignore
class RW_DestZone { public $m; public function __construct( $m ) { $this->m = $m; } public function get_shipping_methods( $enabled_only = false ) { unset( $enabled_only ); return $this->m; } } // phpcs:ignore
class WC_Shipping_Zones { public static function get_zone_matching_package( $p ) { $c = isset( $p['destination']['country'] ) ? $p['destination']['country'] : ''; if ( 'ZA' === $c ) { return new RW_DestZone( array( new RW_DestMethod( 'flat_rate', 1 ) ) ); } if ( 'GB' === $c ) { return new RW_DestZone( array( new RW_DestMethod( 'flat_rate', 9 ) ) ); } return new RW_DestZone( array( new RW_DestMethod( 'custom_carrier', 8 ) ) ); } } // phpcs:ignore
rw_reset(); rw_books(); rw_cart( array( 3655 ) ); $GLOBALS['rw']['ship'] = true;
$GLOBALS['rw']['opts']['woocommerce_flat_rate_1_settings'] = array( 'cost' => '40', 'rwgcm_cost_ZAR' => '800' );
$GLOBALS['rw']['opts']['woocommerce_flat_rate_9_settings'] = array( 'cost' => '5' );
$GLOBALS['rw']['ship_country'] = 'ZA';
RWGCM_Shipping_Prices::reset_memo();
assert_true( 'fragment currency: geo ZA and shipping ZA (priced) stays ZAR', 'ZAR' === rw_currency() );
$GLOBALS['rw']['ship_country'] = 'GB';
RWGCM_Shipping_Prices::reset_memo();
assert_true( 'fragment currency: geo ZA and calculator shipping GB (not priced in ZAR) is GBP, same as cart totals', 'GBP' === rw_currency() );
$before = WC()->session->saved;
RWGCM_Price_Book_Currency::persist_customer_session();
assert_true( 'calculator writes the customer session before the response is flushed', WC()->session->saved === $before + 1 );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
