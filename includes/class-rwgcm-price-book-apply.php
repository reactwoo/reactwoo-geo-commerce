<?php
/**
 * Apply fixed price-book amounts on the storefront and in the cart.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product price, sign-up fee, and cart line application.
 */
class RWGCM_Price_Book_Apply {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 100, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 100, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 100, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 100, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 100, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 100, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_variation_cache_price' ), 100, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( __CLASS__, 'filter_variation_cache_regular' ), 100, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( __CLASS__, 'filter_variation_cache_sale' ), 100, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'filter_prices_hash' ), 20, 3 );
		add_filter( 'woocommerce_subscriptions_product_sign_up_fee', array( __CLASS__, 'filter_signup_fee' ), 20, 2 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 100, 2 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation' ), 20, 3 );
		add_action( 'rwgcm_before_cart_totals', array( __CLASS__, 'apply_cart_prices' ), 5, 1 );
	}

	/**
	 * Geo rules should not run when the fixed book or a non-base currency is in effect.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function allow_base_adjustment( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return true;
		}
		if ( ! class_exists( 'RWGCM_Price_Book', false ) || ! class_exists( 'RWGCM_Price_Book_Currency', false ) ) {
			return true;
		}
		$base  = RWGCM_Price_Book_Currency::base_currency();
		$book  = RWGCM_Price_Book_Currency::get_book( $product->get_id() );
		$uses  = RWGCM_Price_Book::has_price( $book, $base );
		$active = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : $base;
		return RWGCM_Price_Book::should_apply_base_adjustment(
			$active,
			$base,
			$uses,
			RWGCM_Price_Book::base_adjustments_enabled()
		);
	}

	/**
	 * @param string|float $price   Price.
	 * @param WC_Product   $product Product.
	 * @return string|float
	 */
	public static function filter_price( $price, $product ) {
		$resolved = self::prices_for( $product );
		if ( null === $resolved ) {
			return $price;
		}
		return $resolved['active'];
	}

	/**
	 * @param string|float $price   Regular price.
	 * @param WC_Product   $product Product.
	 * @return string|float
	 */
	public static function filter_regular_price( $price, $product ) {
		$resolved = self::prices_for( $product );
		if ( null === $resolved ) {
			return $price;
		}
		return $resolved['regular'];
	}

	/**
	 * @param string|float $price   Sale price.
	 * @param WC_Product   $product Product.
	 * @return string|float
	 */
	public static function filter_sale_price( $price, $product ) {
		$resolved = self::prices_for( $product );
		if ( null === $resolved ) {
			return $price;
		}
		return $resolved['sale'];
	}

	/**
	 * @param string|float $price     Cached price.
	 * @param WC_Product   $variation Variation.
	 * @param WC_Product   $parent    Parent.
	 * @return string|float
	 */
	public static function filter_variation_cache_price( $price, $variation, $parent ) {
		unset( $parent );
		return self::filter_price( $price, $variation );
	}

	/**
	 * @param string|float $price     Cached regular price.
	 * @param WC_Product   $variation Variation.
	 * @param WC_Product   $parent    Parent.
	 * @return string|float
	 */
	public static function filter_variation_cache_regular( $price, $variation, $parent ) {
		unset( $parent );
		return self::filter_regular_price( $price, $variation );
	}

	/**
	 * @param string|float $price     Cached sale price.
	 * @param WC_Product   $variation Variation.
	 * @param WC_Product   $parent    Parent.
	 * @return string|float
	 */
	public static function filter_variation_cache_sale( $price, $variation, $parent ) {
		unset( $parent );
		return self::filter_sale_price( $price, $variation );
	}

	/**
	 * Keep the variation price transient bounded: one entry per currency.
	 *
	 * @param array      $hash       Hash payload.
	 * @param WC_Product $product    Variable product.
	 * @param bool       $for_display Display context.
	 * @return array
	 */
	public static function filter_prices_hash( $hash, $product, $for_display ) {
		unset( $product, $for_display );
		if ( ! is_array( $hash ) ) {
			$hash = array();
		}
		if ( function_exists( 'is_admin' ) && is_admin() && ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) ) {
			return $hash;
		}
		$hash['rwgcm_price_book_currency'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		return $hash;
	}

