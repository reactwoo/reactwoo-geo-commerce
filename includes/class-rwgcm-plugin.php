<?php
/**
 * Geo Commerce — satellite plugin (requires Geo Core + WooCommerce).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main controller for ReactWoo Geo Commerce.
 */
class RWGCM_Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function boot() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'maybe_admin_notice_missing_deps' ) );
		}

		if ( ! $this->dependencies_ok() ) {
			return;
		}

		require_once RWGCM_PATH . 'includes/class-rwgcm-pricing-rules.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-pricing-calc.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-pricing-apply.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-catalog-price.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-catalog-price-variable.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-order-geo.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-admin-orders-list.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-geo.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-cart-bridge.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-fee-rules.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-fee-rules-apply.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-cart-fees.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-shipping.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-coupon-geo.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-attribution.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-admin.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-admin-pricing.php';
		require_once RWGCM_PATH . 'includes/class-rwgcm-admin-fees.php';
		RWGCM_Attribution::init();
		RWGCM_Order_Geo::init();
		RWGCM_Admin_Orders_List::init();
		RWGCM_Geo::init();
		RWGCM_Cart_Bridge::init();
		RWGCM_Fee_Rules_Apply::init();
		RWGCM_Cart_Fees::init();
		RWGCM_Shipping::init();
		RWGCM_Coupon_Geo::init();
		RWGCM_Pricing_Apply::init();
		RWGCM_Catalog_Price::init();
		RWGCM_Catalog_Price_Variable::init();
		RWGCM_Admin::init();
		RWGCM_Admin_Pricing::init();
		RWGCM_Admin_Fees::init();

		/**
		 * Fires when Geo Commerce is ready (Geo Core + WooCommerce active).
		 */
		do_action( 'rwgcm_loaded' );
	}

	/**
	 * @return bool
	 */
	private function is_geo_core_active() {
		if ( function_exists( 'rwgc_is_geo_core_active' ) ) {
			return (bool) rwgc_is_geo_core_active();
		}
		// Fallback when helpers are unavailable (load order, or Geo Core loaded without functions-rwgc.php).
		return class_exists( 'RWGC_Plugin', false )
			|| ( defined( 'RWGC_VERSION' ) && defined( 'RWGC_FILE' ) );
	}

	/**
	 * @return bool
	 */
	private function is_woocommerce_active() {
		if ( function_exists( 'rwgc_is_woocommerce_active' ) ) {
			return (bool) rwgc_is_woocommerce_active();
		}
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * @return bool
	 */
	private function dependencies_ok() {
		return $this->is_geo_core_active() && $this->is_woocommerce_active();
	}

	/**
	 * @return void
	 */
	public function maybe_admin_notice_missing_deps() {
		if ( $this->dependencies_ok() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$messages = array();
		if ( ! $this->is_geo_core_active() ) {
			$messages[] = __( 'ReactWoo Geo Commerce requires ReactWoo Geo Core to be installed and active.', 'reactwoo-geo-commerce' );
		}
		if ( ! $this->is_woocommerce_active() ) {
			$messages[] = __( 'ReactWoo Geo Commerce requires WooCommerce to be installed and active.', 'reactwoo-geo-commerce' );
		}
		foreach ( $messages as $msg ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $msg ) );
		}
	}
}
