<?php
/**
 * Localised product price shared by the shortcode, block, and Elementor widgets.
 *
 * Amounts come from WooCommerce's filtered getters (`get_price()`, `get_regular_price()`,
 * `wc_get_price_to_display()`). Price books and pricing rules stay on those filters.
 * This class does not calculate a discount or convert a currency.
 *
 * The HTML varies by visitor country. It follows `rwgc_get_visitor_country()` the same
 * way catalog prices do, and it does not read the `rwgc_cc` cookie, define
 * DONOTCACHEPAGE, or send nocache headers. Geo Core varies full-page caches with
 * that cookie and the LiteSpeed vary group `rwgc_cc_{ISO2}`.
 * Cache-safe price mode can replace this HTML with a placeholder. That mode lives
 * in `RWGCM_Cache_Safe_Price` and still resolves the country with Geo Core.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders one product price.
 */
class RWGCM_Product_Price {

	const SHORTCODE = 'rwgcm_price';

	/**
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 9 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * How this shortcode stays on Geo Core's country vary.
	 *
	 * @return string
	 */
	public static function cache_compatibility_notes() {
		return 'The [rwgcm_price] shortcode prints the same country-specific amount as the product page. It follows rwgc_get_visitor_country() from Geo Core. It does not read the rwgc_cc cookie, define DONOTCACHEPAGE, or send nocache headers. Geo Core varies full-page caches with the rwgc_cc cookie and the LiteSpeed vary group rwgc_cc_{ISO2}, both taken from the server-resolved country. A page cache that ignores that vary serves one country\'s price to everyone. Cache-safe price mode (off, auto, or ajax) can print a placeholder that loads the price from the uncached REST route instead. Cart and checkout stay server-rendered.';
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		if ( ! function_exists( 'wp_register_style' ) || ! defined( 'RWGCM_URL' ) ) {
			return;
		}
		wp_register_style(
			'rwgcm-product-price',
			RWGCM_URL . 'assets/css/product-price.css',
			array(),
			defined( 'RWGCM_VERSION' ) ? RWGCM_VERSION : '0.8.0'
		);
	}

	/**
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		return self::render( is_array( $atts ) ? $atts : array() );
	}

	/**
	 * @param array<string, mixed>|string $raw Shortcode, block, or widget settings.
	 * @return string
	 */
	public static function render( $raw ) {
		$args = self::parse_args( $raw );
		if ( class_exists( 'RWGCM_Cache_Safe_Price', false ) && RWGCM_Cache_Safe_Price::should_placeholder( 'element' ) ) {
			if ( (int) $args['product_id'] <= 0 && (int) $args['variation_id'] <= 0 ) {
				$current = self::current_product();
				if ( self::is_product( $current ) ) {
					$args['product_id'] = (int) $current->get_id();
				}
			}
			self::enqueue();
			RWGCM_Cache_Safe_Price::enqueue_assets();
			return RWGCM_Cache_Safe_Price::element_placeholder( $args );
		}
		self::enqueue();

		$product = self::resolve_product( $args );
		if ( ! self::can_purchase( $product ) ) {
			return self::fallback_html( $args, $product );
		}

		$built = self::build_price( $product, $args );
		if ( null === $built ) {
			return self::fallback_html( $args, $product );
		}

		return self::price_html( $built, $args );
	}

