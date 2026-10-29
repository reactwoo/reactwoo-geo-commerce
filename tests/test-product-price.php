<?php
/**
 * Localised product price (run with: php tests/test-product-price.php).
 *
 * Visitor country is overridden through rwgc_get_visitor_country(), the same
 * Geo Core entry the storefront and the country simulator use. Expected rule
 * amounts come from RWGCM_Simulator::pricing_preview(), not a second formula.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$_SERVER['REQUEST_URI'] = '/product/localised/';

$GLOBALS['rw_options']          = array();
$GLOBALS['rw_books']            = array();
$GLOBALS['rw_products']         = array();
$GLOBALS['rw_visitor_country']  = 'ZA';

function __( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function esc_html( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function absint( $value ) { // phpcs:ignore
	return abs( (int) $value );
}
function sanitize_key( $key ) { // phpcs:ignore
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function sanitize_text_field( $text ) { // phpcs:ignore
	$text = strip_tags( (string) $text );
	$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	return trim( $text );
}
function sanitize_html_class( $class, $fallback = '' ) { // phpcs:ignore
	$clean = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	return '' === $clean ? (string) $fallback : $clean;
}
function wp_kses_post( $html ) { // phpcs:ignore
	return strip_tags( (string) $html, '<span><del><ins><bdi>' );
}
function is_admin() { // phpcs:ignore
	return false;
}
function did_action( $hook = '' ) { // phpcs:ignore
	unset( $hook );
	return 0;
}
function rwgc_get_visitor_country() { // phpcs:ignore
	return (string) $GLOBALS['rw_visitor_country'];
}
function current_user_can( $cap ) { // phpcs:ignore
	unset( $cap );
	return ! empty( $GLOBALS['rw_can_preview'] );
}
function get_option( $key, $default = false ) { // phpcs:ignore
	if ( is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] ) ) {
		return $GLOBALS['rw_options'][ $key ];
	}
	return $default;
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore
	unset( $single );
	if ( '_rwgcm_price_book' === $key && isset( $GLOBALS['rw_books'][ $id ] ) ) {
		return $GLOBALS['rw_books'][ $id ];
	}
	return '';
}
function get_woocommerce_currency() { // phpcs:ignore
	return RWGCM_Price_Book_Currency::filter_currency( (string) get_option( 'woocommerce_currency', 'GBP' ) );
}
function wc_get_product( $id ) { // phpcs:ignore
	return isset( $GLOBALS['rw_products'][ $id ] ) ? $GLOBALS['rw_products'][ $id ] : null;
}
function wc_get_price_decimals() { // phpcs:ignore
	return 2;
}
function wc_get_price_decimal_separator() { // phpcs:ignore
	return '.';
}
function wc_get_price_thousand_separator() { // phpcs:ignore
	return ',';
}
function wc_format_decimal( $number, $dp = '' ) { // phpcs:ignore
	$decimals = '' === $dp ? 2 : (int) $dp;
	return number_format( (float) $number, $decimals, '.', '' );
}
function wc_price( $price, $args = array() ) { // phpcs:ignore
	$currency = ( is_array( $args ) && ! empty( $args['currency'] ) ) ? (string) $args['currency'] : get_woocommerce_currency();
	return '<span class="woocommerce-Price-amount amount"><bdi>' . $currency . ' ' . number_format( (float) $price, 2, '.', '' ) . '</bdi></span>';
}
function wc_get_price_to_display( $product, $args = array() ) { // phpcs:ignore
	if ( is_array( $args ) && array_key_exists( 'price', $args ) ) {
		$price = (float) $args['price'];
	} elseif ( is_object( $product ) && method_exists( $product, 'get_price' ) ) {
		$price = (float) $product->get_price();
	} else {
		$price = 0.0;
	}
	if ( 'incl' === get_option( 'woocommerce_tax_display_shop', 'excl' ) ) {
		$price = round( $price * 1.2, 2 );
	}
	return $price;
}

/**
 * Dispatch the variation-price filters WooCommerce runs for the From/range.
 * Every other hook returns the incoming value, matching the rest of this harness.
 *
 * @param string $tag   Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $tag, $value ) { // phpcs:ignore
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
 * Minimal product whose view prices pass through the real Geo Commerce filters.
 */
