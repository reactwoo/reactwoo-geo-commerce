<?php
/**
 * Variable product: min/max and variation prices match geo pricing rules (storefront).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks WooCommerce variation price filters + cache hash so per-country rules do not share transients.
 */
class RWGCM_Catalog_Price_Variable {

	/**
	 * One-time cleanup marker; bump when the variation price cache must be purged again.
	 */
	const PRICE_CACHE_FIX_OPTION  = 'rwgcm_var_price_cache_fix';
	const PRICE_CACHE_FIX_VERSION = '1';

	/**
	 * Per-request memoized pricing signature per product id (context is fixed per request).
	 *
	 * @var array<int, string>
	 */
	private static $signature_cache = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'filter_price_hash' ), 10, 3 );
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_variation_price' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( __CLASS__, 'filter_variation_regular_price' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( __CLASS__, 'filter_variation_sale_price' ), 99, 3 );
		// Run before WooCommerce reads variation prices (template/the_content), so the
		// already-bloated transients are removed before they can be read into memory.
		add_action( 'init', array( __CLASS__, 'maybe_purge_legacy_price_cache' ), 0 );
	}

	/**
	 * Make the variation price cache key vary by the winning price rule (a bounded set)
	 * rather than the raw visitor context.
	 *
	 * WooCommerce stores variation prices in the `wc_var_prices_{product_id}` transient keyed
	 * by this hash, and appends a new entry whenever the hash changes (never pruning). The
	 * previous implementation hashed the full context snapshot (country, region, city, IP,
	 * coordinates, weather, time, page version, …), so the key was effectively unique per
	 * request: the transient grew without bound until reading/writing it (tens of MB)
	 * exhausted PHP memory on catalog pages. Keying on the matched rule keeps the number of
	 * distinct entries bounded by the number of pricing rules, and is correct because the
	 * winning rule fully determines the adjusted price.
	 *
	 * @param array      $price_hash Hash payload.
	 * @param \WC_Product $product   Variable product.
	 * @param bool       $for_display Display vs edit context.
	 * @return array
	 */
	public static function filter_price_hash( $price_hash, $product, $for_display ) {
		unset( $for_display );
		if ( ! class_exists( 'RWGCM_Pricing_Resolution', false ) || ! RWGCM_Pricing_Resolution::is_pricing_effective() ) {
			return $price_hash;
		}
		if ( ! is_array( $price_hash ) ) {
			$price_hash = array();
		}
		if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_type( 'variable' ) ) {
			return $price_hash;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_hash;
		}
		if ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() ) {
			$price_hash['rwgcm_rule'] = self::pricing_signature_for_product( $product );
		} else {
			$country = RWGCM_Pricing_Calc::get_visitor_country();
			$price_hash['rwgcm_visitor_country'] = strlen( $country ) === 2 ? $country : '';
		}
		return $price_hash;
	}

	/**
	 * Bounded signature for the price-affecting rule currently winning for a product.
	 *
	 * Returns 'none' when no price adjustment applies, so all unadjusted contexts share a
	 * single cache entry (the base prices). Distinct values are bounded by the number of
	 * configured price rules.
	 *
	 * @param \WC_Product $product Variable product.
	 * @return string
	 */
	private static function pricing_signature_for_product( $product ) {
		$pid = is_a( $product, 'WC_Product' ) ? (int) $product->get_id() : 0;
		if ( $pid > 0 && array_key_exists( $pid, self::$signature_cache ) ) {
			return self::$signature_cache[ $pid ];
		}

		$signature = 'none';
		if ( class_exists( 'RWGCM_Rule_Evaluator', false ) && function_exists( 'rwgc_get_context_snapshot' ) ) {
			$ctx  = rwgc_get_context_snapshot();
			$rule = RWGCM_Rule_Evaluator::get_winning_price_rule( $product, is_array( $ctx ) ? $ctx : array() );
			$adj  = RWGCM_Rule_Evaluator::get_price_adjustment_for_calc( $rule );
			if ( null !== $adj ) {
				$rule_id   = is_array( $rule ) && isset( $rule['id'] ) ? (string) $rule['id'] : '';
				$signature = substr( md5( $rule_id . '|' . $adj['type'] . '|' . $adj['value'] ), 0, 16 );
			}
		}

		if ( $pid > 0 ) {
			self::$signature_cache[ $pid ] = $signature;
		}
		return $signature;
	}

	/**
	 * One-time removal of WooCommerce variation price transients bloated by the previous
	 * context-hash key. Guarded by an option so it runs once per fix version.
	 *
	 * @return void
	 */
	public static function maybe_purge_legacy_price_cache() {
		if ( (string) get_option( self::PRICE_CACHE_FIX_OPTION ) === self::PRICE_CACHE_FIX_VERSION ) {
			return;
		}
		self::purge_variation_price_cache();
		update_option( self::PRICE_CACHE_FIX_OPTION, self::PRICE_CACHE_FIX_VERSION, false );
	}

	/**
	 * Delete the wc_var_prices_* transients directly (by option name, without loading their
	 * potentially huge values) and bump WooCommerce's product transient version.
	 *
	 * @return void
	 */
	public static function purge_variation_price_cache() {
		global $wpdb;

		$like_value   = $wpdb->esc_like( '_transient_wc_var_prices_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_wc_var_prices_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_value,
				$like_timeout
			)
		);

		if ( class_exists( 'WC_Cache_Helper' ) ) {
			// Invalidate any object-cache copies of product transients too.
			WC_Cache_Helper::get_transient_version( 'product', true );
		}
	}

	/**
	 * @param string|float $price     Active price (edit).
	 * @param \WC_Product  $variation Variation.
	 * @param \WC_Product  $parent    Variable parent.
	 * @return string
	 */
	public static function filter_variation_price( $price, $variation, $parent ) {
		unset( $parent );
		if ( ! self::guard( $variation ) ) {
			return $price;
		}
		$base = RWGCM_Pricing_Calc::get_base_unit_price( $variation );
		if ( $base <= 0 ) {
			return $price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param string|float $regular_price Regular price (edit).
	 * @param \WC_Product  $variation     Variation.
	 * @param \WC_Product  $parent        Variable parent.
	 * @return string
	 */
	public static function filter_variation_regular_price( $regular_price, $variation, $parent ) {
		unset( $parent );
		if ( ! self::guard( $variation ) ) {
			return $regular_price;
		}
		$base = floatval( $regular_price );
		if ( $base <= 0 ) {
			return $regular_price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param string|float $sale_price Sale price (edit).
	 * @param \WC_Product  $variation  Variation.
	 * @param \WC_Product  $parent     Variable parent.
	 * @return string
	 */
	public static function filter_variation_sale_price( $sale_price, $variation, $parent ) {
		unset( $parent );
		if ( '' === (string) $sale_price || null === $sale_price ) {
			return $sale_price;
		}
		if ( ! self::guard( $variation ) ) {
			return $sale_price;
		}
		$base = floatval( $sale_price );
		if ( $base <= 0 ) {
			return $sale_price;
		}
		return self::format_adjusted( $base, $variation, $variation );
	}

	/**
	 * @param \WC_Product $variation Variation.
	 * @return bool
	 */
	private static function guard( $variation ) {
		if ( ! class_exists( 'RWGCM_Pricing_Resolution', false ) || ! RWGCM_Pricing_Resolution::is_pricing_effective() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( ! is_a( $variation, 'WC_Product_Variation' ) ) {
			return false;
		}
		if ( ! apply_filters( 'rwgcm_apply_catalog_price', true, $variation ) ) {
			return false;
		}
		return null !== RWGCM_Pricing_Resolution::find_price_adjustment( $variation );
	}

	/**
	 * @param float       $base      Positive amount.
	 * @param \WC_Product $variation Variation (for rule + filters).
	 * @param \WC_Product $product_for_filter Same as variation for filter API.
	 * @return string
	 */
	private static function format_adjusted( $base, $variation, $product_for_filter ) {
		$rule = RWGCM_Pricing_Resolution::find_price_adjustment( $variation );
		if ( null === $rule ) {
			return wc_format_decimal( $base, wc_get_price_decimals() );
		}
		$new_price = RWGCM_Pricing_Calc::compute_adjusted( (float) $base, $rule );
		$new_price = apply_filters( 'rwgcm_adjusted_unit_price', $new_price, (float) $base, $rule, $product_for_filter, array(), '' );
		return wc_format_decimal( $new_price, wc_get_price_decimals() );
	}
}
