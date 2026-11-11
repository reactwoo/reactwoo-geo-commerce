<?php
/**
 * Order geo attribution on classic checkout and the Checkout block (Store API).
 *
 * @package ReactWoo_Geo_Commerce
 */

/**
 * Classic and Store API checkout write visitor country meta once.
 */
class OrderGeoAttributionTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['rw_actions']            = array();
		$GLOBALS['rw_filters']            = array();
		$GLOBALS['rw_orders']             = array();
		$GLOBALS['rw_country']            = 'za';
		$GLOBALS['rw_visitor']            = array( 'country_code' => 'us' );
		$GLOBALS['rw_geo_throw']          = false;
		$GLOBALS['rw_postmeta_calls']     = 0;
		$GLOBALS['rw_wc_get_order_calls'] = 0;
		$GLOBALS['rw_did']                = array();
		$_POST                            = array();
		$_REQUEST                         = array();
		$_COOKIE                          = array();
		$_SERVER['REQUEST_URI']           = '/wp-json/wc/store/v1/checkout';
		$_SERVER['HTTP_CF_IPCOUNTRY']     = 'DE';
		RWGCM_Order_Geo::init();
	}

	/**
	 * @param int $id Order ID.
	 * @return WC_Order
	 */
	private function order( $id ) {
		$order                      = new WC_Order( $id );
		$GLOBALS['rw_orders'][ $id ] = $order;
		return $order;
	}

	/**
	 * @return void
	 */
	private function attribution_filter() {
		add_filter(
			'rwgcm_checkout_order_meta',
			static function ( $meta ) {
				$meta['_rwgcm_utm_source_ft'] = 'newsletter';
				$meta['billing_country']      = 'US';
				return $meta;
			}
		);
	}

	/**
	 * @return int
	 */
	private function attributed_count() {
		return isset( $GLOBALS['rw_did']['rwgcm_order_attributed'] ) ? count( $GLOBALS['rw_did']['rwgcm_order_attributed'] ) : 0;
	}

	/**
	 * Classic checkout stores the Geo Core country once.
	 *
	 * @return void
	 */
	public function test_classic_checkout_writes_once() {
		$this->attribution_filter();
		$_POST['billing_country'] = 'US';
		$order                    = $this->order( 10 );

		do_action( 'woocommerce_checkout_order_processed', 10, array( 'billing_country' => 'US' ), $order );
		$captured = $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true );
		do_action( 'woocommerce_checkout_order_processed', 10, array( 'billing_country' => 'FR' ), $order );

		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( 'newsletter', $order->get_meta( '_rwgcm_utm_source_ft', true ) );
		$this->assertSame( '', $order->get_meta( 'billing_country', true ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', (string) $captured );
		$this->assertSame( $captured, $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( 1, $this->attributed_count() );
		$this->assertSame( 0, $GLOBALS['rw_postmeta_calls'] );
		$this->assertGreaterThan( 0, $GLOBALS['rw_wc_get_order_calls'] );
	}

	/**
	 * Store API checkout uses Geo Core, not classic posted fields, and writes once.
	 *
	 * @return void
	 */
	public function test_store_api_checkout_writes_once() {
		$this->attribution_filter();
		$_POST['billing_country']    = 'US';
		$_POST['shipping_country']   = 'FR';
		$_REQUEST['billing_country'] = 'US';
		$_COOKIE['rwgc_cc']          = 'GB';
		$order                       = $this->order( 11 );
		$lookups                     = $GLOBALS['rw_wc_get_order_calls'];

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		$captured = $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true );
		do_action( 'woocommerce_store_api_checkout_order_processed', $order );

		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( 'newsletter', $order->get_meta( '_rwgcm_utm_source_ft', true ) );
		$this->assertSame( $captured, $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( 1, $this->attributed_count() );
		$this->assertSame( $lookups, $GLOBALS['rw_wc_get_order_calls'] );
		$this->assertSame( 0, $GLOBALS['rw_postmeta_calls'] );
	}

	/**
	 * An empty Geo Core country falls back to visitor data, still ignoring checkout POST.
	 *
	 * @return void
	 */
	public function test_store_api_uses_visitor_data_when_country_function_is_empty() {
		$GLOBALS['rw_country']     = '';
		$GLOBALS['rw_visitor']     = array( 'country_code' => 'ie' );
		$_POST['billing_country']  = 'US';
		$_COOKIE['rwgc_cc']        = 'GB';
		$order                     = $this->order( 12 );

		do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );

		$this->assertSame( 'IE', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( 1, $order->saves );
	}

	/**
	 * Both Store API hooks and the classic hook together store the meta once.
	 *
	 * @return void
	 */
	public function test_both_checkout_paths_write_once() {
		$order = $this->order( 77 );

		do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );
		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( 1, $order->saves );
		$captured = $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true );

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		do_action( 'woocommerce_checkout_order_processed', 77, array( 'billing_country' => 'US' ), $order );
		do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );

		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( $captured, $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( 1, $this->attributed_count() );
		$this->assertSame( 0, $GLOBALS['rw_postmeta_calls'] );
	}

	/**
	 * A stored country, capture time, and attribution value are not replaced.
	 *
	 * @return void
	 */
	public function test_existing_meta_is_not_overwritten() {
		$order = $this->order( 20 );
		$order->update_meta_data( RWGCM_Order_Geo::META_COUNTRY, 'GB' );
		$order->update_meta_data( RWGCM_Order_Geo::META_CAPTURED, '2020-01-01T00:00:00+00:00' );
		$order->update_meta_data( '_rwgcm_utm_source_ft', 'newsletter' );
		$order->saves = 0;
		$GLOBALS['rw_country'] = 'ZA';
		$GLOBALS['rw_geo_throw'] = true;
		add_filter(
			'rwgcm_checkout_order_meta',
			static function () {
				throw new RuntimeException( 'attribution filter ran on an order that was already captured' );
			}
		);

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		do_action( 'woocommerce_checkout_order_processed', 20 );

		$this->assertSame( 'GB', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( '2020-01-01T00:00:00+00:00', $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 'newsletter', $order->get_meta( '_rwgcm_utm_source_ft', true ) );
		$this->assertSame( 0, $order->saves );
		$this->assertSame( 0, $this->attributed_count() );
	}

	/**
	 * An empty country is filled. A capture time and attribution value already stored stay put.
	 *
	 * @return void
	 */
	public function test_empty_values_are_filled_without_overwriting_stored_meta() {
		$order = $this->order( 21 );
		$order->update_meta_data( RWGCM_Order_Geo::META_COUNTRY, '' );
		$order->update_meta_data( RWGCM_Order_Geo::META_CAPTURED, '2020-01-01T00:00:00+00:00' );
		$order->update_meta_data( '_rwgcm_utm_source_ft', 'newsletter' );
		$order->saves = 0;
		add_filter(
			'rwgcm_checkout_order_meta',
			static function ( $meta ) {
				$meta['_rwgcm_utm_source_ft'] = 'ads';
				$meta['_rwgcm_utm_source_lt'] = 'ads';
				return $meta;
			}
		);

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		do_action( 'woocommerce_checkout_order_processed', 21 );

		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( '2020-01-01T00:00:00+00:00', $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 'newsletter', $order->get_meta( '_rwgcm_utm_source_ft', true ) );
		$this->assertSame( 'ads', $order->get_meta( '_rwgcm_utm_source_lt', true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( 1, $this->attributed_count() );
	}

	/**
	 * Meta is written on the order object returned by the datastore, not post meta.
	 *
	 * @return void
	 */
	public function test_hpos_order_object_path() {
		$order   = $this->order( 501 );
		$lookups = $GLOBALS['rw_wc_get_order_calls'];

		do_action( 'woocommerce_store_api_checkout_order_processed', $order );

		$this->assertInstanceOf( WC_Order::class, $order );
		$this->assertSame( 'ZA', $order->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertNotSame( '', $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( $lookups, $GLOBALS['rw_wc_get_order_calls'] );
		$this->assertSame( 0, $GLOBALS['rw_postmeta_calls'] );

		$loaded = wc_get_order( 501 );
		$this->assertSame( $order, $loaded );
		do_action( 'woocommerce_checkout_order_processed', 501 );

		$this->assertSame( 'ZA', $loaded->get_meta( RWGCM_Order_Geo::META_COUNTRY, true ) );
		$this->assertSame( 1, $order->saves );
		$this->assertSame( 0, $GLOBALS['rw_postmeta_calls'] );
		$this->assertNotSame( '', $order->get_meta( RWGCM_Order_Geo::META_CAPTURED, true ) );
	}
}