class RW_Product extends WC_Product {
	public $id;
	public $regular;
	public $sale;
	public $type;
	public $children;
	public $purchasable;
	public $in_stock;
	public $meta;

	public function __construct( $id, $regular, $type = 'simple', $sale = '', $children = array() ) {
		$this->id          = (int) $id;
		$this->regular     = (string) $regular;
		$this->sale        = (string) $sale;
		$this->type        = (string) $type;
		$this->children    = $children;
		$this->purchasable = true;
		$this->in_stock    = true;
		$this->meta        = array();
	}
	public function get_id() {
		return $this->id;
	}
	public function is_type( $type ) {
		return $type === $this->type;
	}
	public function is_purchasable() {
		return (bool) $this->purchasable;
	}
	public function is_in_stock() {
		return (bool) $this->in_stock;
	}
	public function get_visible_children() {
		return $this->children;
	}
	public function get_meta( $key, $single = true ) {
		unset( $single );
		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}
	public function get_price( $context = 'view' ) {
		$active = ( '' !== (string) $this->sale && is_numeric( $this->sale ) && (float) $this->sale < (float) $this->regular ) ? $this->sale : $this->regular;
		if ( 'edit' === $context ) {
			return $active;
		}
		if ( 'variation' === $this->type ) {
			$active = RWGCM_Catalog_Price_Variable::filter_variation_price( $active, $this, $this );
		} else {
			$active = RWGCM_Catalog_Price::filter_product_price( $active, $this );
		}
		return RWGCM_Price_Book_Apply::filter_price( $active, $this );
	}
	public function get_regular_price( $context = 'view' ) {
		if ( 'edit' === $context ) {
			return $this->regular;
		}
		$price = $this->regular;
		if ( 'variation' === $this->type ) {
			$price = RWGCM_Catalog_Price_Variable::filter_variation_regular_price( $price, $this, $this );
		}
		return RWGCM_Price_Book_Apply::filter_regular_price( $price, $this );
	}
	public function get_sale_price( $context = 'view' ) {
		if ( 'edit' === $context ) {
			return $this->sale;
		}
		$price = $this->sale;
		if ( 'variation' === $this->type ) {
			$price = RWGCM_Catalog_Price_Variable::filter_variation_sale_price( $price, $this, $this );
		}
		return RWGCM_Price_Book_Apply::filter_sale_price( $price, $this );
	}
	public function is_on_sale( $context = 'view' ) {
		$regular = $this->get_regular_price( $context );
		$sale    = $this->get_sale_price( $context );
		return '' !== (string) $sale && is_numeric( (string) $sale ) && (float) $sale < (float) $regular;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-apply.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-calc.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-rules.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-pricing-resolution.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-catalog-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-catalog-price-variable.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-simulator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-product-price.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-product-price-block.php';

$passed = 0;
$failed = 0;

/**
 * @param string $label     Label.
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

/**
 * @param string $label  Label.
 * @param string $html   HTML.
 * @param string $needle Needle.
 * @return void
 */
function assert_has( $label, $html, $needle ) {
	$ok = is_string( $html ) && false !== strpos( $html, $needle );
	if ( ! $ok ) {
		echo '  html: ' . $html . "\n";
	}
	assert_true( $label, $ok );
}

/**
 * @param string $label  Label.
 * @param string $html   HTML.
 * @param string $needle Needle.
 * @return void
 */
function assert_lacks( $label, $html, $needle ) {
	$ok = is_string( $html ) && false === strpos( $html, $needle );
	if ( ! $ok ) {
		echo '  html: ' . $html . "\n";
	}
	assert_true( $label, $ok );
}

/**
 * @param string $iso2 Country.
 * @return void
 */
function override_visitor_country( $iso2 ) {
	$GLOBALS['rw_visitor_country'] = strtoupper( $iso2 );
}

$GLOBALS['rw_options']['woocommerce_currency']        = 'ZAR';
$GLOBALS['rw_options']['woocommerce_tax_display_shop'] = 'excl';
$GLOBALS['rw_options']['rwgcm_pricing']                = array(
	'enabled' => true,
	'rules'   => array(
		array(
			'country'      => 'ZA',
			'type'         => 'percent',
			'value'        => -15,
			'category_ids' => array(),
			'active'       => true,
			'label'        => 'ZA -15%',
		),
	),
);

// Store currency is ZAR, so the ZA -15% rule adjusts the catalog price.
// GB, US, and DE use fixed price-book amounts.
$GLOBALS['rw_products'][10] = new RW_Product( 10, '1000' );
$GLOBALS['rw_books'][10]    = RWGCM_Price_Book::sanitize_book(
	array(
		'GBP' => array( 'regular' => '39' ),
		'USD' => array( 'regular' => '49' ),
		'EUR' => array( 'regular' => '45' ),
	)
);
$GLOBALS['rw_products'][11] = new RW_Product( 11, '45', 'simple', '40' );
$GLOBALS['rw_books'][11]    = RWGCM_Price_Book::sanitize_book(
	array(
		'EUR' => array(
			'regular' => '45',
			'sale'    => '40',
		),
	)
);
$GLOBALS['rw_products'][21] = new RW_Product( 21, '10', 'variation' );
$GLOBALS['rw_products'][22] = new RW_Product( 22, '12', 'variation' );
$GLOBALS['rw_products'][23] = new RW_Product( 23, '1', 'variation' );
$GLOBALS['rw_products'][23]->in_stock = false;
$GLOBALS['rw_books'][21]    = RWGCM_Price_Book::sanitize_book( array( 'USD' => array( 'regular' => '49' ) ) );
$GLOBALS['rw_books'][22]    = RWGCM_Price_Book::sanitize_book( array( 'USD' => array( 'regular' => '60' ) ) );
$GLOBALS['rw_books'][23]    = RWGCM_Price_Book::sanitize_book( array( 'USD' => array( 'regular' => '10' ) ) );
$GLOBALS['rw_products'][20] = new RW_Product( 20, '10', 'variable', '', array( 21, 22, 23 ) );

$GLOBALS['rw_products'][13] = new RW_Product( 13, '39' );
$GLOBALS['rw_books'][13]    = RWGCM_Price_Book::sanitize_book( array( 'GBP' => array( 'regular' => '39' ) ) );
$GLOBALS['rw_products'][13]->meta['_subscription_period']          = 'year';
$GLOBALS['rw_products'][13]->meta['_subscription_period_interval'] = 1;

$GLOBALS['rw_products'][30] = new RW_Product( 30, '39' );
$GLOBALS['rw_products'][30]->in_stock = false;
$GLOBALS['rw_products'][31] = new RW_Product( 31, '39' );
$GLOBALS['rw_products'][31]->purchasable = false;

$za_preview = RWGCM_Simulator::pricing_preview( 'ZA', array(), 1000 );
$za_amount  = number_format( (float) $za_preview['adjusted'], 2, '.', '' );
assert_true( 'simulator prices the ZA -15% rule at 850.00', '850.00' === $za_amount );

override_visitor_country( 'ZA' );
$za_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
assert_has( 'ZA renders the simulator amount in ZAR', $za_html, $za_amount . ' ZAR' );
assert_lacks( 'ZA percent rule replaces the 1000 catalog price', $za_html, '1000.00' );
assert_true(
	'ZA rendered amount matches the filtered getter',
	false !== strpos( $za_html, number_format( (float) wc_get_price_to_display( $GLOBALS['rw_products'][10] ), 2, '.', '' ) )
);

override_visitor_country( 'GB' );
$gb_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
$gb_book = RWGCM_Price_Book::resolve_prices( $GLOBALS['rw_books'][10]['GBP'] );
assert_has( 'GB renders the GBP price-book amount', $gb_html, $gb_book['active'] . ' GBP' );
assert_lacks( 'GB does not show the ZAR catalog amount', $gb_html, '850.00' );

override_visitor_country( 'US' );
$us_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
$us_book = RWGCM_Price_Book::resolve_prices( $GLOBALS['rw_books'][10]['USD'] );
assert_has( 'US renders the USD price-book amount', $us_html, $us_book['active'] . ' USD' );

override_visitor_country( 'DE' );
$de_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
$de_book = RWGCM_Price_Book::resolve_prices( $GLOBALS['rw_books'][10]['EUR'] );
assert_has( 'DE renders the EUR price-book amount', $de_html, $de_book['active'] . ' EUR' );
assert_true( 'the four country prices are not interchangeable', $za_html !== $gb_html && $gb_html !== $us_html && $us_html !== $de_html );

override_visitor_country( 'ZA' );
$amount_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'amount' ) );
assert_has( 'amount format prints the number', $amount_html, '850.00' );
assert_lacks( 'amount format omits the currency code', $amount_html, 'ZAR' );
$symbol_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'symbol' ) );
assert_has( 'symbol format uses wc_price currency and amount', $symbol_html, 'ZAR 850.00' );
$wrapped = RWGCM_Product_Price::render(
	array(
		'product_id' => array( 'value' => 10 ),
		'format'     => array( 'value' => 'code' ),
	)
);
assert_has( 'atomic value wrappers still render ZAR', $wrapped, '850.00 ZAR' );

