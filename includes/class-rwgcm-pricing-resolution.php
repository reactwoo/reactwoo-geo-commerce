<?php
/**
 * Resolves which price adjustment applies: generic rules (Geo Core targets) or legacy rows.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges {@see RWGCM_Pricing_Calc} with the new rule engine.
 */
class RWGCM_Pricing_Resolution {

	/**
	 * Memo of resolved adjustments keyed by product, currency, country and context.
	 *
	 * @var array<string, array{type: string, value: float}|null>
	 */
	private static $adjustments = array();

	/**
	 * Whether cart/catalog geo pricing should run.
	 *
	 * @return bool
	 */
	public static function is_pricing_effective() {
		if ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() ) {
			return true;
		}
		return class_exists( 'RWGCM_Pricing_Rules', false ) && RWGCM_Pricing_Rules::is_enabled();
	}

	/**
	 * Legacy-shaped adjustment array for {@see RWGCM_Pricing_Calc::compute_adjusted()} or null.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return array{type: string, value: float}|null
	 */
	public static function find_price_adjustment( $product ) {
		$key = self::adjustment_cache_key( $product );
		if ( array_key_exists( $key, self::$adjustments ) ) {
			return self::$adjustments[ $key ];
		}
		self::$adjustments[ $key ] = self::resolve_price_adjustment( $product );
		return self::$adjustments[ $key ];
	}

	/**
	 * @return void
	 */
	public static function flush_request_cache() {
		self::$adjustments = array();
	}

	/**
	 * @param \WC_Product|mixed $product Product or variation.
	 * @return array{type: string, value: float}|null
	 */
	private static function resolve_price_adjustment( $product ) {
		if ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() && class_exists( 'RWGCM_Rule_Evaluator', false ) ) {
			$ctx = function_exists( 'rwgc_get_context_snapshot' ) ? rwgc_get_context_snapshot() : array();
			if ( ! is_array( $ctx ) ) {
				$ctx = array();
			}
			$win = RWGCM_Rule_Evaluator::get_winning_price_rule( $product, $ctx );
			return RWGCM_Rule_Evaluator::get_price_adjustment_for_calc( $win );
		}
		$country = class_exists( 'RWGCM_Pricing_Calc', false ) ? RWGCM_Pricing_Calc::get_visitor_country() : '';
		return class_exists( 'RWGCM_Pricing_Rules', false ) ? RWGCM_Pricing_Rules::find_matching_rule( $country, $product ) : null;
	}

	/**
	 * Key includes every input that can change the resolved adjustment.
	 *
	 * @param mixed $product Product or variation.
	 * @return string
	 */
	private static function adjustment_cache_key( $product ) {
		$id   = 0;
		$cats = '';
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$id = (int) $product->get_id();
		}
		if ( is_object( $product ) && method_exists( $product, 'get_category_ids' ) ) {
			$ids = array_map( 'intval', (array) $product->get_category_ids() );
			sort( $ids, SORT_NUMERIC );
			$cats = implode( ',', $ids );
		}
		$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		$country  = '';
		if ( class_exists( 'RWGCM_Pricing_Calc', false ) ) {
			$country = (string) RWGCM_Pricing_Calc::get_visitor_country();
		} elseif ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$country = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		$ctx = function_exists( 'rwgc_get_context_snapshot' ) ? rwgc_get_context_snapshot() : array();
		if ( ! is_array( $ctx ) ) {
			$ctx = array();
		}
		$generic = ( class_exists( 'RWGCM_Diagnostics', false ) && RWGCM_Diagnostics::uses_generic_pricing_rules() ) ? '1' : '0';
		$legacy  = function_exists( 'get_option' ) ? get_option( 'rwgcm_pricing', array() ) : array();
		$encoded = self::encode_cache_payload(
			array(
				'ctx'     => $ctx,
				'generic' => $generic,
				'legacy'  => $legacy,
			)
		);
		return $id . '|' . $cats . '|' . $currency . '|' . $country . '|' . md5( $encoded );
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function encode_cache_payload( $value ) {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );
		return is_string( $encoded ) ? $encoded : '';
	}
}
