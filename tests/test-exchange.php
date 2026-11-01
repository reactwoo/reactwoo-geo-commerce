<?php
/**
 * Visitor-currency conversion (run with: php tests/test-exchange.php).
 *
 * £49 / £99 / £199 at 22.1 ZAR per GBP, then ZA −15%, is
 * 920.47 / 1859.72 / 3738.22 before rounding or markup.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$_SERVER['REQUEST_URI'] = '/product/converted/';

$GLOBALS['rw_options']         = array(
	'woocommerce_currency' => 'GBP',
);
$GLOBALS['rw_books']           = array();
$GLOBALS['rw_products']        = array();
$GLOBALS['rw_visitor_country'] = 'ZA';
$GLOBALS['rw_actions']         = array();
$GLOBALS['rw_can']             = true;
$GLOBALS['rw_nonce_ok']        = true;

function __( $text, $domain = 'default' ) { unset( $domain ); return $text; } // phpcs:ignore
function esc_html__( $text, $domain = 'default' ) { unset( $domain ); return $text; } // phpcs:ignore
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } // phpcs:ignore
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); } // phpcs:ignore
function absint( $value ) { return abs( (int) $value ); } // phpcs:ignore
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); } // phpcs:ignore
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); } // phpcs:ignore
function is_admin() { return false; } // phpcs:ignore
function did_action( $hook = '' ) { unset( $hook ); return 0; } // phpcs:ignore
function rwgc_get_visitor_country() { return (string) $GLOBALS['rw_visitor_country']; } // phpcs:ignore
function current_user_can( $cap ) { unset( $cap ); return ! empty( $GLOBALS['rw_can'] ); } // phpcs:ignore
function wp_unslash( $value ) { return $value; } // phpcs:ignore
function wp_verify_nonce( $nonce, $action ) { unset( $nonce, $action ); return ! empty( $GLOBALS['rw_nonce_ok'] ); } // phpcs:ignore
function get_option( $key, $default = false ) { // phpcs:ignore
	if ( is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] ) ) {
		return $GLOBALS['rw_options'][ $key ];
	}
	return $default;
}
function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
	unset( $autoload );
	$existed = is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] );
	$old     = $existed ? $GLOBALS['rw_options'][ $key ] : null;
	$GLOBALS['rw_options'][ $key ] = $value;
	if ( class_exists( 'RWGCM_Cache_Purge', false ) ) {
		if ( $existed ) {
			RWGCM_Cache_Purge::on_updated_option( $key, $old, $value );
		} else {
			RWGCM_Cache_Purge::on_added_option( $key, $value );
		}
	}
	return true;
}
function do_action( $tag, $payload = null ) { // phpcs:ignore
	$GLOBALS['rw_actions'][ $tag ][] = $payload;
	if ( 'rwgcm_exchange_rates_saved' === $tag && class_exists( 'RWGCM_Cache_Purge', false ) ) {
		RWGCM_Cache_Purge::on_exchange_rates_saved( $payload );
	}
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore
	unset( $single );
	if ( '_rwgcm_price_book' === $key && isset( $GLOBALS['rw_books'][ $id ] ) ) {
		return $GLOBALS['rw_books'][ $id ];
	}
	return '';
}
function get_woocommerce_currency() { // phpcs:ignore
	if ( ! empty( $GLOBALS['rw_force_currency'] ) ) {
		return (string) $GLOBALS['rw_force_currency'];
	}
	return RWGCM_Price_Book_Currency::filter_currency( (string) get_option( 'woocommerce_currency', 'GBP' ) );
}
function get_woocommerce_currency_symbol( $code = '' ) { // phpcs:ignore
	$map = array( 'GBP' => '£', 'ZAR' => 'R', 'USD' => '$', 'EUR' => '€' );
	return isset( $map[ $code ] ) ? $map[ $code ] : $code;
}
function wc_get_product( $id ) { return isset( $GLOBALS['rw_products'][ $id ] ) ? $GLOBALS['rw_products'][ $id ] : null; } // phpcs:ignore
function wc_get_price_decimals() { return 2; } // phpcs:ignore
function wc_format_decimal( $number, $dp = '' ) { // phpcs:ignore
	$decimals = '' === $dp ? 2 : (int) $dp;
	return number_format( (float) $number, $decimals, '.', '' );
}
function wc_price( $price, $args = array() ) { // phpcs:ignore
	$currency = ( is_array( $args ) && ! empty( $args['currency'] ) ) ? (string) $args['currency'] : get_woocommerce_currency();
	return '<span class="woocommerce-Price-amount amount"><bdi>' . $currency . ' ' . number_format( (float) $price, 2, '.', '' ) . '</bdi></span>';
}
function wc_get_price_to_display( $product, $args = array() ) { // phpcs:ignore
	unset( $product );
	return (float) ( is_array( $args ) && array_key_exists( 'price', $args ) ? $args['price'] : 0 );
}

/**
 * @param string $tag Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $tag, $value ) { // phpcs:ignore
	if ( 'rwgcm_exchange_http_get' === $tag ) {
		if ( ! empty( $GLOBALS['rw_http_throw'] ) ) {
			throw new Exception( 'provider down' );
		}
		return array_key_exists( 'rw_http', $GLOBALS ) ? $GLOBALS['rw_http'] : $value;
	}
	if ( 'rwgcm_exchange_rate_options' === $tag && class_exists( 'RWGCM_Exchange', false ) ) {
		return RWGCM_Exchange::filter_option_names( $value );
	}
	$args = func_get_args();
	if ( ! isset( $args[2] ) || ! is_object( $args[2] ) ) {
		return $value;
	}
	$product = $args[2];
	$parent  = isset( $args[3] ) ? $args[3] : $product;
	if ( 'woocommerce_variation_prices_price' === $tag ) {
		$value = RWGCM_Catalog_Price_Variable::filter_variation_price( $value, $product, $parent );
		return RWGCM_Price_Book_Apply::filter_variation_cache_price( $value, $product, $parent );
	}
	if ( 'woocommerce_variation_prices_regular_price' === $tag ) {
		$value = RWGCM_Catalog_Price_Variable::filter_variation_regular_price( $value, $product, $parent );
		return RWGCM_Price_Book_Apply::filter_variation_cache_regular( $value, $product, $parent );
	}
	if ( 'woocommerce_variation_prices_sale_price' === $tag ) {
		$value = RWGCM_Catalog_Price_Variable::filter_variation_sale_price( $value, $product, $parent );
		return RWGCM_Price_Book_Apply::filter_variation_cache_sale( $value, $product, $parent );
	}
	return $value;
}

class WC_Product {} // phpcs:ignore

/**
 * Product whose view prices pass through the real filters.
 */
