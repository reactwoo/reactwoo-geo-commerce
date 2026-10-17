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
		add_filter( 'woocommerce_variable_price_html', array( __CLASS__, 'filter_variable_price_html' ), 100, 2 );
		add_filter( 'woocommerce_variable_subscription_price_html', array( __CLASS__, 'filter_variable_price_html' ), 100, 2 );
		add_filter( 'woocommerce_subscriptions_product_price_string', array( __CLASS__, 'filter_subscription_price_string' ), 100, 3 );
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
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$force    = class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::forces_store_currency();
		return RWGCM_Price_Book::variation_prices_hash( $hash, $currency, $force );
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
		if ( self::is_variable_product( $product ) ) {
			return self::filter_variable_price_html( $html, $product );
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
	 * Variable and variable-subscription From/range HTML.
	 *
	 * @param string     $html    Existing HTML.
	 * @param WC_Product $product Parent product.
	 * @return string
	 */
	public static function filter_variable_price_html( $html, $product ) {
		if ( self::skip_product_filters() || ! is_object( $product ) || ! self::is_variable_product( $product ) ) {
			return $html;
		}
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return $html;
		}
		$active = get_woocommerce_currency();
		$base   = RWGCM_Price_Book_Currency::base_currency();
		$range  = RWGCM_Price_Book::variable_range( self::variation_rows( $product, $active ), $active, $base );
		if ( null === $range ) {
			return $html;
		}
		$subscription = method_exists( $product, 'is_type' ) && $product->is_type( 'variable-subscription' );
		return self::format_variable_range_html( (string) $html, $range, $subscription );
	}

	/**
	 * Keep the billing-period tail and replace the amount inside a subscription string.
	 *
	 * @param string     $string  Subscription price string.
	 * @param WC_Product $product Product.
	 * @param array      $include Included parts. `price` is the inner HTML when WCS built the string.
	 * @return string
	 */
	public static function filter_subscription_price_string( $string, $product, $include = array() ) {
		if ( ! self::is_variable_product( $product ) ) {
			return $string;
		}
		$inner = ( is_array( $include ) && isset( $include['price'] ) ) ? (string) $include['price'] : (string) $string;
		$html  = self::filter_variable_price_html( '' !== $inner ? $inner : (string) $string, $product );
		if ( $html === $inner ) {
			return $string;
		}
		if ( '' !== $inner && false !== strpos( (string) $string, $inner ) ) {
			return str_replace( $inner, $html, (string) $string );
		}
		return $html;
	}

	/**
	 * @param string                               $existing_html Existing price HTML.
	 * @param array{currency: string, min: string, max: string, mode: string} $range Range decision.
	 * @param bool                                 $subscription  Variable subscription layout (From + period).
	 * @return string
	 */
	public static function format_variable_range_html( $existing_html, array $range, $subscription ) {
		$currency = $range['currency'];
		$min      = $range['min'];
		$max      = $range['max'];
		$from     = self::leading_from_label( $existing_html );
		$tail     = self::subscription_tail( $existing_html );
		$min_html = function_exists( 'wc_price' ) ? wc_price( $min, array( 'currency' => $currency ) ) : $currency . ' ' . $min;
		if ( (float) $min !== (float) $max ) {
			if ( $subscription || '' !== $from ) {
				if ( '' === $from ) {
					$from = __( 'From:', 'reactwoo-geo-commerce' ) . ' ';
				}
				return $from . $min_html . $tail;
			}
			$max_html = function_exists( 'wc_price' ) ? wc_price( $max, array( 'currency' => $currency ) ) : $currency . ' ' . $max;
			if ( function_exists( 'wc_format_price_range' ) ) {
				return wc_format_price_range( $min_html, $max_html );
			}
			return $min_html . ' – ' . $max_html;
		}
		return $from . $min_html . $tail;
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
		if ( class_exists( 'RWGCM_Price_Book_Currency', false ) && RWGCM_Price_Book_Currency::forces_store_currency() ) {
			return true;
		}
		return false;
	}

	/**
	 * @param object $product Product.
	 * @return bool
	 */
	private static function is_variable_product( $product ) {
		return is_object( $product ) && method_exists( $product, 'is_type' ) && ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) );
	}

	/**
	 * @param object $product Parent product.
	 * @param string $active  Active currency.
	 * @return array<int, array{raw: string, book: string}>
	 */
	private static function variation_rows( $product, $active ) {
		$rows = array();
		if ( ! is_object( $product ) ) {
			return $rows;
		}
		$ids = array();
		if ( method_exists( $product, 'get_visible_children' ) ) {
			$ids = $product->get_visible_children();
		} elseif ( method_exists( $product, 'get_children' ) ) {
			$ids = $product->get_children();
		}
		if ( ! is_array( $ids ) ) {
			return $rows;
		}
		foreach ( $ids as $child_id ) {
			$child = function_exists( 'wc_get_product' ) ? wc_get_product( $child_id ) : null;
			if ( ! is_object( $child ) || ! method_exists( $child, 'get_id' ) ) {
				continue;
			}
			$raw  = method_exists( $child, 'get_price' ) ? $child->get_price( 'edit' ) : '';
			$book = RWGCM_Price_Book_Currency::get_book( $child->get_id() );
			$book_amount = '';
			if ( RWGCM_Price_Book::has_price( $book, $active ) ) {
				$resolved = RWGCM_Price_Book::resolve_prices( $book[ RWGCM_Price_Book::normalize_currency( $active ) ] );
				if ( is_array( $resolved ) ) {
					$book_amount = $resolved['active'];
				}
			}
			$rows[] = array(
				'raw'  => (string) $raw,
				'book' => (string) $book_amount,
			);
		}
		return $rows;
	}

	/**
	 * @param string $html Price HTML.
	 * @return string
	 */
	private static function leading_from_label( $html ) {
		$text = self::plain_text( $html );
		if ( preg_match( '/^(from:?)\s*/i', $text, $matches ) ) {
			$label = $matches[1];
			if ( ':' !== substr( $label, -1 ) ) {
				$label .= ':';
			}
			return $label . ' ';
		}
		return '';
	}

	/**
	 * Billing period left on a subscription price string, such as " / month".
	 *
	 * @param string $html Price HTML.
	 * @return string
	 */
	private static function subscription_tail( $html ) {
		$text = self::plain_text( $html );
		if ( preg_match( '/(\s+\/\s+\S.*)$/', $text, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * @param string $html HTML or plain text.
	 * @return string
	 */
	private static function plain_text( $html ) {
		$html = (string) $html;
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return trim( wp_strip_all_tags( $html ) );
		}
		return trim( strip_tags( $html ) );
	}
}
