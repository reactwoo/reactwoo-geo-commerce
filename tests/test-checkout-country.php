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
	$o = array(
		'woocommerce_currency'               => 'GBP',
		'rwgcm_price_book_country_map'       => array( 'ZA' => 'ZAR', 'GB' => 'GBP', 'DE' => 'EUR', 'US' => 'USD' ),
		'rwgcm_price_book_fallback_currency' => '',
	);
	return isset( $o[ $k ] ) ? $o[ $k ] : $d;
}
/** WC stand-ins. */
class RW_Session { public function get( $k ) { return isset( $GLOBALS['rw']['session'][ $k ] ) ? $GLOBALS['rw']['session'][ $k ] : null; } public function set( $k, $v ) { $GLOBALS['rw']['session'][ $k ] = $v; } } // phpcs:ignore
class RW_Cart { public function get_cart() { return $GLOBALS['rw']['cart']; } } // phpcs:ignore
class RW_Countries { public function get_allowed_countries() { return $GLOBALS['rw']['allowed']; } public function get_shipping_countries() { return $GLOBALS['rw']['allowed']; } } // phpcs:ignore
/** Guest customer pre-filled from the store base country, as WooCommerce does. */
class RW_Customer { public function get_billing_country() { return 'GB'; } } // phpcs:ignore
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

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