$GLOBALS['rw_options']['woocommerce_tax_display_shop'] = 'incl';
override_visitor_country( 'GB' );
$tax_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
assert_has( 'inclusive tax display follows the shop setting', $tax_html, '46.80 GBP' );
$GLOBALS['rw_options']['woocommerce_tax_display_shop'] = 'excl';
$excl_html = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
assert_has( 'exclusive tax display keeps the book amount', $excl_html, '39.00 GBP' );

override_visitor_country( 'DE' );
$sale_html = RWGCM_Product_Price::render( array( 'product_id' => 11, 'format' => 'code' ) );
assert_has( 'sale price is the active EUR amount', $sale_html, '40.00 EUR' );
assert_has( 'regular price is struck through', $sale_html, '<del>' );
assert_has( 'struck-through regular amount is kept', $sale_html, '45.00 EUR' );
assert_has( 'screen reader announces the original price', $sale_html, 'Original price was: 45.00 EUR' );
$no_sale = RWGCM_Product_Price::render( array( 'product_id' => 11, 'format' => 'code', 'sale' => 'no' ) );
assert_lacks( 'sale=no hides the struck-through price', $no_sale, '<del>' );
assert_has( 'sale=no still shows the active price', $no_sale, '40.00 EUR' );

override_visitor_country( 'US' );
$from_html = RWGCM_Product_Price::render( array( 'product_id' => 20, 'format' => 'code' ) );
assert_has( 'variable product shows From for the lowest variation', $from_html, 'rwgcm-price__from' );
assert_has( 'From price is the lowest USD variation', $from_html, '49.00 USD' );
assert_lacks( 'out-of-stock variation is not the From price', $from_html, '10.00' );
assert_lacks( 'higher variation is not shown beside From', $from_html, '60.00' );
$no_from = RWGCM_Product_Price::render( array( 'product_id' => 20, 'format' => 'code', 'from' => 'no' ) );
assert_lacks( 'from=no omits the prefix', $no_from, 'rwgcm-price__from' );
assert_has( 'from=no still shows the lowest price', $no_from, '49.00 USD' );
$one = RWGCM_Product_Price::render( array( 'variation_id' => 21, 'product_id' => 20, 'format' => 'code' ) );
assert_has( 'variation_id renders that variation', $one, '49.00 USD' );
assert_lacks( 'a chosen variation has no From prefix', $one, 'rwgcm-price__from' );

