<?php
/**
 * Per-currency shipping amounts for WooCommerce flat rate (including
 * shipping-class costs), free shipping, legacy local pickup, and
 * block-checkout local pickup (phase 1 of the per-currency shipping scope).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extra instance fields ("Cost (ZAR)", "Minimum order amount (ZAR)"), the
 * read-time swap that lets WooCommerce's own cost formula and shipping tax run
 * unchanged, the computed "shipping priced" decision, rate tagging and the
 * optional hiding of unpriced rates.
 */
class RWGCM_Shipping_Prices {

	/**
	 * Hide rates that are not priced in the visitor currency (yes/no, default no).
	 */
	const OPTION_HIDE_UNPRICED = 'rwgcm_shipping_hide_unpriced';

	/**
	 * Manual block-checkout local pickup costs. Location id => currency => amount.
	 * The WooCommerce location list is never written.
	 */
	const OPTION_PICKUP_COSTS = 'rwgcm_pickup_location_costs';

	/**
	 * Rate and order shipping-line meta: the currency the amount was entered in.
	 */
	const META_PRICED = 'rwgcm_priced_currency';

	/**
	 * Rate meta set when the site's rwgcm_price_book_shipping_priced filter declared the rate priced.
	 */
	const META_SOURCE = 'rwgcm_priced_source';

	/**
	 * Supported method id => WooCommerce setting swapped per currency.
	 *
	 * @var array<string, string>
	 */
	private static $swap = array(
		'flat_rate'     => 'cost',
		'free_shipping' => 'min_amount',
		'local_pickup'  => 'cost',
	);

