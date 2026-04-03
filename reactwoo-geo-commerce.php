<?php
/**
 * Plugin Name: ReactWoo Geo Commerce
 * Description: WooCommerce personalization overlays on ReactWoo Geo Core. Requires Geo Core and WooCommerce.
 * Version: 0.2.17.0
 * Author: ReactWoo
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reactwoo-geo-commerce
 * Requires Plugins: reactwoo-geocore, woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RWGCM_VERSION' ) ) {
	define( 'RWGCM_VERSION', '0.2.17.0' );
}
if ( ! defined( 'RWGCM_FILE' ) ) {
	define( 'RWGCM_FILE', __FILE__ );
}
if ( ! defined( 'RWGCM_PATH' ) ) {
	define( 'RWGCM_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RWGCM_URL' ) ) {
	define( 'RWGCM_URL', plugin_dir_url( __FILE__ ) );
}

require_once RWGCM_PATH . 'includes/class-rwgcm-plugin.php';

/**
 * Bootstrap after Geo Core.
 *
 * @return void
 */
function rwgcm_boot() {
	RWGCM_Plugin::instance()->boot();
}

add_action( 'plugins_loaded', 'rwgcm_boot', 20 );
