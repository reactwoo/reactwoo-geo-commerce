<?php
/**
 * Limit WooCommerce payment gateways by billing country.
 *
 * Rules are optional. A country with no country rule and no matching Geo Core
 * country group keeps every enabled gateway. Subscription renewals are not
 * filtered: the renewal charges the gateway already stored on the subscription.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout gateway availability.
 */
class RWGCM_Gateway_Availability {

	const OPTION_KEY = 'rwgcm_gateway_rules';

	/**
	 * Gateway ids from the latest gateway_choices() call that were missing from WooCommerce's registry.
	 *
	 * @var array<int, string>
	 */
	private static $last_unregistered_ids = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ), 20 );
	}

	/**
	 * @param mixed $gateways Gateway id => gateway.
	 * @return mixed
	 */
	public static function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			return $gateways;
		}
		$flags = self::current_flags();
		if ( ! self::should_restrict( $flags ) ) {
			return $gateways;
		}
		$country = isset( $flags['billing_country'] ) ? (string) $flags['billing_country'] : '';
		$rules   = self::stored_rules();
		$allowed = self::resolve_allowed_ids( $country, $rules, self::matching_group_gateway_lists( $country, $rules ) );
		if ( null === $allowed ) {
			return $gateways;
		}
		return self::select_gateway_ids( $gateways, $allowed );
	}

	/**
	 * Restrict only at checkout (or change-payment-method) when a billing country is known.
	 * The block checkout lists the payment methods from the Store API cart response
	 * (cart, cart/update-customer, batch), so those routes count as checkout too.
	 * Admin screens, renewals, and renewal pay-for-order keep the full gateway list.
	 *
	 * @param array<string, mixed> $flags Request flags.
	 * @return bool
	 */
	public static function should_restrict( array $flags ) {
		$admin      = ! empty( $flags['admin'] );
		$front_ajax = ! empty( $flags['front_ajax'] );
		if ( $admin && ! $front_ajax ) {
			return false;
		}
		if ( ! empty( $flags['renewal'] ) || ! empty( $flags['renewal_pay'] ) ) {
			return false;
		}
		$at_payment = ! empty( $flags['checkout'] ) || ! empty( $flags['change_payment'] ) || ! empty( $flags['store_api_checkout'] ) || ! empty( $flags['store_api_cart'] );
		if ( ! $at_payment ) {
			return false;
		}
		$country = isset( $flags['billing_country'] ) ? RWGCM_Price_Book::normalize_country( $flags['billing_country'] ) : '';
		return '' !== $country;
	}

	/**
	 * Country rule replaces group matches. Matching groups are unioned.
	 * Null means no rule (keep every enabled gateway). An empty list means none.
	 *
	 * @param string              $country             Billing ISO2.
	 * @param array<string, mixed> $rules              countries + groups.
	 * @param array<int, array>   $group_gateway_lists Gateway ids for each matching group.
	 * @return array<int, string>|null
	 */
	public static function resolve_allowed_ids( $country, array $rules, array $group_gateway_lists ) {
		$country   = RWGCM_Price_Book::normalize_country( $country );
		$countries = ( isset( $rules['countries'] ) && is_array( $rules['countries'] ) ) ? $rules['countries'] : array();
		if ( '' === $country ) {
			return null;
		}
		if ( array_key_exists( $country, $countries ) ) {
			$ids = $countries[ $country ];
			return is_array( $ids ) ? self::normalize_ids( $ids ) : array();
		}
		$union   = array();
		$matched = false;
		foreach ( $group_gateway_lists as $ids ) {
			if ( ! is_array( $ids ) ) {
				continue;
			}
			$matched = true;
			foreach ( self::normalize_ids( $ids ) as $id ) {
				if ( ! in_array( $id, $union, true ) ) {
					$union[] = $id;
				}
			}
		}
		return $matched ? $union : null;
	}

	/**
	 * @param string              $country         Billing ISO2.
	 * @param array<string, mixed> $rules          Stored rules.
	 * @param array<string, array> $group_countries Slug => ISO2 members.
	 * @return array<int, array<int, string>>
	 */
	public static function gateway_lists_for_country( $country, array $rules, array $group_countries ) {
		$country = RWGCM_Price_Book::normalize_country( $country );
		$groups  = ( isset( $rules['groups'] ) && is_array( $rules['groups'] ) ) ? $rules['groups'] : array();
		$lists   = array();
		if ( '' === $country ) {
			return $lists;
		}
		foreach ( $groups as $slug => $ids ) {
			$members = isset( $group_countries[ $slug ] ) && is_array( $group_countries[ $slug ] ) ? $group_countries[ $slug ] : array();
			$normalized = array();
			foreach ( $members as $member ) {
				$iso = RWGCM_Price_Book::normalize_country( $member );
				if ( '' !== $iso ) {
					$normalized[] = $iso;
				}
			}
			if ( in_array( $country, $normalized, true ) ) {
				$lists[] = is_array( $ids ) ? $ids : array();
			}
		}
		return $lists;
	}

	/**
	 * @param array<string, mixed> $gateways   Gateway id => gateway.
	 * @param array<int, string>   $allowed_ids Allowed ids.
	 * @return array<string, mixed>
	 */
	public static function select_gateway_ids( array $gateways, array $allowed_ids ) {
		$allowed_ids = self::normalize_ids( $allowed_ids );
		$out         = array();
		foreach ( $gateways as $id => $gateway ) {
			if ( in_array( (string) $id, $allowed_ids, true ) ) {
				$out[ $id ] = $gateway;
			}
		}
		return $out;
	}

	/**
	 * Drop empty selections. A missing key means "all enabled gateways".
	 *
	 * @param mixed $raw Posted or stored rules.
	 * @return array{countries: array<string, array<int, string>>, groups: array<string, array<int, string>>}
	 */
	public static function sanitize_rules( $raw ) {
		$rules = self::normalize_rules( $raw );
		foreach ( array( 'countries', 'groups' ) as $bucket ) {
			foreach ( $rules[ $bucket ] as $key => $ids ) {
				if ( empty( $ids ) ) {
					unset( $rules[ $bucket ][ $key ] );
				}
			}
		}
		return $rules;
	}

	/**
	 * Keep an explicit empty list so a stored key can mean "no gateways".
	 *
	 * @param mixed $raw Raw rules.
	 * @return array{countries: array<string, array<int, string>>, groups: array<string, array<int, string>>}
	 */
	public static function normalize_rules( $raw ) {
		$out = array(
			'countries' => array(),
			'groups'    => array(),
		);
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		if ( isset( $raw['countries'] ) && is_array( $raw['countries'] ) ) {
			foreach ( $raw['countries'] as $country => $ids ) {
				$country = RWGCM_Price_Book::normalize_country( $country );
				if ( '' === $country || ! is_array( $ids ) ) {
					continue;
				}
				$out['countries'][ $country ] = self::normalize_ids( $ids );
			}
		}
		if ( isset( $raw['groups'] ) && is_array( $raw['groups'] ) ) {
			foreach ( $raw['groups'] as $slug => $ids ) {
				$slug = self::normalize_slug( $slug );
				if ( '' === $slug || ! is_array( $ids ) ) {
					continue;
				}
				$out['groups'][ $slug ] = self::normalize_ids( $ids );
			}
		}
		return $out;
	}

	/**
	 * @return array{countries: array<string, array<int, string>>, groups: array<string, array<int, string>>}
	 */
	public static function stored_rules() {
		$raw = array();
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( self::OPTION_KEY, array() );
			$raw    = is_array( $stored ) ? $stored : array();
		}
		return self::normalize_rules( $raw );
	}

	/**
	 * Every registered WooCommerce gateway, including ones the gateway has switched off.
	 *
	 * Uses WC()->payment_gateways()->payment_gateways(), the full registry, then
	 * any gateway the woocommerce_payment_gateways filter still adds. Checkout
	 * does not use that fallback. The available-gateway list hides Paystack when
	 * its constructor sets enabled to false because the store currency is unsupported.
	 *
	 * @return array<string, array{title: string, enabled: bool}>
	 */
	public static function gateway_choices() {
		self::$last_unregistered_ids = array();
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}
		$wc = WC();
		if ( ! $wc || ! method_exists( $wc, 'payment_gateways' ) ) {
			return array();
		}
		$controller = $wc->payment_gateways();
		if ( ! is_object( $controller ) || ! method_exists( $controller, 'payment_gateways' ) ) {
			return array();
		}
		$all                         = $controller->payment_gateways();
		$all                         = is_array( $all ) ? $all : array();
		$missing                     = self::unregistered_gateway_instances( $all );
		self::$last_unregistered_ids = array_keys( $missing );
		foreach ( $missing as $id => $gateway ) {
			$all[ $id ] = $gateway;
		}
		return self::choices_from_gateways( $all );
	}

	/**
	 * Ids missing from WooCommerce's own list on the last gateway_choices() call.
	 * Checkout does not use these instances.
	 *
	 * @return array<int, string>
	 */
	public static function last_unregistered_gateway_ids() {
		return self::$last_unregistered_ids;
	}

	/**
	 * Warning when the admin list had to instantiate a gateway WooCommerce never registered.
	 *
	 * @param array<int, string>                              $ids     Gateway ids from the fallback.
	 * @param array<string, array{title?: string, enabled?: bool}> $choices Choices shown on the screen.
	 * @return string Empty when every listed gateway is in WooCommerce's registry.
	 */
	public static function unregistered_checkout_notice( array $ids, array $choices ) {
		$labels = array();
		foreach ( $ids as $id ) {
			$id = is_string( $id ) ? $id : '';
			if ( '' === $id ) {
				continue;
			}
			$title    = isset( $choices[ $id ]['title'] ) ? (string) $choices[ $id ]['title'] : $id;
			$labels[] = sprintf( '%s (%s)', $title, $id );
		}
		$count = count( $labels );
		if ( 0 === $count ) {
			return '';
		}
		return sprintf(
			/* translators: %s: gateway titles and ids, for example "Paystack (paystack)". */
			_n(
				'WooCommerce did not register %s; it will not show at checkout until that is fixed.',
				'WooCommerce did not register %s; they will not show at checkout until that is fixed.',
				$count,
				'reactwoo-geo-commerce'
			),
			implode( ', ', $labels )
		);
	}

	/**
	 * Gateways a plugin adds through woocommerce_payment_gateways that the WooCommerce
	 * registry does not hold. That happens when WC_Payment_Gateways was built before the
	 * plugin hooked in (Paystack 5.8.5 adds its filter on plugins_loaded at priority 99),
	 * or when two gateways share a woocommerce_gateway_order position and one overwrites
	 * the other. Admin listing only: checkout still uses the WooCommerce registry.
	 *
	 * @param array<string, mixed> $registered Gateway id => gateway from payment_gateways().
	 * @return array<string, object> Gateway id => instance, for ids not already registered.
	 */
	public static function unregistered_gateway_instances( array $registered ) {
		if ( ! function_exists( 'apply_filters' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
			return array();
		}
		$known_classes = array();
		foreach ( $registered as $gateway ) {
			if ( is_object( $gateway ) ) {
				$known_classes[ strtolower( get_class( $gateway ) ) ] = true;
			}
		}
		$entries = apply_filters( 'woocommerce_payment_gateways', array() );
		$out     = array();
		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			$gateway = null;
			if ( is_object( $entry ) ) {
				$gateway = $entry;
			} elseif ( is_string( $entry ) && '' !== $entry && ! isset( $known_classes[ strtolower( $entry ) ] ) && class_exists( $entry ) && is_subclass_of( $entry, 'WC_Payment_Gateway' ) ) {
				try {
					$gateway = new $entry();
				} catch ( \Throwable $e ) {
					continue;
				}
			}
			if ( ! is_object( $gateway ) || ! is_a( $gateway, 'WC_Payment_Gateway' ) || empty( $gateway->id ) || ! is_string( $gateway->id ) ) {
				continue;
			}
			$id = $gateway->id;
			if ( isset( $registered[ $id ] ) || isset( $out[ $id ] ) ) {
				continue;
			}
			$out[ $id ] = $gateway;
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $gateways Gateway id => gateway object.
	 * @return array<string, array{title: string, enabled: bool}>
	 */
	public static function choices_from_gateways( array $gateways ) {
		$choices = array();
		foreach ( $gateways as $id => $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}
			$key     = isset( $gateway->id ) && is_string( $gateway->id ) && '' !== $gateway->id ? $gateway->id : (string) $id;
			$key     = preg_replace( '/[^A-Za-z0-9_\-]/', '', $key );
			$key     = is_string( $key ) ? $key : '';
			if ( '' === $key ) {
				continue;
			}
			$title = method_exists( $gateway, 'get_method_title' ) ? (string) $gateway->get_method_title() : '';
			if ( '' === $title && method_exists( $gateway, 'get_title' ) ) {
				$title = (string) $gateway->get_title();
			}
			$choices[ $key ] = array(
				'title'   => '' !== $title ? $title : $key,
				'enabled' => self::gateway_enabled_in_woocommerce( $gateway ),
			);
		}
		return $choices;
	}

	/**
	 * WooCommerce stores enabled as yes/no. Paystack sets the property to boolean false
	 * when is_valid_for_use() fails.
	 *
	 * @param object $gateway Gateway instance.
	 * @return bool
	 */
	public static function gateway_enabled_in_woocommerce( $gateway ) {
		if ( ! is_object( $gateway ) || ! isset( $gateway->enabled ) ) {
			return false;
		}
		$value = $gateway->enabled;
		if ( is_bool( $value ) ) {
			return $value;
		}
		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( 'yes', 'true', '1' ), true );
	}

	/**
	 * Hint for a gateway WooCommerce has switched off because of the store currency.
	 *
	 * @param string             $title       Gateway title.
	 * @param string             $store_currency Store currency code.
	 * @param array<int, string> $currencies  Currencies ReactWoo Geo will offer.
	 * @return string
	 */
	public static function disabled_currency_hint( $title, $store_currency, array $currencies ) {
		$store = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $store_currency ) );
		$store = is_string( $store ) && '' !== $store ? $store : __( 'the store currency', 'reactwoo-geo-commerce' );
		$codes = array();
		foreach ( $currencies as $code ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $code ) );
			if ( is_string( $code ) && '' !== $code && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
		}
		if ( empty( $codes ) ) {
			return sprintf(
				/* translators: 1: gateway title, 2: store currency code. */
				__( '%1$s shows as disabled in WooCommerce because the store currency is %2$s. Add the currencies it can charge. ReactWoo Geo then offers it on those checkouts. WooCommerce can still show Action needed.', 'reactwoo-geo-commerce' ),
				$title,
				$store
			);
		}
		return sprintf(
			/* translators: 1: gateway title, 2: store currency code, 3: checkout currency codes. */
			__( '%1$s shows as disabled in WooCommerce because the store currency is %2$s; ReactWoo Geo enables it for %3$s checkouts.', 'reactwoo-geo-commerce' ),
			$title,
			$store,
			implode( ', ', $codes )
		);
	}

	/**
	 * Paystack and its extra instances disable themselves when the store currency is unsupported.
	 *
	 * @param string             $gateway_id Gateway id.
	 * @param array<int, string> $currencies Saved currency list.
	 * @return bool
	 */
	public static function explain_disabled_gateway( $gateway_id, array $currencies ) {
		$id = strtolower( (string) $gateway_id );
		if ( 'paystack' === $id || 0 === strpos( $id, 'paystack-' ) ) {
			return true;
		}
		return ! empty( $currencies );
	}

	/**
	 * Keep saved gateway ids the form did not render. Submitted rows replace only the gateways that were shown.
	 *
	 * @param array<string, mixed> $existing            Stored rules.
	 * @param array<string, mixed> $posted              Posted countries and groups.
	 * @param array<int, string>   $shown_gateway_ids   Gateway columns on the form.
	 * @param array<int, string>   $shown_countries     Country rows on the form.
	 * @param array<int, string>   $shown_groups        Group rows on the form.
	 * @return array{countries: array<string, array<int, string>>, groups: array<string, array<int, string>>}
	 */
	public static function merge_posted_rules( array $existing, array $posted, array $shown_gateway_ids, array $shown_countries, array $shown_groups ) {
		$existing  = self::normalize_rules( $existing );
		$posted    = self::normalize_rules( $posted );
		$shown_ids = self::normalize_ids( $shown_gateway_ids );
		$countries = array();
		foreach ( $shown_countries as $country ) {
			$country = RWGCM_Price_Book::normalize_country( $country );
			if ( '' !== $country && ! in_array( $country, $countries, true ) ) {
				$countries[] = $country;
			}
		}
		$groups = array();
		foreach ( $shown_groups as $slug ) {
			$slug = self::normalize_slug( $slug );
			if ( '' !== $slug && ! in_array( $slug, $groups, true ) ) {
				$groups[] = $slug;
			}
		}
		return array(
			'countries' => self::merge_rule_bucket( $existing['countries'], $posted['countries'], $shown_ids, $countries ),
			'groups'    => self::merge_rule_bucket( $existing['groups'], $posted['groups'], $shown_ids, $groups ),
		);
	}

	/**
	 * @param array<string, array<int, string>> $existing  Stored bucket.
	 * @param array<string, array<int, string>> $posted    Posted bucket.
	 * @param array<int, string>                $shown_ids Gateway ids rendered as inputs.
	 * @param array<int, string>                $shown_keys Row keys rendered on the form.
	 * @return array<string, array<int, string>>
	 */
	private static function merge_rule_bucket( array $existing, array $posted, array $shown_ids, array $shown_keys ) {
		$out  = $existing;
		$keys = $shown_keys;
		foreach ( array_keys( $posted ) as $key ) {
			if ( ! in_array( $key, $keys, true ) ) {
				$keys[] = $key;
			}
		}
		foreach ( $keys as $key ) {
			$previous = isset( $existing[ $key ] ) && is_array( $existing[ $key ] ) ? $existing[ $key ] : array();
			$kept     = array();
			foreach ( $previous as $id ) {
				if ( ! in_array( $id, $shown_ids, true ) && ! in_array( $id, $kept, true ) ) {
					$kept[] = $id;
				}
			}
			$submitted = isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ? $posted[ $key ] : array();
			if ( ! empty( $shown_ids ) ) {
				$submitted = array_values(
					array_filter(
						$submitted,
						static function ( $id ) use ( $shown_ids ) {
							return in_array( $id, $shown_ids, true );
						}
					)
				);
			}
			$merged = $kept;
			foreach ( $submitted as $id ) {
				if ( ! in_array( $id, $merged, true ) ) {
					$merged[] = $id;
				}
			}
			if ( empty( $merged ) ) {
				unset( $out[ $key ] );
			} else {
				$out[ $key ] = $merged;
			}
		}
		return $out;
	}

	/**
	 * Geo Core country groups, when that registry is loaded.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function country_group_choices() {
		$choices = array();
		if ( ! class_exists( 'RWGC_Country_Groups', false ) ) {
			return $choices;
		}
		$all = RWGC_Country_Groups::get_all();
		if ( ! is_array( $all ) ) {
			return $choices;
		}
		foreach ( $all as $slug => $config ) {
			$slug = self::normalize_slug( $slug );
			if ( '' === $slug || ! is_array( $config ) ) {
				continue;
			}
			$label = isset( $config['label'] ) && is_string( $config['label'] ) && '' !== $config['label'] ? $config['label'] : $slug;
			$choices[ $slug ] = $label;
		}
		return $choices;
	}

	/**
	 * @param string $slug Raw slug.
	 * @return string
	 */
	public static function normalize_slug( $slug ) {
		$slug = strtolower( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $slug ) );
		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function current_flags() {
		$change  = self::change_payment_subscription_id() > 0;
		$country = '';
		if ( $change ) {
			$country = self::change_payment_billing_country();
		}
		if ( '' === $country ) {
			$country = RWGCM_Price_Book_Currency::entered_billing_country();
		}
		$store_api_cart = self::is_store_api_cart();
		if ( '' === $country && $store_api_cart ) {
			$country = self::customer_billing_country();
		}
		return array(
			'admin'              => function_exists( 'is_admin' ) && is_admin(),
			'front_ajax'         => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'renewal'            => RWGCM_Price_Book_Currency::is_renewal_request(),
			'renewal_pay'        => self::is_renewal_pay_page(),
			'checkout'           => self::is_checkout_request(),
			'change_payment'     => $change,
			'store_api_checkout' => self::is_store_api_checkout(),
			'store_api_cart'     => $store_api_cart,
			'billing_country'    => $country,
		);
	}

	/**
	 * @return bool
	 */
	private static function is_checkout_request() {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ) {
			return true;
		}
		return defined( 'WOOCOMMERCE_CHECKOUT' ) && WOOCOMMERCE_CHECKOUT;
	}

	/**
	 * @return bool
	 */
	private static function is_store_api_checkout() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/wc/store/' ) && false !== strpos( $uri, '/checkout' );
	}

	/**
	 * Store API cart and batch routes. Their responses carry payment_methods,
	 * which is the list the block checkout renders.
	 *
	 * @return bool
	 */
	private static function is_store_api_cart() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false === strpos( $uri, '/wc/store/' ) && false === strpos( $uri, 'rest_route=%2Fwc%2Fstore%2F' ) && false === strpos( $uri, 'rest_route=/wc/store/' ) ) {
			return false;
		}
		return false !== strpos( $uri, '/cart' ) || false !== strpos( $uri, '%2Fcart' ) || false !== strpos( $uri, '/batch' ) || false !== strpos( $uri, '%2Fbatch' );
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
	 * @return int
	 */
	private static function change_payment_subscription_id() {
		return RWGCM_Price_Book_Currency::change_payment_subscription_id();
	}

	/**
	 * @return string
	 */
	private static function change_payment_billing_country() {
		$id = self::change_payment_subscription_id();
		if ( $id <= 0 || ! function_exists( 'wcs_get_subscription' ) ) {
			return '';
		}
		$subscription = wcs_get_subscription( $id );
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_billing_country' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_country( $subscription->get_billing_country() );
	}

	/**
	 * Paying a renewal order must not hide the gateway stored on the subscription.
	 *
	 * @return bool
	 */
	private static function is_renewal_pay_page() {
		if ( ! function_exists( 'wcs_order_contains_renewal' ) || ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() || ! function_exists( 'get_query_var' ) ) {
			return false;
		}
		$order_id = function_exists( 'absint' ) ? absint( get_query_var( 'order-pay' ) ) : (int) get_query_var( 'order-pay' );
		if ( $order_id <= 0 ) {
			return false;
		}
		return (bool) wcs_order_contains_renewal( $order_id );
	}

	/**
	 * @param string              $country Billing ISO2.
	 * @param array<string, mixed> $rules  Stored rules.
	 * @return array<int, array<int, string>>
	 */
	private static function matching_group_gateway_lists( $country, array $rules ) {
		$membership = array();
		if ( class_exists( 'RWGC_Country_Groups', false ) ) {
			$groups = isset( $rules['groups'] ) && is_array( $rules['groups'] ) ? $rules['groups'] : array();
			foreach ( array_keys( $groups ) as $slug ) {
				$membership[ $slug ] = RWGC_Country_Groups::get_countries_for_group( $slug );
			}
		}
		return self::gateway_lists_for_country( $country, $rules, $membership );
	}

	/**
	 * @param array<int, mixed> $ids Raw ids.
	 * @return array<int, string>
	 */
	private static function normalize_ids( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
			if ( ! is_string( $id ) || '' === $id || in_array( $id, $out, true ) ) {
				continue;
			}
			$out[] = $id;
		}
		return $out;
	}
}
