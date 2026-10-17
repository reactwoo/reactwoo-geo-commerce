<?php
/**
 * Persist the price-book currency on orders and subscription renewals.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order currency meta and renewal lock.
 */
class RWGCM_Price_Book_Order {

	const META_CURRENCY = '_rwgcm_price_book_currency';

	const META_SOURCE = '_rwgcm_price_book_source';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'on_checkout_create_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'on_store_api_checkout_update_order_meta' ), 20, 1 );
		add_action( 'woocommerce_checkout_subscription_created', array( __CLASS__, 'on_subscription_created' ), 10, 2 );
		add_action( 'wcs_renewal_order_created', array( __CLASS__, 'on_renewal_order_created' ), 5, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_admin_note' ), 16, 1 );
	}

	/**
	 * Cart totals were already calculated in get_woocommerce_currency().
	 * The order stores that same code. A renewal keeps the subscription currency.
	 *
	 * @param WC_Order             $order Order.
	 * @param array<string, mixed> $data  Posted checkout data.
	 * @return void
	 */
	public static function on_checkout_create_order( $order, $data ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'set_currency' ) ) {
			return;
		}
		$billing = '';
		if ( is_array( $data ) && isset( $data['billing_country'] ) ) {
			$billing = (string) $data['billing_country'];
		}
		$active = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$meta   = self::checkout_meta( $billing, $active );
		$order->set_currency( $meta['currency'] );
		self::write_meta( $order, $meta );
	}

	/**
	 * Block checkout does not fire woocommerce_checkout_create_order.
	 * The order currency is already the price-book currency; this stores the same meta.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function on_store_api_checkout_update_order_meta( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}
		$billing = method_exists( $order, 'get_billing_country' ) ? (string) $order->get_billing_country() : '';
		$active  = '';
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$active = get_woocommerce_currency();
		} elseif ( method_exists( $order, 'get_currency' ) ) {
			$active = (string) $order->get_currency();
		}
		self::write_meta( $order, self::checkout_meta( $billing, $active ) );
		if ( method_exists( $order, 'get_id' ) && (int) $order->get_id() > 0 && method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * @param string $billing_country Billing ISO2.
	 * @param string $active_currency Currency already chosen for the cart. Empty uses the decision.
	 * @return array{currency: string, source: string}
	 */
	public static function checkout_meta( $billing_country, $active_currency ) {
		$base     = RWGCM_Price_Book_Currency::base_currency();
		$renewal  = RWGCM_Price_Book_Currency::renewal_currency();
		$decision = RWGCM_Price_Book::resolve_charge(
			$billing_country,
			'',
			$base,
			$renewal,
			RWGCM_Price_Book::configured_fallback_currency( $base )
		);
		$currency = '' !== $renewal ? $renewal : RWGCM_Price_Book::normalize_currency( $active_currency );
		if ( '' === $currency ) {
			$currency = $decision['currency'];
		}
		if ( '' === $currency ) {
			$currency = $base;
		}
		$source = $decision['source'];
		if ( '' === $renewal && $decision['currency'] !== $currency ) {
			$source = 'cart';
		}
		return array(
			'currency' => $currency,
			'source'   => $source,
		);
	}

	/**
	 * @param object                         $order Order.
	 * @param array{currency: string, source: string} $meta Meta.
	 * @return void
	 */
	private static function write_meta( $order, array $meta ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}
		$order->update_meta_data( self::META_CURRENCY, $meta['currency'] );
		$order->update_meta_data( self::META_SOURCE, $meta['source'] );
	}

	/**
	 * @param WC_Subscription $subscription Subscription.
	 * @param WC_Order        $order        Parent order.
	 * @return void
	 */
	public static function on_subscription_created( $subscription, $order ) {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'update_meta_data' ) ) {
			return;
		}
		$currency = is_object( $order ) && method_exists( $order, 'get_currency' ) ? $order->get_currency() : '';
		$source   = is_object( $order ) && method_exists( $order, 'get_meta' ) ? $order->get_meta( self::META_SOURCE, true ) : '';
		if ( '' === $currency ) {
			return;
		}
		$subscription->set_currency( $currency );
		$subscription->update_meta_data( self::META_CURRENCY, $currency );
		if ( is_string( $source ) && '' !== $source ) {
			$subscription->update_meta_data( self::META_SOURCE, $source );
		}
		if ( method_exists( $subscription, 'save' ) ) {
			$subscription->save();
		}
	}

	/**
	 * Renewal orders keep the subscription currency and its stored totals.
	 * Price-book filters are skipped for the renewal request, so a later edit
	 * to the product book does not reprice the renewal.
	 *
	 * @param WC_Order        $renewal_order Renewal order.
	 * @param WC_Subscription $subscription  Subscription.
	 * @return void
	 */
	public static function on_renewal_order_created( $renewal_order, $subscription ) {
		if ( ! is_object( $renewal_order ) || ! is_object( $subscription ) || ! method_exists( $subscription, 'get_currency' ) ) {
			return;
		}
		$currency = RWGCM_Price_Book::normalize_currency( $subscription->get_currency() );
		if ( '' === $currency ) {
			return;
		}
		$renewal_order->set_currency( $currency );
		$renewal_order->update_meta_data( self::META_CURRENCY, $currency );
		$renewal_order->update_meta_data( self::META_SOURCE, 'renewal' );
		if ( method_exists( $renewal_order, 'save' ) ) {
			$renewal_order->save();
		}
	}

	/**
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function render_admin_note( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$currency = $order->get_meta( self::META_CURRENCY, true );
		if ( ! is_string( $currency ) || '' === $currency ) {
			return;
		}
		$source = $order->get_meta( self::META_SOURCE, true );
		echo '<div class="address"><p><strong>' . esc_html__( 'Geo Commerce price book', 'reactwoo-geo-commerce' ) . '</strong><br/>';
		echo esc_html__( 'Currency', 'reactwoo-geo-commerce' ) . ': <code>' . esc_html( strtoupper( $currency ) ) . '</code>';
		if ( is_string( $source ) && '' !== $source ) {
			echo '<br/>' . esc_html__( 'Chosen from', 'reactwoo-geo-commerce' ) . ': <code>' . esc_html( $source ) . '</code>';
		}
		echo '</p></div>';
	}
}
