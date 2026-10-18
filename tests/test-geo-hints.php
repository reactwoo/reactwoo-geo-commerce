<?php
// Standalone tests: Commerce never reads the cart before wp_loaded (0.4.8).
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['wp_loaded'] = 0; $GLOBALS['reads'] = 0; $GLOBALS['notices'] = 0;
function did_action( $h ) { return 'wp_loaded' === $h ? $GLOBALS['wp_loaded'] : 0; }
function apply_filters( $h, $v ) { return $v; }
class FakeCart {
	function get_cart() { $GLOBALS['reads']++; if ( ! did_action( 'wp_loaded' ) ) { $GLOBALS['notices']++; } return array( 1, 2 ); }
	function get_cart_contents_count() { return count( $this->get_cart() ) + 1; }
}
class FakeWC { public $cart; function __construct() { $this->cart = new FakeCart(); } }
function WC() { static $w; return $w ?: ( $w = new FakeWC() ); }
require __DIR__ . '/../includes/class-rwgcm-geo.php';
$p = 0; $f = 0;
function assert_true( $n, $c ) { global $p, $f; if ( $c ) { $p++; } else { $f++; echo "FAIL: $n\n"; } }

$geo = array( 'country_code' => 'ZA', 'currency' => 'ZAR' );
$early = RWGCM_Geo::merge_commerce_hints( $geo );
assert_true( 'before wp_loaded: cart not read', 0 === $GLOBALS['reads'] && 0 === $GLOBALS['notices'] );
assert_true( 'before wp_loaded: no cart count key', ! array_key_exists( 'rwgc_commerce_cart_items', $early ) );
assert_true( 'before wp_loaded: geo data untouched', 'ZA' === $early['country_code'] && 'ZAR' === $early['currency'] );
for ( $i = 0; $i < 10; $i++ ) { RWGCM_Geo::merge_commerce_hints( $geo ); }
assert_true( 'repeated early resolves: still 0 reads', 0 === $GLOBALS['reads'] );
assert_true( 'cart_is_ready false before wp_loaded', false === RWGCM_Geo::cart_is_ready() );
$GLOBALS['wp_loaded'] = 1;
$late = RWGCM_Geo::merge_commerce_hints( $geo );
assert_true( 'after wp_loaded: cart count merged (no stale early result)', 3 === $late['rwgc_commerce_cart_items'] );
assert_true( 'after wp_loaded: no notice', 0 === $GLOBALS['notices'] && 1 === $GLOBALS['reads'] );
assert_true( 'cart_is_ready true after wp_loaded', true === RWGCM_Geo::cart_is_ready() );
assert_true( 'non-array payload passes through', 'x' === RWGCM_Geo::merge_commerce_hints( 'x' ) );

$cur = file_get_contents( __DIR__ . '/../includes/class-rwgcm-price-book-currency.php' );
assert_true( 'currency resolver returns the geo/billing decision before wp_loaded without reading the cart', false !== strpos( $cur, "if ( ! self::cart_is_ready() ) {\n\t\t\treturn \$preferred;\n\t\t}" ) && false !== strpos( $cur, "! did_action( 'wp_loaded' )" ) );
assert_true( 'cart lines are only read through the guarded helper', 1 === substr_count( $cur, 'WC()->cart->get_cart()' ) && false !== strpos( $cur, "if ( self::\$reading_cart || ! self::cart_is_ready() ) {" ) );
assert_true( 'geo hints guard sits before the cart read', strpos( file_get_contents( __DIR__ . '/../includes/class-rwgcm-geo.php' ), 'cart_is_ready()' ) < strpos( file_get_contents( __DIR__ . '/../includes/class-rwgcm-geo.php' ), 'get_cart_contents_count()' ) );

echo "$p passed, $f failed\n"; exit( $f ? 1 : 0 );