override_visitor_country( 'GB' );
$period_html = RWGCM_Product_Price::render( array( 'product_id' => 13, 'format' => 'code' ) );
assert_has( 'subscription period is appended', $period_html, '>/year<' );
assert_has( 'subscription price is the GBP book amount', $period_html, '39.00 GBP' );
$GLOBALS['rw_products'][13]->meta['_subscription_period_interval'] = 2;
$interval_html = RWGCM_Product_Price::render( array( 'product_id' => 13, 'format' => 'code' ) );
assert_has( 'interval greater than one is spelled out', $interval_html, '>/2 years<' );
$custom_period = RWGCM_Product_Price::render( array( 'product_id' => 13, 'format' => 'code', 'period' => '/quarter' ) );
assert_has( 'custom period replaces the subscription period', $custom_period, '>/quarter<' );
assert_lacks( 'custom period does not also show the subscription period', $custom_period, '/2 years' );

$missing = RWGCM_Product_Price::render( array( 'product_id' => 999 ) );
assert_has( 'missing product uses the default fallback', $missing, 'Unavailable' );
assert_has( 'fallback is marked unavailable', $missing, 'rwgcm-price--unavailable' );
$soon = RWGCM_Product_Price::render( array( 'product_id' => 999, 'fallback' => 'Coming soon' ) );
assert_has( 'fallback text is configurable', $soon, 'Coming soon' );
assert_lacks( 'custom fallback replaces the default', $soon, 'Unavailable' );
$stock = RWGCM_Product_Price::render( array( 'product_id' => 30, 'format' => 'code' ) );
assert_has( 'out of stock uses the fallback', $stock, 'Unavailable' );
assert_lacks( 'out of stock does not print a price', $stock, '39.00' );
$hidden = RWGCM_Product_Price::render( array( 'product_id' => 31, 'fallback' => 'Coming soon' ) );
assert_has( 'not purchasable uses the fallback', $hidden, 'Coming soon' );
$xss = RWGCM_Product_Price::render( array( 'product_id' => 999, 'fallback' => '<script>alert(1)</script>' ) );
assert_lacks( 'fallback markup is not executed', $xss, '<script' );
override_visitor_country( 'ZA' );
$xss_period = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'amount', 'period' => 'per <img src=x onerror=alert(1)> year' ) );
assert_lacks( 'period markup is not executed', $xss_period, '<img' );
assert_has( 'period text is kept after the tag is stripped', $xss_period, 'per year' );