	/**
	 * @param array<string, mixed>|string $raw Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function parse_args( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$format = sanitize_key( (string) self::scalar( self::raw_value( $raw, 'format' ), 'symbol' ) );
		if ( ! in_array( $format, array( 'amount', 'symbol', 'code' ), true ) ) {
			$format = 'symbol';
		}
		$from = sanitize_key( (string) self::scalar( self::raw_value( $raw, 'from' ), 'auto' ) );
		if ( ! in_array( $from, array( 'auto', 'yes', 'no' ), true ) ) {
			$from = 'auto';
		}
		$color = sanitize_key( (string) self::scalar( self::raw_value( $raw, 'color' ), 'inherit' ) );
		if ( ! in_array( $color, array( 'inherit', 'primary', 'secondary', 'accent', 'text' ), true ) ) {
			$color = 'inherit';
		}

		$fallback = self::clean_text( self::scalar( self::raw_value( $raw, 'fallback' ), '' ), 80 );
		if ( '' === $fallback ) {
			$fallback = __( 'Unavailable', 'reactwoo-geo-commerce' );
		}

		$classes = array();
		foreach ( array( 'class', 'className' ) as $key ) {
			$classes = array_merge( $classes, self::class_list( self::raw_value( $raw, $key ) ) );
		}
		$classes = array_merge( $classes, self::class_list( self::raw_value( $raw, 'classes' ) ) );

		return array(
			'product_id'   => absint( self::scalar( self::raw_value( $raw, 'product_id' ), '0' ) ),
			'variation_id' => absint( self::scalar( self::raw_value( $raw, 'variation_id' ), '0' ) ),
			'format'       => $format,
			'period'       => self::clean_text( self::scalar( self::raw_value( $raw, 'period' ), '' ), 40 ),
			'from'         => $from,
			'sale'         => self::flag( self::raw_value( $raw, 'sale' ), true ),
			'fallback'     => $fallback,
			'color'        => $color,
			'classes'      => $classes,
		);
	}

	/**
	 * @return void
	 */
	private static function enqueue() {
		if ( function_exists( 'wp_register_style' ) ) {
			self::register_assets();
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rwgcm-product-price' );
		}
	}

	/**
	 * @param array<string, mixed> $args Parsed args.
	 * @return object|null
	 */
	private static function resolve_product( array $args ) {
		$id = $args['variation_id'] > 0 ? $args['variation_id'] : $args['product_id'];
		if ( $id > 0 ) {
			return function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		}
		return self::current_product();
	}

