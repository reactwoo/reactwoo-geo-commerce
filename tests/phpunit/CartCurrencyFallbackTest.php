<?php
/**
 * Cart currency stays in the store currency unless the visitor currency can price it.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rw_options'] ) && is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] ) ) {
			return $GLOBALS['rw_options'][ $key ];
		}
		if ( 'woocommerce_currency' === $key ) {
			return 'GBP';
		}
		if ( 'woocommerce_currency_pos' === $key ) {
			return 'left';
		}
		return $default;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int
	 */
	function did_action( $hook ) {
		unset( $hook );
		return 1;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	/**
	 * @return bool
	 */
	function is_checkout() {
		return ! empty( $GLOBALS['rw_is_checkout'] );
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	/**
	 * @return bool
	 */
	function is_cart() {
		return ! empty( $GLOBALS['rw_is_cart'] );
	}
}

if ( ! function_exists( 'is_order_received_page' ) ) {
	/**
	 * @return bool
	 */
	function is_order_received_page() {
		return false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
	/**
	 * @param string $currency Currency.
	 * @return string
	 */
	function get_woocommerce_currency_symbol( $currency = '' ) {
		$symbols = array(
			'GBP' => '£',
			'ZAR' => 'R',
			'EUR' => '€',
		);
		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : (string) $currency;
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	/**
	 * @param mixed                $amount Amount.
	 * @param array<string, mixed> $args   Args.
	 * @return string
	 */
	function wc_price( $amount, $args = array() ) {
		$currency = isset( $args['currency'] ) ? (string) $args['currency'] : get_woocommerce_currency();
		return get_woocommerce_currency_symbol( $currency ) . $amount;
	}
}

/**
 * Catalog stand-in. Returns a serialized book when that currency has an amount.
 */
class RW_Currency_DB {

	/**
	 * @var string
	 */
	public $postmeta = 'wp_postmeta';

	/**
	 * @var string
	 */
	public $last_error = '';

	/**
	 * @var string
	 */
	private $like = '';

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * @param string $query    Query.
	 * @param string $meta_key Meta key.
	 * @param string $like     Like pattern.
	 * @return string
	 */
	public function prepare( $query, $meta_key = '', $like = '' ) {
		unset( $query, $meta_key );
		$this->like = (string) $like;
		return 'prepared';
	}

	/**
	 * @param string $query Query.
	 * @return string|null
	 */
	public function get_var( $query ) {
		unset( $query );
		if ( ! preg_match( '/s:\\d+:"([A-Z]{3})"/', $this->like, $matches ) ) {
			return null;
		}
		$currency = $matches[1];
		$catalog  = isset( $GLOBALS['rw_catalog'] ) && is_array( $GLOBALS['rw_catalog'] ) ? $GLOBALS['rw_catalog'] : array();
		foreach ( $catalog as $book ) {
			if ( is_array( $book ) && RWGCM_Price_Book::has_price( $book, $currency ) ) {
				return serialize( $book );
			}
		}
		return null;
	}
}

/**
 * Cart stand-in.
 */
class RW_Currency_Cart {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_cart() {
		return isset( $GLOBALS['rw_cart_items'] ) && is_array( $GLOBALS['rw_cart_items'] ) ? $GLOBALS['rw_cart_items'] : array();
	}

	/**
	 * @return bool
	 */
	public function needs_shipping() {
		return ! empty( $GLOBALS['rw_needs_shipping'] );
	}

	/**
	 * @return string
	 */
	public function get_cart_subtotal() {
		return get_woocommerce_currency_symbol( get_woocommerce_currency() ) . '0.00';
	}

	/**
	 * @return int
	 */
	public function get_cart_contents_count() {
		return count( $this->get_cart() );
	}
}

/**
 * Session stand-in.
 */
class RW_Currency_Session {

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		return isset( $GLOBALS['rw_session'][ $key ] ) ? $GLOBALS['rw_session'][ $key ] : null;
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$GLOBALS['rw_session'][ $key ] = $value;
	}
}

/**
 * Customer stand-in.
 */
class RW_Currency_Customer {

	/**
	 * @return string
	 */
	public function get_shipping_country() {
		return isset( $GLOBALS['rw_ship_country'] ) ? (string) $GLOBALS['rw_ship_country'] : '';
	}

	/**
	 * @return string
	 */
	public function get_shipping_state() {
		return '';
	}

	/**
	 * @return string
	 */
	public function get_shipping_postcode() {
		return '';
	}

	/**
	 * @return string
	 */
	public function get_shipping_city() {
		return '';
	}

	/**
	 * @return string
	 */
	public function get_shipping_address() {
		return '';
	}

	/**
	 * @return string
	 */
	public function get_shipping_address_2() {
		return '';
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * @return object
	 */
	function WC() {
		$wc           = new stdClass();
		$wc->cart     = empty( $GLOBALS['rw_no_cart'] ) ? new RW_Currency_Cart() : null;
		$wc->session  = new RW_Currency_Session();
		$wc->customer = new RW_Currency_Customer();
		return $wc;
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	/**
	 * @return string
	 */
	function get_woocommerce_currency() {
		return RWGCM_Price_Book_Currency::filter_currency( 'GBP' );
	}
}

/**
 * Simple product.
 */
class RW_Currency_Product {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $price;

	/**
	 * @param int    $id    Product ID.
	 * @param string $price Store price.
	 */
	public function __construct( $id, $price ) {
		$this->id    = (int) $id;
		$this->price = (string) $price;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param string $type Type.
	 * @return bool
	 */
	public function is_type( $type ) {
		return 'simple' === $type;
	}

	/**
	 * @param string $context Context.
	 * @return string
	 */
	public function get_price( $context = 'view' ) {
		if ( 'edit' === $context ) {
			return $this->price;
		}
		return (string) RWGCM_Price_Book_Apply::filter_price( $this->price, $this );
	}

	/**
	 * @param string $context Context.
	 * @return string
	 */
	public function get_regular_price( $context = 'edit' ) {
		unset( $context );
		return $this->price;
	}

	/**
	 * @param string $context Context.
	 * @return string
	 */
	public function get_sale_price( $context = 'edit' ) {
		unset( $context );
		return '';
	}
}

/**
 * Order line.
 */
class RW_Currency_Line {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @param int $id Product ID.
	 */
	public function __construct( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * @return int
	 */
	public function get_product_id() {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function get_variation_id() {
		return 0;
	}

	/**
	 * @param string $key Meta key.
	 * @return string
	 */
	public function get_meta( $key ) {
		unset( $key );
		return '';
	}
}

/**
 * REST response stand-in.
 */
class RW_Currency_Response {

	/**
	 * @var array<string, mixed>
	 */
	public $data;

	/**
	 * @param array<string, mixed> $data Payload.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * @param array<string, mixed> $data Payload.
	 * @return void
	 */
	public function set_data( $data ) {
		$this->data = $data;
	}
}

/**
 * REST request stand-in.
 */
class RW_Currency_Request {

	/**
	 * @var string
	 */
	public $route;

	/**
	 * @param string $route Route.
	 */
	public function __construct( $route ) {
		$this->route = (string) $route;
	}

	/**
	 * @return string
	 */
	public function get_route() {
		return $this->route;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-exchange.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-shipping-prices.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-price-book-apply.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-price-book-store-api.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-price-book-order.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgcm-gateway-availability.php';

/**
 * Empty, mixed, and fully priced carts, Store API, and both checkouts.
 */
class CartCurrencyFallbackTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rw_actions']         = array();
		$GLOBALS['rw_filters']         = array();
		$GLOBALS['rw_orders']          = array();
		$GLOBALS['rw_country']         = 'ZA';
		$GLOBALS['rw_visitor']         = array();
		$GLOBALS['rw_books']           = array();
		$GLOBALS['rw_catalog']         = array();
		$GLOBALS['rw_cart_items']      = array();
		$GLOBALS['rw_options']         = array();
		$GLOBALS['rw_session']         = array();
		$GLOBALS['rw_ship_country']    = '';
		$GLOBALS['rw_needs_shipping']  = false;
		$GLOBALS['rw_no_cart']         = false;
		$GLOBALS['rw_is_checkout']     = false;
		$GLOBALS['rw_is_cart']         = false;
		$GLOBALS['rw_postmeta_calls']  = 0;
		$GLOBALS['wpdb']               = new RW_Currency_DB();
		$_POST                         = array();
		$_GET                          = array();
		$_REQUEST                      = array();
		$_SERVER['REQUEST_URI']        = '/product/betty-boop/';
		RWGCM_Price_Book_Currency::reset_shipping_state_cache();
		while ( '' !== RWGCM_Price_Book_Currency::current_store_api_route() ) {
			RWGCM_Price_Book_Currency::clear_store_api_route();
		}
	}

	/**
	 * @param int                  $id   Product ID.
	 * @param array<string, mixed> $rows Currency rows.
	 * @return void
	 */
	private function book( $id, array $rows ) {
		$clean                        = RWGCM_Price_Book::sanitize_book( $rows );
		$GLOBALS['rw_books'][ $id ]   = $clean;
		$GLOBALS['rw_catalog'][ $id ] = $clean;
	}

	/**
	 * @param array<int, int>             $ids    Product IDs.
	 * @param array<int, string>          $prices Store prices keyed by ID.
	 * @return void
	 */
	private function set_cart( array $ids, array $prices = array() ) {
		$GLOBALS['rw_cart_items'] = array();
		foreach ( $ids as $id ) {
			$price = isset( $prices[ $id ] ) ? $prices[ $id ] : '10.00';
			$GLOBALS['rw_cart_items'][ 'k' . $id ] = array(
				'data'     => new RW_Currency_Product( $id, $price ),
				'quantity' => 1,
			);
		}
	}

	/**
	 * @return string
	 */
	private function currency() {
		RWGCM_Price_Book_Currency::reset_shipping_state_cache();
		return RWGCM_Price_Book_Currency::filter_currency( 'GBP' );
	}

	/**
	 * @param int $id Order ID.
	 * @return WC_Order
	 */
	private function order( $id ) {
		$order = new WC_Order( $id );
		$GLOBALS['rw_orders'][ $id ] = $order;
		$lines = array();
		foreach ( $this->cart_ids() as $product_id ) {
			$lines[] = new RW_Currency_Line( $product_id );
		}
		$order->set_line_items( $lines );
		return $order;
	}

	/**
	 * @return array<int, int>
	 */
	private function cart_ids() {
		$ids = array();
		foreach ( (array) $GLOBALS['rw_cart_items'] as $item ) {
			if ( is_array( $item ) && isset( $item['data'] ) && is_object( $item['data'] ) ) {
				$ids[] = (int) $item['data']->get_id();
			}
		}
		return $ids;
	}

	/**
	 * @param string $route Route.
	 * @return array<string, mixed>
	 */
	private function store_cart( $route = '/wc/store/v1/cart' ) {
		$currency = $this->currency();
		$items    = array();
		$total    = 0;
		foreach ( (array) $GLOBALS['rw_cart_items'] as $key => $item ) {
			$product = $item['data'];
			$minor   = (string) (int) round( (float) $product->get_price() * 100 );
			$total  += (int) $minor;
			$items[] = array(
				'key'      => (string) $key,
				'id'       => $product->get_id(),
				'quantity' => 1,
				'prices'   => array(
					'price'           => $minor,
					'currency_code'   => $currency,
					'currency_symbol' => get_woocommerce_currency_symbol( $currency ),
				),
			);
		}
		$response = RWGCM_Price_Book_Store_Api::filter_rest_response(
			new RW_Currency_Response(
				array(
					'items'  => $items,
					'totals' => array(
						'total_price'     => (string) $total,
						'currency_code'   => $currency,
						'currency_symbol' => get_woocommerce_currency_symbol( $currency ),
					),
				)
			),
			null,
			new RW_Currency_Request( $route )
		);
		$data = $response->get_data();
		return is_array( $data ) ? $data : array();
	}

	/**
	 * A fresh ZA visit with no ZAR prices keeps the empty cart in GBP.
	 *
	 * @return void
	 */
	public function test_empty_cart_without_book_or_rate_uses_store_currency() {
		$this->set_cart( array() );

		$this->assertFalse( RWGCM_Price_Book_Currency::currency_is_priceable( 'ZAR' ) );
		$this->assertSame( 'GBP', $this->currency() );
		$cart = $this->store_cart();
		$this->assertSame( 'GBP', $cart['totals']['currency_code'] );
		$this->assertSame( '£', $cart['totals']['currency_symbol'] );
		$this->assertSame( '0', $cart['totals']['total_price'] );
	}

	/**
	 * The same request's product HTML uses the cart currency.
	 *
	 * @return void
	 */
	public function test_product_html_matches_empty_cart_currency() {
		$this->set_cart( array() );
		$product = new RW_Currency_Product( 11883, '23' );
		$html    = '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">£</span>23.00</span>';

		$this->assertSame( 'GBP', $this->currency() );
		$this->assertSame( $html, RWGCM_Price_Book_Apply::filter_price_html( $html, $product ) );
		$this->assertSame( '23', (string) RWGCM_Price_Book_Apply::filter_price( '23', $product ) );

		$fragments = RWGCM_Shipping_Prices::filter_cart_fragments(
			array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content"><p class="total">£0.00</p></div>',
			)
		);
		$this->assertStringContainsString( 'data-currency="GBP"', $fragments['span.rwgcm-mini-cart-currency'] );
		$this->assertStringNotContainsString( 'data-currency="ZAR"', $fragments['div.widget_shopping_cart_content'] );
	}

	/**
	 * No cart object yet (widget boot) still refuses an unpriceable currency.
	 *
	 * @return void
	 */
	public function test_cart_not_ready_uses_store_currency_when_unpriceable() {
		$GLOBALS['rw_no_cart'] = true;

		$this->assertSame( 'GBP', $this->currency() );
	}

	/**
	 * An empty cart keeps ZAR when some product actually has a ZAR amount.
	 *
	 * @return void
	 */
	public function test_empty_cart_with_book_entries_keeps_visitor_currency() {
		$this->book( 500, array( 'ZAR' => array( 'regular' => '499' ), 'GBP' => array( 'regular' => '23' ) ) );
		$this->set_cart( array() );

		$this->assertTrue( RWGCM_Price_Book_Currency::currency_is_priceable( 'ZAR' ) );
		$this->assertSame( 'ZAR', $this->currency() );
	}

	/**
	 * An enabled ZAR rate makes the empty cart priceable. Off does not.
	 *
	 * @return void
	 */
	public function test_empty_cart_follows_the_exchange_path() {
		$this->set_cart( array() );
		$GLOBALS['rw_options'][ RWGCM_Exchange::OPTION_SETTINGS ] = array(
			'mode'   => 'auto',
			'source' => 'manual',
		);
		$GLOBALS['rw_options'][ RWGCM_Exchange::OPTION_MANUAL ] = array(
			'base'  => 'GBP',
			'rates' => array( 'ZAR' => '23' ),
		);

		$this->assertTrue( RWGCM_Price_Book_Currency::currency_is_priceable( 'ZAR' ) );
		$this->assertSame( 'ZAR', $this->currency() );

		$GLOBALS['rw_options'][ RWGCM_Exchange::OPTION_SETTINGS ]['mode'] = 'off';
		$this->assertFalse( RWGCM_Price_Book_Currency::currency_is_priceable( 'ZAR' ) );
		$this->assertSame( 'GBP', $this->currency() );
	}

	/**
	 * One priced line and one unpriced line: the whole cart is GBP.
	 *
	 * @return void
	 */
	public function test_mixed_cart_falls_back_for_every_line() {
		$this->book( 500, array( 'ZAR' => array( 'regular' => '499' ) ) );
		$this->set_cart( array( 500, 11883 ), array( 500 => '23', 11883 => '10' ) );

		$this->assertSame( 'GBP', $this->currency() );
		$priced   = new RW_Currency_Product( 500, '23' );
		$unpriced = new RW_Currency_Product( 11883, '10' );
		$this->assertSame( '23', (string) RWGCM_Price_Book_Apply::filter_price( '23', $priced ) );
		$this->assertSame( '10', (string) RWGCM_Price_Book_Apply::filter_price( '10', $unpriced ) );

		$cart = $this->store_cart();
		$this->assertSame( 'GBP', $cart['totals']['currency_code'] );
		$this->assertSame( '3300', $cart['totals']['total_price'] );
		$this->assertSame( 'GBP', $cart['items'][0]['prices']['currency_code'] );
		$this->assertSame( 'GBP', $cart['items'][1]['prices']['currency_code'] );
		$this->assertSame( '2300', $cart['items'][0]['prices']['price'] );
		$this->assertSame( '1000', $cart['items'][1]['prices']['price'] );
	}

	/**
	 * A cart whose every line has a ZAR book stays in ZAR, including Store API totals.
	 *
	 * @return void
	 */
	public function test_fully_priced_cart_keeps_visitor_currency() {
		$this->book( 500, array( 'ZAR' => array( 'regular' => '499' ), 'GBP' => array( 'regular' => '23' ) ) );
		$this->book( 501, array( 'ZAR' => array( 'regular' => '199' ), 'GBP' => array( 'regular' => '9' ) ) );
		$this->set_cart( array( 500, 501 ), array( 500 => '23', 501 => '9' ) );

		$this->assertSame( 'ZAR', $this->currency() );
		$this->assertSame( '499.00', (string) RWGCM_Price_Book_Apply::filter_price( '23', new RW_Currency_Product( 500, '23' ) ) );
		$cart = $this->store_cart();
		$this->assertSame( 'ZAR', $cart['totals']['currency_code'] );
		$this->assertSame( 'R', $cart['totals']['currency_symbol'] );
		$this->assertSame( '69800', $cart['totals']['total_price'] );
		$this->assertSame( 'ZAR', $cart['items'][0]['prices']['currency_code'] );
		$this->assertSame( 'ZAR', $cart['items'][1]['prices']['currency_code'] );
	}

	/**
	 * France with an EUR book is unchanged: the cart, the price, and Store API stay EUR.
	 *
	 * @return void
	 */
	public function test_eur_book_cart_is_unchanged() {
		$GLOBALS['rw_country'] = 'FR';
		$this->book( 70, array( 'EUR' => array( 'regular' => '45' ), 'GBP' => array( 'regular' => '39' ) ) );
		$this->set_cart( array( 70 ), array( 70 => '39' ) );

		$this->assertSame( 'EUR', $this->currency() );
		$this->assertSame( '45.00', (string) RWGCM_Price_Book_Apply::filter_price( '39', new RW_Currency_Product( 70, '39' ) ) );
		$html = '<span class="woocommerce-Price-currencySymbol">€</span>45.00';
		$this->assertSame( $html, RWGCM_Price_Book_Apply::filter_price_html( $html, new RW_Currency_Product( 70, '39' ) ) );
		$cart = $this->store_cart();
		$this->assertSame( 'EUR', $cart['totals']['currency_code'] );
		$this->assertSame( '4500', $cart['totals']['total_price'] );
	}

	/**
	 * Classic checkout stores GBP for an unpriced ZA cart and still records ZA.
	 *
	 * @return void
	 */
	public function test_classic_checkout_order_currency_matches_cart_and_keeps_geo() {
		$this->set_cart( array( 11883 ), array( 11883 => '23' ) );
		$GLOBALS['rw_is_checkout']    = true;
		$_POST['billing_country']     = 'ZA';
		$_SERVER['REQUEST_URI']       = '/checkout/';
		$order                        = $this->order( 90 );
		$order->set_billing_country( 'ZA' );
		$GLOBALS['rw_actions']        = array();
		RWGCM_Price_Book_Order::init();
		RWGCM_Price_Book_Store_Api::init();
		RWGCM_Order_Geo::init();

		do_action( 'woocommerce_checkout_create_order', $order, array( 'billing_country' => 'ZA' ) );
		do_action( 'woocommerce_checkout_order_processed', 90, array( 'billing_country' => 'ZA' ), $order );

		$this->assertSame( 'GBP', $order->get_currency() );
		$this->assertSame( 'GBP', $order->get_meta( RWGCM_Price_Book_Order::META_CURRENCY, true ) );
		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertNotSame( '', $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( '23', (string) RWGCM_Price_Book_Apply::filter_price( '23', new RW_Currency_Product( 11883, '23' ) ) );
	}

	/**
	 * Checkout block stores GBP for an unpriced ZA cart and still records ZA.
	 *
	 * @return void
	 */
	public function test_block_checkout_order_currency_matches_cart_and_keeps_geo() {
		$this->set_cart( array( 11883 ), array( 11883 => '23' ) );
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$order                  = $this->order( 91 );
		$order->set_billing_country( 'ZA' );
		$order->set_currency( 'ZAR' );
		$GLOBALS['rw_actions'] = array();
		RWGCM_Price_Book_Order::init();
		RWGCM_Price_Book_Store_Api::init();
		RWGCM_Order_Geo::init();

		do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );

		$this->assertSame( 'GBP', $this->currency() );
		$this->assertSame( 'GBP', $order->get_currency() );
		$this->assertSame( 'GBP', $order->get_meta( RWGCM_Price_Book_Order::META_CURRENCY, true ) );
		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		// Price-book meta and geo attribution each save the Checkout block order.
		$this->assertSame( 2, $order->saves );
	}

	/**
	 * A fully priced EUR cart is stored as EUR on both checkouts.
	 *
	 * @return void
	 */
	public function test_eur_order_currency_on_classic_and_block_checkout() {
		$GLOBALS['rw_country']     = 'FR';
		$GLOBALS['rw_is_checkout'] = true;
		$_POST['billing_country']  = 'FR';
		$this->book( 70, array( 'EUR' => array( 'regular' => '45' ), 'GBP' => array( 'regular' => '39' ) ) );
		$this->set_cart( array( 70 ), array( 70 => '39' ) );
		$classic = $this->order( 92 );
		$classic->set_billing_country( 'FR' );
		RWGCM_Price_Book_Order::on_checkout_create_order( $classic, array( 'billing_country' => 'FR' ) );
		RWGCM_Order_Geo::on_checkout_processed( 92 );

		$this->assertSame( 'EUR', $classic->get_currency() );
		$this->assertSame( 'EUR', $classic->get_meta( RWGCM_Price_Book_Order::META_CURRENCY, true ) );
		$this->assertSame( 'FR', $classic->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );

		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$block                  = $this->order( 93 );
		$block->set_billing_country( 'FR' );
		$block->set_currency( 'GBP' );
		RWGCM_Price_Book_Order::on_store_api_checkout_update_order_meta( $block );
		RWGCM_Order_Geo::on_checkout_processed( $block );

		$this->assertSame( 'EUR', $block->get_currency() );
		$this->assertSame( 'EUR', $block->get_meta( RWGCM_Price_Book_Order::META_CURRENCY, true ) );
		$this->assertSame( 'FR', $block->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( '45.00', (string) RWGCM_Price_Book_Apply::filter_price( '39', new RW_Currency_Product( 70, '39' ) ) );
	}

	/**
	 * A ZAR order for a GBP-only line is still refused. The fallback order is not.
	 *
	 * @return void
	 */
	public function test_guard_matches_the_cart_currency() {
		$this->set_cart( array( 11883 ), array( 11883 => '23' ) );
		$bad = $this->order( 94 );
		$bad->set_currency( 'ZAR' );
		$threw = false;
		try {
			RWGCM_Price_Book_Store_Api::guard_order_currency( $bad );
		} catch ( Exception $e ) {
			$threw = true;
		}
		$this->assertTrue( $threw );

		$good = $this->order( 95 );
		$good->set_currency( $this->currency() );
		RWGCM_Price_Book_Store_Api::guard_order_currency( $good );
		$this->assertSame( 'GBP', $good->get_currency() );
	}

	/**
	 * rwgcm_currency_is_priceable overrides the empty-cart decision.
	 *
	 * @return void
	 */
	public function test_currency_is_priceable_filter() {
		$this->set_cart( array() );
		add_filter(
			'rwgcm_currency_is_priceable',
			static function () {
				return true;
			}
		);
		$this->assertSame( 'ZAR', $this->currency() );

		$GLOBALS['rw_filters']['rwgcm_currency_is_priceable'] = array(
			static function () {
				return false;
			},
		);
		$this->book( 70, array( 'EUR' => array( 'regular' => '45' ) ) );
		$GLOBALS['rw_country'] = 'FR';
		$this->assertSame( 'GBP', $this->currency() );
	}

	/**
	 * rwgcm_cart_currency_fallback replaces the resolved cart currency.
	 *
	 * @return void
	 */
	public function test_cart_currency_fallback_filter() {
		$this->book( 500, array( 'ZAR' => array( 'regular' => '499' ) ) );
		$this->set_cart( array( 500 ), array( 500 => '23' ) );
		$this->assertSame( 'ZAR', $this->currency() );

		add_filter(
			'rwgcm_cart_currency_fallback',
			static function () {
				return 'GBP';
			}
		);
		$this->assertSame( 'GBP', $this->currency() );

		$GLOBALS['rw_filters']['rwgcm_cart_currency_fallback'] = array(
			static function () {
				return 'ZAR';
			},
		);
		$this->set_cart( array() );
		$GLOBALS['rw_books']   = array();
		$GLOBALS['rw_catalog'] = array();
		$this->assertSame( 'ZAR', $this->currency() );
	}

	/**
	 * Country gateway rules do not follow the currency fallback.
	 *
	 * @return void
	 */
	public function test_gateway_country_rule_is_unchanged() {
		$this->set_cart( array( 11883 ), array( 11883 => '23' ) );
		$this->assertSame( 'GBP', $this->currency() );
		$gateways = array(
			'bacs'     => 'bacs',
			'paystack' => 'paystack',
		);
		$result = RWGCM_Gateway_Availability::apply_country_rule( $gateways, array( 'paystack' ), null );

		$this->assertSame( array( 'paystack' => 'paystack' ), $result['gateways'] );
		$this->assertFalse( $result['fallback'] );
	}
}