	/**
	 * Blank sign-up fee in a non-base currency means zero, so a GBP fee is not
	 * charged as if it were rand, euros, or dollars.
	 *
	 * @param string|float $fee     Sign-up fee.
	 * @param WC_Product   $product Product.
	 * @return string|float
	 */
	public static function filter_signup_fee( $fee, $product ) {
		if ( self::skip_product_filters() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $fee;
		}
		$active = get_woocommerce_currency();
		$book   = RWGCM_Price_Book_Currency::get_book( $product->get_id() );
		if ( ! RWGCM_Price_Book::has_price( $book, $active ) ) {
			return $fee;
		}
		$prices = RWGCM_Price_Book::resolve_prices( $book[ RWGCM_Price_Book::normalize_currency( $active ) ] );
		if ( null === $prices ) {
			return $fee;
		}
		if ( '' === $prices['signup_fee'] ) {
			if ( $active === RWGCM_Price_Book_Currency::base_currency() ) {
				return $fee;
			}
			return '0';
		}
		return $prices['signup_fee'];
	}

	/**
	 * Products with no book price keep their store-currency amount and symbol.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function filter_price_html( $html, $product ) {
		if ( self::skip_product_filters() || ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return $html;
		}
		if ( method_exists( $product, 'is_type' ) && ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) ) {
			return $html;
		}
		$active = get_woocommerce_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		if ( $active === $base ) {
			return $html;
		}
		$book = RWGCM_Price_Book_Currency::get_book( $product->get_id() );
		if ( RWGCM_Price_Book::has_price( $book, $active ) ) {
			return $html;
		}
		$raw = $product->get_price( 'edit' );
		if ( '' === (string) $raw || ! function_exists( 'wc_price' ) ) {
			return $html;
		}
		return wc_price( $raw, array( 'currency' => $base ) );
	}

	/**
	 * @param array      $data      Variation payload.
	 * @param WC_Product $parent    Parent.
	 * @param WC_Product $variation Variation.
	 * @return array
	 */
	public static function filter_available_variation( $data, $parent, $variation ) {
		unset( $parent );
		if ( self::skip_product_filters() || ! is_array( $data ) || ! is_object( $variation ) || ! method_exists( $variation, 'get_id' ) ) {
			return $data;
		}
		$active = get_woocommerce_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		if ( $active === $base ) {
			return $data;
		}
		$book = RWGCM_Price_Book_Currency::get_book( $variation->get_id() );
		if ( RWGCM_Price_Book::has_price( $book, $active ) || ! function_exists( 'wc_price' ) ) {
			return $data;
		}
		$raw = method_exists( $variation, 'get_price' ) ? $variation->get_price( 'edit' ) : '';
		if ( '' === (string) $raw ) {
			return $data;
		}
		$data['price_html'] = wc_price( $raw, array( 'currency' => $base ) );
		return $data;
	}

	/**
	 * @param WC_Cart $cart Cart.
	 * @return void
	 */
	public static function apply_cart_prices( $cart ) {
		if ( self::skip_product_filters() || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( ! is_array( $item ) || empty( $item['data'] ) || ! is_object( $item['data'] ) ) {
				continue;
			}
			if ( ! empty( $item['bundled_by'] ) || ! empty( $item['composite_parent'] ) || ! empty( $item['composite_item'] ) ) {
				continue;
			}
			$product = $item['data'];
			if ( ! method_exists( $product, 'set_price' ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}
			$resolved = self::prices_for( $product );
			if ( null === $resolved ) {
				continue;
			}
			$product->set_price( $resolved['active'] );
		}
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array{regular: string, sale: string, signup_fee: string, active: string, on_sale: bool}|null
	 */
	private static function prices_for( $product ) {
		if ( self::skip_product_filters() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return null;
		}
		$active = RWGCM_Price_Book::normalize_currency( get_woocommerce_currency() );
		if ( '' === $active ) {
			return null;
		}
		$book = RWGCM_Price_Book_Currency::get_book( $product->get_id() );
		if ( ! RWGCM_Price_Book::has_price( $book, $active ) ) {
			return null;
		}
		return RWGCM_Price_Book::resolve_prices( $book[ $active ] );
	}

	/**
	 * @return bool
	 */
	private static function skip_product_filters() {
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::is_renewal_request() ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() && ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) ) {
			return true;
		}
		return false;
	}
}
