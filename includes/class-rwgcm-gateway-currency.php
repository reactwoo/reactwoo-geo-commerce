<?php
/**
 * Opt-in currency compatibility for payment gateways.
 *
 * A gateway with no saved currency list is left alone. Paystack WooCommerce
 * Payment Gateway 5.8.5 (wordpress.org plugin woo-paystack, id paystack)
 * disables itself unless get_woocommerce_currency() is in
 * woocommerce_paystack_supported_currencies (default NGN, USD, ZAR, GHS, KES,
 * XOF, EGP, RWF). That filter only widens the allow-list. It cannot make a
 * GBP store currency look like ZAR, so the gateway settings screen uses a
 * scoped woocommerce_currency filter instead. Product saves, REST, cron, and
 * WP-CLI keep the store currency from 0.4.1.
 *
 * Paystack already initializes and verifies with $order->get_currency() and
 * amount * 100. ZAR minor units are cents, so that matches. XOF and RWF are
 * ISO zero-decimal currencies, but Paystack 5.8.5 still multiplies those by 100.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway currency allow-lists and the settings-screen currency override.
 */
class RWGCM_Gateway_Currency {

	const OPTION_KEY = 'rwgcm_gateway_currencies';

	/**
	 * Paystack reads this filter inside is_valid_for_use(). The same method is
	 * inherited by paystack-one through paystack-five.
	 */
	const PAYSTACK_CURRENCIES_FILTER = 'woocommerce_paystack_supported_currencies';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_currency', array( __CLASS__, 'filter_currency' ), 30 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ), 30 );
		add_filter( self::PAYSTACK_CURRENCIES_FILTER, array( __CLASS__, 'filter_paystack_supported_currencies' ) );
		add_filter( 'woocommerce_no_available_payment_methods_message', array( __CLASS__, 'filter_no_methods_message' ), 20 );
	}

	/**
	 * Gateways removed by currency lists on this request.
	 *
	 * @var array<int, string>
	 */
	private static $removed_for_currency = array();

	/**
	 * Classic checkout: say why nothing is offered when the currency lists
	 * removed every available gateway.
	 *
	 * @param mixed $message WooCommerce message.
	 * @return mixed
	 */
	public static function filter_no_methods_message( $message ) {
		if ( empty( self::$removed_for_currency ) || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $message;
		}
		return self::no_methods_message( get_woocommerce_currency() );
	}

	/**
	 * @param string $currency Currency.
	 * @return string
	 */
	public static function no_methods_message( $currency ) {
		return sprintf(
			/* translators: %s: currency code, for example ZAR. */
			__( 'No payment method can take payments in %s right now. Please contact us to complete your order.', '[REDACTED]-geo-commerce' ),
			RWGCM_Price_Book::normalize_currency( $currency )
		);
	}

	/**
	 * Settings screen only. Everywhere else the currency argument is unchanged,
	 * including the store currency 0.4.1 forces on writes.
	 *
	 * @param string $currency Currency after earlier filters.
	 * @return string
	 */
	public static function filter_currency( $currency ) {
		$override = self::currency_override_for_request( $currency, self::request_context() );
		return null === $override ? $currency : $override;
	}

	/**
	 * Drop a configured gateway unless the cart or order currency is allowed.
	 * Admin, cron, CLI, and non-Store-API REST are unchanged.
	 *
	 * @param mixed $gateways Gateway id => gateway.
	 * @return mixed
	 */
	public static function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ! self::should_filter_offer( self::request_context() ) ) {
			return $gateways;
		}
		$currency = self::payment_currency();
		if ( '' === $currency ) {
			return $gateways;
		}
		$out = self::select_for_currency( $gateways, $currency, self::stored_currencies() );
		self::$removed_for_currency = empty( $out ) ? array_map( 'strval', array_keys( $gateways ) ) : array();
		if ( empty( $out ) && ! empty( $gateways ) && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( 'Payments by country: no available gateway is allowed for ' . $currency . ' (removed: ' . implode( ', ', array_keys( $gateways ) ) . ').', array( 'source' => 'rwgcm-gateways' ) );
		}
		return $out;
	}

	/**
	 * Keep Paystack's allow-list, and add currencies saved for paystack ids.
	 * The store currency is not added.
	 *
	 * @param mixed $currencies Currency codes.
	 * @return array<int, string>
	 */
	public static function filter_paystack_supported_currencies( $currencies ) {
		return self::merge_currencies( $currencies, self::paystack_family_currencies( self::stored_currencies() ) );
	}

	/**
	 * @param string               $store_currency Currency the request would otherwise use.
	 * @param array<string, mixed> $context        page, tab, section, cron, cli, rest, syncing, lists, charged.
	 * @return string|null Mapped currency, or null to leave the request alone.
	 */
	public static function currency_override_for_request( $store_currency, array $context ) {
		if ( ! empty( $context['cron'] ) || ! empty( $context['cli'] ) || ! empty( $context['rest'] ) || ! empty( $context['syncing'] ) ) {
			return null;
		}
		$page    = isset( $context['page'] ) ? (string) $context['page'] : '';
		$tab     = isset( $context['tab'] ) ? (string) $context['tab'] : '';
		$section = isset( $context['section'] ) ? self::normalize_gateway_id( $context['section'] ) : '';
		if ( 'wc-settings' !== $page || 'checkout' !== $tab || '' === $section ) {
			return null;
		}
		$lists    = ( isset( $context['lists'] ) && is_array( $context['lists'] ) ) ? $context['lists'] : array();
		$charged  = ( isset( $context['charged'] ) && is_array( $context['charged'] ) ) ? $context['charged'] : array();
		$supported = isset( $lists[ $section ] ) && is_array( $lists[ $section ] ) ? $lists[ $section ] : array();
		return self::settings_currency( $store_currency, $supported, $charged );
	}

	/**
	 * Null when this gateway is not configured or the store currency is already allowed.
	 *
	 * @param string            $store_currency Store currency.
	 * @param array<int, mixed> $supported      Currencies this gateway may take.
	 * @param array<int, mixed> $charged        Currencies the country map actually charges.
	 * @return string|null
	 */
	public static function settings_currency( $store_currency, array $supported, array $charged ) {
		$store     = RWGCM_Price_Book::normalize_currency( $store_currency );
		$supported = self::normalize_currency_list( $supported );
		if ( empty( $supported ) || ( '' !== $store && in_array( $store, $supported, true ) ) ) {
			return null;
		}
		$charged = self::normalize_currency_list( $charged );
		foreach ( $supported as $code ) {
			if ( in_array( $code, $charged, true ) ) {
				return $code;
			}
		}
		return $supported[0];
	}

	/**
	 * @param array<string, bool> $context Request flags.
	 * @return bool
	 */
	public static function should_filter_offer( array $context ) {
		if ( ! empty( $context['cron'] ) || ! empty( $context['cli'] ) || ! empty( $context['syncing'] ) ) {
			return false;
		}
		if ( ! empty( $context['rest'] ) && empty( $context['store_api'] ) ) {
			return false;
		}
		if ( ! empty( $context['admin'] ) && empty( $context['front_ajax'] ) && empty( $context['store_api'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Gateways with no list stay. A listed gateway stays only for a matching currency.
	 *
	 * @param array<string, mixed>           $gateways Gateways.
	 * @param string                         $currency Cart or order currency.
	 * @param array<string, array<int, string>> $lists Gateway id => currencies.
	 * @return array<string, mixed>
	 */
	public static function select_for_currency( array $gateways, $currency, array $lists ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		$out      = array();
		foreach ( $gateways as $id => $gateway ) {
			if ( self::offered_for_currency( $id, $currency, $lists ) ) {
				$out[ $id ] = $gateway;
			}
		}
		return $out;
	}

	/**
	 * @param string                               $gateway_id Gateway id.
	 * @param string                               $currency   Cart or order currency.
	 * @param array<string, array<int, string>>    $lists      Saved lists.
	 * @return bool
	 */
	public static function offered_for_currency( $gateway_id, $currency, array $lists ) {
		$id = self::normalize_gateway_id( $gateway_id );
		if ( '' === $id || ! isset( $lists[ $id ] ) || ! is_array( $lists[ $id ] ) || empty( $lists[ $id ] ) ) {
			return true;
		}
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		return '' !== $currency && in_array( $currency, self::normalize_currency_list( $lists[ $id ] ), true );
	}

	/**
	 * Currency a gateway availability check should see. Payment uses the cart
	 * or order currency. The settings screen uses the mapped currency.
	 *
	 * @param string               $store_currency  Store currency.
	 * @param string               $active_currency Visitor, cart, or order currency.
	 * @param array<string, mixed> $context         Same shape as currency_override_for_request, plus payment.
	 * @return string
	 */
	public static function currency_seen_by_gateway( $store_currency, $active_currency, array $context ) {
		if ( ! empty( $context['payment'] ) ) {
			$active = RWGCM_Price_Book::normalize_currency( $active_currency );
			return '' !== $active ? $active : RWGCM_Price_Book::normalize_currency( $store_currency );
		}
		$override = self::currency_override_for_request( $store_currency, $context );
		if ( null !== $override ) {
			return $override;
		}
		$store = RWGCM_Price_Book::normalize_currency( $store_currency );
		return '' !== $store ? $store : RWGCM_Price_Book::normalize_currency( $active_currency );
	}

	/**
	 * Charge payload in the order currency's minor units.
	 *
	 * @param string|float $order_total    Order total in major units.
	 * @param string       $order_currency Order currency.
	 * @return array{currency: string, amount: int, minor_unit_exponent: int}
	 */
	public static function charge_payload( $order_total, $order_currency ) {
		$currency = RWGCM_Price_Book::normalize_currency( $order_currency );
		$exponent = self::minor_unit_exponent( $currency );
		$amount   = (int) round( (float) $order_total * pow( 10, $exponent ) );
		return array(
			'currency'            => $currency,
			'amount'              => $amount,
			'minor_unit_exponent' => $exponent,
		);
	}

	/**
	 * Paystack verify divides the minor amount back to major units and requires
	 * the payment currency to match the order currency.
	 *
	 * @param int|float    $minor_paid       Amount paid in minor units.
	 * @param string|float $order_total      Order total in major units.
	 * @param string       $payment_currency Currency Paystack reported.
	 * @param string       $order_currency   Order currency.
	 * @return bool
	 */
	public static function payment_matches_order( $minor_paid, $order_total, $payment_currency, $order_currency ) {
		$payment = RWGCM_Price_Book::normalize_currency( $payment_currency );
		$order   = RWGCM_Price_Book::normalize_currency( $order_currency );
		if ( '' === $payment || $payment !== $order ) {
			return false;
		}
		$exponent = self::minor_unit_exponent( $order );
		$paid     = ( (float) $minor_paid ) / pow( 10, $exponent );
		return round( $paid, $exponent ) >= round( (float) $order_total, $exponent );
	}

	/**
	 * ISO 4217 minor-unit exponent. ZAR, USD, NGN, GHS, KES, and EGP are 2.
	 * XOF and RWF are 0 here; Paystack 5.8.5 still sends those as amount * 100.
	 *
	 * @param string $currency Currency code.
	 * @return int
	 */
	public static function minor_unit_exponent( $currency ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		$zero     = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		$three    = array( 'BHD', 'IQD', 'JOD', 'KWD', 'OMR', 'TND' );
		if ( in_array( $currency, $zero, true ) ) {
			return 0;
		}
		if ( in_array( $currency, $three, true ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * @param array<string, array<int, string>> $lists Saved lists.
	 * @return array<int, string>
	 */
	public static function paystack_family_currencies( array $lists ) {
		$extra = array();
		foreach ( $lists as $id => $codes ) {
			$id = self::normalize_gateway_id( $id );
			if ( 'paystack' !== $id && 0 !== strpos( $id, 'paystack-' ) ) {
				continue;
			}
			if ( ! is_array( $codes ) ) {
				continue;
			}
			foreach ( self::normalize_currency_list( $codes ) as $code ) {
				$extra[] = $code;
			}
		}
		return self::normalize_currency_list( $extra );
	}

	/**
	 * @param mixed             $existing Existing allow-list.
	 * @param array<int, mixed> $extra    Currencies to add.
	 * @return array<int, string>
	 */
	public static function merge_currencies( $existing, array $extra ) {
		$seed = is_array( $existing ) ? $existing : array();
		return self::normalize_currency_list( array_merge( $seed, $extra ) );
	}

	/**
	 * Currencies implied by country gateway rules and the country map.
	 * Shown as a hint. It is not saved on its own.
	 *
	 * @param string                            $gateway_id  Gateway id.
	 * @param array<string, mixed>              $rules       Country and group rules.
	 * @param array<string, string>             $country_map ISO2 => currency.
	 * @return array<int, string>
	 */
	public static function currencies_covered_by_country_rules( $gateway_id, array $rules, array $country_map ) {
		$id        = self::normalize_gateway_id( $gateway_id );
		$countries = ( isset( $rules['countries'] ) && is_array( $rules['countries'] ) ) ? $rules['countries'] : array();
		$found     = array();
		foreach ( $countries as $country => $ids ) {
			if ( ! is_array( $ids ) || ! in_array( $id, $ids, true ) ) {
				continue;
			}
			$country = RWGCM_Price_Book::normalize_country( $country );
			if ( '' === $country || ! isset( $country_map[ $country ] ) ) {
				continue;
			}
			$found[] = $country_map[ $country ];
		}
		return self::normalize_currency_list( $found );
	}

	/**
	 * @param mixed $raw Posted or stored map.
	 * @return array<string, array<int, string>>
	 */
	public static function sanitize_lists( $raw ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $id => $codes ) {
			$id = self::normalize_gateway_id( $id );
			if ( '' === $id ) {
				continue;
			}
			if ( is_string( $codes ) ) {
				$codes = preg_split( '/[^A-Za-z]+/', $codes );
			}
			if ( ! is_array( $codes ) ) {
				continue;
			}
			$list = self::normalize_currency_list( $codes );
			if ( ! empty( $list ) ) {
				$out[ $id ] = $list;
			}
		}
		return $out;
	}

	/**
	 * Replace only gateway ids present in the posted form. A blank value clears that gateway.
	 * Ids the form did not submit stay as they were saved.
	 *
	 * @param array<string, mixed> $existing Stored lists.
	 * @param mixed                $posted   Posted id => currency text or list.
	 * @return array<string, array<int, string>>
	 */
	public static function merge_posted_lists( array $existing, $posted ) {
		$out = self::sanitize_lists( $existing );
		if ( ! is_array( $posted ) ) {
			return $out;
		}
		foreach ( $posted as $id => $codes ) {
			$id = self::normalize_gateway_id( $id );
			if ( '' === $id ) {
				continue;
			}
			$one = self::sanitize_lists( array( $id => $codes ) );
			if ( isset( $one[ $id ] ) ) {
				$out[ $id ] = $one[ $id ];
			} else {
				unset( $out[ $id ] );
			}
		}
		return $out;
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function stored_currencies() {
		$raw = array();
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( self::OPTION_KEY, array() );
			$raw    = is_array( $stored ) ? $stored : array();
		}
		return self::sanitize_lists( $raw );
	}

	/**
	 * @param string $id Raw gateway id.
	 * @return string
	 */
	public static function normalize_gateway_id( $id ) {
		$id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
		return is_string( $id ) ? $id : '';
	}

	/**
	 * @param array<int, mixed> $codes Raw codes.
	 * @return array<int, string>
	 */
	private static function normalize_currency_list( array $codes ) {
		$out = array();
		foreach ( $codes as $code ) {
			$norm = RWGCM_Price_Book::normalize_currency( $code );
			if ( '' === $norm || in_array( $norm, $out, true ) ) {
				continue;
			}
			$out[] = $norm;
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function request_context() {
		$get     = isset( $_GET ) && is_array( $_GET ) ? $_GET : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page    = isset( $get['page'] ) ? sanitize_key( wp_unslash( $get['page'] ) ) : '';
		$tab     = isset( $get['tab'] ) ? sanitize_key( wp_unslash( $get['tab'] ) ) : '';
		$section = isset( $get['section'] ) ? self::normalize_gateway_id( wp_unslash( $get['section'] ) ) : '';
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$rest    = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' );
		$store   = $rest && ( false !== strpos( $uri, '/wc/store/' ) || false !== strpos( $uri, 'rest_route=/wc/store/' ) || false !== strpos( $uri, 'rest_route=%2Fwc%2Fstore%2F' ) );
		$charged = array();
		if ( class_exists( 'RWGCM_Price_Book', false ) ) {
			foreach ( RWGCM_Price_Book::country_currency_map() as $currency ) {
				$charged[] = $currency;
			}
		}
		return array(
			'page'       => function_exists( 'sanitize_key' ) ? $page : $page,
			'tab'        => $tab,
			'section'    => $section,
			'cron'       => ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ),
			'cli'        => defined( 'WP_CLI' ) && WP_CLI,
			'rest'       => $rest,
			'store_api'  => $store,
			'syncing'    => class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::is_product_sync(),
			'admin'      => function_exists( 'is_admin' ) && is_admin(),
			'front_ajax' => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'lists'      => self::stored_currencies(),
			'charged'    => $charged,
		);
	}

	/**
	 * Order-pay uses the order currency. Otherwise the active cart currency,
	 * which on the storefront is the visitor currency.
	 *
	 * @return string
	 */
	private static function payment_currency() {
		$order_id = 0;
		if ( function_exists( 'get_query_var' ) ) {
			$order_id = function_exists( 'absint' ) ? absint( get_query_var( 'order-pay' ) ) : (int) get_query_var( 'order-pay' );
		}
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( is_object( $order ) && method_exists( $order, 'get_currency' ) ) {
				return RWGCM_Price_Book::normalize_currency( $order->get_currency() );
			}
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
		}
		return '';
	}
}