$colored = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code', 'color' => 'primary', 'class' => 'extra-price <bad>' ) );
assert_has( 'primary colour preset adds the BEM class', $colored, 'rwgcm-price--color-primary' );
assert_has( 'extra class is kept', $colored, 'extra-price' );
assert_lacks( 'unsafe class characters are stripped', $colored, '<bad>' );
$bad_color = RWGCM_Product_Price::render( array( 'product_id' => 10, 'color' => 'red;}' ) );
assert_lacks( 'unknown colour presets are ignored', $bad_color, 'rwgcm-price--color-red' );

$GLOBALS['product'] = $GLOBALS['rw_products'][10];
override_visitor_country( 'ZA' );
$current = RWGCM_Product_Price::shortcode( array( 'format' => 'code' ) );
assert_has( 'shortcode without an ID uses the current product', $current, '850.00 ZAR' );
unset( $GLOBALS['product'] );
$none = RWGCM_Product_Price::render( array() );
assert_has( 'no current product uses the fallback', $none, 'Unavailable' );

$block = RWGCM_Product_Price_Block::render_block( array( 'product_id' => 10, 'format' => 'code' ) );
assert_true( 'block render uses the shared renderer', $block === RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) ) );

$fixed = $GLOBALS['rw_options']['rwgcm_pricing'];
$fixed['rules'][0]['type']  = 'fixed_line';
$fixed['rules'][0]['value'] = -15;
$GLOBALS['rw_options']['rwgcm_pricing'] = $fixed;
override_visitor_country( 'ZA' );
$fixed_preview = RWGCM_Simulator::pricing_preview( 'ZA', array(), 1000 );
$fixed_amount  = number_format( (float) $fixed_preview['adjusted'], 2, '.', '' );
$fixed_html    = RWGCM_Product_Price::render( array( 'product_id' => 10, 'format' => 'code' ) );
assert_true( 'simulator prices the ZA fixed_line rule at 985.00', '985.00' === $fixed_amount );
assert_has( 'ZA fixed_line rule matches the simulator', $fixed_html, $fixed_amount . ' ZAR' );
$GLOBALS['rw_options']['rwgcm_pricing']['rules'][0]['type']  = 'percent';
$GLOBALS['rw_options']['rwgcm_pricing']['rules'][0]['value'] = -15;

