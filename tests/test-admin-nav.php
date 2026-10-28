<?php
/**
 * Commerce admin navigation. No WordPress database.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-admin.php';

$passed = 0;
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Result.
 * @return void
 */
function assert_nav( $label, $ok ) {
	global $passed, $failed;
	if ( $ok ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

$routes = array();
foreach ( RWGCM_Admin::admin_routes() as $route ) {
	$routes[ $route['menu_slug'] ] = $route;
}

assert_nav( 'the Commerce hub slug is rwgc-commerce-hub', 'rwgc-commerce-hub' === RWGCM_Admin::COMMERCE_HUB_SLUG );
assert_nav( 'Rules is a Commerce screen', isset( $routes['rwgcm-pricing'] ) && 'Rules' === $routes['rwgcm-pricing']['label'] );
assert_nav( 'Price books is its own screen', isset( $routes['rwgcm-price-books'] ) && 'Price books' === $routes['rwgcm-price-books']['label'] && 'price-books' === $routes['rwgcm-price-books']['route'] );
assert_nav( 'Payments by country is its own screen', isset( $routes['rwgcm-payments'] ) && 'Payments by country' === $routes['rwgcm-payments']['label'] );
assert_nav( 'Settings stays in the Commerce area', isset( $routes['rwgcm-settings'] ) && 'Settings' === $routes['rwgcm-settings']['label'] && ! isset( $routes['rwgcm-settings']['section'] ) );
assert_nav(
	'Price books is not the rule builder',
	'rwgcm-pricing' !== $routes['rwgcm-price-books']['menu_slug'] && 'RWGCM_Admin_Rules' !== $routes['rwgcm-price-books']['callback'][0]
);

$nav = array_keys( RWGCM_Admin::inner_nav_items() );
$rules_at    = array_search( 'rwgcm-pricing', $nav, true );
$books_at    = array_search( 'rwgcm-price-books', $nav, true );
$payments_at = array_search( 'rwgcm-payments', $nav, true );
$settings_at = array_search( 'rwgcm-settings', $nav, true );
assert_nav(
	'fallback nav order is Rules, Price books, Payments by country, Settings',
	false !== $rules_at && $rules_at < $books_at && $books_at < $payments_at && $payments_at < $settings_at
);

$promoted = RWGCM_Admin::promote_hub_cards(
	array(
		array(
			'menu_slug' => 'rwgcm-fees',
			'label'     => 'Cart fees',
			'url'       => 'https://example.test/fees',
		),
		array(
			'menu_slug' => 'rwgcm-pricing',
			'label'     => 'Rules',
			'url'       => 'https://example.test/rules',
		),
	)
);
$labels = array();
foreach ( array_slice( $promoted, 0, 4 ) as $card ) {
	$labels[] = $card['label'];
}
assert_nav(
	'the Commerce hub leads with Rules, Price books, Configure payment gateways, and Settings',
	array( 'Rules', 'Price books', 'Configure payment gateways', 'Settings' ) === $labels
);
assert_nav(
	'the payments hub card keeps the payments screen url slot',
	'rwgcm-payments' === $promoted[2]['menu_slug'] && ! empty( $promoted[2]['prominent'] )
);
assert_nav(
	'other Commerce screens stay after the primary cards',
	'rwgcm-fees' === $promoted[4]['menu_slug']
);
assert_nav( 'WooCommerce Payments tab is the checkout settings tab', RWGCM_Admin::is_wc_payments_screen( array( 'page' => 'wc-settings', 'tab' => 'checkout' ) ) );
assert_nav( 'a product edit screen is not the WooCommerce payments screen', ! RWGCM_Admin::is_wc_payments_screen( array( 'page' => 'post', 'tab' => '' ) ) );
assert_nav( 'Commerce settings is not the WooCommerce payments screen', ! RWGCM_Admin::is_wc_payments_screen( array( 'page' => 'rwgcm-payments', 'tab' => 'checkout' ) ) );
assert_nav( 'a WooCommerce Payments section is still the Payments screen', RWGCM_Admin::is_wc_payments_screen( array( 'page' => 'wc-settings', 'tab' => 'checkout' ) ) );
$payments_redirect = RWGCM_Admin::settings_redirect_url( 'payments' );
$books_redirect    = RWGCM_Admin::settings_redirect_url( 'price-books' );
assert_nav( 'saving Payments by country returns to rwgcm-payments', false !== strpos( $payments_redirect, 'page=rwgcm-payments' ) && false === strpos( $payments_redirect, 'rwgcm-price-books' ) );
assert_nav( 'saving price books returns to rwgcm-price-books', false !== strpos( $books_redirect, 'page=rwgcm-price-books' ) );
$shipping_redirect = RWGCM_Admin::settings_redirect_url( 'price-books-shipping' );
assert_nav( 'saving local pickup costs returns to the Shipping tab and not a zone', false !== strpos( $shipping_redirect, 'page=rwgcm-price-books' ) && false !== strpos( $shipping_redirect, 'tab=shipping' ) && false === strpos( $shipping_redirect, 'zone_id' ) );
$checkout_settings = RWGCM_Admin::filter_checkout_settings( array(), '' );
$checkout_desc     = isset( $checkout_settings[0]['desc'] ) ? (string) $checkout_settings[0]['desc'] : '';
assert_nav(
	'the WooCommerce Payments screen settings include the per-country link',
	false !== strpos( $checkout_desc, 'Set per-country availability in ReactWoo Geo' ) && false !== strpos( $checkout_desc, 'page=rwgcm-payments' )
);
assert_nav( 'a gateway settings section does not gain the overview field', array() === RWGCM_Admin::filter_checkout_settings( array(), 'bacs' ) );
$gateway_fields = RWGCM_Admin::append_gateway_settings_note( array( 'enabled' => array( 'type' => 'checkbox' ) ) );
assert_nav(
	'a classic gateway settings form links to Payments by country and is not a saved checkbox',
	isset( $gateway_fields['rwgcm_payments_by_country'] )
		&& 'title' === $gateway_fields['rwgcm_payments_by_country']['type']
		&& false !== strpos( (string) $gateway_fields['rwgcm_payments_by_country']['description'], 'Set per-country availability in ReactWoo Geo' )
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
