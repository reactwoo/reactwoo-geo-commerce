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
		add_action( 'litespeed_init', array( __CLASS__, 'register_litespeed_vary' ), 20 );
		add_action( 'wp', array( __CLASS__, 'mark_transactional_pages_uncached' ) );
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
	 * How catalog currency stays aligned with Geo Core's page cache.
	 *
	 * @return string
	 */
	public static function cache_compatibility_notes() {
		return 'Catalog prices follow rwgc_get_visitor_country() from Geo Core. This plugin does not look up the visitor IP and does not read the rwgc_cc cookie. Geo Core varies full-page caches with the rwgc_cc cookie and the LiteSpeed vary group rwgc_cc_{ISO2}, both taken from the server-resolved country. A page cache that ignores that vary serves one country\'s prices to everyone. Cart and checkout are not cached; once a billing country is entered there, that country selects the currency.';
	}

	/**
	 * Geo Core visitor country. Never the rwgc_cc cookie and never a local IP lookup.
	 *
	 * @return string
	 */
	public static function visitor_country() {
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_country( rwgc_get_visitor_country() );
	}

	/**
	 * Repeat Geo Core's LiteSpeed country vary. Idempotent if Geo Core already added it.
	 *
	 * @return void
	 */
	public static function register_litespeed_vary() {
		if ( ! class_exists( 'RWGC_Cache_Compat', false ) || ! function_exists( 'do_action' ) ) {
			return;
		}
		$group = RWGC_Cache_Compat::country_vary_group( RWGC_Cache_Compat::resolve_country_vary_key() );
		if ( '' === $group ) {
			return;
		}
		do_action( 'litespeed_vary_add', $group );
	}

	/**
	 * Cart and checkout must not be served from a country-varied catalog cache.
	 *
	 * @return void
	 */
	public static function mark_transactional_pages_uncached() {
		$cart     = function_exists( 'is_cart' ) && is_cart();
		$checkout = function_exists( 'is_checkout' ) && is_checkout();
		if ( ! $cart && ! $checkout ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}

	/**
	 * Billing country the shopper has entered on cart or checkout.
	 * Saved account billing is used there too. Catalog HTML stays on the geo country.
	 *
	 * @return string
	 */
	public static function entered_billing_country() {
		$posted = self::posted_billing_country();
		if ( '' !== $posted ) {
			self::remember_billing_country( $posted );
		}
		$remembered = self::session_billing_country();
		if ( '' === $remembered && self::billing_context_active() ) {
			$remembered = self::account_billing_country();
		}
		$country = RWGCM_Price_Book::effective_billing_country( $posted, $remembered, self::billing_context_active() );
		if ( '' !== $country ) {
			return $country;
		}
		if ( self::checkout_form_active() ) {
			return self::customer_billing_country();
		}
		return '';
	}

	/**
	 * Subscription id from the change-payment-method request, or 0.
	 *
	 * @return int
	 */
	public static function change_payment_subscription_id() {
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['change_payment_method'] ) : $_GET['change_payment_method']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id  = function_exists( 'absint' ) ? absint( $raw ) : (int) $raw;
			if ( $id > 0 ) {
				return $id;
			}
		}
		if ( function_exists( 'get_query_var' ) ) {
			$id = function_exists( 'absint' ) ? absint( get_query_var( 'subscription-payment-method' ) ) : (int) get_query_var( 'subscription-payment-method' );
			if ( $id > 0 ) {
				return $id;
			}
		}
		return 0;
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

		$change_currency = self::change_payment_currency();
		if ( '' !== $change_currency ) {
			return $change_currency;
		}

		$base = self::base_currency();
		if ( '' === $base ) {
			$base = RWGCM_Price_Book::normalize_currency( $fallback );
		}
		$decision = RWGCM_Price_Book::resolve_context(
			self::entered_billing_country(),
			self::geo_country(),
			$base,
			null,
			null,
			RWGCM_Price_Book::configured_fallback_currency( $base )
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
	 * Saved account country. Used on cart and checkout only, never for catalog HTML.
	 *
	 * @return string
	 */
	private static function account_billing_country() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || ! function_exists( 'get_user_meta' ) ) {
			return '';
		}
		$profile = get_user_meta( $user_id, 'billing_country', true );
		return RWGCM_Price_Book::normalize_country( is_string( $profile ) ? $profile : '' );
	}

	/**
	 * @return string
	 */
	private static function session_billing_country() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$wc = WC();
		if ( ! $wc || ! $wc->session || ! method_exists( $wc->session, 'get' ) ) {
			return '';
		}
		$saved = $wc->session->get( self::SESSION_BILLING );
		return RWGCM_Price_Book::normalize_country( is_string( $saved ) ? $saved : '' );
	}

	/**
	 * Cart, checkout, checkout AJAX, and Store API cart or checkout.
	 *
	 * @return bool
	 */
	private static function billing_context_active() {
		if ( self::checkout_form_active() ) {
			return true;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		return self::is_store_api_cart_or_checkout( false );
	}

	/**
	 * Checkout form, including checkout AJAX and the Store API checkout route.
	 * The customer billing country is read only here, not on the catalog.
	 *
	 * @return bool
	 */
	private static function checkout_form_active() {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ) {
			return true;
		}
		if ( defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( self::change_payment_subscription_id() > 0 ) {
			return true;
		}
		return self::is_store_api_cart_or_checkout( true );
	}

	/**
	 * @param bool $checkout_only When true, a cart route does not count.
	 * @return bool
	 */
	private static function is_store_api_cart_or_checkout( $checkout_only ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false === strpos( $uri, '/wc/store/' ) ) {
			return false;
		}
		if ( false !== strpos( $uri, '/checkout' ) ) {
			return true;
		}
		if ( $checkout_only ) {
			return false;
		}
		return false !== strpos( $uri, '/cart' );
	}

	/**
	 * Changing the payment method keeps the subscription currency.
	 *
	 * @return string
	 */
	private static function change_payment_currency() {
		$id = self::change_payment_subscription_id();
		if ( $id <= 0 || ! function_exists( 'wcs_get_subscription' ) ) {
			return '';
		}
		$subscription = wcs_get_subscription( $id );
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_currency' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_currency( $subscription->get_currency() );
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
	 * Does not read $_COOKIE['rwgc_cc'].
	 *
	 * @return string
	 */
	private static function geo_country() {
		return self::visitor_country();
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
