<?php
/**
 * Store API currency honesty (run with: php tests/test-store-api-currency.php).
 *
 * A ZA visitor resolves to ZAR. Every Store API product node and every cart
 * must pair a currency only with an amount priced in that currency.
 *
 * @package ReactWoo_Geo_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'REST_REQUEST', true );
$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/products';

$GLOBALS['rw_books']    = array();
$GLOBALS['rw_products'] = array();
$GLOBALS['rw_currency'] = 'ZAR';

function __( $t, $d = 'default' ) { unset( $d ); return $t; } // phpcs:ignore
function apply_filters( $tag, $value ) { unset( $tag ); return $value; } // phpcs:ignore
function add_filter() {} // phpcs:ignore
function add_action() {} // phpcs:ignore
function did_action() { return 1; } // phpcs:ignore
function rwgc_get_visitor_country() { return 'ZA'; } // phpcs:ignore
function get_option( $k, $d = false ) { // phpcs:ignore
	$o = array( 'woocommerce_currency' => 'GBP', 'woocommerce_currency_pos' => 'left' );
	return isset( $o[ $k ] ) ? $o[ $k ] : $d;
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore
	unset( $key, $single );
	return isset( $GLOBALS['rw_books'][ $id ] ) ? $GLOBALS['rw_books'][ $id ] : '';
}
function get_woocommerce_currency() { return $GLOBALS['rw_currency']; } // phpcs:ignore
function get_woocommerce_currency_symbol( $c = '' ) { // phpcs:ignore
	$s = array( 'GBP' => '&pound;', 'ZAR' => 'R' );
	return isset( $s[ $c ] ) ? $s[ $c ] : $c;
}
function wc_get_product( $id ) { return isset( $GLOBALS['rw_products'][ $id ] ) ? $GLOBALS['rw_products'][ $id ] : null; } // phpcs:ignore

/** Minimal product. */
class RW_Product {
	public $id; public $price; public $type; public $children; // phpcs:ignore
	public function __construct( $id, $price, $type = 'simple', $children = array() ) { $this->id = $id; $this->price = $price; $this->type = $type; $this->children = $children; }
	public function get_id() { return $this->id; }
	public function is_type( $t ) { return $t === $this->type; }
	public function get_children() { return $this->children; }
	public function get_price( $ctx = 'view' ) {
		return 'edit' === $ctx ? $this->price : RWGCM_Price_Book_Apply::filter_price( $this->price, $this );
	}
}
/** REST stand-ins. */
class RW_Response { public $d; public function __construct( $d ) { $this->d = $d; } public function get_data() { return $this->d; } public function set_data( $d ) { $this->d = $d; } } // phpcs:ignore
class RW_Request { public $r; public function __construct( $r ) { $this->r = $r; } public function get_route() { return $this->r; } } // phpcs:ignore
/** Order stand-ins. */
class RW_Item { public $p; public function __construct( $p ) { $this->p = $p; } public function get_product_id() { return $this->p; } public function get_variation_id() { return 0; } public function get_meta( $k ) { unset( $k ); return ''; } } // phpcs:ignore
class RW_Order { public $c; public $i; public function __construct( $c, $ids ) { $this->c = $c; $this->i = array_map( function ( $p ) { return new RW_Item( $p ); }, $ids ); } public function get_currency() { return $this->c; } public function get_items( $t ) { unset( $t ); return $this->i; } } // phpcs:ignore

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-apply.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-store-api.php';

$passed = 0;
$failed = 0;
function assert_true( $label, $condition ) { // phpcs:ignore
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$label}\n"; return; }
	++$failed; echo "FAIL: {$label}\n";
}

// Fixtures: 11883 Betty Boop (no book, £23), 500 with a ZAR book (R499 / £23).
$GLOBALS['rw_products'][11883] = new RW_Product( 11883, '23' );
$GLOBALS['rw_products'][500]   = new RW_Product( 500, '23' );
$GLOBALS['rw_books'][500]      = RWGCM_Price_Book::sanitize_book( array( 'ZAR' => array( 'regular' => '499' ), 'GBP' => array( 'regular' => '23' ) ) );
$GLOBALS['rw_products'][600]   = new RW_Product( 600, '10', 'variable', array( 601, 602 ) );
$GLOBALS['rw_books'][601]      = RWGCM_Price_Book::sanitize_book( array( 'ZAR' => array( 'regular' => '199' ) ) );