	/**
	 * Memo for computed decisions within a request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $memo = array();

	/**
	 * @return void
	 */
	public static function init() {
		foreach ( array_keys( self::$swap ) as $id ) {
			add_filter( 'woocommerce_shipping_instance_form_fields_' . $id, array( __CLASS__, 'filter_form_fields_' . $id ) );
			add_filter( 'woocommerce_shipping_' . $id . '_instance_option', array( __CLASS__, 'filter_instance_option' ), 20, 3 );
		}
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 50, 2 );
		// Block local pickup reads one cost in add_rate(), before tax. Swap it there.
		add_filter( 'woocommerce_shipping_method_add_rate_args', array( __CLASS__, 'filter_add_rate_args' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_method_modal_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_storefront_notice_style' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_block_notice_script' ) );
		add_action( 'update_option_' . self::OPTION_PICKUP_COSTS, array( __CLASS__, 'flush_rate_cache' ) );
		add_action( 'add_option_' . self::OPTION_PICKUP_COSTS, array( __CLASS__, 'flush_rate_cache' ) );
		// Persistent explanation (0.5.0): rendered from the current state on every
		// classic cart / checkout render and checkout fragment refresh.
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'render_checkout_notice' ), 11 );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'filter_checkout_fragments' ) );
		// Inside the cart totals table, which cart.js replaces as a whole.
		// Not a .woocommerce-info: that class is lifted into the top notice wrapper
		// after update_cart / the shipping calculator.
		add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'render_cart_notice' ) );
		add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'render_cart_currency_marker' ), 11 );
		add_filter( 'woocommerce_cart_hash', array( __CLASS__, 'filter_cart_hash' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'filter_cart_fragments' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_cart_fragment_refresh' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'print_cart_fragment_refresh_script' ), 20 );
		add_action( 'update_option_' . self::OPTION_HIDE_UNPRICED, array( __CLASS__, 'flush_rate_cache' ) );
		add_action( 'add_option_' . self::OPTION_HIDE_UNPRICED, array( __CLASS__, 'flush_rate_cache' ) );
		if ( function_exists( 'did_action' ) && did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_store_api_data();
			self::register_checkout_block();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_store_api_data' ) );
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_checkout_block' ) );
		}
	}

	/**
	 * WooCommerce caches calculated rates in the session, keyed by a package
	 * hash that includes the "shipping" transient version. Hiding changes
	 * which rates exist, so bump that version when the setting changes.
	 *
	 * @return void
	 */
	public static function flush_rate_cache() {
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	/**
	 * Full-width currency fields inside WooCommerce's shipping method modal.
	 * The price-books stylesheet is not loaded on that screen.
	 *
	 * @param string $hook Admin hook suffix.
	 * @return void
	 */
	public static function enqueue_method_modal_styles( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'shipping' !== $tab ) {
			return;
		}
		if ( ! defined( 'RWGCM_URL' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$ver = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.6.0';
		wp_enqueue_style( 'rwgcm-shipping-modal', RWGCM_URL . 'admin/css/rwgcm-shipping-modal.css', array(), $ver );
	}

	/**
	 * Cart and Checkout blocks: show extensions.rwgcm.shipping_currency_notice.
	 * Skipped when the blocks integration already enqueued the same handle.
	 *
	 * @return void
	 */
	public static function enqueue_block_notice_script() {
		if ( ! function_exists( 'has_block' ) ) {
			return;
		}
		if ( ! has_block( 'woocommerce/checkout' ) && ! has_block( 'woocommerce/cart' ) ) {
			return;
		}
		if ( ! defined( 'RWGCM_URL' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		if ( function_exists( 'wp_script_is' ) && wp_script_is( 'rwgcm-shipping-currency-notice', 'enqueued' ) ) {
			return;
		}
		$ver = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.6.0';
		wp_enqueue_script(
			'rwgcm-shipping-currency-notice',
			RWGCM_URL . 'assets/js/shipping-currency-notice.js',
			array( 'wp-plugins', 'wp-element', 'wp-data', 'wc-blocks-checkout' ),
			$ver,
			true
		);
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rwgcm-shipping-currency-notice', RWGCM_URL . 'assets/css/shipping-currency-notice.css', array(), $ver );
		}
	}

	/**
	 * Notice wrapping on classic cart and checkout, and on the blocks when the
	 * integration script is not what enqueued it.
	 *
	 * @return void
	 */
	public static function enqueue_storefront_notice_style() {
		$on_cart     = function_exists( 'is_cart' ) && is_cart();
		$on_checkout = function_exists( 'is_checkout' ) && is_checkout();
		if ( ! $on_cart && ! $on_checkout ) {
			return;
		}
		if ( ! defined( 'RWGCM_URL' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$ver = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.6.0';
		wp_enqueue_style( 'rwgcm-shipping-currency-notice', RWGCM_URL . 'assets/css/shipping-currency-notice.css', array(), $ver );
	}

	/**
	 * Checkout and cart blocks read extensions.rwgcm.shipping_currency_notice.
	 *
	 * @return void
	 */
	public static function register_checkout_block() {
		if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			return;
		}
		if ( ! class_exists( 'RWGCM_Shipping_Block_Integration', false ) ) {
			require_once __DIR__ . '/class-rwgcm-shipping-block.php';
		}
		if ( ! class_exists( 'RWGCM_Shipping_Block_Integration', false ) ) {
			return;
		}
		$integration = new RWGCM_Shipping_Block_Integration();
		$register    = static function ( $registry ) use ( $integration ) {
			if ( is_object( $registry ) && method_exists( $registry, 'register' ) ) {
				$registry->register( $integration );
			}
		};
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && method_exists( '\Automattic\WooCommerce\Blocks\Package', 'container' ) ) {
			try {
				$registry = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry::class );
				$register( $registry );
				return;
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $e );
			}
		}
		add_action( 'woocommerce_blocks_checkout_block_registration', $register );
		add_action( 'woocommerce_blocks_cart_block_registration', $register );
	}

	/**
	 * Notice text for a cart kept in the store currency because shipping to
	 * the address is not priced in the visitor currency.
	 *
	 * @param string $wanted  Visitor currency.
	 * @param string $base    Store currency.
	 * @param string $country Shipping country name or code.
	 * @return string
	 */
	public static function fallback_notice( $wanted, $base, $country ) {
		if ( '' === $country ) {
			return sprintf(
				/* translators: 1: store currency, 2: visitor currency. */
				__( 'Prices are shown in %1$s because shipping to this address isn’t priced in %2$s.', 'reactwoo-geo-commerce' ),
				$base,
				$wanted
			);
		}
		return sprintf(
			/* translators: 1: store currency, 2: shipping country, 3: visitor currency. */
			__( 'Prices are shown in %1$s because shipping to %2$s isn’t priced in %3$s.', 'reactwoo-geo-commerce' ),
			$base,
			$country,
			$wanted
		);
	}

	/**
	 * Current fallback state for the cart: "ZAR|GB" or ''.
	 *
	 * @return array{state: string, wanted: string, country: string}
	 */
	private static function current_fallback() {
		$out = array( 'state' => '', 'wanted' => '', 'country' => '' );
		if ( ! function_exists( 'get_woocommerce_currency' ) || RWGCM_Price_Book_Currency::forces_store_currency() ) {
			return $out;
		}
		get_woocommerce_currency();
		$wanted = RWGCM_Price_Book_Currency::shipping_fallback_currency();
		if ( '' === $wanted ) {
			return $out;
		}
		$country = '';
		if ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_shipping_country' ) ) {
			$country = (string) WC()->customer->get_shipping_country();
		}
		return array( 'state' => $wanted . '|' . $country, 'wanted' => $wanted, 'country' => $country );
	}

	/**
	 * Notice text for the current cart, or '' when the cart is not held in
	 * the store currency by the shipping fallback (including visitors whose
	 * currency is the store currency anyway).
	 *
	 * @return string
	 */
	public static function current_notice() {
		$now = self::current_fallback();
		if ( '' === $now['state'] ) {
			return '';
		}
		return self::fallback_notice( $now['wanted'], RWGCM_Price_Book_Currency::base_currency(), self::country_name( $now['country'] ) );
	}

	/**
	 * Checkout: one wrapper, always present (empty when there is nothing to
	 * say) so update_order_review replaces it in place.
	 * Cart: a single row inside the totals table, omitted when there is
	 * nothing to say. It is not a WooCommerce notice, so cart.js does not
	 * move it to the top notice wrapper when it replaces the totals.
	 *
	 * @param string $message Notice text or ''.
	 * @param string $context checkout or cart.
	 * @return string
	 */
	public static function notice_html( $message, $context = 'checkout' ) {
		if ( 'cart' === $context ) {
			if ( '' === (string) $message ) {
				return '';
			}
			return '<tr class="rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--cart"><td colspan="2" role="status">' . esc_html( $message ) . '</td></tr>';
		}
		$class = 'rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--checkout';
		if ( '' === $message ) {
			return '<div class="' . esc_attr( $class ) . '"></div>';
		}
		return '<div class="' . esc_attr( $class ) . '"><div class="woocommerce-info" role="status">' . esc_html( $message ) . '</div></div>';
	}

	/**
	 * Classic checkout page load.
	 *
	 * @return void
	 */
	public static function render_checkout_notice() {
		echo self::notice_html( self::current_notice(), 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in notice_html().
	}

	/**
	 * Classic checkout update_order_review: replace the wrapper in place.
	 *
	 * @param mixed $fragments Fragments.
	 * @return mixed
	 */
	public static function filter_checkout_fragments( $fragments ) {
		if ( ! is_array( $fragments ) ) {
			return $fragments;
		}
		$fragments['.rwgcm-shipping-currency-notice--checkout'] = self::notice_html( self::current_notice(), 'checkout' );
		return $fragments;
	}

	/**
	 * Classic cart page, inside the cart totals table so the shipping
	 * calculator's totals replacement includes it.
	 *
	 * @return void
	 */
	public static function render_cart_notice() {
		echo self::notice_html( self::current_notice(), 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in notice_html().
	}

	/**
	 * Always inside the replaced totals, including when the notice itself is omitted.
	 *
	 * @param string $currency Active currency.
	 * @return string
	 */
	public static function cart_currency_marker_html( $currency ) {
		$currency = RWGCM_Price_Book::normalize_currency( (string) $currency );
		return '<tr class="rwgcm-cart-currency-row" hidden><td colspan="2"><span class="rwgcm-cart-currency" data-rwgcm-currency="' . esc_attr( $currency ) . '" hidden></span></td></tr>';
	}

	/**
	 * @return void
	 */
	public static function render_cart_currency_marker() {
		$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		echo self::cart_currency_marker_html( $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
	}

	/**
	 * Cart-fragment session storage is keyed by this hash. Including the
	 * active currency makes a currency switch (including a shipping-calculator
	 * country change) miss the cached mini-cart.
	 *
	 * @param mixed $hash WooCommerce cart hash.
	 * @return string
	 */
	public static function filter_cart_hash( $hash ) {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return is_string( $hash ) ? $hash : '';
		}
		return self::hash_with_currency( $hash, get_woocommerce_currency() );
	}

	/**
	 * @param mixed  $hash     WooCommerce cart hash.
	 * @param string $currency Active currency.
	 * @return string
	 */
	public static function hash_with_currency( $hash, $currency ) {
		$hash = is_string( $hash ) ? $hash : '';
		return $hash . '|rwgcm:' . RWGCM_Price_Book::normalize_currency( (string) $currency );
	}

	/**
	 * Selectors for a theme header amount that is not inside
	 * div.widget_shopping_cart_content. WooCommerce only refreshes the widget.
	 * A selector already present (the theme built it) is left alone.
	 *
	 * @return array<int, string>
	 */
	public static function header_cart_fragment_selectors() {
		$defaults = array(
			'a.cart-contents',
			'.site-header-cart a.cart-contents',
		);
		$selectors = function_exists( 'apply_filters' ) ? apply_filters( 'rwgcm_header_cart_fragment_selectors', $defaults ) : $defaults;
		if ( ! is_array( $selectors ) ) {
			return $defaults;
		}
		$out = array();
		foreach ( $selectors as $selector ) {
			if ( is_string( $selector ) && '' !== $selector ) {
				$out[] = $selector;
			}
		}
		return $out;
	}

	/**
	 * @param string $selector CSS selector.
	 * @return string Last class token, or "amount".
	 */
	public static function fragment_class_from_selector( $selector ) {
		if ( preg_match_all( '/\.([_a-zA-Z][_a-zA-Z0-9-]*)/', (string) $selector, $matches ) && ! empty( $matches[1] ) ) {
			return (string) $matches[1][ count( $matches[1] ) - 1 ];
		}
		return 'amount';
	}

	/**
	 * Header amount markup for one selector. $total and $count are passed in
	 * so a theme fragment can be built without another cart read.
	 *
	 * @param string $selector CSS selector.
	 * @param string $currency Active currency.
	 * @param string $total    Formatted subtotal HTML.
	 * @param string $count    Item count.
	 * @return string
	 */
	public static function header_cart_fragment_html( $selector, $currency, $total, $count ) {
		$currency = RWGCM_Price_Book::normalize_currency( (string) $currency );
		$class    = self::fragment_class_from_selector( $selector );
		$marker   = '<span class="rwgcm-mini-cart-currency" data-currency="' . esc_attr( $currency ) . '" hidden></span>';
		if ( false !== strpos( (string) $selector, 'cart-contents' ) ) {
			return '<a class="' . esc_attr( $class ) . '" href="#" data-rwgcm-currency="' . esc_attr( $currency ) . '"><span class="count">' . esc_html( (string) $count ) . '</span>' . (string) $total . $marker . '</a>';
		}
		return '<span class="' . esc_attr( $class ) . '" data-rwgcm-currency="' . esc_attr( $currency ) . '">' . (string) $total . $marker . '</span>';
	}

	/**
	 * Add header fragments the theme did not already register.
	 *
	 * @param array<string, string> $fragments Fragments.
	 * @param string                $currency  Active currency.
	 * @param string                $total     Formatted subtotal HTML.
	 * @param string                $count     Item count.
	 * @return array<string, string>
	 */
	public static function append_header_cart_fragments( array $fragments, $currency, $total, $count ) {
		foreach ( self::header_cart_fragment_selectors() as $selector ) {
			if ( isset( $fragments[ $selector ] ) ) {
				continue;
			}
			$fragments[ $selector ] = self::header_cart_fragment_html( $selector, $currency, $total, $count );
		}
		return $fragments;
	}

	/**
	 * Stamp the active currency onto refreshed cart fragments.
	 *
	 * The currency is whatever get_woocommerce_currency() resolved for this
	 * request. On get_refreshed_fragments that is the cart's shipping
	 * destination (persisted in woocommerce_calculated_shipping), the same
	 * input the cart totals request used.
	 *
	 * @param mixed $fragments Fragments.
	 * @return mixed
	 */
	public static function filter_cart_fragments( $fragments ) {
		if ( ! is_array( $fragments ) ) {
			return $fragments;
		}
		$currency = function_exists( 'get_woocommerce_currency' ) ? RWGCM_Price_Book::normalize_currency( (string) get_woocommerce_currency() ) : '';
		$marker   = '<span class="rwgcm-mini-cart-currency" data-currency="' . esc_attr( $currency ) . '" hidden></span>';
		$fragments['span.rwgcm-mini-cart-currency'] = $marker;
		$key = 'div.widget_shopping_cart_content';
		if ( isset( $fragments[ $key ] ) && is_string( $fragments[ $key ] ) ) {
			$without = preg_replace( '/<span class="rwgcm-mini-cart-currency" data-currency="[^"]*" hidden><\/span>/', '', $fragments[ $key ] );
			$html    = is_string( $without ) ? $without : $fragments[ $key ];
			$updated = preg_replace( '/(<div\b[^>]*widget_shopping_cart_content[^>]*>)/', '$1' . $marker, $html, 1 );
			if ( is_string( $updated ) ) {
				$fragments[ $key ] = $updated;
			}
		}
		$total = '';
		$count = '';
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			if ( method_exists( WC()->cart, 'get_cart_subtotal' ) ) {
				$total = (string) WC()->cart->get_cart_subtotal();
			}
			if ( method_exists( WC()->cart, 'get_cart_contents_count' ) ) {
				$count = (string) (int) WC()->cart->get_cart_contents_count();
			}
		}
		if ( '' !== $total ) {
			$fragments = self::append_header_cart_fragments( $fragments, $currency, $total, $count );
		}
		return $fragments;
	}

	/**
	 * Own footer script, dependent on jQuery. wc-cart-fragments is not
	 * enqueued on the cart page, so triggering wc_fragment_refresh alone
	 * never sends get_refreshed_fragments.
	 *
	 * @return void
	 */
	public static function enqueue_cart_fragment_refresh() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		if ( ! function_exists( 'wp_register_script' ) || ! function_exists( 'wp_enqueue_script' ) || ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}
		$ver = defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.6.0';
		wp_register_script( 'rwgcm-cart-fragments', false, array( 'jquery' ), $ver, true );
		wp_enqueue_script( 'rwgcm-cart-fragments' );
		wp_add_inline_script( 'rwgcm-cart-fragments', self::cart_fragment_refresh_script() );
	}

	/**
	 * Fallback when the handle was not printed with the footer scripts.
	 *
	 * @return void
	 */
	public static function print_cart_fragment_refresh_script() {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		if ( function_exists( 'wp_script_is' ) && wp_script_is( 'rwgcm-cart-fragments', 'done' ) ) {
			return;
		}
		echo '<script>' . self::cart_fragment_refresh_script() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- script is a static string with no user input.
	}

	/**
	 * Posts get_refreshed_fragments after each cart-totals refresh.
	 *
	 * Bound with jQuery on document.body, because WooCommerce triggers
	 * updated_cart_totals, updated_shipping_method and updated_wc_div with
	 * jQuery. The calculator submit does not send: that request has not
	 * saved the shipping country yet. A fragment response must not arm the
	 * debounce again (that cleared the timer before it fired). A switch
	 * that arrives while a request is in flight queues one more send.
	 *
	 * @return string
	 */
	public static function cart_fragment_refresh_script() {
		return <<<'JS'
jQuery(function($){
  var timer=null,inflight=null,again=false;
  function arm(){
    if(timer){window.clearTimeout(timer);}
    timer=window.setTimeout(send,80);
  }
  function endpoint(){
    var params=window.wc_cart_fragments_params||window.wc_cart_params||{};
    var url=params.wc_ajax_url?String(params.wc_ajax_url):(window.location.pathname+'?wc-ajax=%%endpoint%%');
    return url.replace('%%endpoint%%','get_refreshed_fragments');
  }
  function send(){
    timer=null;
    if(inflight){again=true;return;}
    again=false;
    var el=document.querySelector('.cart_totals [data-rwgcm-currency]');
    var currency=el?(el.getAttribute('data-rwgcm-currency')||''):'';
    try{
      var previous=sessionStorage.getItem('rwgcm_cart_currency')||'';
      if(currency&&previous&&currency!==previous){
        var drop=[];
        for(var i=0;i<sessionStorage.length;i++){
          var key=sessionStorage.key(i);
          if(key&&key.indexOf('wc_fragments')===0){drop.push(key);}
        }
        for(var j=0;j<drop.length;j++){sessionStorage.removeItem(drop[j]);}
      }
      if(currency){sessionStorage.setItem('rwgcm_cart_currency',currency);}
    }catch(err){}
    inflight=$.ajax({url:endpoint(),type:'POST',data:{time:new Date().getTime()}});
    inflight.done(function(data){
      if(!data||!data.fragments){return;}
      $.each(data.fragments,function(sel,html){
        var nodes=$(sel);
        if(nodes.length){nodes.replaceWith(html);}
      });
      try{
        var stored=window.wc_cart_fragments_params;
        if(stored&&data.cart_hash){
          sessionStorage.setItem(stored.fragment_name,JSON.stringify(data.fragments));
          sessionStorage.setItem(stored.cart_hash_key,data.cart_hash);
          localStorage.setItem(stored.cart_hash_key,data.cart_hash);
        }
      }catch(err2){}
      $(document.body).trigger('wc_fragments_refreshed');
    });
    inflight.always(function(){
      inflight=null;
      if(again){arm();}
    });
  }
  $(document.body).on('updated_cart_totals updated_shipping_method updated_wc_div',arm);
  $(document.body).on('submit','form.woocommerce-shipping-calculator',function(){
    // The calculator has not saved the shipping country yet.
  });
  $(document).ajaxComplete(function(event,xhr,settings){
    var data=settings&&settings.data?String(settings.data):'';
    var url=settings&&settings.url?String(settings.url):'';
    if(url.indexOf('get_refreshed_fragments')!==-1){return;}
    if(data.indexOf('calc_shipping')!==-1||url.indexOf('update_shipping_method')!==-1){arm();}
  });
});
JS;
	}

	/**
	 * @param string $code ISO2.
	 * @return string
	 */
	private static function country_name( $code ) {
		if ( '' !== $code && function_exists( 'WC' ) && WC()->countries ) {
			$all = WC()->countries->get_countries();
			if ( isset( $all[ $code ] ) ) {
				return html_entity_decode( (string) $all[ $code ], ENT_QUOTES );
			}
		}
		return $code;
	}

	/**
	 * Store API: extensions.rwgcm on the cart, for block checkouts and themes.
	 *
	 * @return void
	 */
	public static function register_store_api_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartSchema' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => 'rwgcm',
				'data_callback'   => array( __CLASS__, 'store_api_data' ),
				'schema_callback' => array( __CLASS__, 'store_api_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function store_api_data() {
		$now = self::current_fallback();
		return array(
			'shipping_fallback_currency' => $now['wanted'],
			'shipping_currency_notice'   => '' === $now['state'] ? '' : self::fallback_notice( $now['wanted'], RWGCM_Price_Book_Currency::base_currency(), self::country_name( $now['country'] ) ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function store_api_schema() {
		return array(
			'shipping_fallback_currency' => array(
				'description' => 'Visitor currency the cart would use if shipping to the address were priced in it.',
				'type'        => 'string',
				'readonly'    => true,
			),
			'shipping_currency_notice'   => array(
				'description' => 'Why the cart is shown in the store currency.',
				'type'        => 'string',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Setting key that holds the amount for a currency.
	 *
	 * @param string $method_id Method id.
	 * @param string $currency  Currency code.
	 * @return string Empty for an unsupported method.
	 */
	public static function field_key( $method_id, $currency ) {
		if ( ! isset( self::$swap[ $method_id ] ) ) {
			return '';
		}
		return 'rwgcm_' . self::$swap[ $method_id ] . '_' . RWGCM_Price_Book::normalize_currency( $currency );
	}

	/**
	 * Price-book currencies other than the store currency.
	 *
	 * @param string|null $base Store currency (tests).
	 * @return array<int, string>
	 */
	public static function extra_currencies( $base = null ) {
		$base = RWGCM_Price_Book::normalize_currency( null === $base ? RWGCM_Price_Book_Currency::base_currency() : $base );
		$out  = array();
		foreach ( RWGCM_Price_Book::currencies() as $code ) {
			if ( $code !== $base ) {
				$out[] = $code;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function filter_form_fields_flat_rate( $fields ) {
		return self::add_fields( 'flat_rate', $fields, self::extra_currencies() );
	}

	/**
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function filter_form_fields_free_shipping( $fields ) {
		return self::add_fields( 'free_shipping', $fields, self::extra_currencies() );
	}

	/**
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function filter_form_fields_local_pickup( $fields ) {
		return self::add_fields( 'local_pickup', $fields, self::extra_currencies() );
	}

	/**
	 * Adds a heading and one input per currency. The inputs reuse the
	 * sanitize callback of WooCommerce's own field, so a cost formula is
	 * validated exactly like the store-currency one.
	 *
	 * @param string               $method_id  Method id.
	 * @param mixed                $fields     Fields.
	 * @param array<int, string>   $currencies Extra currencies.
	 * @return mixed
	 */
	public static function add_fields( $method_id, $fields, array $currencies ) {
		if ( ! is_array( $fields ) || empty( $currencies ) || ! isset( self::$swap[ $method_id ] ) ) {
			return $fields;
		}
		$setting = self::$swap[ $method_id ];
		$native  = isset( $fields[ $setting ] ) && is_array( $fields[ $setting ] ) ? $fields[ $setting ] : array();
		$fields['rwgcm_currency_prices'] = array(
			'title'       => __( 'Prices in other currencies (Geo Commerce)', 'reactwoo-geo-commerce' ),
			'type'        => 'title',
			'default'     => '',
			'description' => 'min_amount' === $setting
				? __( 'Used instead of the minimum above when the cart is in that currency. Leave blank: this free shipping is not priced in that currency, so carts shipped to this zone stay in the store currency.', 'reactwoo-geo-commerce' )
				: __( 'Used instead of the cost above when the cart is in that currency. Same format as the cost above. Leave blank: not offered in that currency, so carts shipped to this zone stay in the store currency.', 'reactwoo-geo-commerce' ),
		);
		$sanitize = isset( $native['sanitize_callback'] ) ? $native['sanitize_callback'] : null;
		$helper   = 'min_amount' === $setting
			? __( 'Leave blank and carts shipped to this zone stay in the store currency.', 'reactwoo-geo-commerce' )
			: __( 'Leave blank and carts shipped to this zone stay in the store currency.', 'reactwoo-geo-commerce' );
		foreach ( $currencies as $code ) {
			$key = self::field_key( $method_id, $code );
			if ( '' === $key ) {
				continue;
			}
			$title = 'min_amount' === $setting
				/* translators: %s: currency code. */
				? sprintf( __( 'Minimum order amount (%s)', 'reactwoo-geo-commerce' ), $code )
				/* translators: %s: currency code. */
				: sprintf( __( 'Cost (%s)', 'reactwoo-geo-commerce' ), $code );
			$fields[ $key ] = self::currency_text_field(
				$title,
				sprintf(
					/* translators: %s: currency code. */
					__( 'Leave blank: not offered in %s', 'reactwoo-geo-commerce' ),
					$code
				),
				$helper,
				$sanitize
			);
		}
		if ( 'flat_rate' === $method_id ) {
			$fields = self::add_class_cost_fields( $fields, $currencies );
		}
		return $fields;
	}

	/**
	 * One stacked text input. The helper is under the field (not only a tooltip)
	 * and the keypad is decimal on a phone.
	 *
	 * @param string      $title       Visible label.
	 * @param string      $placeholder Placeholder.
	 * @param string      $description Helper under the field.
	 * @param mixed       $sanitize    WooCommerce sanitize callback or null.
	 * @return array<string, mixed>
	 */
	public static function currency_text_field( $title, $placeholder, $description, $sanitize ) {
		$field = array(
			'title'             => $title,
			'type'              => 'text',
			'class'             => 'wc-shipping-modal-price rwgcm-currency-price',
			'placeholder'       => $placeholder,
			'default'           => '',
			'desc_tip'          => false,
			'description'       => $description,
			'custom_attributes' => array(
				'inputmode' => 'decimal',
			),
		);
		if ( null !== $sanitize ) {
			$field['sanitize_callback'] = $sanitize;
		}
		return $field;
	}

	/**
	 * Per-currency shipping-class costs, stacked under the base currency costs.
	 * Keys match the data model: rwgcm_class_cost_{id}_{CUR}, rwgcm_no_class_cost_{CUR}.
	 *
	 * @param array<string, mixed> $fields     Fields so far.
	 * @param array<int, string>   $currencies Extra currencies.
	 * @return array<string, mixed>
	 */
	public static function add_class_cost_fields( array $fields, array $currencies ) {
		$native_keys = array();
		foreach ( $fields as $key => $field ) {
			$key = (string) $key;
			if ( self::is_native_class_cost_key( $key ) ) {
				$native_keys[ $key ] = is_array( $field ) ? $field : array();
			}
		}
		foreach ( $native_keys as $native_key => $native_field ) {
			$sanitize = isset( $native_field['sanitize_callback'] ) ? $native_field['sanitize_callback'] : null;
			$base_title = isset( $native_field['title'] ) && '' !== (string) $native_field['title']
				? (string) $native_field['title']
				: ( 'no_class_cost' === $native_key ? __( 'No shipping class cost', 'reactwoo-geo-commerce' ) : $native_key );
			foreach ( $currencies as $code ) {
				$key = self::manual_component_key( $native_key, $code );
				if ( '' === $key || isset( $fields[ $key ] ) ) {
					continue;
				}
				$fields[ $key ] = self::currency_text_field(
					sprintf(
						/* translators: 1: shipping class cost label, 2: currency code. */
						__( '%1$s (%2$s)', 'reactwoo-geo-commerce' ),
						function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $base_title ) : strip_tags( $base_title ),
						$code
					),
					sprintf(
						/* translators: %s: currency code. */
						__( 'Leave blank: not offered in %s', 'reactwoo-geo-commerce' ),
						$code
					),
					__( 'Leave this blank and the flat rate stays in the store currency, including its base cost.', 'reactwoo-geo-commerce' ),
					$sanitize
				);
			}
		}
		return $fields;
	}

	/**
	 * Read-time swap. WooCommerce reads cost / min_amount through
	 * get_instance_option() in init(), calculate_shipping() and is_available(),
	 * so returning the per-currency value here keeps its formula, class logic
	 * and shipping tax unchanged. Admin screens, REST exports, cron and CLI
	 * force the store currency and never see a swapped value.
	 *
	 * @param mixed  $value  Stored value.
	 * @param string $key    Setting key.
	 * @param mixed  $method WC_Shipping_Method.
	 * @return mixed
	 */
	public static function filter_instance_option( $value, $key, $method ) {
		if ( ! is_object( $method ) || ! isset( $method->id ) || ! isset( self::$swap[ $method->id ] ) ) {
			return $value;
		}
		$key        = (string) $key;
		$is_primary = self::$swap[ $method->id ] === $key;
		$is_class   = 'flat_rate' === $method->id && self::is_native_class_cost_key( $key );
		if ( ! $is_primary && ! $is_class ) {
			return $value;
		}
		if ( RWGCM_Price_Book_Currency::forces_store_currency() || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $value;
		}
		$settings = isset( $method->instance_settings ) && is_array( $method->instance_settings ) ? $method->instance_settings : array();
		return self::swapped_value( $method->id, $settings, $value, get_woocommerce_currency(), RWGCM_Price_Book_Currency::base_currency(), $key );
	}

	/**
	 * @param string               $method_id Method id.
	 * @param array<string, mixed> $settings  Instance settings.
	 * @param mixed                $value     Store-currency value.
	 * @param string               $active    Active currency.
	 * @param string               $base      Store currency.
	 * @param string               $key       Setting key being read. Empty means the method's primary cost or minimum.
	 * @return mixed
	 */
	public static function swapped_value( $method_id, array $settings, $value, $active, $base, $key = '' ) {
		$active = RWGCM_Price_Book::normalize_currency( $active );
		if ( '' === $active || $active === RWGCM_Price_Book::normalize_currency( $base ) ) {
			return $value;
		}
		if ( '' === $key ) {
			$key = isset( self::$swap[ $method_id ] ) ? self::$swap[ $method_id ] : '';
		}
		// A flat rate with a missing class amount stays entirely in the store currency.
		if ( 'flat_rate' === $method_id ) {
			$status = self::method_status( $method_id, $settings, $active, $base );
			if ( empty( $status['priced'] ) ) {
				return $value;
			}
		}
		$manual_key = self::manual_component_key( $key, $active );
		if ( '' === $manual_key ) {
			$manual_key = self::field_key( $method_id, $active );
		}
		if ( '' !== $manual_key && isset( $settings[ $manual_key ] ) && '' !== trim( (string) $settings[ $manual_key ] ) ) {
			return trim( (string) $settings[ $manual_key ] );
		}
		return $value;
	}

	/**
	 * Setting key for a flat-rate component in one currency.
	 *
	 * @param string $native_key cost, class_cost_{id}, or no_class_cost.
	 * @param string $currency   Currency code.
	 * @return string
	 */
	public static function manual_component_key( $native_key, $currency ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		if ( '' === $currency ) {
			return '';
		}
		$native_key = (string) $native_key;
		if ( 'cost' === $native_key ) {
			return 'rwgcm_cost_' . $currency;
		}
		if ( 'min_amount' === $native_key ) {
			return 'rwgcm_min_amount_' . $currency;
		}
		if ( 'no_class_cost' === $native_key ) {
			return 'rwgcm_no_class_cost_' . $currency;
		}
		if ( 0 === strpos( $native_key, 'class_cost_' ) ) {
			$id = substr( $native_key, strlen( 'class_cost_' ) );
			if ( '' === $id || ! preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
				return '';
			}
			return 'rwgcm_class_cost_' . $id . '_' . $currency;
		}
		return '';
	}

	/**
	 * WooCommerce's own class-cost keys. Per-currency copies start with rwgcm_.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_native_class_cost_key( $key ) {
		$key = (string) $key;
		if ( 0 === strpos( $key, 'rwgcm_' ) ) {
			return false;
		}
		return 0 === strpos( $key, 'class_cost_' ) || 'no_class_cost' === $key;
	}

	/**
	 * Whether a method instance is priced in a currency, from its settings.
	 *
	 * @param string               $method_id Method id.
	 * @param array<string, mixed> $settings  Raw instance settings (woocommerce_{id}_{n}_settings).
	 * @param string               $currency  Currency.
	 * @param string               $base      Store currency.
	 * @return array{priced: bool, reason: string, amount: string}
	 */
	public static function method_status( $method_id, array $settings, $currency, $base ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		if ( '' === $currency || $currency === RWGCM_Price_Book::normalize_currency( $base ) ) {
			return array( 'priced' => true, 'reason' => 'base', 'amount' => '' );
		}
		if ( 'pickup_location' === $method_id ) {
			return self::pickup_method_status( $settings, $currency );
		}
		if ( 'flat_rate' === $method_id ) {
			return self::flat_rate_method_status( $settings, $currency );
		}
		if ( ! isset( self::$swap[ $method_id ] ) ) {
			return array( 'priced' => false, 'reason' => 'unsupported', 'amount' => '' );
		}
		$key      = self::field_key( $method_id, $currency );
		$override = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
		$native   = isset( $settings[ self::$swap[ $method_id ] ] ) ? trim( (string) $settings[ self::$swap[ $method_id ] ] ) : '';

		if ( 'free_shipping' === $method_id ) {
			$requires = isset( $settings['requires'] ) ? (string) $settings['requires'] : '';
			if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
				return array( 'priced' => true, 'reason' => 'coupon' === $requires ? 'coupon_only' : 'no_minimum', 'amount' => '' );
			}
			if ( '' !== $override ) {
				return array( 'priced' => true, 'reason' => 'override', 'amount' => $override );
			}
			if ( self::is_zero( $native ) ) {
				return array( 'priced' => true, 'reason' => 'zero', 'amount' => '' );
			}
			return array( 'priced' => false, 'reason' => 'missing', 'amount' => '' );
		}

		if ( '' !== $override ) {
			return array( 'priced' => true, 'reason' => 'override', 'amount' => $override );
		}
		if ( self::is_zero( $native ) ) {
			return array( 'priced' => true, 'reason' => 'zero', 'amount' => '' );
		}
		return array( 'priced' => false, 'reason' => 'missing', 'amount' => '' );
	}

	/**
	 * Blank or 0 amounts are the same in every currency.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_zero( $value ) {
		$value = trim( (string) $value );
		return '' === $value || ( is_numeric( $value ) && 0.0 === (float) $value );
	}

	/**
	 * True when a flat rate has a shipping-class cost that is not blank or zero.
	 * Blank and numeric zero are the same in every currency and do not need a manual amount.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function has_class_costs( array $settings ) {
		foreach ( self::flat_rate_components( $settings ) as $component ) {
			if ( 'cost' === $component['key'] ) {
				continue;
			}
			if ( ! self::is_zero( $component['value'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Base cost plus each native class cost.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<int, array{key: string, value: string}>
	 */
	public static function flat_rate_components( array $settings ) {
		$out = array(
			array(
				'key'   => 'cost',
				'value' => isset( $settings['cost'] ) ? trim( (string) $settings['cost'] ) : '',
			),
		);
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( self::is_native_class_cost_key( $key ) ) {
				$out[] = array(
					'key'   => $key,
					'value' => trim( (string) $value ),
				);
			}
		}
		return $out;
	}

	/**
	 * A flat rate is priced only when every non-zero component has a manual amount.
	 * Otherwise nothing is swapped, so a ZAR base is never added to a GBP class cost.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $currency Currency.
	 * @return array{priced: bool, reason: string, amount: string}
	 */
	public static function flat_rate_method_status( array $settings, $currency ) {
		$missing_class = false;
		$missing_base  = false;
		$base_manual   = '';
		$class_manual  = array();
		foreach ( self::flat_rate_components( $settings ) as $component ) {
			if ( self::is_zero( $component['value'] ) ) {
				continue;
			}
			$manual_key = self::manual_component_key( $component['key'], $currency );
			$manual     = ( '' !== $manual_key && isset( $settings[ $manual_key ] ) ) ? trim( (string) $settings[ $manual_key ] ) : '';
			if ( '' === $manual ) {
				if ( 'cost' === $component['key'] ) {
					$missing_base = true;
				} else {
					$missing_class = true;
				}
				continue;
			}
			if ( 'cost' === $component['key'] ) {
				$base_manual = $manual;
			} else {
				$class_manual[] = $manual;
			}
		}
		if ( $missing_class ) {
			return array( 'priced' => false, 'reason' => 'class_costs', 'amount' => '' );
		}
		if ( $missing_base ) {
			return array( 'priced' => false, 'reason' => 'missing', 'amount' => '' );
		}
		if ( '' === $base_manual && empty( $class_manual ) ) {
			return array( 'priced' => true, 'reason' => 'zero', 'amount' => '' );
		}
		$parts = array();
		if ( '' !== $base_manual ) {
			$parts[] = $base_manual;
		}
		if ( ! empty( $class_manual ) ) {
			$parts[] = 'class ' . implode( ', ', $class_manual );
		}
		return array( 'priced' => true, 'reason' => 'override', 'amount' => implode( ' + ', $parts ) );
	}

	/**
	 * Decision for a set of zones: each zone is a list of enabled methods
	 * array{id, instance_id, settings}. Default: every method in every zone
	 * must be priced. With hiding on: each zone needs at least one priced method.
	 * A zone with no enabled methods charges nothing, so it does not block.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $zones    Methods per package zone.
	 * @param string                                       $currency Currency.
	 * @param string                                       $base     Store currency.
	 * @param bool                                         $hide     Hide unpriced methods.
	 * @return bool
	 */
	public static function zones_priced( array $zones, $currency, $base, $hide ) {
		foreach ( $zones as $methods ) {
			$priced = 0;
			$total  = 0;
			foreach ( (array) $methods as $m ) {
				if ( ! is_array( $m ) || empty( $m['id'] ) ) {
					continue;
				}
				++$total;
				$status = self::method_status( (string) $m['id'], isset( $m['settings'] ) && is_array( $m['settings'] ) ? $m['settings'] : array(), $currency, $base );
				if ( $status['priced'] ) {
					++$priced;
				}
			}
			if ( 0 === $total ) {
				continue;
			}
			if ( $hide ? 0 === $priced : $priced < $total ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function hide_unpriced() {
		$on = function_exists( 'get_option' ) && 'yes' === get_option( self::OPTION_HIDE_UNPRICED, 'no' );
		return (bool) ( function_exists( 'apply_filters' ) ? apply_filters( 'rwgcm_shipping_hide_unpriced', $on ) : $on );
	}

	/**
	 * Computed default of rwgcm_price_book_shipping_priced for real packages.
	 *
	 * @param string       $currency Currency.
	 * @param array<mixed> $packages WooCommerce cart shipping packages.
	 * @return bool
	 */
	public static function computed_priced( $currency, array $packages ) {
		$base = RWGCM_Price_Book_Currency::base_currency();
		$cur  = RWGCM_Price_Book::normalize_currency( $currency );
		if ( '' === $cur || $cur === $base ) {
			return true;
		}
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return false;
		}
		$zones = array();
		foreach ( $packages as $package ) {
			if ( is_array( $package ) ) {
				$zones[] = self::package_methods( $package );
			}
		}
		if ( empty( $zones ) ) {
			return false;
		}
		return self::zones_priced( $zones, $cur, $base, self::hide_unpriced() );
	}

	/**
	 * Enabled methods of the zone matching a package, with raw settings.
	 *
	 * @param array<string, mixed> $package Package.
	 * @return array<int, array<string, mixed>>
	 */
	public static function package_methods( array $package ) {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}
		$dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		$memo = 'zone:' . md5( (string) wp_json_encode( array( $dest['country'] ?? '', $dest['state'] ?? '', $dest['postcode'] ?? '' ) ) );
		if ( isset( self::$memo[ $memo ] ) ) {
			return self::$memo[ $memo ];
		}
		$zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		$out  = array_merge( self::zone_methods( $zone ), self::pickup_methods_for_currency_decision() );
		self::$memo[ $memo ] = $out;
		return $out;
	}

	/**
	 * @param mixed $zone WC_Shipping_Zone.
	 * @param bool  $enabled_only Only enabled methods.
	 * @return array<int, array<string, mixed>>
	 */
	public static function zone_methods( $zone, $enabled_only = true ) {
		$out = array();
		if ( ! is_object( $zone ) || ! method_exists( $zone, 'get_shipping_methods' ) ) {
			return $out;
		}
		foreach ( $zone->get_shipping_methods( $enabled_only ) as $method ) {
			if ( ! is_object( $method ) || empty( $method->id ) ) {
				continue;
			}
			$instance = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
			$raw      = $instance ? get_option( 'woocommerce_' . $method->id . '_' . $instance . '_settings', array() ) : array();
			$out[]    = array(
				'id'          => (string) $method->id,
				'instance_id' => $instance,
				'title'       => method_exists( $method, 'get_method_title' ) ? (string) ( isset( $raw['title'] ) && '' !== $raw['title'] ? $raw['title'] : $method->get_method_title() ) : (string) $method->id,
				'enabled'     => isset( $method->enabled ) && 'yes' === $method->enabled,
				'settings'    => is_array( $raw ) ? $raw : array(),
			);
		}
		return $out;
	}

	/**
	 * Tags each rate with the currency its amount is in, and hides unpriced
	 * rates when that setting is on (never all of them).
	 *
	 * @param mixed $rates   Rates.
	 * @param mixed $package Package.
	 * @return mixed
	 */
	public static function filter_package_rates( $rates, $package ) {
		if ( ! is_array( $rates ) || empty( $rates ) || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $rates;
		}
		$active = RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
		$base   = RWGCM_Price_Book_Currency::base_currency();
		$forced = false;
		if ( $active !== $base && is_array( $package ) ) {
			$computed = self::computed_priced( $active, array( $package ) );
			$forced   = ! $computed && RWGCM_Price_Book_Currency::shipping_priced( $active, array( $package ) );
		}
		$settings = array();
		$priced   = array();
		foreach ( $rates as $rate_id => $rate ) {
			if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_method_id' ) ) {
				continue;
			}
			$mid  = (string) $rate->get_method_id();
			$inst = method_exists( $rate, 'get_instance_id' ) ? (int) $rate->get_instance_id() : 0;
			if ( 'pickup_location' === $mid ) {
				$status_settings = self::pickup_rate_settings( $rate );
			} else {
				if ( ! isset( $settings[ $mid . ':' . $inst ] ) ) {
					$raw                            = $inst ? get_option( 'woocommerce_' . $mid . '_' . $inst . '_settings', array() ) : array();
					$settings[ $mid . ':' . $inst ] = is_array( $raw ) ? $raw : array();
				}
				$status_settings = $settings[ $mid . ':' . $inst ];
			}
			$status = self::method_status( $mid, $status_settings, $active, $base );
			$cur    = self::rate_currency( $status['priced'], $forced, $active, $base );
			if ( method_exists( $rate, 'add_meta_data' ) ) {
				$rate->add_meta_data( self::META_PRICED, $cur );
				if ( $forced && ! $status['priced'] ) {
					$rate->add_meta_data( self::META_SOURCE, 'filter' );
				}
			}
			$priced[ $rate_id ] = $cur === $active;
		}
		if ( $active !== $base && self::hide_unpriced() && in_array( true, $priced, true ) ) {
			foreach ( $priced as $rate_id => $ok ) {
				if ( ! $ok ) {
					unset( $rates[ $rate_id ] );
				}
			}
		}
		return $rates;
	}

	/**
	 * @param bool   $priced Method priced in the active currency.
	 * @param bool   $forced Site filter declared shipping priced.
	 * @param string $active Active currency.
	 * @param string $base   Store currency.
	 * @return string
	 */
	public static function rate_currency( $priced, $forced, $active, $base ) {
		return ( $priced || $forced ) ? $active : $base;
	}

	/**
	 * Order guard: in a non-store currency every shipping line must carry
	 * the order currency as its "priced in" meta, so a store-currency-only rate
	 * is never charged as another currency. Store-currency orders pass.
	 *
	 * @param array<int, array{total: float, meta: string}> $lines    Shipping lines.
	 * @param string                                        $currency Order currency.
	 * @param string                                        $base     Store currency.
	 * @return bool
	 */
	public static function shipping_lines_ok( array $lines, $currency, $base ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		if ( '' === $currency || $currency === RWGCM_Price_Book::normalize_currency( $base ) ) {
			return true;
		}
		foreach ( $lines as $line ) {
			$meta = isset( $line['meta'] ) ? RWGCM_Price_Book::normalize_currency( $line['meta'] ) : '';
			if ( $meta !== $currency ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Short reason for a method status, for the Shipping overview.
	 *
	 * @param array{priced: bool, reason: string, amount: string} $status Status.
	 * @param string                                              $currency Currency.
	 * @param string                                              $method_id Method id.
	 * @return string
	 */
	public static function reason_label( array $status, $currency, $method_id = '' ) {
		switch ( $status['reason'] ) {
			case 'override':
				return 'free_shipping' === $method_id
					/* translators: 1: currency, 2: amount. */
					? sprintf( __( 'minimum %1$s %2$s', 'reactwoo-geo-commerce' ), $currency, $status['amount'] )
					: $currency . ' ' . $status['amount'];
			case 'zero':
				return __( 'free (no amount needed)', 'reactwoo-geo-commerce' );
			case 'coupon_only':
				return __( 'coupon only (no amount needed)', 'reactwoo-geo-commerce' );
			case 'no_minimum':
				return __( 'no minimum (no amount needed)', 'reactwoo-geo-commerce' );
			case 'class_costs':
				/* translators: %s: currency code. */
				return sprintf( __( 'class costs need a %s amount', 'reactwoo-geo-commerce' ), $currency );
			case 'unsupported':
				return __( 'carrier, store currency only', 'reactwoo-geo-commerce' );
			case 'missing':
				/* translators: %s: currency code. */
				return sprintf( __( 'no %s amount', 'reactwoo-geo-commerce' ), $currency );
		}
		return '';
	}

	/**
	 * Read-only overview data: one row per zone method, one verdict per zone
	 * and currency.
	 *
	 * @param array<int, array{id: int, name: string, methods: array<int, array<string, mixed>>}> $zones Zones (all methods, enabled flag included).
	 * @param array<int, string>                                                                    $currencies Extra currencies.
	 * @param string                                                                                $base Store currency.
	 * @param bool                                                                                  $hide Hide unpriced methods.
	 * @return array<int, array<string, mixed>>
	 */
	public static function overview( array $zones, array $currencies, $base, $hide ) {
		$out = array();
		foreach ( $zones as $zone ) {
			$enabled = array();
			$rows    = array();
			foreach ( (array) $zone['methods'] as $m ) {
				$cells = array();
				foreach ( $currencies as $cur ) {
					$st           = self::method_status( (string) $m['id'], (array) $m['settings'], $cur, $base );
					$cells[ $cur ] = array(
						'priced' => $st['priced'],
						'label'  => self::reason_label( $st, $cur, (string) $m['id'] ),
					);
				}
				$rows[] = array(
					'title'       => (string) ( $m['title'] ?? $m['id'] ),
					'id'          => (string) $m['id'],
					'instance_id' => (int) ( $m['instance_id'] ?? 0 ),
					'enabled'     => ! empty( $m['enabled'] ),
					'cells'       => $cells,
				);
				if ( ! empty( $m['enabled'] ) ) {
					$enabled[] = $m;
				}
			}
			$verdicts = array();
			foreach ( $currencies as $cur ) {
				$verdicts[ $cur ] = empty( $enabled ) ? null : self::zones_priced( array( $enabled ), $cur, $base, $hide );
			}
			$out[] = array(
				'id'       => (int) $zone['id'],
				'name'     => (string) $zone['name'],
				'rows'     => $rows,
				'verdicts' => $verdicts,
			);
		}
		return $out;
	}

	/**
	 * Every zone including "Locations not covered by your other zones".
	 *
	 * @return array<int, array{id: int, name: string, methods: array<int, array<string, mixed>>}>
	 */
	public static function all_zones() {
		$out = array();
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $out;
		}
		foreach ( WC_Shipping_Zones::get_zones( 'admin' ) as $data ) {
			$zone  = WC_Shipping_Zones::get_zone( (int) $data['zone_id'] );
			$out[] = array(
				'id'      => (int) $data['zone_id'],
				'name'    => (string) $data['zone_name'],
				'methods' => self::zone_methods( $zone, false ),
			);
		}
		$rest  = WC_Shipping_Zones::get_zone( 0 );
		$out[] = array(
			'id'      => 0,
			'name'    => $rest && method_exists( $rest, 'get_zone_name' ) ? (string) $rest->get_zone_name() : __( 'Locations not covered by your other zones', 'reactwoo-geo-commerce' ),
			'methods' => self::zone_methods( $rest, false ),
		);
		return $out;
	}

	/**
	 * Tests: forget memoized zones.
	 *
	 * @return void
	 */
	public static function reset_memo() {
		self::$memo = array();
	}

	/**
	 * Stored block-pickup amounts. Absence of a key means that location is not priced.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function pickup_costs() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_PICKUP_COSTS, array() ) : array();
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function stored_pickup_locations() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$rows = get_option( 'pickup_location_pickup_locations', array() );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Shared GBP cost from WooCommerce's pickup method settings.
	 *
	 * @return string
	 */
	public static function pickup_shared_cost() {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}
		$settings = get_option( 'woocommerce_pickup_location_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings['cost'] ) ) {
			return '';
		}
		return trim( (string) $settings['cost'] );
	}

	/**
	 * Block local pickup is offered only when WooCommerce has enabled the method.
	 *
	 * @return bool
	 */
	public static function pickup_method_enabled() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$settings = get_option( 'woocommerce_pickup_location_settings', array() );
		return is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === (string) $settings['enabled'];
	}

	/**
	 * Opaque location id. WooCommerce's rate id uses the array index; a location
	 * that already has an id keeps that string. This does not invent an id from
	 * the name or address.
	 *
	 * @param array<string, mixed> $location Location row.
	 * @param int|string           $index    Array index.
	 * @return string
	 */
	public static function pickup_location_id( array $location, $index ) {
		if ( isset( $location['id'] ) && is_scalar( $location['id'] ) ) {
			$id = self::sanitize_location_id( $location['id'] );
			if ( '' !== $id ) {
				return $id;
			}
		}
		return self::sanitize_location_id( (string) $index );
	}

	/**
	 * @param mixed $id Raw id.
	 * @return string
	 */
	public static function sanitize_location_id( $id ) {
		$id = trim( (string) $id );
		if ( '' === $id || strlen( $id ) > 100 ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9_.:-]+$/', $id ) ) {
			return '';
		}
		return $id;
	}

	/**
	 * @param array<string, mixed> $location Location.
	 * @return bool
	 */
	public static function location_is_enabled( array $location ) {
		if ( ! array_key_exists( 'enabled', $location ) ) {
			return true;
		}
		$value = $location['enabled'];
		if ( is_bool( $value ) ) {
			return $value;
		}
		return ! in_array( strtolower( trim( (string) $value ) ), array( '', '0', 'no', 'false' ), true );
	}

	/**
	 * GBP cost shown for a location. A cost on the location wins; otherwise the
	 * method cost. Neither value is written by this plugin.
	 *
	 * @param array<string, mixed> $location Location.
	 * @param string               $shared   Method cost.
	 * @return string
	 */
	public static function location_gbp_cost( array $location, $shared ) {
		if ( isset( $location['cost'] ) && '' !== trim( (string) $location['cost'] ) ) {
			return trim( (string) $location['cost'] );
		}
		return trim( (string) $shared );
	}

	/**
	 * Enabled pickup locations as methods for the currency decision.
	 * A disabled method, or a method with no enabled locations, does not block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function pickup_methods_for_currency_decision() {
		if ( ! self::pickup_method_enabled() ) {
			return array();
		}
		$shared = self::pickup_shared_cost();
		$costs  = self::pickup_costs();
		$out    = array();
		foreach ( self::stored_pickup_locations() as $index => $location ) {
			if ( ! is_array( $location ) || ! self::location_is_enabled( $location ) ) {
				continue;
			}
			$id    = self::pickup_location_id( $location, $index );
			$out[] = array(
				'id'          => 'pickup_location',
				'instance_id' => 0,
				'title'       => isset( $location['name'] ) ? (string) $location['name'] : $id,
				'enabled'     => true,
				'settings'    => array(
					'cost'          => self::location_gbp_cost( $location, $shared ),
					'location_id'   => $id,
					'pickup_costs'  => $costs,
				),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $settings Settings including location_id and pickup_costs.
	 * @param string               $currency Currency.
	 * @return array{priced: bool, reason: string, amount: string}
	 */
	public static function pickup_method_status( array $settings, $currency ) {
		$cost   = isset( $settings['cost'] ) ? trim( (string) $settings['cost'] ) : '';
		$loc    = isset( $settings['location_id'] ) ? trim( (string) $settings['location_id'] ) : '';
		$manual = '' !== $loc ? self::lookup_pickup_amount( $settings, $loc, $currency ) : '';
		if ( '' !== $manual ) {
			return array( 'priced' => true, 'reason' => 'override', 'amount' => $manual );
		}
		if ( self::is_zero( $cost ) ) {
			return array( 'priced' => true, 'reason' => 'zero', 'amount' => '' );
		}
		return array( 'priced' => false, 'reason' => 'missing', 'amount' => '' );
	}

	/**
	 * @param array<string, mixed> $settings    Settings.
	 * @param string               $location_id Location id.
	 * @param string               $currency    Currency.
	 * @return string
	 */
	public static function lookup_pickup_amount( array $settings, $location_id, $currency ) {
		$map = isset( $settings['pickup_costs'] ) && is_array( $settings['pickup_costs'] ) ? $settings['pickup_costs'] : self::pickup_costs();
		$location_id = (string) $location_id;
		$currency    = RWGCM_Price_Book::normalize_currency( $currency );
		if ( '' === $location_id || '' === $currency || ! isset( $map[ $location_id ] ) || ! is_array( $map[ $location_id ] ) ) {
			return '';
		}
		return isset( $map[ $location_id ][ $currency ] ) ? trim( (string) $map[ $location_id ][ $currency ] ) : '';
	}

	/**
	 * Settings for one calculated pickup rate. The rate id is pickup_location:{index}.
	 *
	 * @param mixed $rate WC_Shipping_Rate.
	 * @return array<string, mixed>
	 */
	public static function pickup_rate_settings( $rate ) {
		$rate_id   = ( is_object( $rate ) && method_exists( $rate, 'get_id' ) ) ? (string) $rate->get_id() : '';
		$index     = self::pickup_index_from_rate_id( $rate_id );
		$locations = self::stored_pickup_locations();
		$location  = ( null !== $index && isset( $locations[ $index ] ) && is_array( $locations[ $index ] ) ) ? $locations[ $index ] : array();
		$id        = self::pickup_location_id( $location, null === $index ? '' : (string) $index );
		return array(
			'cost'         => self::location_gbp_cost( $location, self::pickup_shared_cost() ),
			'location_id'  => $id,
			'pickup_costs' => self::pickup_costs(),
		);
	}

	/**
	 * @param string $rate_id Rate id.
	 * @return int|null
	 */
	public static function pickup_index_from_rate_id( $rate_id ) {
		$rate_id = (string) $rate_id;
		if ( 1 !== preg_match( '/^pickup_location:(\d+)$/', $rate_id, $m ) ) {
			return null;
		}
		return (int) $m[1];
	}

	/**
	 * Manual amount for a pickup rate, or null when the location has none.
	 * Null leaves WooCommerce's GBP cost in place.
	 *
	 * @param string $rate_id  Rate id.
	 * @param string $currency Active currency.
	 * @return string|null
	 */
	public static function pickup_manual_amount_for_rate( $rate_id, $currency ) {
		$index     = self::pickup_index_from_rate_id( $rate_id );
		$locations = self::stored_pickup_locations();
		$location  = ( null !== $index && isset( $locations[ $index ] ) && is_array( $locations[ $index ] ) ) ? $locations[ $index ] : array();
		$id        = self::pickup_location_id( $location, null === $index ? '' : (string) $index );
		$amount    = self::lookup_pickup_amount( array( 'pickup_costs' => self::pickup_costs() ), $id, $currency );
		return '' === $amount ? null : $amount;
	}

	/**
	 * Replace a block-pickup rate cost before WooCommerce calculates shipping tax.
	 * Does not write the location list or the GBP cost.
	 *
	 * @param mixed $args   Rate args.
	 * @param mixed $method Shipping method.
	 * @return mixed
	 */
	public static function filter_add_rate_args( $args, $method ) {
		if ( ! is_array( $args ) || ! is_object( $method ) || ! isset( $method->id ) || 'pickup_location' !== $method->id ) {
			return $args;
		}
		if ( RWGCM_Price_Book_Currency::forces_store_currency() || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $args;
		}
		$active = RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
		$base   = RWGCM_Price_Book::normalize_currency( RWGCM_Price_Book_Currency::base_currency() );
		if ( '' === $active || $active === $base ) {
			return $args;
		}
		$amount = self::pickup_manual_amount_for_rate( isset( $args['id'] ) ? (string) $args['id'] : '', $active );
		if ( null === $amount ) {
			return $args;
		}
		$args['cost'] = $amount;
		return $args;
	}

	/**
	 * Numeric pickup amount. Blank stays blank. Anything else is rejected so a
	 * formula cannot be stored as if it were a fixed cost.
	 *
	 * @param mixed $raw Posted value.
	 * @return string
	 */
	public static function sanitize_pickup_amount( $raw ) {
		$raw = trim( (string) $raw );
		$raw = str_replace( ' ', '', $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! preg_match( '/^\d+([.,]\d+)?$/', $raw ) ) {
			return '';
		}
		return str_replace( ',', '.', $raw );
	}

	/**
	 * Merge posted pickup amounts into the side option.
	 * Shown ids are replaced. Ids that were not on the form are kept.
	 * Deleted ids are removed. GBP location rows are not part of this array.
	 *
	 * @param array<string, mixed> $stored     Current option.
	 * @param array<string, mixed> $posted     id => currency => raw amount.
	 * @param array<int, mixed>    $shown_ids  Location ids on the form.
	 * @param array<int, mixed>    $delete_ids Orphan ids to remove.
	 * @param array<int, string>   $currencies Extra currencies.
	 * @return array<string, array<string, string>>
	 */
	public static function merge_pickup_costs( array $stored, array $posted, array $shown_ids, array $delete_ids, array $currencies ) {
		$allowed = array();
		foreach ( $currencies as $code ) {
			$code = RWGCM_Price_Book::normalize_currency( $code );
			if ( '' !== $code ) {
				$allowed[ $code ] = true;
			}
		}
		$out = array();
		foreach ( $stored as $id => $row ) {
			$id = self::sanitize_location_id( $id );
			if ( '' === $id || ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $row as $code => $amount ) {
				$code   = RWGCM_Price_Book::normalize_currency( $code );
				$amount = self::sanitize_pickup_amount( $amount );
				if ( '' !== $code && isset( $allowed[ $code ] ) && '' !== $amount ) {
					$clean[ $code ] = $amount;
				}
			}
			if ( ! empty( $clean ) ) {
				$out[ $id ] = $clean;
			}
		}
		$shown = array();
		foreach ( $shown_ids as $id ) {
			$id = self::sanitize_location_id( $id );
			if ( '' !== $id ) {
				$shown[ $id ] = true;
			}
		}
		$delete = array();
		foreach ( $delete_ids as $id ) {
			$id = self::sanitize_location_id( $id );
			if ( '' !== $id ) {
				$delete[ $id ] = true;
			}
		}
		foreach ( array_keys( $shown ) as $id ) {
			if ( isset( $delete[ $id ] ) ) {
				unset( $out[ $id ] );
				continue;
			}
			$row    = array();
			$posted_row = ( isset( $posted[ $id ] ) && is_array( $posted[ $id ] ) ) ? $posted[ $id ] : array();
			foreach ( array_keys( $allowed ) as $code ) {
				$amount = self::sanitize_pickup_amount( isset( $posted_row[ $code ] ) ? $posted_row[ $code ] : '' );
				if ( '' !== $amount ) {
					$row[ $code ] = $amount;
				}
			}
			if ( empty( $row ) ) {
				unset( $out[ $id ] );
			} else {
				$out[ $id ] = $row;
			}
		}
		foreach ( array_keys( $delete ) as $id ) {
			unset( $out[ $id ] );
		}
		return $out;
	}

	/**
	 * Cards for the Shipping tab, plus stored ids that no longer match a location.
	 *
	 * @param array<int, mixed>    $locations   WooCommerce locations.
	 * @param string               $shared_cost Method GBP cost.
	 * @param array<string, mixed> $stored      Side option.
	 * @return array{cards: array<int, array<string, mixed>>, orphans: array<int, array<string, mixed>>}
	 */
	public static function pickup_admin_model( array $locations, $shared_cost, array $stored ) {
		$cards = array();
		$ids   = array();
		foreach ( $locations as $index => $location ) {
			if ( ! is_array( $location ) ) {
				continue;
			}
			$id         = self::pickup_location_id( $location, $index );
			$ids[ $id ] = true;
			$cards[]    = array(
				'id'       => $id,
				'name'     => isset( $location['name'] ) && '' !== (string) $location['name'] ? (string) $location['name'] : $id,
				'enabled'  => self::location_is_enabled( $location ),
				'gbp_cost' => self::location_gbp_cost( $location, $shared_cost ),
				'amounts'  => ( isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ) ? $stored[ $id ] : array(),
			);
		}
		$orphans = array();
		foreach ( $stored as $id => $amounts ) {
			$id = (string) $id;
			if ( isset( $ids[ $id ] ) || ! is_array( $amounts ) ) {
				continue;
			}
			$orphans[] = array(
				'id'      => $id,
				'amounts' => $amounts,
			);
		}
		return array(
			'cards'   => $cards,
			'orphans' => $orphans,
		);
	}
}
