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

	const META_RATE = '_rwgcm_exchange_rate';

	const META_RATE_BASE = '_rwgcm_exchange_rate_base';

	const META_RATE_SOURCE = '_rwgcm_exchange_rate_source';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'on_checkout_create_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'on_checkout_update_order_meta' ), 20, 1 );
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
		self::record_exchange_rate( $order );
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
		self::record_exchange_rate( $order );
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
	 * Classic checkout has line items by the time this fires.
	 *
	 * @param int|object $order_id Order id, or an order object.
	 * @return void
	 */
	public static function on_checkout_update_order_meta( $order_id ) {
		$order = $order_id;
		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		self::record_exchange_rate( $order );
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) && (int) $order->get_id() > 0 && method_exists( $order, 'save' ) ) {
			$order->save();
		}
	}

	/**
	 * Store the rate that was multiplied into a converted line. Fixed book
	 * prices do not record a rate.
	 *
	 * @param object|null $order Order.
	 * @return void
	 */
	public static function record_exchange_rate( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) || ! class_exists( 'RWGCM_Exchange', false ) ) {
			return;
		}
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::is_renewal_request() ) {
			return;
		}
		$currency = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';
		$ids      = array();
		if ( method_exists( $order, 'get_items' ) ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
					continue;
				}
				$variation = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
				$ids[]     = $variation > 0 ? $variation : (int) $item->get_product_id();
			}
		}
		$meta = RWGCM_Exchange::order_rate_meta( $currency, $ids );
		if ( null === $meta ) {
			return;
		}
		$order->update_meta_data( self::META_RATE, $meta['rate'] );
		$order->update_meta_data( self::META_RATE_BASE, $meta['base'] );
		$order->update_meta_data( self::META_RATE_SOURCE, $meta['source'] );
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
		$rate = $order->get_meta( self::META_RATE, true );
		$base = $order->get_meta( self::META_RATE_BASE, true );
		if ( is_string( $rate ) && '' !== $rate ) {
			$pair = ( is_string( $base ) && '' !== $base ) ? strtoupper( $base ) : '';
			echo '<br/>' . esc_html__( 'Exchange rate', 'reactwoo-geo-commerce' ) . ': <code>' . esc_html( $rate ) . '</code>';
			if ( '' !== $pair ) {
				echo ' ' . esc_html(
					sprintf(
						/* translators: 1: order currency, 2: store currency, 3: rate */
						__( '%1$s per 1 %2$s', 'reactwoo-geo-commerce' ),
						strtoupper( (string) $currency ),
						$pair
					)
				);
			}
		}
		echo '</p></div>';
	}
}