	/**
	 * Global product on a single product template, then the queried post.
	 *
	 * @return object|null
	 */
	private static function current_product() {
		if ( isset( $GLOBALS['product'] ) && self::is_product( $GLOBALS['product'] ) ) {
			return $GLOBALS['product'];
		}
		$post_id = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
		if ( $post_id > 0 && function_exists( 'wc_get_product' ) ) {
			$found = wc_get_product( $post_id );
			if ( self::is_product( $found ) ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * @param object|null          $product Product.
	 * @param array<string, mixed> $args    Parsed args.
	 * @return array<string, mixed>|null
	 */
	private static function build_price( $product, array $args ) {
		$variable = self::is_variable_parent( $product ) && $args['variation_id'] <= 0;
		if ( $variable ) {
			$rows = self::child_rows( $product );
			if ( empty( $rows ) ) {
				return null;
			}
			if ( self::mixed_currencies( $rows ) ) {
				$rows = self::child_rows( $product, true );
			}
			$winner = self::lowest_row( $rows );
			if ( null === $winner ) {
				return null;
			}
			$distinct = self::distinct_amounts( $rows );
			$show_from = 'no' !== $args['from'] && ( 'yes' === $args['from'] || $distinct > 1 );
			$period_product = $winner['product'];
			$period = self::period_text( $period_product, $args['period'] );
			if ( '' === $args['period'] && '' === $period ) {
				$period = self::subscription_suffix( $product );
			}
			$winner['show_from'] = $show_from;
			$winner['period']    = $period;
			$winner['show_sale'] = self::show_sale( $args, $winner );
			return $winner;
		}

		$row = self::row_for( $product, false );
		if ( null === $row || '' === (string) $row['price'] || ! is_numeric( $row['price'] ) ) {
			return null;
		}
		$row['amount']         = self::display_amount( $product, $row['price'] );
		$row['regular_amount'] = self::display_amount( $product, $row['regular'] );
		$row['show_from']      = false;
		$row['period']         = self::period_text( $product, $args['period'] );
		$row['show_sale']      = self::show_sale( $args, $row );
		return $row;
	}

	/**
	 * @param object $product    Variable parent.
	 * @param bool   $force_base Use the unfiltered store-currency amount.
	 * @return array<int, array<string, mixed>>
	 */
	private static function child_rows( $product, $force_base = false ) {
		$rows = array();
		foreach ( self::child_products( $product ) as $child ) {
			if ( ! self::can_purchase( $child ) ) {
				continue;
			}
			$row = self::row_for( $child, $force_base, $product );
			if ( null === $row || '' === (string) $row['price'] || ! is_numeric( $row['price'] ) ) {
				continue;
			}
			$row['amount']         = self::display_amount( $child, $row['price'] );
			$row['regular_amount'] = self::display_amount( $child, $row['regular'] );
			$rows[]                = $row;
		}
		return $rows;
	}

	/**
	 * Filtered unit prices, or the store-currency edit price when this currency
	 * has no price-book amount (the same choice as the product-page price HTML).
	 *
	 * Variations use the variation price filters WooCommerce applies when it
	 * builds the From/range, so a pricing rule on a variation matches the product page.
	 *
	 * @param object      $product    Product or variation.
	 * @param bool        $force_base Ignore the visitor currency and read edit prices.
	 * @param object|null $parent     Variable parent, when this row is a variation.
	 * @return array<string, mixed>|null
	 */
	private static function row_for( $product, $force_base, $parent = null ) {
		if ( ! self::is_product( $product ) ) {
			return null;
		}
		$active   = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		$base     = class_exists( 'RWGCM_Price_Book_Currency', false ) ? (string) RWGCM_Price_Book_Currency::base_currency() : $active;
		$use_edit = (bool) $force_base;
		if ( ! $use_edit && '' !== $active && '' !== $base && $active !== $base && class_exists( 'RWGCM_Price_Book', false ) && class_exists( 'RWGCM_Price_Book_Currency', false ) ) {
			$book = RWGCM_Price_Book_Currency::get_book( (int) $product->get_id() );
			if ( ! RWGCM_Price_Book::has_price( $book, $active ) ) {
				$use_edit = true;
			}
		}
		$currency = $use_edit && '' !== $base ? $base : $active;
		$on_sale  = false;
		if ( $use_edit ) {
			// No book price in the visitor currency: the cart charges the store
			// currency and applies the geo rule. get_price() is that amount.
			$price   = $product->get_price();
			$regular = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price() : $price;
			if ( method_exists( $product, 'is_on_sale' ) ) {
				$on_sale = (bool) $product->is_on_sale();
			}
		} elseif ( self::is_variation( $product ) && function_exists( 'apply_filters' ) ) {
			$owner   = self::is_product( $parent ) ? $parent : $product;
			$price   = apply_filters( 'woocommerce_variation_prices_price', $product->get_price( 'edit' ), $product, $owner );
			$regular = apply_filters( 'woocommerce_variation_prices_regular_price', method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price( 'edit' ) : $price, $product, $owner );
			$sale    = apply_filters( 'woocommerce_variation_prices_sale_price', method_exists( $product, 'get_sale_price' ) ? $product->get_sale_price( 'edit' ) : '', $product, $owner );
			$on_sale = '' !== (string) $sale && is_numeric( (string) $sale ) && is_numeric( (string) $regular ) && (float) $sale < (float) $regular;
		} else {
			$price   = $product->get_price();
			$regular = method_exists( $product, 'get_regular_price' ) ? $product->get_regular_price() : $price;
			if ( method_exists( $product, 'is_on_sale' ) ) {
				$on_sale = (bool) $product->is_on_sale();
			}
		}
		return array(
			'product'  => $product,
			'price'    => $price,
			'regular'  => $regular,
			'currency' => strtoupper( substr( (string) $currency, 0, 3 ) ),
			'on_sale'  => $on_sale,
		);
	}

	/**
	 * @param object|null $product Product.
	 * @return bool
	 */
	private static function is_variation( $product ) {
		return self::is_product( $product ) && method_exists( $product, 'is_type' ) && $product->is_type( 'variation' );
	}

	/**
	 * @param array<string, mixed> $args Parsed args.
	 * @param array<string, mixed> $row  Price row.
	 * @return bool
	 */
	private static function show_sale( array $args, array $row ) {
		if ( empty( $args['sale'] ) || empty( $row['on_sale'] ) ) {
			return false;
		}
		return isset( $row['regular_amount'], $row['amount'] ) && (float) $row['regular_amount'] > (float) $row['amount'];
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return array<string, mixed>|null
	 */
	private static function lowest_row( array $rows ) {
		$best = null;
		foreach ( $rows as $row ) {
			if ( null === $best || (float) $row['amount'] < (float) $best['amount'] ) {
				$best = $row;
			}
		}
		return $best;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return bool
	 */
	private static function mixed_currencies( array $rows ) {
		$seen = array();
		foreach ( $rows as $row ) {
			$seen[ isset( $row['currency'] ) ? (string) $row['currency'] : '' ] = true;
		}
		return count( $seen ) > 1;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return int
	 */
	private static function distinct_amounts( array $rows ) {
		$seen = array();
		foreach ( $rows as $row ) {
			$seen[ number_format( (float) $row['amount'], 4, '.', '' ) ] = true;
		}
		return count( $seen );
	}

	/**
	 * @param object $product Product.
	 * @param mixed  $price   Unit price before tax display.
	 * @return float
	 */
	private static function display_amount( $product, $price ) {
		if ( function_exists( 'wc_get_price_to_display' ) ) {
			return (float) wc_get_price_to_display(
				$product,
				array(
					'qty'   => 1,
					'price' => $price,
				)
			);
		}
		return (float) $price;
	}

	/**
	 * @param object $product Product.
	 * @param string $custom  Merchant suffix. Empty uses the subscription period.
	 * @return string
	 */
	private static function period_text( $product, $custom ) {
		$custom = trim( (string) $custom );
		if ( '' !== $custom ) {
			return $custom;
		}
		return self::subscription_suffix( $product );
	}

	/**
	 * @param object $product Product.
	 * @return string
	 */
	private static function subscription_suffix( $product ) {
		$period   = '';
		$interval = 1;
		if ( class_exists( 'WC_Subscriptions_Product', false ) ) {
			if ( method_exists( 'WC_Subscriptions_Product', 'get_period' ) ) {
				$period = (string) WC_Subscriptions_Product::get_period( $product );
			}
			if ( method_exists( 'WC_Subscriptions_Product', 'get_interval' ) ) {
				$interval = (int) WC_Subscriptions_Product::get_interval( $product );
			}
		}
		if ( '' === $period && is_object( $product ) && method_exists( $product, 'get_meta' ) ) {
			$period   = (string) $product->get_meta( '_subscription_period', true );
			$interval = (int) $product->get_meta( '_subscription_period_interval', true );
		}
		$period = sanitize_key( $period );
		if ( ! in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ) {
			return '';
		}
		if ( $interval < 1 ) {
			$interval = 1;
		}
		$labels = array(
			'day'   => array( __( 'day', 'reactwoo-geo-commerce' ), __( 'days', 'reactwoo-geo-commerce' ) ),
			'week'  => array( __( 'week', 'reactwoo-geo-commerce' ), __( 'weeks', 'reactwoo-geo-commerce' ) ),
			'month' => array( __( 'month', 'reactwoo-geo-commerce' ), __( 'months', 'reactwoo-geo-commerce' ) ),
			'year'  => array( __( 'year', 'reactwoo-geo-commerce' ), __( 'years', 'reactwoo-geo-commerce' ) ),
		);
		if ( 1 === $interval ) {
			return '/' . $labels[ $period ][0];
		}
		return '/' . $interval . ' ' . $labels[ $period ][1];
	}

	/**
	 * @param array<string, mixed> $built Built price.
	 * @param array<string, mixed> $args  Parsed args.
	 * @return string
	 */
	private static function price_html( array $built, array $args ) {
		$product_id = isset( $built['product'] ) && self::is_product( $built['product'] ) ? (int) $built['product']->get_id() : 0;
		$currency   = isset( $built['currency'] ) ? (string) $built['currency'] : '';
		$current    = self::format_price_html( $built['amount'], $currency, $args['format'] );
		$classes    = array( 'rwgcm-price', 'rwgcm-price--format-' . $args['format'] );
		if ( 'inherit' !== $args['color'] ) {
			$classes[] = 'rwgcm-price--color-' . $args['color'];
		}
		if ( ! empty( $built['show_from'] ) ) {
			$classes[] = 'rwgcm-price--from';
		}
		if ( ! empty( $built['show_sale'] ) ) {
			$classes[] = 'rwgcm-price--sale';
		}
		foreach ( $args['classes'] as $extra ) {
			$classes[] = $extra;
		}

		$html = '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" data-product-id="' . esc_attr( (string) $product_id ) . '">';
		if ( ! empty( $built['show_from'] ) ) {
			$html .= '<span class="rwgcm-price__from">' . esc_html__( 'From', 'reactwoo-geo-commerce' ) . '</span>';
		}
		if ( ! empty( $built['show_sale'] ) ) {
			$regular = self::format_price_html( $built['regular_amount'], $currency, $args['format'] );
			$html   .= '<span class="rwgcm-price__regular" aria-hidden="true"><del>' . $regular . '</del></span>';
			$html   .= '<span class="screen-reader-text">' . esc_html( sprintf(
				/* translators: %s: original price including currency */
				__( 'Original price was: %s', 'reactwoo-geo-commerce' ),
				self::plain_with_code( $built['regular_amount'], $currency )
			) ) . '</span>';
			$html .= '<span class="rwgcm-price__amount rwgcm-price__sale" aria-hidden="true"><ins>' . $current . '</ins></span>';
			$html .= '<span class="screen-reader-text">' . esc_html( sprintf(
				/* translators: %s: current price including currency */
				__( 'Current price is: %s', 'reactwoo-geo-commerce' ),
				self::plain_with_code( $built['amount'], $currency )
			) ) . '</span>';
		} else {
			$html .= '<span class="rwgcm-price__amount">' . $current . '</span>';
		}
		if ( ! empty( $built['period'] ) ) {
			$html .= '<span class="rwgcm-price__period">' . esc_html( (string) $built['period'] ) . '</span>';
		}
		$html .= '</span>';
		return $html;
	}

	/**
	 * @param array<string, mixed> $args    Parsed args.
	 * @param object|null          $product Product, when one was resolved.
	 * @return string
	 */
	private static function fallback_html( array $args, $product ) {
		$id = self::is_product( $product ) ? (int) $product->get_id() : 0;
		if ( $id <= 0 ) {
			$id = $args['variation_id'] > 0 ? (int) $args['variation_id'] : (int) $args['product_id'];
		}
		$classes = array( 'rwgcm-price', 'rwgcm-price--unavailable' );
		if ( 'inherit' !== $args['color'] ) {
			$classes[] = 'rwgcm-price--color-' . $args['color'];
		}
		foreach ( $args['classes'] as $extra ) {
			$classes[] = $extra;
		}
		return '<span class="' . esc_attr( implode( ' ', $classes ) ) . '" data-product-id="' . esc_attr( (string) $id ) . '"><span class="rwgcm-price__fallback">' . esc_html( (string) $args['fallback'] ) . '</span></span>';
	}

	/**
	 * @param mixed  $amount   Display amount.
	 * @param string $currency ISO currency code.
	 * @param string $format   amount, symbol, or code.
	 * @return string Safe HTML.
	 */
	private static function format_price_html( $amount, $currency, $format ) {
		if ( 'amount' === $format || 'code' === $format ) {
			return esc_html( 'code' === $format ? self::plain_with_code( $amount, $currency ) : self::format_plain( $amount ) );
		}
		if ( function_exists( 'wc_price' ) ) {
			$args = array();
			if ( '' !== $currency ) {
				$args['currency'] = $currency;
			}
			$html = wc_price( $amount, $args );
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
		}
		return esc_html( self::format_plain( $amount ) );
	}

	/**
	 * @param mixed  $amount   Display amount.
	 * @param string $currency ISO currency code.
	 * @return string
	 */
	private static function plain_with_code( $amount, $currency ) {
		$text = self::format_plain( $amount );
		if ( '' !== $currency ) {
			$text .= ' ' . $currency;
		}
		return $text;
	}

	/**
	 * @param mixed $amount Display amount.
	 * @return string
	 */
	private static function format_plain( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		if ( $decimals < 0 ) {
			$decimals = 0;
		}
		$decimal_sep  = function_exists( 'wc_get_price_decimal_separator' ) ? (string) wc_get_price_decimal_separator() : '.';
		$thousand_sep = function_exists( 'wc_get_price_thousand_separator' ) ? (string) wc_get_price_thousand_separator() : ',';
		if ( '' === $decimal_sep ) {
			$decimal_sep = '.';
		}
		return number_format( (float) $amount, $decimals, $decimal_sep, $thousand_sep );
	}

	/**
	 * @param object $product Variable parent.
	 * @return array<int, object>
	 */
	private static function child_products( $product ) {
		$ids = array();
		if ( method_exists( $product, 'get_visible_children' ) ) {
			$ids = $product->get_visible_children();
		} elseif ( method_exists( $product, 'get_children' ) ) {
			$ids = $product->get_children();
		}
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( $ids as $id ) {
			$child = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( self::is_product( $child ) ) {
				$out[] = $child;
			}
		}
		return $out;
	}

	/**
	 * @param object|null $product Product.
	 * @return bool
	 */
	private static function can_purchase( $product ) {
		if ( ! self::is_product( $product ) ) {
			return false;
		}
		if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
			return false;
		}
		if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
			return false;
		}
		return true;
	}

	/**
	 * @param object|null $product Product.
	 * @return bool
	 */
	private static function is_variable_parent( $product ) {
		return self::is_product( $product ) && method_exists( $product, 'is_type' ) && ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) );
	}

	/**
	 * @param mixed $product Candidate.
	 * @return bool
	 */
	private static function is_product( $product ) {
		return is_object( $product ) && method_exists( $product, 'get_id' ) && method_exists( $product, 'get_price' );
	}

	/**
	 * @param array<string, mixed> $raw Attributes.
	 * @param string               $key Key.
	 * @return mixed|null
	 */
	private static function raw_value( array $raw, $key ) {
		if ( ! array_key_exists( $key, $raw ) ) {
			return null;
		}
		$value = $raw[ $key ];
		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			return $value['value'];
		}
		if ( is_object( $value ) && isset( $value->value ) ) {
			return $value->value;
		}
		return $value;
	}

	/**
	 * @param mixed  $value   Raw value.
	 * @param string $default Default when the value is missing.
	 * @return scalar
	 */
	private static function scalar( $value, $default ) {
		if ( null === $value ) {
			return $default;
		}
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( is_scalar( $value ) ) {
			return $value;
		}
		return $default;
	}

	/**
	 * @param mixed $value   Raw flag.
	 * @param bool  $default Default when absent.
	 * @return bool
	 */
	private static function flag( $value, $default ) {
		if ( null === $value ) {
			return $default;
		}
		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			$value = $value['value'];
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		$token = strtolower( trim( (string) $value ) );
		if ( '' === $token ) {
			return $default;
		}
		return ! in_array( $token, array( '0', 'no', 'false', 'off' ), true );
	}

	/**
	 * @param mixed $value Class string or list.
	 * @return array<int, string>
	 */
	private static function class_list( $value ) {
		if ( null === $value ) {
			return array();
		}
		if ( is_array( $value ) && array_key_exists( 'value', $value ) && ! isset( $value[0] ) ) {
			$value = $value['value'];
		}
		$parts = is_array( $value ) ? $value : preg_split( '/\s+/', (string) $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			if ( is_array( $part ) || is_object( $part ) ) {
				continue;
			}
			$class_name = function_exists( 'sanitize_html_class' ) ? sanitize_html_class( (string) $part ) : '';
			if ( '' !== $class_name ) {
				$out[] = $class_name;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $value Raw text.
	 * @param int   $limit Maximum length.
	 * @return string
	 */
	private static function clean_text( $value, $limit ) {
		if ( is_bool( $value ) || null === $value ) {
			$value = '';
		}
		$text = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $value ) : trim( strip_tags( (string) $value ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $limit );
		}
		return substr( $text, 0, $limit );
	}
}
