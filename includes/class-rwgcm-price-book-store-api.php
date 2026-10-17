<?php
/**
 * Keeps the reported currency honest outside the classic product HTML.
 *
 * The active currency (for example ZAR) may only be paired with an amount
 * that came from a price-book entry in that currency. A product without
 * one keeps its store-currency amount, so it must also report the store
 * currency, as filter_price_html already does for classic HTML.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store API product payloads, structured data, and a checkout guard.
 */
class RWGCM_Price_Book_Store_Api {

	/**
	 * @return void
	 */
	public static function init() {
		// rest_request_after_callbacks runs inside WP_REST_Server::dispatch(), so it covers
		// HTTP requests, batch sub-requests and rest_do_request()/preloading. Block hydration
		// calls the Store API controller directly and fires its own twin filter.
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'filter_after_callbacks' ), 20, 3 );
		add_filter( 'woocommerce_hydration_request_after_callbacks', array( __CLASS__, 'filter_after_callbacks' ), 20, 3 );
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'filter_structured_data' ), 20, 2 );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'filter_rank_math_json_ld' ), 99, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'guard_order_currency' ), 30, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'guard_order_currency' ), 30, 1 );
	}

	/**
	 * rest_request_after_callbacks( $response, $handler, $request ).
	 *
	 * @param mixed $response Response.
	 * @param mixed $handler  Handler.
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public static function filter_after_callbacks( $response, $handler, $request ) {
		unset( $handler );
		return self::filter_rest_response( $response, null, $request );
	}

	/**
	 * Store API responses (/wc/store/...): products, product collections,
	 * cart cross-sells, and batch sub-responses.
	 *
	 * @param mixed $response WP_REST_Response.
	 * @param mixed $server   Server.
	 * @param mixed $request  WP_REST_Request.
	 * @return mixed
	 */
	public static function filter_rest_response( $response, $server, $request ) {
		unset( $server );
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
			return $response;
		}
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( 0 !== strpos( $route, '/wc/store/' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) && ! ( $data instanceof stdClass ) ) {
			return $response;
		}
		$active = self::active_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		if ( '' === $active || $active === $base ) {
			return $response;
		}
		$response->set_data(
			self::relabel_payload( $data, $active, $base, array( __CLASS__, 'product_priced_in' ), self::currency_fields( $base ) )
		);
		return $response;
	}

	/**
	 * Walks a Store API payload. Every product node (an id plus prices with a
	 * currency_code) priced in the active currency is left alone. A product
	 * node without an active-currency price gets the base currency fields.
	 * Cart line items (nodes with key and quantity) are not touched: the
	 * whole cart already follows RWGCM_Price_Book::currency_for_lines().
	 *
	 * @param array<mixed>         $data        Payload.
	 * @param string               $active      Active currency.
	 * @param string               $base        Store currency.
	 * @param callable             $priced_in   fn( int $product_id, string $currency ): bool.
	 * @param array<string, mixed> $base_fields Store API currency fields for the base currency.
	 * @return array<mixed>
	 */
	public static function relabel_payload( $data, $active, $base, $priced_in, array $base_fields ) {
		$active = RWGCM_Price_Book::normalize_currency( $active );
		$base   = RWGCM_Price_Book::normalize_currency( $base );
		if ( '' === $active || $active === $base ) {
			return $data;
		}
		return self::walk( $data, $active, $priced_in, $base_fields );
	}

	/**
	 * Recursive walk over arrays and objects. WooCommerce's ProductSchema
	 * returns `prices` (and other nested parts) as stdClass, so both shapes
	 * must be handled and kept as they were.
	 *
	 * @param mixed                $node        Node.
	 * @param string               $active      Active currency.
	 * @param callable             $priced_in   Callback.
	 * @param array<string, mixed> $base_fields Base currency fields.
	 * @return mixed
	 */
	private static function walk( $node, $active, $priced_in, array $base_fields ) {
		$is_object = $node instanceof stdClass;
		if ( ! $is_object && ! is_array( $node ) ) {
			return $node;
		}
		$arr = $is_object ? get_object_vars( $node ) : $node;
		if ( self::is_product_node( $arr ) ) {
			$prices_obj = $arr['prices'] instanceof stdClass;
			$prices     = $prices_obj ? get_object_vars( $arr['prices'] ) : $arr['prices'];
			if ( $active === $prices['currency_code'] && ! call_user_func( $priced_in, (int) $arr['id'], $active ) ) {
				$prices        = array_merge( $prices, $base_fields );
				$arr['prices'] = $prices_obj ? (object) $prices : $prices;
			}
		}
		foreach ( $arr as $key => $value ) {
			if ( 'prices' !== $key && ( is_array( $value ) || $value instanceof stdClass ) ) {
				$arr[ $key ] = self::walk( $value, $active, $priced_in, $base_fields );
			}
		}
		return $is_object ? (object) $arr : $arr;
	}

	/**
	 * @param array<mixed> $node Node.
	 * @return bool
	 */
	public static function is_product_node( array $node ) {
		if ( ! isset( $node['id'] ) || ! is_numeric( $node['id'] ) || ! isset( $node['prices'] ) ) {
			return false;
		}
		if ( isset( $node['key'] ) && isset( $node['quantity'] ) ) {
			return false;
		}
		$prices = $node['prices'] instanceof stdClass ? get_object_vars( $node['prices'] ) : $node['prices'];
		return is_array( $prices ) && isset( $prices['currency_code'] );
	}

	/**
	 * Whether every line of an order or cart has a price-book amount in the
	 * currency it is charged in. The store currency is always priced.
	 *
	 * @param array<int, int> $product_ids Line product (or variation) ids.
	 * @param string          $currency    Charged currency.
	 * @param string          $base        Store currency.
	 * @param callable        $priced_in   fn( int $product_id, string $currency ): bool.
	 * @return bool
	 */
	public static function lines_priced_in( array $product_ids, $currency, $base, $priced_in ) {
		$currency = RWGCM_Price_Book::normalize_currency( $currency );
		$base     = RWGCM_Price_Book::normalize_currency( $base );
		if ( '' === $currency || $currency === $base ) {
			return true;
		}
		foreach ( $product_ids as $id ) {
			if ( ! call_user_func( $priced_in, (int) $id, $currency ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Defence in depth: refuse to create an order whose currency differs
	 * from the store currency while a line has no book price in it, so a
	 * gateway is never asked to charge a store-currency number as another
	 * currency. currency_for_lines() should make this unreachable.
	 *
	 * @param mixed $order WC_Order.
	 * @return void
	 * @throws Exception When the order currency and line prices disagree.
	 */
	public static function guard_order_currency( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_currency' ) || ! method_exists( $order, 'get_items' ) ) {
			return;
		}
		if ( RWGCM_Price_Book_Currency::is_renewal_request() ) {
			return;
		}
		$ids = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			if ( method_exists( $item, 'get_meta' ) && ( $item->get_meta( '_bundled_by' ) || $item->get_meta( '_composite_parent' ) ) ) {
				continue;
			}
			$variation = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
			$ids[]     = $variation > 0 ? $variation : (int) $item->get_product_id();
		}
		$base = RWGCM_Price_Book_Currency::base_currency();
		if ( self::lines_priced_in( $ids, $order->get_currency(), $base, array( __CLASS__, 'line_priced_in' ) ) ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'Geo Commerce blocked an order: currency ' . $order->get_currency() . ' but a line has no price-book amount in it.', array( 'source' => 'rwgcm-price-book' ) );
		}
		throw new Exception( __( 'Prices in your cart changed currency. Please review your cart and try again.', '[REDACTED]-geo-commerce' ) );
	}

	/**
	 * Same rule as cart_line_support(): the line's own id carries the book.
	 *
	 * @param int    $product_id Product or variation id.
	 * @param string $currency   Currency.
	 * @return bool
	 */
	public static function line_priced_in( $product_id, $currency ) {
		return RWGCM_Price_Book::has_price( RWGCM_Price_Book_Currency::get_book( $product_id ), $currency );
	}

	/**
	 * Catalog rule. A variable product counts as priced only when every
	 * child variation has a book price in that currency.
	 *
	 * @param int    $product_id Product id.
	 * @param string $currency   Currency.
	 * @return bool
	 */
	public static function product_priced_in( $product_id, $currency ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( is_object( $product ) && method_exists( $product, 'is_type' ) && ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) && method_exists( $product, 'get_children' ) ) {
			$children = (array) $product->get_children();
			if ( empty( $children ) ) {
				return false;
			}
			foreach ( $children as $child ) {
				if ( ! self::line_priced_in( (int) $child, $currency ) ) {
					return false;
				}
			}
			return true;
		}
		return self::line_priced_in( $product_id, $currency );
	}

	/**
	 * JSON-LD offers: priceCurrency must match the amount.
	 *
	 * @param array<string, mixed> $markup  Markup.
	 * @param mixed                $product WC_Product.
	 * @return array<string, mixed>
	 */
	public static function filter_structured_data( $markup, $product ) {
		if ( ! is_array( $markup ) || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
			return $markup;
		}
		$active = self::active_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		if ( '' === $active || $active === $base || self::product_priced_in( $product->get_id(), $active ) ) {
			return $markup;
		}
		$markup['offers'] = self::replace_currency_key( $markup['offers'], $active, $base );
		return $markup;
	}

	/**
	 * Rank Math replaces WooCommerce's JSON-LD with its own graph
	 * (rank_math/json_ld). Its Product entity takes priceCurrency from
	 * get_woocommerce_currency(), so relabel it the same way.
	 *
	 * @param mixed $data   Entities keyed by id.
	 * @param mixed $jsonld Rank Math JsonLD instance.
	 * @return mixed
	 */
	public static function filter_rank_math_json_ld( $data, $jsonld = null ) {
		unset( $jsonld );
		if ( ! is_array( $data ) || ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'get_queried_object_id' ) ) {
			return $data;
		}
		$active = self::active_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		return self::relabel_json_ld_entities( $data, (int) get_queried_object_id(), $active, $base, array( __CLASS__, 'product_priced_in' ) );
	}

	/**
	 * @param array<mixed> $data       Rank Math entities.
	 * @param int          $product_id Product being viewed.
	 * @param string       $active     Active currency.
	 * @param string       $base       Base currency.
	 * @param callable     $priced_in  Callback.
	 * @return array<mixed>
	 */
	public static function relabel_json_ld_entities( array $data, $product_id, $active, $base, $priced_in ) {
		if ( $product_id <= 0 || '' === $active || $active === $base || call_user_func( $priced_in, $product_id, $active ) ) {
			return $data;
		}
		foreach ( $data as $key => $entity ) {
			if ( ! is_array( $entity ) || ! isset( $entity['@type'] ) ) {
				continue;
			}
			$types = (array) $entity['@type'];
			if ( in_array( 'Product', $types, true ) || in_array( 'ProductGroup', $types, true ) ) {
				$data[ $key ] = self::replace_currency_key( $entity, $active, $base );
			}
		}
		return $data;
	}

	/**
	 * @param array<mixed> $node   Node.
	 * @param string       $active Active currency.
	 * @param string       $base   Base currency.
	 * @return array<mixed>
	 */
	public static function replace_currency_key( array $node, $active, $base ) {
		foreach ( $node as $key => $value ) {
			if ( 'priceCurrency' === $key && $value === $active ) {
				$node[ $key ] = $base;
			} elseif ( is_array( $value ) ) {
				$node[ $key ] = self::replace_currency_key( $value, $active, $base );
			}
		}
		return $node;
	}

	/**
	 * Store API currency fields for one currency (mirrors WooCommerce's
	 * CurrencyFormatter, with an explicit currency).
	 *
	 * @param string $currency Currency code.
	 * @return array<string, mixed>
	 */
	public static function currency_fields( $currency ) {
		$position = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency_pos', 'left' ) : 'left';
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol( $currency ) ) : $currency;
		$prefix   = '';
		$suffix   = '';
		switch ( $position ) {
			case 'left_space':
				$prefix = $symbol . ' ';
				break;
			case 'right_space':
				$suffix = ' ' . $symbol;
				break;
			case 'right':
				$suffix = $symbol;
				break;
			default:
				$prefix = $symbol;
		}
		return array(
			'currency_code'   => $currency,
			'currency_symbol' => $symbol,
			'currency_prefix' => $prefix,
			'currency_suffix' => $suffix,
		);
	}

	/**
	 * @return string
	 */
	private static function active_currency() {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return '';
		}
		return RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
	}
}
