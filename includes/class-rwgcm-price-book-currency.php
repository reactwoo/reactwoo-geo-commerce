<?php
/**
 * Choose the active WooCommerce currency for a price book.
 *
 * Revolut Gateway for WooCommerce (4.22.x) creates the checkout payment from
 * get_woocommerce_currency() and the cart total, then updates that payment
 * from the WooCommerce order currency and holds the order if they differ.
 * Filtering woocommerce_currency — and setting the same code on the order —
 * is what makes Revolut charge the price-book currency. See
 * RWGCM_Price_Book_Currency::revolut_constraint_notes().
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime currency resolution.
 */
class RWGCM_Price_Book_Currency {

	const SESSION_BILLING = 'rwgcm_billing_country';

	/**
	 * Subscription currency while a scheduled renewal is running.
	 *
	 * @var string
	 */
	private static $renewal_currency = '';

	/**
	 * True while a renewal cart or scheduled payment is in progress.
	 *
	 * @var bool
	 */
	private static $in_renewal = false;

	/**
	 * Recursion guard for the currency filter.
	 *
	 * @var bool
	 */
	private static $resolving = false;

	/**
	 * True while this class is reading the cart, so price filters cannot load it again.
	 *
	 * @var bool
	 */
	private static $reading_cart = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_currency', array( __CLASS__, 'filter_currency' ), 20 );
		add_action( 'woocommerce_scheduled_subscription_payment', array( __CLASS__, 'flag_scheduled_renewal' ), 0, 1 );
		add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'remember_checkout_billing' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( __CLASS__, 'remember_store_api_billing' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( __CLASS__, 'remember_store_api_billing' ), 10, 2 );
	}

	/**
	 * How Revolut reads currency, for operators and the release notes.
	 *
	 * Inspected against Revolut Gateway for WooCommerce 4.22.11:
	 * - Checkout widgets and wc_revolut_create_order() send get_woocommerce_currency()
	 *   plus the cart total. They do not read the order currency at that step.
	 * - After the Woo order exists, woocommerce_order_validator() patches the
	 *   Revolut order to $order->get_currency(), and verify_order_total() puts
	 *   the order on hold when the currencies or amounts diverge.
	 * - Card availability uses get_woocommerce_currency() against
	 *   $card_payments_currency_list, which includes ZAR, GBP, EUR, and USD.
	 * - Merchant-initiated subscription renewals use $renewal_order->get_currency().
	 * - The merchant account needs a Revolut currency account for each code.
	 *   This plugin does not convert.
	 *
	 * @return string
	 */
	public static function revolut_constraint_notes() {
		return 'Revolut checkout charges get_woocommerce_currency() and then requires the WooCommerce order currency to match. Renewals charge the renewal order currency. ZAR, GBP, EUR, and USD are in the card currency list; the Revolut merchant account must still have each currency account enabled.';
	}

	/**
	 * @param string $currency Store currency from the option.
	 * @return string
	 */
	public static function filter_currency( $currency ) {
		if ( self::$resolving ) {
			return $currency;
		}
		if ( self::skip_request() ) {
			return $currency;
		}
		self::$resolving = true;
		$resolved        = self::resolve_active_currency( (string) $currency );
		self::$resolving = false;
		return $resolved;
	}

	/**
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function flag_scheduled_renewal( $subscription_id ) {
		self::$in_renewal       = true;
		self::$renewal_currency = '';
		if ( function_exists( 'wcs_get_subscription' ) ) {
			$subscription = wcs_get_subscription( $subscription_id );
			if ( is_object( $subscription ) && method_exists( $subscription, 'get_currency' ) ) {
				self::$renewal_currency = RWGCM_Price_Book::normalize_currency( $subscription->get_currency() );
			}
		}
	}

	/**
	 * @param WC_Customer          $customer Customer.
	 * @param array<string, mixed> $data     Posted checkout data.
	 * @return void
	 */
	public static function remember_checkout_billing( $customer, $data ) {
		unset( $customer );
		if ( is_array( $data ) && isset( $data['billing_country'] ) ) {
			self::remember_billing_country( $data['billing_country'] );
		}
	}

	/**
	 * @param WC_Customer    $customer Customer.
	 * @param WP_REST_Request $request  Store API request.
	 * @return void
	 */
	public static function remember_store_api_billing( $customer, $request ) {
		unset( $customer );
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
			return;
		}
		$billing = $request->get_param( 'billing_address' );
		if ( is_array( $billing ) && isset( $billing['country'] ) ) {
			self::remember_billing_country( $billing['country'] );
		}
	}

	/**
	 * @return bool
	 */
	public static function is_renewal_request() {
		if ( self::$in_renewal || '' !== self::$renewal_currency ) {
			return true;
		}
		if ( self::$resolving || self::$reading_cart ) {
			return false;
		}
		return '' !== self::renewal_currency_from_cart();
	}

	/**
	 * @return string
	 */
	public static function renewal_currency() {
		if ( '' !== self::$renewal_currency ) {
			return self::$renewal_currency;
		}
		return self::renewal_currency_from_cart();
	}

	/**
	 * @return string
	 */
	public static function base_currency() {
		$raw = function_exists( 'get_option' ) ? get_option( 'woocommerce_currency', '' ) : '';
		$code = RWGCM_Price_Book::normalize_currency( is_string( $raw ) ? $raw : '' );
		return '' !== $code ? $code : 'GBP';
	}

	/**
	 * @param int $product_id Product or variation ID.
	 * @return array<string, mixed>
	 */
	public static function get_book( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( $product_id, RWGCM_Price_Book::META_KEY, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param string $fallback Currency passed into the filter (store option).
	 * @return string
	 */
	private static function resolve_active_currency( $fallback ) {
		$pay_currency = self::pay_for_order_currency();
		if ( '' !== $pay_currency ) {
			return $pay_currency;
		}

		$renewal = self::renewal_currency();
		if ( '' !== $renewal ) {
			self::$in_renewal = true;
			return $renewal;
		}

		$base = self::base_currency();
		if ( '' === $base ) {
			$base = RWGCM_Price_Book::normalize_currency( $fallback );
		}
		$decision = RWGCM_Price_Book::resolve_context(
			self::explicit_billing_country(),
			self::geo_country(),
			$base
		);
		$preferred = $decision['currency'];
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgcm_price_book_active_currency', $preferred, $decision );
			$norm     = RWGCM_Price_Book::normalize_currency( $filtered );
			if ( '' !== $norm ) {
				$preferred = $norm;
			}
		}

		if ( ! self::cart_is_ready() ) {
			return $preferred;
		}
		$supports = self::cart_line_support( $preferred );
		return RWGCM_Price_Book::currency_for_lines( $preferred, $base, $supports );
	}

	/**
	 * @return bool
	 */
	private static function skip_request() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return true;
	}

	/**
	 * @return string
	 */
	private static function pay_for_order_currency() {
		$order_id = 0;
		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() && function_exists( 'get_query_var' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
		}
		if ( $order_id <= 0 && isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) && function_exists( 'wc_get_order_id_by_order_key' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key      = wc_clean( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_id = (int) wc_get_order_id_by_order_key( $key );
		}
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return '';
		}
		$order = wc_get_order( $order_id );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_currency' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_currency( $order->get_currency() );
	}

	/**
	 * Billing country the shopper has actually supplied. The store default
	 * copied onto a guest session is not treated as a decision, so geo IP can
	 * still choose the currency before checkout.
	 *
	 * @return string
	 */
	private static function explicit_billing_country() {
		$posted = self::posted_billing_country();
		if ( '' !== $posted ) {
			self::remember_billing_country( $posted );
			return $posted;
		}
		if ( function_exists( 'WC' ) && WC() && WC()->session && method_exists( WC()->session, 'get' ) ) {
			$saved = WC()->session->get( self::SESSION_BILLING );
			$saved = RWGCM_Price_Book::normalize_country( is_string( $saved ) ? $saved : '' );
			if ( '' !== $saved ) {
				return $saved;
			}
		}
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id > 0 && function_exists( 'get_user_meta' ) ) {
			$profile = get_user_meta( $user_id, 'billing_country', true );
			$profile = RWGCM_Price_Book::normalize_country( is_string( $profile ) ? $profile : '' );
			if ( '' !== $profile ) {
				return $profile;
			}
		}
		$at_checkout = ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) )
			|| ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT );
		if ( $at_checkout ) {
			return self::customer_billing_country();
		}
		return '';
	}

	/**
	 * @param mixed $country Country code.
	 * @return void
	 */
	private static function remember_billing_country( $country ) {
		$country = RWGCM_Price_Book::normalize_country( $country );
		if ( '' === $country || ! function_exists( 'WC' ) ) {
			return;
		}
		$wc = WC();
		if ( ! $wc || ! $wc->session || ! method_exists( $wc->session, 'set' ) ) {
			return;
		}
		$wc->session->set( self::SESSION_BILLING, $country );
	}

	/**
	 * @return string
	 */
	private static function posted_billing_country() {
		if ( isset( $_POST['billing_country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return RWGCM_Price_Book::normalize_country( wp_unslash( $_POST['billing_country'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$parsed = array();
			parse_str( wp_unslash( $_POST['post_data'] ), $parsed ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( isset( $parsed['billing_country'] ) ) {
				return RWGCM_Price_Book::normalize_country( $parsed['billing_country'] );
			}
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function customer_billing_country() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$wc = WC();
		if ( ! $wc || ! $wc->customer || ! method_exists( $wc->customer, 'get_billing_country' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_country( $wc->customer->get_billing_country() );
	}

	/**
	 * Geo Core visitor country. Not used once a billing country is known.
	 *
	 * @return string
	 */
	private static function geo_country() {
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			return RWGCM_Price_Book::normalize_country( rwgc_get_visitor_country() );
		}
		return '';
	}

	/**
	 * @return string
	 */
	private static function renewal_currency_from_cart() {
		if ( self::$reading_cart ) {
			return '';
		}
		if ( ! function_exists( 'wcs_cart_contains_renewal' ) || ! function_exists( 'wcs_get_subscription' ) ) {
			return '';
		}
		if ( ! self::cart_is_ready() ) {
			return '';
		}
		self::$reading_cart = true;
		try {
			$item = wcs_cart_contains_renewal();
		} finally {
			self::$reading_cart = false;
		}
		if ( ! is_array( $item ) ) {
			return '';
		}
		$subscription_id = 0;
		if ( isset( $item['subscription_renewal']['subscription_id'] ) ) {
			$subscription_id = (int) $item['subscription_renewal']['subscription_id'];
		}
		if ( $subscription_id <= 0 ) {
			return '';
		}
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_currency' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_currency( $subscription->get_currency() );
	}

	/**
	 * @return bool
	 */
	private static function cart_is_ready() {
		if ( function_exists( 'did_action' ) && ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		$wc = WC();
		return $wc && $wc->cart && method_exists( $wc->cart, 'get_cart' );
	}

	/**
	 * @param string $currency Preferred currency.
	 * @return array<int, bool>
	 */
	private static function cart_line_support( $currency ) {
		$supports = array();
		if ( self::$reading_cart || ! self::cart_is_ready() ) {
			return $supports;
		}
		self::$reading_cart = true;
		try {
			$items = WC()->cart->get_cart();
		} finally {
			self::$reading_cart = false;
		}
		if ( ! is_array( $items ) ) {
			return $supports;
		}
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['bundled_by'] ) || ! empty( $item['composite_parent'] ) || ! empty( $item['composite_item'] ) ) {
				continue;
			}
			if ( empty( $item['data'] ) || ! is_object( $item['data'] ) || ! method_exists( $item['data'], 'get_id' ) ) {
				continue;
			}
			$book       = self::get_book( $item['data']->get_id() );
			$supports[] = RWGCM_Price_Book::has_price( $book, $currency );
		}
		return $supports;
	}
}