class RW_Product extends WC_Product {
	public $id;
	public $regular;
	public $sale;
	public $type;
	public $charged;
	public function __construct( $id, $regular, $sale = '' ) {
		$this->id      = (int) $id;
		$this->regular = (string) $regular;
		$this->sale    = (string) $sale;
		$this->type    = 'simple';
		$this->charged = null;
	}
	public function get_id() { return $this->id; }
	public function is_type( $type ) { return $type === $this->type; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function get_price( $context = 'view' ) {
		$active = ( '' !== (string) $this->sale && is_numeric( $this->sale ) && (float) $this->sale < (float) $this->regular ) ? $this->sale : $this->regular;
		if ( 'edit' === $context ) {
			return $active;
		}
		$active = RWGCM_Catalog_Price::filter_product_price( $active, $this );
		return RWGCM_Price_Book_Apply::filter_price( $active, $this );
	}
	public function get_regular_price( $context = 'view' ) {
		if ( 'edit' === $context ) {
			return $this->regular;
		}
		return RWGCM_Price_Book_Apply::filter_regular_price( $this->regular, $this );
	}
	public function get_sale_price( $context = 'view' ) {
		if ( 'edit' === $context ) {
			return $this->sale;
		}
		return RWGCM_Price_Book_Apply::filter_sale_price( $this->sale, $this );
	}
	public function is_on_sale() {
		$sale = $this->get_sale_price();
		return '' !== (string) $sale && is_numeric( (string) $sale ) && (float) $sale < (float) $this->get_regular_price();
	}
	public function set_price( $price ) { $this->charged = $price; }
	public function get_price_html() {
		return RWGCM_Price_Book_Apply::filter_price_html( wc_price( $this->get_price() ), $this );
	}
}

class RW_Variable extends RW_Product { // phpcs:ignore
	public $children;
	public function __construct( $id, array $children ) {
		parent::__construct( $id, '' );
		$this->type     = 'variable';
		$this->children = $children;
	}
	public function is_type( $type ) { return $type === $this->type; }
	public function get_visible_children() { return $this->children; }
}

class RW_Cart { // phpcs:ignore
	public $items;
	public function __construct( $items ) { $this->items = $items; }
	public function get_cart() { return $this->items; }
	public function is_empty() { return empty( $this->items ); }
}

class RW_Order_Item { // phpcs:ignore
	public $id;
	public function __construct( $id ) { $this->id = (int) $id; }
	public function get_product_id() { return $this->id; }
	public function get_variation_id() { return 0; }
}

class RW_Order { // phpcs:ignore
	public $currency;
	public $items;
	public $meta = array();
	public function __construct( $currency, $ids ) {
		$this->currency = $currency;
		$this->items    = array();
		foreach ( $ids as $id ) {
			$this->items[] = new RW_Order_Item( $id );
		}
	}
	public function get_currency() { return $this->currency; }
	public function get_items( $type = '' ) { unset( $type ); return $this->items; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function get_meta( $key, $single = true ) { unset( $single ); return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-exchange.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-apply.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-order.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-store-api.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-resolution.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-apply.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-catalog-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-catalog-price-variable.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-product-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-product-price-block.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-safe-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-cache-purge.php';

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
function assert_has( $label, $html, $needle ) { // phpcs:ignore
	$ok = is_string( $html ) && false !== strpos( $html, $needle );
	if ( ! $ok ) {
		echo '  html: ' . $html . "\n";
	}
	assert_true( $label, $ok );
}
function assert_lacks( $label, $html, $needle ) { // phpcs:ignore
	$ok = is_string( $html ) && false === strpos( $html, $needle );
	if ( ! $ok ) {
		echo '  html: ' . $html . "\n";
	}
	assert_true( $label, $ok );
}

function rw_pricing( $on = true ) {
	$GLOBALS['rw_options']['rwgcm_pricing'] = array(
		'enabled' => $on ? 1 : 0,
		'rules'   => array(
			array(
				'country' => 'ZA',
				'type'    => 'percent',
				'value'   => -15,
				'active'  => 1,
			),
		),
	);
}

function rw_rates( $zar = '22.1' ) {
	$GLOBALS['rw_options']['rwgcm_exchange_settings'] = array(
		'mode'     => 'auto',
		'source'   => 'manual',
		'provider' => 'ecb',
		'api_key'  => '',
		'rounding' => 'none',
		'markup'   => '0',
	);
	$GLOBALS['rw_options']['rwgcm_exchange_rates'] = array(
		'base'  => 'GBP',
		'rates' => array( 'ZAR' => (string) $zar ),
	);
}

rw_pricing( true );
rw_rates( '22.1' );

$amounts = array( 49 => '920.47', 99 => '1,859.72', 199 => '3,738.22' );
foreach ( $amounts as $gbp => $zar ) {
	$id                         = 1000 + (int) $gbp;
	$GLOBALS['rw_products'][ $id ] = new RW_Product( $id, '10' );
	$GLOBALS['rw_books'][ $id ]    = RWGCM_Price_Book::sanitize_book(
		array(
			'GBP' => array( 'regular' => (string) $gbp ),
			'USD' => array( 'regular' => '40' ),
			'EUR' => array( 'regular' => '45' ),
		)
	);
	$html = RWGCM_Product_Price::render( array( 'product_id' => $id, 'format' => 'code' ) );
	assert_has( '£' . $gbp . ' converts to R' . $zar . ' after ZA -15%', $html, $zar . ' ZAR' );
	$plain = str_replace( ',', '', $zar );
	assert_lacks( '£' . $gbp . ' is not left as GBP', $html, 'GBP' );
	assert_true( 'getter matches the element for £' . $gbp, $plain === (string) $GLOBALS['rw_products'][ $id ]->get_price() );
	$catalog = $GLOBALS['rw_products'][ $id ]->get_price_html();
	assert_has( 'catalog HTML uses the converted amount for £' . $gbp, $catalog, 'ZAR ' . $plain );
	assert_lacks( 'catalog HTML is not rewritten to GBP for £' . $gbp, $catalog, 'GBP' );
}

$product = $GLOBALS['rw_products'][1049];
$cart    = new RW_Cart( array( array( 'data' => $product, 'quantity' => 3 ) ) );
RWGCM_Price_Book_Apply::apply_cart_prices( $cart );
RWGCM_Pricing_Apply::apply_rules( $cart );
assert_true( 'cart line equals the displayed price', '920.47' === (string) $product->charged && '920.47' === (string) $product->get_price() );
assert_true(
	'cart total equals displayed unit times quantity',
	abs( ( (float) $product->charged * 3 ) - ( (float) $product->get_price() * 3 ) ) < 0.001
);
assert_true(
	'a convertible line lets the cart use ZAR',
	'ZAR' === RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', array( RWGCM_Price_Book_Store_Api::line_priced_in( 1049, 'ZAR' ) ) )
);

$block = RWGCM_Product_Price_Block::render_block( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_true( 'block matches the shortcode', $block === RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) ) );
$_SERVER['REQUEST_URI'] = '/wp-json/rwgcm/v1/prices';
$rest = RWGCM_Cache_Safe_Price::build_prices_response(
	array(
		array( 'context' => 'element', 'product_id' => 1049, 'format' => 'code' ),
		array( 'context' => 'catalog', 'product_id' => 1049 ),
	),
	'203.0.113.90'
);
$_SERVER['REQUEST_URI'] = '/product/converted/';
$rest_html = isset( $rest['body']['prices'][0]['html'] ) ? $rest['body']['prices'][0]['html'] : '';
$rest_cat  = isset( $rest['body']['prices'][1]['html'] ) ? $rest['body']['prices'][1]['html'] : '';
assert_has( 'price route element is the converted ZAR amount', $rest_html, '920.47 ZAR' );
assert_has( 'price route catalog is the converted ZAR amount', $rest_cat, 'ZAR 920.47' );

$classic = file_get_contents( dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgcm-elementor-product-price.php' );
$atomic  = file_get_contents( dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgcm-elementor-atomic-product-price.php' );
assert_true( 'classic and atomic widgets use the shared renderer', is_string( $classic ) && is_string( $atomic ) && false !== strpos( $classic, 'RWGCM_Product_Price::render' ) && false !== strpos( $atomic, 'RWGCM_Product_Price::render' ) );

$node = RWGCM_Price_Book_Store_Api::relabel_payload(
	array(
		'id'     => 1049,
		'prices' => array(
			'price'           => '92047',
			'currency_code'   => 'ZAR',
			'currency_symbol' => 'R',
		),
	),
	'ZAR',
	'GBP',
	array( 'RWGCM_Price_Book_Store_Api', 'product_priced_in' ),
	array( 'currency_code' => 'GBP', 'currency_symbol' => '£' )
);
assert_true( 'Store API keeps ZAR on a converted product', isset( $node['prices']['currency_code'] ) && 'ZAR' === $node['prices']['currency_code'] );

$GLOBALS['rw_products'][1299] = new RW_Product( 1299, '49' );
$GLOBALS['rw_books'][1299]    = RWGCM_Price_Book::sanitize_book(
	array(
		'ZAR' => array( 'regular' => '1299' ),
		'GBP' => array( 'regular' => '49' ),
	)
);
$fixed = RWGCM_Product_Price::render( array( 'product_id' => 1299, 'format' => 'code' ) );
assert_has( 'a fixed ZAR book price stays R1299', $fixed, '1,299.00 ZAR' );
assert_lacks( 'the percent rule is not stacked on the fixed ZAR price', $fixed, '1104.15' );
assert_true( 'cart currency stays ZAR for a fixed book price', 'ZAR' === RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', array( RWGCM_Price_Book_Store_Api::line_priced_in( 1299, 'ZAR' ) ) ) );
$fixed_cart_product = $GLOBALS['rw_products'][1299];
RWGCM_Price_Book_Apply::apply_cart_prices( new RW_Cart( array( array( 'data' => $fixed_cart_product ) ) ) );
assert_true( 'cart charges the fixed ZAR book price', '1299.00' === (string) $fixed_cart_product->charged );

$order = new RW_Order( 'ZAR', array( 1049 ) );
RWGCM_Price_Book_Order::record_exchange_rate( $order );
assert_true( 'the order records the rate used', isset( $order->meta[ RWGCM_Price_Book_Order::META_RATE ] ) && '22.1' === (string) $order->meta[ RWGCM_Price_Book_Order::META_RATE ] );
assert_true( 'the order records the rate base', isset( $order->meta[ RWGCM_Price_Book_Order::META_RATE_BASE ] ) && 'GBP' === $order->meta[ RWGCM_Price_Book_Order::META_RATE_BASE ] );
$fixed_order = new RW_Order( 'ZAR', array( 1299 ) );
RWGCM_Price_Book_Order::record_exchange_rate( $fixed_order );
assert_true( 'a fixed book price does not record a conversion rate', ! isset( $fixed_order->meta[ RWGCM_Price_Book_Order::META_RATE ] ) );

unset( $GLOBALS['rw_options']['rwgcm_exchange_rates'] );
$missing = RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_has( 'a missing rate keeps the undiscounted store price in GBP', $missing, '10.00 GBP' );
assert_lacks( 'a missing rate does not apply ZA -15% on top of a GBP book price', $missing, '8.50' );
assert_lacks( 'a missing rate does not convert', $missing, '920.47' );
assert_lacks( 'a missing rate does not label the price as ZAR', $missing, 'ZAR' );
assert_true(
	'a missing rate keeps the cart in the store currency',
	'GBP' === RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', array( RWGCM_Price_Book_Store_Api::line_priced_in( 1049, 'ZAR' ) ) )
);
$unpriced_id                         = 1100;
$GLOBALS['rw_products'][ $unpriced_id ] = new RW_Product( $unpriced_id, '100' );
$unpriced = RWGCM_Product_Price::render( array( 'product_id' => $unpriced_id, 'format' => 'code' ) );
assert_has( 'no book and no rate still applies the ZA rule in GBP', $unpriced, '85.00 GBP' );

rw_rates( '22.1' );
$GLOBALS['rw_options']['rwgcm_exchange_settings']['mode'] = 'off';
$disabled = RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_has( 'conversion off keeps the undiscounted store price in GBP', $disabled, '10.00 GBP' );
assert_lacks( 'conversion off does not convert', $disabled, '920.47' );
$empty_settings = RWGCM_Exchange::sanitize_settings( array() );
assert_true( 'an empty settings array defaults to auto and manual', 'auto' === $empty_settings['mode'] && 'manual' === $empty_settings['source'] && 'none' === $empty_settings['rounding'] && '0' === $empty_settings['markup'] );

rw_rates( '22.1' );
$GLOBALS['rw_options']['rwgcm_exchange_settings']['rounding'] = 'whole';
$whole = RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_has( 'whole-unit rounding is R920.00', $whole, '920.00 ZAR' );
assert_true( 'round_amount whole units', '920.00' === RWGCM_Exchange::round_amount( 920.465, 'whole' ) );
$GLOBALS['rw_options']['rwgcm_exchange_settings']['rounding'] = '99';
$charm = RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_has( '.99 rounding is R919.99', $charm, '919.99 ZAR' );
assert_true( 'round_amount .99', '919.99' === RWGCM_Exchange::round_amount( 920.465, '99' ) );

rw_rates( '22.1' );
$GLOBALS['rw_options']['rwgcm_exchange_settings']['markup'] = '10';
$marked = (string) $GLOBALS['rw_products'][1049]->get_price();
assert_true( 'markup is applied before the percent rule', '1012.51' === $marked );

rw_rates( '22.1' );
$GLOBALS['rw_options']['rwgcm_pricing']['rules'][0]['type']  = 'fixed_line';
$GLOBALS['rw_options']['rwgcm_pricing']['rules'][0]['value'] = -15;
$fixed_line = (string) $GLOBALS['rw_products'][1049]->get_price();
assert_true( 'a fixed-line rule is applied in GBP before conversion', '751.40' === $fixed_line );
rw_pricing( true );

$GLOBALS['rw_products'][1080] = new RW_Product( 1080, '100', '80' );
$GLOBALS['rw_books'][1080]    = RWGCM_Price_Book::sanitize_book(
	array(
		'GBP' => array(
			'regular'    => '100',
			'sale'       => '80',
			'signup_fee' => '10',
		),
	)
);
assert_true( 'sale price is converted after the percent rule', '1502.80' === (string) $GLOBALS['rw_products'][1080]->get_price() );
assert_true( 'regular price is converted after the same rule', '1878.50' === (string) $GLOBALS['rw_products'][1080]->get_regular_price() );
assert_true( 'signup fee converts without the percent rule', '221.00' === (string) RWGCM_Price_Book_Apply::filter_signup_fee( '5', $GLOBALS['rw_products'][1080] ) );
$kept = RWGCM_Price_Book_Apply::filter_available_variation( array( 'price_html' => 'KEEP' ), null, $GLOBALS['rw_products'][1049] );
assert_true( 'variation JSON keeps the active-currency HTML when conversion applies', isset( $kept['price_html'] ) && 'KEEP' === $kept['price_html'] );
$GLOBALS['rw_products'][201] = new RW_Product( 201, '49' );
$GLOBALS['rw_products'][202] = new RW_Product( 202, '99' );
$GLOBALS['rw_books'][201]    = RWGCM_Price_Book::sanitize_book( array( 'GBP' => array( 'regular' => '49' ) ) );
$GLOBALS['rw_books'][202]    = RWGCM_Price_Book::sanitize_book( array( 'GBP' => array( 'regular' => '99' ) ) );
$GLOBALS['rw_products'][200] = new RW_Variable( 200, array( 201, 202 ) );
$range = RWGCM_Price_Book_Apply::filter_variable_price_html( 'From: stale', $GLOBALS['rw_products'][200] );
assert_has( 'variable range uses the lowest converted amount', $range, '920.47' );
assert_has( 'variable range is labelled in ZAR', $range, 'ZAR' );
assert_lacks( 'variable range is not left in GBP', $range, 'GBP' );

$GLOBALS['rw_options']['rwgcm_exchange_settings']['source'] = 'provider';
$GLOBALS['rw_options']['rwgcm_exchange_rates_cache']        = array(
	'base'       => 'GBP',
	'rates'      => array( 'ZAR' => '20' ),
	'provider'   => 'ecb',
	'fetched_at' => time() - 100000,
);
$stale = RWGCM_Exchange::quote( 'GBP', 'ZAR' );
assert_true( 'a stale provider cache is used before manual rates', is_array( $stale ) && '20' === $stale['rate'] && 'cache' === $stale['source'] );
unset( $GLOBALS['rw_options']['rwgcm_exchange_rates_cache'] );
$manual = RWGCM_Exchange::quote( 'GBP', 'ZAR' );
assert_true( 'manual rates are used when the provider cache is empty', is_array( $manual ) && '22.1' === $manual['rate'] && 'manual' === $manual['source'] );
unset( $GLOBALS['rw_options']['rwgcm_exchange_rates'] );
assert_true( 'no rate at all does not quote', null === RWGCM_Exchange::quote( 'GBP', 'ZAR' ) );

$GLOBALS['rw_options']['rwgcm_exchange_rates_cache'] = array(
	'base'       => 'EUR',
	'rates'      => array(
		'GBP' => '2',
		'ZAR' => '4',
	),
	'provider'   => 'ecb',
	'fetched_at' => time(),
);
$cross = RWGCM_Exchange::quote( 'GBP', 'ZAR' );
assert_true( 'ECB rates cross through EUR', is_array( $cross ) && '2' === $cross['rate'] && 'ecb' === $cross['source'] );

$parsed = RWGCM_Exchange::parse_ecb( '<Cube currency=\'USD\' rate=\'1.08\'/><Cube currency="ZAR" rate="20.5"/>' );
assert_true( 'ECB XML parses USD, ZAR, and EUR', is_array( $parsed ) && 'EUR' === $parsed['base'] && isset( $parsed['rates']['USD'], $parsed['rates']['ZAR'], $parsed['rates']['EUR'] ) && '1' === $parsed['rates']['EUR'] );
assert_true( 'garbage ECB XML does not parse', null === RWGCM_Exchange::parse_ecb( 'not xml' ) );
$er = RWGCM_Exchange::parse_er_api( '{"result":"success","base_code":"GBP","rates":{"ZAR":22.1}}' );
assert_true( 'open.er JSON parses', is_array( $er ) && 'GBP' === $er['base'] && isset( $er['rates']['ZAR'] ) && '22.1' === $er['rates']['ZAR'] );
assert_true( 'a failed open.er payload does not parse', null === RWGCM_Exchange::parse_er_api( '{"result":"error"}' ) );

$GLOBALS['rw_http'] = false;
assert_true( 'a failed fetch does not replace last-good rates', false === RWGCM_Exchange::refresh() && isset( $GLOBALS['rw_options']['rwgcm_exchange_rates_cache']['rates']['ZAR'] ) );
$GLOBALS['rw_http_throw'] = true;
assert_true( 'a throwing provider client does not fatal', false === RWGCM_Exchange::refresh() );
$GLOBALS['rw_http_throw'] = false;
$GLOBALS['rw_http']       = '<Cube currency=\'USD\' rate=\'1.1\'/>';
assert_true( 'a successful fetch stores the provider table', true === RWGCM_Exchange::refresh() && isset( $GLOBALS['rw_options']['rwgcm_exchange_rates_cache']['rates']['USD'] ) );

$names = RWGCM_Cache_Purge::exchange_rate_options();
assert_true( 'manual rates stay on the purge option', in_array( 'rwgcm_exchange_rates', $names, true ) );
assert_true( 'settings changes purge', in_array( 'rwgcm_exchange_settings', $names, true ) );
assert_true( 'provider cache changes purge', in_array( 'rwgcm_exchange_rates_cache', $names, true ) );

assert_true(
	'rate tables match at 6 decimal places',
	RWGCM_Exchange::rates_equivalent(
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.1' ), 'fetched_at' => 1 ),
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => 22.1000004 ), 'fetched_at' => 99, 'provider' => 'ecb' )
	)
);
assert_true(
	'a real rate change is not treated as the same table',
	! RWGCM_Exchange::rates_equivalent(
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.1' ) ),
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.2' ) )
	)
);
assert_true(
	'a new currency is a rate change',
	! RWGCM_Exchange::rates_equivalent(
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.1' ) ),
		array( 'base' => 'GBP', 'rates' => array( 'ZAR' => '22.1', 'USD' => '1.27' ) )
	)
);

/**
 * @return int
 */
function rw_exchange_purge_count() {
	$count = 0;
	foreach ( (array) ( isset( $GLOBALS['rw_actions']['rwgcm_cache_purged'] ) ? $GLOBALS['rw_actions']['rwgcm_cache_purged'] : array() ) as $payload ) {
		if ( is_array( $payload ) && isset( $payload['reason'] ) && 'exchange_rates' === $payload['reason'] ) {
			++$count;
		}
	}
	return $count;
}

RWGCM_Cache_Purge::reset_exchange_purge_guard();
$GLOBALS['rw_options']['rwgcm_exchange_rates_cache'] = array(
	'base'       => 'GBP',
	'rates'      => array( 'ZAR' => '22.1' ),
	'provider'   => 'ecb',
	'fetched_at' => 1,
);
$purges_before = rw_exchange_purge_count();
$actions_before = isset( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) ? count( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) : 0;
RWGCM_Exchange::write_cache(
	array(
		'base'       => 'GBP',
		'rates'      => array( 'ZAR' => 22.1000004 ),
		'provider'   => 'ecb',
		'fetched_at' => 50,
	)
);
assert_true( 'an unchanged cron refresh does not purge', rw_exchange_purge_count() === $purges_before );
assert_true( 'an unchanged cron refresh does not fire the saved action', $actions_before === ( isset( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) ? count( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) : 0 ) );
assert_true( 'an unchanged refresh still stores the new fetch time', isset( $GLOBALS['rw_options']['rwgcm_exchange_rates_cache']['fetched_at'] ) && 50 === (int) $GLOBALS['rw_options']['rwgcm_exchange_rates_cache']['fetched_at'] );
RWGCM_Cache_Purge::reset_exchange_purge_guard();
$purges_before = rw_exchange_purge_count();
RWGCM_Exchange::write_cache(
	array(
		'base'       => 'GBP',
		'rates'      => array( 'ZAR' => '23' ),
		'provider'   => 'ecb',
		'fetched_at' => 60,
	)
);
assert_true( 'a cron refresh purges once when a rate changes', 1 === ( rw_exchange_purge_count() - $purges_before ) );
RWGCM_Cache_Purge::reset_exchange_purge_guard();
$purges_before = rw_exchange_purge_count();
RWGCM_Exchange::persist(
	array(
		'mode'     => 'auto',
		'source'   => 'manual',
		'provider' => 'ecb',
		'api_key'  => '',
		'rounding' => 'none',
		'markup'   => '0',
	),
	array(
		'base'  => 'GBP',
		'rates' => array( 'ZAR' => '22.1' ),
	),
	false
);
assert_true( 'saving settings and manual rates purges once', 1 === ( rw_exchange_purge_count() - $purges_before ) );

$_POST = array(
	'_wpnonce'                => 'ok',
	'rwgcm_exchange_mode'     => 'auto',
	'rwgcm_exchange_source'   => 'manual',
	'rwgcm_exchange_provider' => 'ecb',
	'rwgcm_exchange_rounding' => 'whole',
	'rwgcm_exchange_markup'   => '2.5',
	'rwgcm_exchange_manual'   => array( 'ZAR' => '22.1', 'USD' => 'nope' ),
);
$saved = RWGCM_Exchange::process_save_request();
assert_true( 'saving rates succeeds', ! empty( $saved['ok'] ) );
assert_true( 'saving writes the manual rate option', isset( $GLOBALS['rw_options']['rwgcm_exchange_rates']['rates']['ZAR'] ) && '22.1' === $GLOBALS['rw_options']['rwgcm_exchange_rates']['rates']['ZAR'] && ! isset( $GLOBALS['rw_options']['rwgcm_exchange_rates']['rates']['USD'] ) );
assert_true( 'saving fires rwgcm_exchange_rates_saved', isset( $GLOBALS['rw_actions']['rwgcm_exchange_rates_saved'] ) );
$GLOBALS['rw_can'] = false;
$denied            = RWGCM_Exchange::process_save_request();
assert_true( 'saving without permission is forbidden', isset( $denied['error'] ) && 'forbidden' === $denied['error'] );
$GLOBALS['rw_can']      = true;
$GLOBALS['rw_nonce_ok'] = false;
$bad                    = RWGCM_Exchange::process_save_request();
assert_true( 'saving with a bad nonce is rejected', isset( $bad['error'] ) && 'nonce' === $bad['error'] );

$boot     = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-plugin.php' );
$settings = file_get_contents( dirname( __DIR__ ) . '/admin/views/settings-commerce.php' );
assert_true( 'the plugin boots exchange rates', is_string( $boot ) && false !== strpos( $boot, 'RWGCM_Exchange::init' ) );
assert_true(
	'the settings screen offers manual rates and both providers',
	is_string( $settings ) && false !== strpos( $settings, 'rwgcm_exchange_mode' ) && false !== strpos( $settings, 'open.er-api.com' ) && false !== strpos( $settings, 'ECB' )
);

$token = RWGCM_Exchange::cache_token();
$GLOBALS['rw_options']['rwgcm_exchange_rates'] = array(
	'base'  => 'GBP',
	'rates' => array( 'ZAR' => '23' ),
);
assert_true( 'a rate change alters the variation price hash token', $token !== RWGCM_Exchange::cache_token() );

// Rule ID 1 as the 0.8.0 admin UI saves it (class-rwgcm-admin-rules.php rule_array_from_post).
// Geo Core rejects operator "is", and its portable match returns false. The visitor country still applies.
class RWGCM_DB { // phpcs:ignore
	public static function rules_table_exists() { return true; }
}
class RWGCM_Rule_Store { // phpcs:ignore
	public static function count_by_status( $status ) { unset( $status ); return 1; }
	public static function get_all_rules() { return array( $GLOBALS['rw_ui_rule'] ); }
	public static function set_purge_on_write( $purge ) { unset( $purge ); }
	public static function update_rule( $id, $rule ) {
		unset( $id );
		$GLOBALS['rw_ui_rule'] = $rule;
		return true;
	}
}
class RWGC_Context_Snapshot { // phpcs:ignore
	public static function from_array( array $context ) { return (object) $context; }
}
class RWGC_Targeting_Rule_Set_Schema { // phpcs:ignore
	public static function sanitize( $raw ) { return is_array( $raw ) ? $raw : null; }
	public static function sanitize_condition_type_string( $type ) { return sanitize_key( (string) $type ); }
}
class RWGC_Target_Operators { // phpcs:ignore
	public static function is_valid( $op ) { return in_array( $op, array( 'in', 'not_in' ), true ); }
	public static function evaluate( $actual, $op, $expected ) { unset( $actual, $op, $expected ); return false; }
}
class RWGC_Rule_Evaluator { // phpcs:ignore
	public static function matches( $set, $snapshot ) { unset( $set, $snapshot ); return false; }
}
function rwgc_get_context_snapshot() { return array(); } // phpcs:ignore

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-condition-library.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-rule-summary.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-condition-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-targeting-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-rule-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-diagnostics.php';

$GLOBALS['rw_ui_rule'] = array(
	'id'         => 1,
	'label'      => 'ZA percent -15',
	'status'     => 'active',
	'priority'   => 100,
	'scope'      => array(
		'type' => 'global',
		'ids'  => array(),
	),
	'conditions' => array(
		'match' => 'all',
		'items' => array(
			array(
				'field'    => 'visitor.country',
				'target'   => 'country',
				'operator' => 'is',
				'value'    => 'ZA',
				'label'    => 'South Africa',
			),
		),
	),
	'actions'    => array(
		array(
			'type'  => 'price_adjustment',
			'mode'  => 'percent',
			'value' => -15.0,
		),
	),
	'meta'       => array(
		'source'                 => 'admin_generic_rules',
		'use_portable_targeting' => false,
		'portable_targeting'     => '',
	),
);
$ui_summary = RWGCM_Rule_Summary::summarize_rule( $GLOBALS['rw_ui_rule'] );
assert_true(
	'the stored 0.8.0 UI rule reads as the South Africa 15% decrease',
	'If visitor country is South Africa, decrease the product price by 15%.' === $ui_summary
);

$GLOBALS['rw_options']['rwgcm_exchange_settings'] = array(
	'mode'     => 'auto',
	'source'   => 'manual',
	'provider' => 'ecb',
	'api_key'  => '',
	'rounding' => 'none',
	'markup'   => '0',
);
$GLOBALS['rw_options']['rwgcm_exchange_rates'] = array(
	'base'  => 'GBP',
	'rates' => array( 'ZAR' => '22.16' ),
);
$ui_price = (string) $GLOBALS['rw_products'][1049]->get_price();
assert_true( 'the UI rule discounts the converted £49 price', '922.96' === $ui_price );
$ui_html = RWGCM_Product_Price::render( array( 'product_id' => 1049, 'format' => 'code' ) );
assert_has( 'the price element includes the 15% country rule', $ui_html, '922.96 ZAR' );
assert_lacks( 'the price element is not the undiscounted conversion', $ui_html, '1,085.84' );
$ui_cart_product = $GLOBALS['rw_products'][1049];
RWGCM_Price_Book_Apply::apply_cart_prices( new RW_Cart( array( array( 'data' => $ui_cart_product ) ) ) );
assert_true( 'the cart charges the discounted converted price', '922.96' === (string) $ui_cart_product->charged );
$fixed_ui = (string) $GLOBALS['rw_products'][1299]->get_price();
assert_true( 'a fixed ZAR book price still ignores the UI percent rule', '1299.00' === $fixed_ui );

$GLOBALS['rw_ui_rule']['conditions']['items'][0]['value'] = 'South Africa';
assert_true( 'a country stored as its name still discounts the converted price', '922.96' === (string) $GLOBALS['rw_products'][1049]->get_price() );

$GLOBALS['rw_force_currency'] = 'GBP';
$fallback = new ReflectionProperty( 'RWGCM_Price_Book_Currency', 'shipping_fallback' );
$fallback->setAccessible( true );
$fallback->setValue( null, 'ZAR' );
$GLOBALS['rw_options']['rwgcm_exchange_settings']['rounding'] = 'none';
assert_true( 'shipping fallback is the store currency', RWGCM_Price_Book_Apply::shipping_fallback_to_store() );
$with_zar = (string) $GLOBALS['rw_products'][1299]->get_price();
assert_true( 'fallback applies -15% to the GBP book even when a ZAR book exists', '41.65' === $with_zar );
assert_true( 'fallback does not charge the fixed ZAR price', '1299.00' !== $with_zar );
$no_zar = (string) $GLOBALS['rw_products'][1049]->get_price();
assert_true( 'fallback applies -15% to the GBP book when there is no ZAR price', '41.65' === $no_zar );
$GLOBALS['rw_products'][1500] = new RW_Product( 1500, '100' );
$GLOBALS['rw_books'][1500]    = RWGCM_Price_Book::sanitize_book( array( 'ZAR' => array( 'regular' => '800' ) ) );
assert_true( 'fallback applies -15% to the regular price when only a ZAR book exists', '85.00' === (string) $GLOBALS['rw_products'][1500]->get_price() );
$cart_product = $GLOBALS['rw_products'][1049];
RWGCM_Price_Book_Apply::apply_cart_prices( new RW_Cart( array( array( 'data' => $cart_product ) ) ) );
RWGCM_Pricing_Apply::apply_rules( new RW_Cart( array( array( 'data' => $cart_product ) ) ) );
assert_true( 'the cart charges the ruled store amount once', '41.65' === (string) $cart_product->charged );
$fallback->setValue( null, '' );
assert_true( 'a GBP book is not discounted when the visitor currency is already GBP', '49.00' === (string) $GLOBALS['rw_products'][1049]->get_price() );
$GLOBALS['rw_force_currency'] = 'ZAR';
assert_true( 'a fixed ZAR price is not discounted while ZAR is charged', '1299.00' === (string) $GLOBALS['rw_products'][1299]->get_price() );
$GLOBALS['rw_force_currency'] = '';

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-action-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-rule-sanitizer.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-rule-migration.php';
$saved_country = RWGCM_Rule_Sanitizer::sanitize_rule(
	array(
		'label'      => 'ZA',
		'status'     => 'active',
		'priority'   => 100,
		'scope'      => array( 'type' => 'global', 'ids' => array() ),
		'conditions' => array(
			'match' => 'all',
			'items' => array(
				array(
					'field'    => 'visitor.country',
					'target'   => 'country',
					'operator' => 'is',
					'value'    => 'South Africa',
					'label'    => 'South Africa',
				),
			),
		),
		'actions'    => array(
			array(
				'type'  => 'price_adjustment',
				'mode'  => 'percent',
				'value' => -15,
			),
		),
	)
);
assert_true( 'saving a rule stores the country name as ZA', is_array( $saved_country ) && 'ZA' === $saved_country['conditions']['items'][0]['value'] );
$GLOBALS['rw_ui_rule']['conditions']['items'][0]['value'] = 'South Africa';
$migrated = RWGCM_Rule_Migration::maybe_normalize_country_names();
assert_true( 'upgrade rewrites a stored country name to ZA', 1 === $migrated && 'ZA' === $GLOBALS['rw_ui_rule']['conditions']['items'][0]['value'] );
assert_true( 'the country migration does not run twice', 0 === RWGCM_Rule_Migration::maybe_normalize_country_names() );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
