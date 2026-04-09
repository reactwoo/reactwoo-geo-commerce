<?php
/**
 * Applies resolved actions to WooCommerce flows (cart, catalog, checkout).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action application facade (cart pricing uses {@see RWGCM_Pricing_Apply}).
 */
class RWGCM_Action_Applier {

	/**
	 * Placeholder for future non-price actions.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Fires when Geo Commerce action applier is ready.
		 */
		do_action( 'rwgcm_action_applier_init' );
	}
}
