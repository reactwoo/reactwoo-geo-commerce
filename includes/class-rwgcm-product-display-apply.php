<?php
/**
 * Applies contextual display overlays without changing canonical product identity.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storefront filters: name, short description, description, gallery, badge, CTA.
 */
class RWGCM_Product_Display_Apply {

	/**
	 * @var array<int, array<string, mixed>|null>
	 */
	private static $overlay_cache = array();

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			do_action( 'rwgcm_product_display_apply_init' );
			return;
		}

		add_filter( 'woocommerce_product_get_name', array( __CLASS__, 'filter_name' ), 99, 2 );
		add_filter( 'woocommerce_product_get_short_description', array( __CLASS__, 'filter_short_description' ), 99, 2 );
		add_filter( 'woocommerce_product_get_description', array( __CLASS__, 'filter_description' ), 99, 2 );
		add_filter( 'woocommerce_product_get_gallery_image_ids', array( __CLASS__, 'filter_gallery' ), 99, 2 );

		add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'maybe_print_badge_single' ), 8 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'maybe_print_badge_loop' ), 9 );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'maybe_print_cta' ), 15 );

		do_action( 'rwgcm_product_display_apply_init' );
	}

	/**
	 * @param \WC_Product $product Product.
	 * @return int
	 */
	private static function canonical_product_id( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return 0;
		}
		if ( $product->is_type( 'variation' ) ) {
			return (int) $product->get_parent_id();
		}
		return (int) $product->get_id();
	}

	/**
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>|null
	 */
	private static function get_resolved_overlay( $product ) {
		$cid = self::canonical_product_id( $product );
		if ( $cid <= 0 ) {
			return null;
		}
		if ( array_key_exists( $cid, self::$overlay_cache ) ) {
			return self::$overlay_cache[ $cid ];
		}
		if ( ! function_exists( 'rwgc_get_context_snapshot' ) ) {
			self::$overlay_cache[ $cid ] = null;
			return null;
		}
		$ctx = rwgc_get_context_snapshot();
		$ov  = RWGCM_Product_Overlay_Resolver::resolve( $cid, is_array( $ctx ) ? $ctx : array() );
		self::$overlay_cache[ $cid ] = $ov;
		return $ov;
	}

	/**
	 * @param array<string, mixed>|null $overlay Resolved overlay.
	 * @param string                    $key     Override key.
	 * @return mixed|null
	 */
	private static function get_override_value( $overlay, $key ) {
		if ( ! is_array( $overlay ) || empty( $overlay['overrides'][ $key ] ) || ! is_array( $overlay['overrides'][ $key ] ) ) {
			return null;
		}
		$b = $overlay['overrides'][ $key ];
		if ( empty( $b['enabled'] ) ) {
			return null;
		}
		return isset( $b['value'] ) ? $b['value'] : null;
	}

	/**
	 * @param string      $name    Name.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function filter_name( $name, $product ) {
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'title' );
		if ( null !== $v && '' !== (string) $v ) {
			return (string) $v;
		}
		return $name;
	}

	/**
	 * @param string      $html    Short description HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function filter_short_description( $html, $product ) {
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'short_description' );
		if ( null !== $v && '' !== (string) $v ) {
			return (string) $v;
		}
		return $html;
	}

	/**
	 * @param string      $html    Description HTML.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public static function filter_description( $html, $product ) {
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'description' );
		if ( null !== $v && '' !== (string) $v ) {
			return (string) $v;
		}
		return $html;
	}

	/**
	 * @param int[]       $ids     Attachment IDs.
	 * @param \WC_Product $product Product.
	 * @return int[]
	 */
	public static function filter_gallery( $ids, $product ) {
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'gallery' );
		if ( null === $v ) {
			return $ids;
		}
		if ( is_array( $v ) ) {
			return array_map( 'absint', $v );
		}
		return $ids;
	}

	/**
	 * @return void
	 */
	public static function maybe_print_badge_single() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		self::print_badge( $product );
	}

	/**
	 * @return void
	 */
	public static function maybe_print_badge_loop() {
		global $product;
		self::print_badge( $product );
	}

	/**
	 * @param \WC_Product|null $product Product.
	 * @return void
	 */
	private static function print_badge( $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'badge' );
		if ( null === $v || '' === (string) $v ) {
			return;
		}
		echo '<span class="rwgcm-overlay-badge">' . esc_html( (string) $v ) . '</span>';
	}

	/**
	 * @return void
	 */
	public static function maybe_print_cta() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}
		$ov = self::get_resolved_overlay( $product );
		$v  = self::get_override_value( $ov, 'cta' );
		if ( null === $v || '' === (string) $v ) {
			return;
		}
		echo '<div class="rwgcm-overlay-cta">' . wp_kses_post( (string) $v ) . '</div>';
	}
}
