<?php
/**
 * Persist visitor geo snapshot on Woo orders (attribution baseline).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order meta + action for CRM / analytics extensions.
 */
class RWGCM_Order_Geo {

	const META_COUNTRY = '_rwgcm_visitor_country_iso2';
	const META_CAPTURED = '_rwgcm_geo_captured_at_gmt';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_checkout_processed' ), 20, 1 );
		// Checkout block / Store API. The draft order already exists when meta is updated.
		// order_processed runs after that order is validated and set to pending, before payment.
		// Place-order runs both, and a retry reuses the draft, so the writer below is idempotent.
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'on_checkout_processed' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_checkout_processed' ), 20, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'render_order_admin_note' ), 15, 1 );
	}

	/**
	 * @param \WC_Order|\WP_Post $order Order or legacy post (Woo passes WC_Order in 3.x).
	 * @return void
	 */
	public static function render_order_admin_note( $order ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$cc = $order->get_meta( self::META_COUNTRY, true );
		if ( is_string( $cc ) && strlen( $cc ) === 2 ) {
			$at = $order->get_meta( self::META_CAPTURED, true );
			echo '<div class="address"><p><strong>' . esc_html__( 'Geo Commerce (visitor at checkout)', 'reactwoo-geo-commerce' ) . '</strong><br/>';
			echo esc_html__( 'Country', 'reactwoo-geo-commerce' ) . ': <code>' . esc_html( strtoupper( $cc ) ) . '</code>';
			if ( is_string( $at ) && '' !== $at ) {
				echo '<br/><span class="description">' . esc_html( $at ) . '</span>';
			}
			echo '</p></div>';
		}

		if ( class_exists( 'RWGCM_Attribution' ) ) {
			$labels = RWGCM_Attribution::order_meta_labels();
			$any    = false;
			foreach ( array_keys( $labels ) as $mk ) {
				$mv = $order->get_meta( $mk, true );
				if ( is_string( $mv ) && '' !== $mv ) {
					$any = true;
					break;
				}
			}
			if ( $any ) {
				echo '<div class="address"><p><strong>' . esc_html__( 'Geo Commerce (attribution)', 'reactwoo-geo-commerce' ) . '</strong><br/>';
				foreach ( $labels as $mk => $mlab ) {
					$mv = $order->get_meta( $mk, true );
					if ( ! is_string( $mv ) || '' === $mv ) {
						continue;
					}
					echo esc_html( $mlab ) . ': <code>' . esc_html( $mv ) . '</code><br/>';
				}
				echo '</p></div>';
			}
		}
	}

	/**
	 * Classic checkout passes an order ID. Store API checkout passes the order object.
	 *
	 * Country comes from Geo Core (`rwgc_get_visitor_country`, then visitor `country_code`).
	 * Classic posted checkout fields are not read, so a Store API REST request uses the same source.
	 *
	 * @param int|\WC_Order|mixed $order_or_id Order ID or order object.
	 * @return void
	 */
	public static function on_checkout_processed( $order_or_id ) {
		$order = self::resolve_order( $order_or_id );
		if ( ! $order ) {
			return;
		}
		self::persist_visitor_geo( $order );
	}

	/**
	 * @param mixed $order_or_id Order ID or order object.
	 * @return \WC_Order|null
	 */
	private static function resolve_order( $order_or_id ) {
		if ( is_object( $order_or_id ) ) {
			return is_a( $order_or_id, 'WC_Order' ) ? $order_or_id : null;
		}
		$order_id = absint( $order_or_id );
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return null;
		}
		return $order;
	}

	/**
	 * A stored meta value is left as it is. An empty value can still be filled.
	 *
	 * @param mixed $value Meta value.
	 * @return bool
	 */
	private static function meta_is_empty( $value ) {
		return ! is_string( $value ) || '' === $value;
	}

	/**
	 * Write visitor country and capture time once, on the order object (HPOS-safe).
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	private static function persist_visitor_geo( $order ) {
		$need_country  = self::meta_is_empty( $order->get_meta( self::META_COUNTRY, true ) );
		$need_captured = self::meta_is_empty( $order->get_meta( self::META_CAPTURED, true ) );
		if ( ! $need_country && ! $need_captured ) {
			return;
		}

		$resolved = self::resolve_visitor();
		$visitor  = $resolved['visitor'];
		$iso2     = $resolved['iso2'];

		/**
		 * Filter visitor geo payload before it is stored on the order.
		 *
		 * @param array<string, mixed> $visitor Geo Core visitor data.
		 * @param \WC_Order            $order   Order.
		 */
		$visitor = apply_filters( 'rwgcm_order_visitor_geo', $visitor, $order );

		$dirty = false;
		if ( $need_country && strlen( $iso2 ) === 2 ) {
			$order->update_meta_data( self::META_COUNTRY, $iso2 );
			$dirty = true;
		}
		if ( $need_captured ) {
			$order->update_meta_data( self::META_CAPTURED, gmdate( 'c' ) );
			$dirty = true;
		}

		/**
		 * Extra order meta (keys must match `#^_rwgcm_[a-z0-9_]+$#`). Values should be scalars.
		 *
		 * @param array<string, scalar|null> $meta    Default empty.
		 * @param \WC_Order                    $order   Order.
		 * @param array<string, mixed>        $visitor Visitor geo payload.
		 * @param string                       $iso2    Country code or empty.
		 */
		$extra = apply_filters( 'rwgcm_checkout_order_meta', array(), $order, $visitor, $iso2 );
		if ( is_array( $extra ) && ! empty( $extra ) ) {
			foreach ( $extra as $meta_key => $meta_val ) {
				if ( ! is_string( $meta_key ) || ! preg_match( '/^_rwgcm_[a-z0-9_]+$/', $meta_key ) ) {
					continue;
				}
				if ( ! is_scalar( $meta_val ) && null !== $meta_val ) {
					continue;
				}
				if ( ! self::meta_is_empty( $order->get_meta( $meta_key, true ) ) ) {
					continue;
				}
				$order->update_meta_data( $meta_key, $meta_val );
				$dirty = true;
			}
		}

		if ( ! $dirty ) {
			return;
		}

		$order->save();

		/**
		 * Fires after Geo Commerce stored geo attribution fields on an order.
		 *
		 * @param \WC_Order             $order   Order.
		 * @param array<string, mixed> $visitor Filtered visitor data.
		 * @param string                $iso2    Two-letter country if known.
		 */
		do_action( 'rwgcm_order_attributed', $order, $visitor, $iso2 );
	}

	/**
	 * Visitor country from Geo Core. No checkout POST fields, cookies, or IP headers are read here.
	 *
	 * @return array{visitor: array<string, mixed>, iso2: string}
	 */
	private static function resolve_visitor() {
		$visitor = array();
		if ( function_exists( 'rwgc_get_visitor_data' ) ) {
			$data = rwgc_get_visitor_data();
			if ( is_array( $data ) ) {
				$visitor = $data;
			}
		}

		$iso2 = '';
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$iso2 = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( '' === $iso2 && isset( $visitor['country_code'] ) ) {
			$iso2 = strtoupper( substr( sanitize_text_field( (string) $visitor['country_code'] ), 0, 2 ) );
		}

		return array(
			'visitor' => $visitor,
			'iso2'    => $iso2,
		);
	}
}
