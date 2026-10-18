<?php
/**
 * Shipping rate cache key carries the Commerce currency (run with: php tests/test-shipping-cache.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['rw_currency'] = 'GBP';
function add_filter() {} // phpcs:ignore
function apply_filters( $tag, $value ) { unset( $tag ); return $value; } // phpcs:ignore
function get_option( $k, $d = false ) { return 'woocommerce_currency' === $k ? 'GBP' : $d; } // phpcs:ignore
function get_woocommerce_currency() { return $GLOBALS['rw_currency']; } // phpcs:ignore
function wp_json_encode( $v ) { return json_encode( $v ); } // phpcs:ignore

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-shipping.php';

$passed = 0;
$failed = 0;
function assert_true( $label, $condition ) { // phpcs:ignore
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$label}\n"; return; }
	++$failed; echo "FAIL: {$label}\n";
}

/** WooCommerce 11.2 WC_Shipping::get_package_hash() (class-wc-shipping.php:419-450), minus the transient version. */
function rw_wc_hash( array $p ) {
	foreach ( array( 'subtotal', 'total', 'package_id', 'package_name', 'rates', 'package_index' ) as $f ) {
		unset( $p[ $f ] );
	}
	foreach ( (array) ( $p['contents'] ?? array() ) as $k => $item ) {
		unset( $p['contents'][ $k ]['data'] );
	}
	return 'wc_ship_' . md5( wp_json_encode( $p ) );
}

$pkg = array(
	'contents'        => array( 'k1' => array( 'product_id' => 11883, 'quantity' => 1, 'line_total' => 23, 'data' => new stdClass() ) ),
	'contents_cost'   => 23,
	'applied_coupons' => array(),
	'user'            => array( 'ID' => 0 ),
	'destination'     => array( 'country' => 'ZA', 'state' => '', 'postcode' => '2000', 'city' => 'Johannesburg', 'address' => '', 'address_1' => '', 'address_2' => '' ),
	'cart_subtotal'   => 23,
);
$packages = array( $pkg );

$gbp = RWGCM_Shipping::add_currency_to_packages( $packages, 'GBP', 'GBP' );
assert_true( 'base currency: packages unchanged (same hash as a store without Commerce)', $gbp === $packages && rw_wc_hash( $gbp[0] ) === rw_wc_hash( $pkg ) );
$zar = RWGCM_Shipping::add_currency_to_packages( $packages, 'ZAR', 'GBP' );
assert_true( 'non-base currency: package carries rwgcm_currency', isset( $zar[0][ RWGCM_Shipping::PACKAGE_CURRENCY_KEY ] ) && 'ZAR' === $zar[0]['rwgcm_currency'] );
assert_true( 'ZAR and GBP packages hash differently', rw_wc_hash( $zar[0] ) !== rw_wc_hash( $gbp[0] ) );
$eur = RWGCM_Shipping::add_currency_to_packages( $packages, 'EUR', 'GBP' );
assert_true( 'ZAR and EUR packages hash differently', rw_wc_hash( $zar[0] ) !== rw_wc_hash( $eur[0] ) );
assert_true( 'same currency twice gives a stable hash', rw_wc_hash( RWGCM_Shipping::add_currency_to_packages( $packages, 'ZAR', 'GBP' )[0] ) === rw_wc_hash( $zar[0] ) );
$back = RWGCM_Shipping::add_currency_to_packages( $zar, 'GBP', 'GBP' );
assert_true( 'switching back to base removes the key (hash back to WooCommerce default)', ! isset( $back[0]['rwgcm_currency'] ) && rw_wc_hash( $back[0] ) === rw_wc_hash( $pkg ) );
$two = RWGCM_Shipping::add_currency_to_packages( array( $pkg, $pkg ), 'ZAR', 'GBP' );
assert_true( 'every package gets the currency', 'ZAR' === $two[0]['rwgcm_currency'] && 'ZAR' === $two[1]['rwgcm_currency'] );
assert_true( 'empty or invalid active currency leaves packages unchanged', RWGCM_Shipping::add_currency_to_packages( $packages, '', 'GBP' ) === $packages );
assert_true( 'non-array entries are left alone', array( 'x' ) === RWGCM_Shipping::add_currency_to_packages( array( 'x' ), 'ZAR', 'GBP' ) );
assert_true( 'lowercase codes are normalised', 'ZAR' === RWGCM_Shipping::add_currency_to_packages( $packages, 'zar', 'gbp' )[0]['rwgcm_currency'] );

$GLOBALS['rw_currency'] = 'ZAR';
$f = RWGCM_Shipping::filter_cart_shipping_packages( $packages );
assert_true( 'filter adds the active currency from get_woocommerce_currency()', 'ZAR' === $f[0]['rwgcm_currency'] );
$GLOBALS['rw_currency'] = 'GBP';
assert_true( 'filter is a no-op in the store currency', RWGCM_Shipping::filter_cart_shipping_packages( $packages ) === $packages );
assert_true( 'filter passes non-array input through', 'x' === RWGCM_Shipping::filter_cart_shipping_packages( 'x' ) );

$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-shipping.php' );
assert_true( 'hooked on woocommerce_cart_shipping_packages', false !== strpos( $src, "'woocommerce_cart_shipping_packages'" ) );
$cur = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php' );
assert_true( 'shipping_priced default is computed from zones since 0.5.0', false !== strpos( $cur, 'RWGCM_Shipping_Prices::computed_priced( $currency, $packages )' ) && false !== strpos( $cur, 'Since 0.5.0' ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
