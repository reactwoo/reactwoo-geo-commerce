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

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