/** What WooCommerce's ProductSchema builds: amount from get_price(), currency from get_woocommerce_currency(). */
function rw_product_node( $id ) {
	$p = $GLOBALS['rw_products'][ $id ];
	return array(
		'id'     => $id,
		'prices' => array_merge(
			array( 'price' => (string) round( (float) $p->get_price() * 100 ), 'currency_minor_unit' => 2 ),
			array( 'currency_code' => get_woocommerce_currency(), 'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol( get_woocommerce_currency() ) ), 'currency_prefix' => html_entity_decode( get_woocommerce_currency_symbol( get_woocommerce_currency() ) ), 'currency_suffix' => '' )
		),
	);
}
/** Cart currency exactly as resolve_active_currency() decides it. */
function rw_cart_currency( array $ids ) {
	$support = array();
	foreach ( $ids as $id ) {
		$support[] = RWGCM_Price_Book_Store_Api::line_priced_in( $id, 'ZAR' );
	}
	return RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', $support );
}
function rw_cart_payload( array $ids, array $cross ) {
	$GLOBALS['rw_currency'] = rw_cart_currency( $ids );
	$items = array();
	$total = 0;
	foreach ( $ids as $id ) {
		$n      = rw_product_node( $id );
		$total += (int) $n['prices']['price'];
		$items[] = array( 'key' => 'k' . $id, 'id' => $id, 'quantity' => 1, 'prices' => $n['prices'], 'totals' => array( 'line_total' => $n['prices']['price'], 'currency_code' => get_woocommerce_currency() ) );
	}
	return array(
		'items'       => $items,
		'cross_sells' => array_map( 'rw_product_node', $cross ),
		'totals'      => array( 'total_price' => (string) $total, 'currency_code' => get_woocommerce_currency() ),
	);
}
function rw_dispatch( $route, array $data ) {
	$r = RWGCM_Price_Book_Store_Api::filter_rest_response( new RW_Response( $data ), null, new RW_Request( $route ) );
	return $r->get_data();
}
/** Honest pair: ZAR only with the book amount, otherwise GBP with the base amount. */
function rw_honest( array $node ) {
	$id  = $node['id'];
	$cur = $node['prices']['currency_code'];
	$amt = (int) $node['prices']['price'];
	$base = (int) round( (float) $GLOBALS['rw_products'][ $id ]->get_price( 'edit' ) * 100 );
	if ( 'GBP' === $cur ) {
		$book = RWGCM_Price_Book_Currency::get_book( $id );
		$gbp  = RWGCM_Price_Book::has_price( $book, 'GBP' ) ? (int) round( (float) $book['GBP']['regular'] * 100 ) : $base;
		return $amt === $gbp || $amt === $base;
	}
	$book = RWGCM_Price_Book_Currency::get_book( $id );
	return RWGCM_Price_Book::has_price( $book, $cur ) && $amt === (int) round( (float) $book[ $cur ]['regular'] * 100 );
}

// 1. /products for ZA, product without a ZAR book (the staging case).
$GLOBALS['rw_currency'] = 'ZAR';
$raw = rw_product_node( 11883 );
assert_true( 'baseline: unfixed Store API pairs ZAR with the GBP amount 2300', 'ZAR' === $raw['prices']['currency_code'] && '2300' === $raw['prices']['price'] && ! rw_honest( $raw ) );
$out = rw_dispatch( '/wc/store/v1/products/11883', $raw );
assert_true( 'ZA product without ZAR book reports GBP', 'GBP' === $out['prices']['currency_code'] );
assert_true( 'ZA product without ZAR book keeps base amount 2300', '2300' === $out['prices']['price'] );
assert_true( 'ZA product without ZAR book uses the £ symbol as prefix', '£' === $out['prices']['currency_symbol'] && '£' === $out['prices']['currency_prefix'] );
assert_true( 'ZA product without ZAR book is an honest pair', rw_honest( $out ) );

// 2. /products for ZA, product with a ZAR book.
$out = rw_dispatch( '/wc/store/v1/products/500', rw_product_node( 500 ) );
assert_true( 'ZA product with ZAR book reports ZAR 49900', 'ZAR' === $out['prices']['currency_code'] && '49900' === $out['prices']['price'] && 'R' === $out['prices']['currency_symbol'] );

// 3. Collection (block product grid) mixing both, plus a partly priced variable product.
$out = rw_dispatch( '/wc/store/v1/products', array( rw_product_node( 11883 ), rw_product_node( 500 ), rw_product_node( 600 ) ) );
assert_true( 'grid: unpriced item relabelled GBP', 'GBP' === $out[0]['prices']['currency_code'] );
assert_true( 'grid: priced item stays ZAR', 'ZAR' === $out[1]['prices']['currency_code'] );
assert_true( 'grid: variable with an unpriced variation reports GBP', 'GBP' === $out[2]['prices']['currency_code'] );
assert_true( 'grid: every node is an honest pair', rw_honest( $out[0] ) && rw_honest( $out[1] ) );

// 4. Batch-style and non-Store-API routes.
$out = rw_dispatch( '/wp/v2/posts', rw_product_node( 11883 ) );
assert_true( 'non-Store-API routes are untouched', 'ZAR' === $out['prices']['currency_code'] );

// 5. Cart: ZA, only ZAR-priced lines -> whole cart ZAR with book amounts.
$cart = rw_dispatch( '/wc/store/v1/cart', rw_cart_payload( array( 500 ), array( 11883 ) ) );
assert_true( 'cart with ZAR-priced line is ZAR', 'ZAR' === $cart['totals']['currency_code'] && '49900' === $cart['totals']['total_price'] );
assert_true( 'cart line is an honest pair', rw_honest( $cart['items'][0] ) );
assert_true( 'cart cross-sell without ZAR book reports GBP', 'GBP' === $cart['cross_sells'][0]['prices']['currency_code'] && rw_honest( $cart['cross_sells'][0] ) );

// 6. Cart: ZA, only unpriced lines -> GBP.
$cart = rw_dispatch( '/wc/store/v1/cart', rw_cart_payload( array( 11883 ), array() ) );
assert_true( 'cart without ZAR book is GBP 2300', 'GBP' === $cart['totals']['currency_code'] && '2300' === $cart['totals']['total_price'] && rw_honest( $cart['items'][0] ) );

// 7. Mixed cart -> the whole cart falls back to GBP, every line honest.
$cart = rw_dispatch( '/wc/store/v1/cart', rw_cart_payload( array( 500, 11883 ), array( 500 ) ) );
assert_true( 'mixed cart is GBP', 'GBP' === $cart['totals']['currency_code'] );
assert_true( 'mixed cart total is GBP 4600 (no ZAR amount leaks in)', '4600' === $cart['totals']['total_price'] );
assert_true( 'mixed cart lines are all GBP honest pairs', 'GBP' === $cart['items'][0]['prices']['currency_code'] && rw_honest( $cart['items'][0] ) && rw_honest( $cart['items'][1] ) );
assert_true( 'mixed cart cross-sell stays honest in GBP', rw_honest( $cart['cross_sells'][0] ) );

// 8. Checkout guard: order currency must match line prices.
$base = 'GBP';
$p    = array( 'RWGCM_Price_Book_Store_Api', 'line_priced_in' );
assert_true( 'guard: ZAR order with all ZAR-priced lines passes', RWGCM_Price_Book_Store_Api::lines_priced_in( array( 500 ), 'ZAR', $base, $p ) );
assert_true( 'guard: GBP order always passes', RWGCM_Price_Book_Store_Api::lines_priced_in( array( 500, 11883 ), 'GBP', $base, $p ) );
assert_true( 'guard: ZAR order with an unpriced line fails', ! RWGCM_Price_Book_Store_Api::lines_priced_in( array( 500, 11883 ), 'ZAR', $base, $p ) );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_Order( 'ZAR', array( 11883 ) ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: creating a ZAR order for a GBP-only line throws', $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_Order( rw_cart_currency( array( 500, 11883 ) ), array( 500, 11883 ) ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: order in the currency_for_lines() currency never throws (mixed)', ! $threw );
$threw = false;
try { RWGCM_Price_Book_Store_Api::guard_order_currency( new RW_Order( rw_cart_currency( array( 500 ) ), array( 500 ) ) ); } catch ( Exception $e ) { $threw = true; } // phpcs:ignore
assert_true( 'guard: order in the currency_for_lines() currency never throws (ZAR)', ! $threw );

// 9. Structured data.
$GLOBALS['rw_currency'] = 'ZAR';
$m = RWGCM_Price_Book_Store_Api::filter_structured_data( array( 'offers' => array( array( 'price' => '23', 'priceCurrency' => 'ZAR', 'priceSpecification' => array( array( 'price' => '23', 'priceCurrency' => 'ZAR' ) ) ) ) ), $GLOBALS['rw_products'][11883] );
assert_true( 'JSON-LD offer for unpriced product says GBP', 'GBP' === $m['offers'][0]['priceCurrency'] && 'GBP' === $m['offers'][0]['priceSpecification'][0]['priceCurrency'] );
$m = RWGCM_Price_Book_Store_Api::filter_structured_data( array( 'offers' => array( array( 'price' => '499', 'priceCurrency' => 'ZAR' ) ) ), $GLOBALS['rw_products'][500] );
assert_true( 'JSON-LD offer for ZAR-priced product stays ZAR', 'ZAR' === $m['offers'][0]['priceCurrency'] );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