// GBP store: a fixed ZAR book wins, and the percent rule does not stack on it.
$GLOBALS['rw_options']['woocommerce_currency'] = 'GBP';
$GLOBALS['rw_products'][40]                    = new RW_Product( 40, '100' );
$GLOBALS['rw_books'][40]                       = RWGCM_Price_Book::sanitize_book(
	array(
		'ZAR' => array( 'regular' => '849' ),
		'USD' => array( 'regular' => '49' ),
		'EUR' => array( 'regular' => '45' ),
	)
);
override_visitor_country( 'GB' );
$gbp_store_gb = RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) );
assert_has( 'GBP store, GB visitor renders the catalog price', $gbp_store_gb, '100.00 GBP' );
override_visitor_country( 'ZA' );
$gbp_store_za = RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) );
assert_has( 'GBP store, ZA visitor renders the ZAR book price', $gbp_store_za, '849.00 ZAR' );
assert_lacks( 'the percent rule does not stack on a fixed ZAR book price', $gbp_store_za, '721.65' );

// No ZAR book: the cart falls back to GBP and applies the ZA rule. Catalog display must match.
$GLOBALS['rw_products'][41] = new RW_Product( 41, '100' );
$za_payable                 = RWGCM_Simulator::pricing_preview( 'ZA', array(), 100 );
$za_payable_amount          = number_format( (float) $za_payable['adjusted'], 2, '.', '' );
assert_true( 'simulator prices the GBP-store ZA rule at 85.00', '85.00' === $za_payable_amount );
assert_true(
	'cart currency falls back to GBP when the product has no ZAR price',
	'GBP' === RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', array( false ) )
);
$catalog_za = RWGCM_Catalog_Price::filter_product_price( '100', $GLOBALS['rw_products'][41] );
assert_true( 'catalog price applies the ZA -15% rule for a GBP charge', '85.00' === (string) $catalog_za );
$za_unpriced = RWGCM_Product_Price::render( array( 'product_id' => 41, 'format' => 'code' ) );
assert_has( 'GBP-store ZA element shows the discounted price the cart charges', $za_unpriced, $za_payable_amount . ' GBP' );
assert_lacks( 'GBP-store ZA element does not keep the undiscounted price', $za_unpriced, '100.00' );
$za_html_price = RWGCM_Price_Book_Apply::filter_price_html( 'stale', $GLOBALS['rw_products'][41] );
assert_has( 'catalog price HTML uses the discounted GBP amount', $za_html_price, 'GBP 85.00' );
override_visitor_country( 'US' );
assert_has( 'GBP store, US visitor renders USD', RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) ), '49.00 USD' );
override_visitor_country( 'DE' );
assert_has( 'GBP store, DE visitor renders EUR', RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) ), '45.00 EUR' );

$saved_uri = $_SERVER['REQUEST_URI'];
$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/rwgcm/product-price';
$GLOBALS['rw_can_preview'] = true;
$_SERVER['HTTP_REFERER'] = 'https://example.test/wp-admin/post.php?post=40&action=edit&rwgc_preview_country=ZA';
override_visitor_country( 'GB' );
$editor_book = RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) );
assert_has( 'Gutenberg preview uses the simulated ZA country for a ZAR book', $editor_book, '849.00 ZAR' );
unset( $_SERVER['HTTP_REFERER'] );
$editor_base = RWGCM_Product_Price::render( array( 'product_id' => 40, 'format' => 'code' ) );
assert_has( 'editor preview without a simulated country stays on GBP', $editor_base, '100.00 GBP' );
assert_lacks( 'editor preview without a simulated country does not show the ZAR book', $editor_base, '849.00' );
$_GET['elementor-preview'] = '40';
$_GET['rwgc_preview_country'] = 'ZA';
$editor_rule = RWGCM_Product_Price::render( array( 'product_id' => 41, 'format' => 'code' ) );
assert_has( 'Elementor preview country applies the ZA rule on the GBP charge', $editor_rule, '85.00 GBP' );
unset( $_GET['elementor-preview'], $_GET['rwgc_preview_country'] );
$GLOBALS['rw_can_preview'] = false;
$_SERVER['REQUEST_URI'] = $saved_uri;

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-weather-affinity.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-weather-strip.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-weather-products-display.php';
$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/block-renderer/rwgcm/weather-products';
$strip_editor = RWGCM_Weather_Strip::render( array( 'fallback' => 'hide' ) );
$products_editor = RWGCM_Weather_Products_Display::render( array() );
assert_has( 'weather strip shows an editor placeholder when weather is missing', $strip_editor, 'rwgcm-weather-strip--editor-placeholder' );
assert_has( 'weather products show an editor placeholder when weather is missing', $products_editor, 'rwgcm-weather-products--editor-placeholder' );
$_SERVER['REQUEST_URI'] = $saved_uri;
$strip_front = RWGCM_Weather_Strip::render( array( 'fallback' => 'hide' ) );
assert_true( 'weather strip stays empty on the storefront when weather is missing', '' === $strip_front );

