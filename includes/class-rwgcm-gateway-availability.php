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
	 * Enabled WooCommerce gateways for the settings checkboxes.
	 *
	 * @return array<string, string> Id => title.
	 */
	public static function gateway_choices() {
		$choices = array();
		if ( ! function_exists( 'WC' ) ) {
			return $choices;
		}
		$wc = WC();
		if ( ! $wc || ! method_exists( $wc, 'payment_gateways' ) ) {
			return $choices;
		}
		$controller = $wc->payment_gateways();
		if ( ! is_object( $controller ) || ! method_exists( $controller, 'payment_gateways' ) ) {
			return $choices;
		}
		$all = $controller->payment_gateways();
		if ( ! is_array( $all ) ) {
			return $choices;
		}
		foreach ( $all as $id => $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}
			$enabled = isset( $gateway->enabled ) && 'yes' === $gateway->enabled;
			if ( ! $enabled ) {
				continue;
			}
			$title = method_exists( $gateway, 'get_title' ) ? (string) $gateway->get_title() : (string) $id;
			$choices[ (string) $id ] = '' !== $title ? $title : (string) $id;
		}
		return $choices;
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
