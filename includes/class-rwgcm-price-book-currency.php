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
	 * Re-entrancy guard for checkout country defaults.
	 *
	 * @var bool
	 */
	private static $defaulting = false;

	/**
	 * True while this class is reading the cart, so price filters cannot load it again.
	 *
	 * @var bool
	 */
	private static $reading_cart = false;

	/**
	 * Nested product save / variation sync depth. Prices stay in the store currency.
	 *
	 * @var int
	 */
	private static $store_currency_writes = 0;

	/**
	 * Currency the cart would be in if shipping were priced in it ('' when
	 * shipping did not decide the last resolution).
	 *
	 * @var string
	 */
	private static $shipping_fallback = '';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_currency', array( __CLASS__, 'filter_currency' ), 20 );
		add_filter( 'default_checkout_billing_country', array( __CLASS__, 'filter_default_checkout_country' ), 20, 2 );
		add_filter( 'default_checkout_shipping_country', array( __CLASS__, 'filter_default_checkout_country' ), 20, 2 );
		add_filter( 'woocommerce_customer_default_location_array', array( __CLASS__, 'filter_default_location' ), 20 );
		add_action( 'woocommerce_scheduled_subscription_payment', array( __CLASS__, 'flag_scheduled_renewal' ), 0, 1 );
		add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'remember_checkout_billing' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( __CLASS__, 'remember_store_api_billing' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( __CLASS__, 'remember_store_api_billing' ), 10, 2 );
		// The shipping calculator updates the customer, then the cart page renders.
		// WooCommerce writes that session at shutdown, after the response is flushed,
		// so a fragment request started from the response still sees the previous
		// country. Persist it now, before any HTML is sent.
		add_action( 'woocommerce_calculated_shipping', array( __CLASS__, 'persist_customer_session' ), 99 );
		add_action( 'litespeed_init', array( __CLASS__, 'register_litespeed_vary' ), 20 );
		add_action( 'wp', array( __CLASS__, 'mark_transactional_pages_uncached' ) );
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'begin_store_currency_write' ), 0 );
		add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'end_store_currency_write' ), 999 );
		add_action( 'woocommerce_variable_product_sync', array( __CLASS__, 'begin_store_currency_write' ), 0 );
		add_action( 'woocommerce_variable_product_sync', array( __CLASS__, 'end_store_currency_write' ), 9999 );
		add_action( 'woocommerce_variable_product_sync_data', array( __CLASS__, 'begin_store_currency_write' ), 0 );
		add_action( 'woocommerce_variable_product_sync_data', array( __CLASS__, 'end_store_currency_write' ), 9999 );
	}

	/**
	 * Hold the store currency across a product save or variation sync.
	 *
	 * @return void
	 */
	public static function begin_store_currency_write() {
		++self::$store_currency_writes;
	}

	/**
	 * @return void
	 */
	public static function end_store_currency_write() {
		if ( self::$store_currency_writes > 0 ) {
			--self::$store_currency_writes;
		}
	}

	/**
	 * True while a product save or variation sync is holding the store currency.
	 *
	 * @return bool
	 */
	public static function is_product_sync() {
		return self::$store_currency_writes > 0;
	}

	/**
	 * REST (except the Store API storefront), cron, WP-CLI, wp-admin, and product
	 * sync must not switch currency or apply a price book. Store API cart and
	 * checkout keep the visitor currency.
	 *
	 * @param array<string, mixed>|null $flags Optional request flags for tests.
	 * @return bool
	 */
	public static function forces_store_currency( $flags = null ) {
		if ( null === $flags ) {
			$flags = self::request_flags();
		}
		if ( ! is_array( $flags ) ) {
			return false;
		}
		if ( ! empty( $flags['syncing'] ) || ! empty( $flags['cron'] ) || ! empty( $flags['cli'] ) ) {
			return true;
		}
		if ( self::is_editor_preview_request() ) {
			// No simulated country: keep the store currency. A preview country is applied below.
			return '' === self::editor_preview_country();
		}
		if ( ! empty( $flags['rest'] ) && empty( $flags['store_api'] ) ) {
			return true;
		}
		if ( ! empty( $flags['admin'] ) && empty( $flags['front_ajax'] ) ) {
			return true;
		}
		return false;
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
		$preview = self::editor_preview_country();
		if ( '' !== $preview ) {
			return $preview;
		}
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_country( rwgc_get_visitor_country() );
	}

	/**
	 * Elementor editor, its preview iframe, the atomic preview route, or a Gutenberg block preview.
	 *
	 * @return bool
	 */
	public static function is_editor_preview_request() {
		$action = isset( $_GET['action'] ) ? self::preview_scalar( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = function_exists( 'sanitize_key' ) ? sanitize_key( $action ) : strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $action ) );
		if ( isset( $_GET['elementor-preview'] ) || 'elementor' === $action ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $uri, 'block-renderer/rwgcm/' ) || false !== strpos( $uri, 'rwgcm/v1/elementor-preview' ) ) {
			return true;
		}
		if ( class_exists( '\Elementor\Plugin', false ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( is_object( $plugin ) && isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return true;
			}
			if ( is_object( $plugin ) && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Geo Core preview country for this editor request (`rwgc_preview_country` or the rule-tester payload).
	 * Empty when the editor has no simulated country, so the price stays in the store currency.
	 *
	 * @return string
	 */
	public static function editor_preview_country() {
		if ( ! self::is_editor_preview_request() || ! self::user_may_preview_country() ) {
			return '';
		}
		$key = class_exists( 'RWGC_Preview', false ) ? RWGC_Preview::QUERY_VAR : 'rwgc_preview_country';
		$iso = self::normalize_preview_iso( self::request_param( $key ) );
		if ( '' === $iso ) {
			$iso = self::preview_iso_from_referer( $key );
		}
		if ( '' === $iso ) {
			$iso = self::rule_tester_preview_country();
		}
		return $iso;
	}

	/**
	 * @return bool
	 */
	private static function user_may_preview_country() {
		$geo = class_exists( 'RWGC_Preview', false ) && method_exists( 'RWGC_Preview', 'can_preview_geo' ) && RWGC_Preview::can_preview_geo();
		$tester = class_exists( 'RWGC_Rule_Tester_Frontend_Preview', false )
			&& method_exists( 'RWGC_Rule_Tester_Frontend_Preview', 'can_preview' )
			&& RWGC_Rule_Tester_Frontend_Preview::can_preview();
		if ( $geo || $tester ) {
			return true;
		}
		if ( class_exists( 'RWGC_Preview', false ) || class_exists( 'RWGC_Rule_Tester_Frontend_Preview', false ) ) {
			return false;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
	}

	/**
	 * @param string $key Query key.
	 * @return string
	 */
	private static function request_param( $key ) {
		if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::preview_scalar( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return self::preview_scalar( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return '';
	}

	/**
	 * @param string $key Query key.
	 * @return string
	 */
	private static function preview_iso_from_referer( $key ) {
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
		if ( '' === $referer ) {
			return '';
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $referer ) : parse_url( $referer );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) || ! is_string( $parts['query'] ) ) {
			return '';
		}
		$args = array();
		parse_str( $parts['query'], $args );
		if ( ! isset( $args[ $key ] ) ) {
			return '';
		}
		return self::normalize_preview_iso( self::preview_scalar( $args[ $key ] ) );
	}

	/**
	 * @return string
	 */
	private static function rule_tester_preview_country() {
		if ( ! class_exists( 'RWGC_Rule_Tester_Frontend_Preview', false ) || ! method_exists( 'RWGC_Rule_Tester_Frontend_Preview', 'is_active' ) || ! RWGC_Rule_Tester_Frontend_Preview::is_active() ) {
			return '';
		}
		if ( ! method_exists( 'RWGC_Rule_Tester_Frontend_Preview', 'get_payload' ) ) {
			return '';
		}
		$payload = RWGC_Rule_Tester_Frontend_Preview::get_payload();
		if ( ! is_array( $payload ) || empty( $payload['context'] ) || ! is_array( $payload['context'] ) || ! isset( $payload['context']['country'] ) ) {
			return '';
		}
		return self::normalize_preview_iso( self::preview_scalar( $payload['context']['country'] ) );
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function preview_scalar( $value ) {
		if ( function_exists( 'wp_unslash' ) ) {
			$value = wp_unslash( $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param string $raw Raw ISO.
	 * @return string
	 */
	private static function normalize_preview_iso( $raw ) {
		$raw = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $raw ) : trim( (string) $raw );
		return RWGCM_Price_Book::normalize_country( $raw );
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
		$context = self::billing_context_active();
		return RWGCM_Price_Book::chosen_billing_country(
			$posted,
			self::session_billing_country(),
			$context ? self::account_billing_country() : '',
			$context
		);
	}

	/**
	 * default_checkout_billing_country / default_checkout_shipping_country:
	 * a customer with no chosen country gets the geo country (if allowed).
	 *
	 * @param mixed  $value Value WooCommerce would pre-fill.
	 * @param string $input Field key.
	 * @return mixed
	 */
	public static function filter_default_checkout_country( $value, $input = '' ) {
		if ( '' !== self::chosen_country_for_defaults() ) {
			return $value;
		}
		return self::geo_default_country( (string) $value, 'shipping_country' === $input );
	}

	/**
	 * woocommerce_customer_default_location_array: the location a new
	 * (guest) customer session starts with.
	 *
	 * @param mixed $location array( country, state ).
	 * @return mixed
	 */
	public static function filter_default_location( $location ) {
		if ( ! is_array( $location ) ) {
			return $location;
		}
		$current = isset( $location['country'] ) ? (string) $location['country'] : '';
		$country = self::geo_default_country( $current, false );
		if ( $country !== $current ) {
			$location['country'] = $country;
			$location['state']   = '';
		}
		return $location;
	}

	/**
	 * @param string $wc_default WooCommerce default.
	 * @param bool   $shipping   Use shipping countries.
	 * @return string
	 */
	private static function geo_default_country( $wc_default, $shipping ) {
		if ( self::$defaulting || self::forces_store_currency() ) {
			return $wc_default;
		}
		self::$defaulting = true;
		try {
			$geo     = self::visitor_country();
			$allowed = self::sell_countries( $shipping );
		} finally {
			self::$defaulting = false;
		}
		return RWGCM_Price_Book::default_checkout_country( $geo, $allowed, $wc_default );
	}

	/**
	 * @param bool $shipping Shipping list instead of selling list.
	 * @return array<int|string>
	 */
	private static function sell_countries( $shipping ) {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		$wc = WC();
		if ( ! $wc || empty( $wc->countries ) || ! is_object( $wc->countries ) ) {
			return array();
		}
		$method = $shipping && method_exists( $wc->countries, 'get_shipping_countries' ) ? 'get_shipping_countries' : 'get_allowed_countries';
		if ( ! method_exists( $wc->countries, $method ) ) {
			return array();
		}
		$list = $wc->countries->$method();
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Chosen billing country without the cart/checkout context limit on the
	 * saved account country (the form is pre-filled from it anyway).
	 *
	 * @return string
	 */
	private static function chosen_country_for_defaults() {
		return RWGCM_Price_Book::chosen_billing_country(
			self::posted_billing_country(),
			self::session_billing_country(),
			self::account_billing_country(),
			true
		);
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

		// A visitor currency that is already the store currency has nothing to explain.
		// Clear a fallback left by an earlier address in this request (for example
		// ZAR, then the shipping calculator moves the customer to GB).
		if ( RWGCM_Price_Book::normalize_currency( $preferred ) === RWGCM_Price_Book::normalize_currency( $base ) ) {
			self::$shipping_fallback = '';
		}
		if ( ! self::cart_is_ready() ) {
			return $preferred;
		}
		self::$shipping_fallback = '';
		$supports = self::cart_line_support( $preferred );
		return RWGCM_Price_Book::currency_for_lines( $preferred, $base, $supports );
	}

	/**
	 * @return bool
	 */
	private static function skip_request() {
		return self::forces_store_currency();
	}

	/**
	 * @return array<string, bool>
	 */
	private static function request_flags() {
		$rest = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || self::is_rest_request_uri();
		return array(
			'syncing'    => self::$store_currency_writes > 0,
			'cron'       => ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ),
			'cli'        => defined( 'WP_CLI' ) && WP_CLI,
			'rest'       => $rest,
			'store_api'  => $rest && self::is_store_api_request_uri(),
			'admin'      => function_exists( 'is_admin' ) && is_admin(),
			'front_ajax' => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * @return bool
	 */
	private static function is_rest_request_uri() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' );
	}

	/**
	 * Store API storefront routes (/wc/store/), including cart, checkout, and batch.
	 *
	 * @return bool
	 */
	private static function is_store_api_request_uri() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/wc/store/' )
			|| false !== strpos( $uri, 'rest_route=/wc/store/' )
			|| false !== strpos( $uri, 'rest_route=%2Fwc%2Fstore%2F' );
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
	 * Write the customer session before the calculator response is flushed.
	 *
	 * The calculator is a country the shopper chose. Remember it the same way
	 * checkout remembers a posted billing country, then resolve again so a
	 * previous ZAR fallback does not survive a move to the store currency.
	 * get_refreshed_fragments then uses that same destination.
	 *
	 * @return void
	 */
	public static function persist_customer_session() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$wc = WC();
		if ( ! $wc ) {
			return;
		}
		if ( $wc->customer && method_exists( $wc->customer, 'get_shipping_country' ) ) {
			self::remember_billing_country( $wc->customer->get_shipping_country() );
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			get_woocommerce_currency();
		}
		if ( ! $wc->session || ! method_exists( $wc->session, 'save_data' ) ) {
			return;
		}
		$wc->session->save_data();
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
		if ( ! empty( $supports ) ) {
			$shipping   = RWGCM_Price_Book::shipping_supports_currency( $currency, self::base_currency(), self::cart_needs_shipping(), self::shipping_priced( $currency ) );
			$items_ok   = ! in_array( false, $supports, true );
			$supports[] = $shipping;
			self::$shipping_fallback = ( $items_ok && ! $shipping ) ? RWGCM_Price_Book::normalize_currency( $currency ) : '';
		}
		return $supports;
	}

	/**
	 * @return bool
	 */
	private static function cart_needs_shipping() {
		if ( ! self::cart_is_ready() || ! method_exists( WC()->cart, 'needs_shipping' ) ) {
			return false;
		}
		self::$reading_cart = true;
		try {
			return (bool) WC()->cart->needs_shipping();
		} finally {
			self::$reading_cart = false;
		}
	}

	/**
	 * Currency that every cart line is priced in but shipping to the current
	 * address is not, from the last resolution ('' when shipping did not
	 * decide it). Used for the "prices shown in GBP" notice.
	 *
	 * @return string
	 */
	public static function shipping_fallback_currency() {
		return self::$shipping_fallback;
	}

	/**
	 * Whether shipping is priced in a currency. The default is computed from
	 * the zone configuration of the cart's shipping packages
	 * (RWGCM_Shipping_Prices::computed_priced): every enabled method in each
	 * matched zone has an amount for the currency (flat rate cost, free
	 * shipping minimum, legacy local pickup cost), or needs none (coupon-only
	 * free shipping, a zero cost). Partially priced zones keep the store
	 * currency unless "hide unpriced methods" is on.
	 *
	 * @param string            $currency Currency.
	 * @param array<mixed>|null $packages Packages, or null for the cart's.
	 * @return bool
	 */
	public static function shipping_priced( $currency, $packages = null ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return false;
		}
		if ( null === $packages ) {
			$packages = self::cart_shipping_packages();
		}
		$packages = is_array( $packages ) ? $packages : array();
		$computed = class_exists( 'RWGCM_Shipping_Prices', false ) && ! empty( $packages ) && RWGCM_Shipping_Prices::computed_priced( $currency, $packages );
		/**
		 * Whether shipping for these packages is priced in $currency.
		 *
		 * Since 0.5.0 the default is computed from the shipping zones (see
		 * RWGCM_Shipping_Prices). Returning true declares that the rates for
		 * these packages are amounts in $currency (the 0.4.5 contract); such
		 * rates are tagged rwgcm_priced_source=filter on the order.
		 *
		 * @param bool         $priced   Computed default.
		 * @param string       $currency Currency code.
		 * @param array<mixed> $packages Shipping packages.
		 */
		return (bool) apply_filters( 'rwgcm_price_book_shipping_priced', $computed, $currency, $packages );
	}

	/**
	 * Destination-only package for the zone lookup. WC_Cart::get_shipping_packages()
	 * is not used here: currency is resolved while WooCommerce is still
	 * calculating line totals (it would read line_total before it exists), and
	 * only the destination decides the zone. Rates themselves are still tagged
	 * per real package in RWGCM_Shipping_Prices::filter_package_rates().
	 *
	 * @return array<mixed>
	 */
	private static function cart_shipping_packages() {
		if ( ! self::cart_is_ready() || ! WC()->customer || ! method_exists( WC()->customer, 'get_shipping_country' ) ) {
			return array();
		}
		$c = WC()->customer;
		return array(
			array(
				'contents'      => array(),
				'contents_cost' => 0,
				'destination'   => array(
					'country'   => (string) $c->get_shipping_country(),
					'state'     => (string) $c->get_shipping_state(),
					'postcode'  => (string) $c->get_shipping_postcode(),
					'city'      => (string) $c->get_shipping_city(),
					'address'   => (string) $c->get_shipping_address(),
					'address_1' => (string) $c->get_shipping_address(),
					'address_2' => (string) $c->get_shipping_address_2(),
				),
			),
		);
	}
}