assert_true( 'rendering does not mark the page uncacheable', ! defined( 'DONOTCACHEPAGE' ) );
$notes = RWGCM_Product_Price::cache_compatibility_notes();
assert_true(
	'cache notes follow Geo Core country vary',
	false !== strpos( $notes, 'rwgc_get_visitor_country' ) && false !== strpos( $notes, 'rwgc_cc' ) && false !== strpos( $notes, 'DONOTCACHEPAGE' )
);
$renderer = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-product-price.php' );
assert_true(
	'renderer does not read the vary cookie or send nocache headers',
	is_string( $renderer )
		&& false === strpos( $renderer, '$_COOKIE' )
		&& false === strpos( $renderer, 'nocache_headers' )
		&& false === strpos( $renderer, "define( 'DONOTCACHEPAGE'" )
		&& false !== strpos( $renderer, "add_shortcode( self::SHORTCODE" )
		&& false !== strpos( $renderer, 'WC_Subscriptions_Product' )
		&& false !== strpos( $renderer, 'wc_get_price_to_display' )
);
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/product-price.css' );
assert_true(
	'price CSS inherits type and uses site colour variables',
	is_string( $css )
		&& false === strpos( $css, 'font-family' )
		&& false === strpos( $css, 'font-size' )
		&& false !== strpos( $css, '--e-global-color-primary' )
		&& false !== strpos( $css, '--wp--preset--color--primary' )
		&& false !== strpos( $css, '.rwgcm-price__amount' )
		&& false !== strpos( $css, '.rwgcm-price__regular' )
);
$block_json = file_get_contents( dirname( __DIR__ ) . '/blocks/product-price/block.json' );
assert_true( 'block name is rwgcm/product-price', is_string( $block_json ) && false !== strpos( $block_json, '"name": "rwgcm/product-price"' ) );
$classic = file_get_contents( dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgcm-elementor-product-price.php' );
$atomic  = file_get_contents( dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgcm-elementor-atomic-product-price.php' );
assert_true( 'classic element name is rwgcm-product-price', is_string( $classic ) && false !== strpos( $classic, "return 'rwgcm-product-price';" ) && false !== strpos( $classic, 'RWGCM_Product_Price::render' ) );
assert_true( 'atomic element name is rwgcm-product-price-atomic', is_string( $atomic ) && false !== strpos( $atomic, "return 'rwgcm-product-price-atomic';" ) && false !== strpos( $atomic, 'RWGCM_Product_Price::render' ) );
$boot = file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgcm-plugin.php' );
assert_true( 'plugin boots the shared price renderer and block', is_string( $boot ) && false !== strpos( $boot, 'RWGCM_Product_Price::init' ) && false !== strpos( $boot, 'RWGCM_Product_Price_Block::init' ) );
$plugin = file_get_contents( dirname( __DIR__ ) . '/reactwoo-geo-commerce.php' );
$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
assert_true(
	'plugin header and constant are 0.8.0',
	is_string( $plugin ) && false !== strpos( $plugin, 'Version: 0.8.0' ) && false !== strpos( $plugin, "define( 'RWGCM_VERSION', '0.8.0' )" )
);
assert_true( 'readme stable tag is 0.8.0', is_string( $readme ) && false !== strpos( $readme, 'Stable tag: 0.8.0' ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
