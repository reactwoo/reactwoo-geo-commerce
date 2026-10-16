<?php
/**
 * Price book decisions (run with: php tests/test-price-book.php).
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

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $tag Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) { // phpcs:ignore
		unset( $tag );
		return $value;
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * Geo Core stand-in. Ignores any client cookie.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country() { // phpcs:ignore
		return 'ZA';
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-availability.php';

$passed = 0;
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $condition Condition.
 * @return void
 */
function assert_true( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

$essential = array(
	'ZAR' => array(
		'regular'    => '849',
		'sale'       => '',
		'signup_fee' => '',
	),
	'GBP' => array(
		'regular'    => '39',
		'sale'       => '',
		'signup_fee' => '0',
	),
	'EUR' => array(
		'regular'    => '45',
		'sale'       => '40',
		'signup_fee' => '10',
	),
	'USD' => array(
		'regular'    => '49',
		'sale'       => '60',
		'signup_fee' => '',
	),
);

$clean = RWGCM_Price_Book::sanitize_book( $essential );
assert_true( 'essential monthly keeps four currencies', count( $clean ) === 4 );
assert_true( 'ZAR regular is 849.00', isset( $clean['ZAR']['regular'] ) && '849.00' === $clean['ZAR']['regular'] );
assert_true( 'GBP recurring is 39.00', isset( $clean['GBP']['regular'] ) && '39.00' === $clean['GBP']['regular'] );
assert_true( 'EUR regular is 45.00', isset( $clean['EUR']['regular'] ) && '45.00' === $clean['EUR']['regular'] );
assert_true( 'USD regular is 49.00', isset( $clean['USD']['regular'] ) && '49.00' === $clean['USD']['regular'] );
assert_true( 'explicit zero sign-up fee is kept', isset( $clean['GBP']['signup_fee'] ) && '0.00' === $clean['GBP']['signup_fee'] );

$zar = RWGCM_Price_Book::resolve_prices( $clean['ZAR'] );
assert_true( 'ZAR active price is 849', is_array( $zar ) && '849.00' === $zar['active'] && ! $zar['on_sale'] );
assert_true( 'blank ZAR sign-up fee stays unset', is_array( $zar ) && '' === $zar['signup_fee'] );

$eur = RWGCM_Price_Book::resolve_prices( $clean['EUR'] );
assert_true( 'lower EUR sale price is the active price', is_array( $eur ) && $eur['on_sale'] && '40.00' === $eur['active'] );
assert_true( 'EUR sign-up fee is 10', is_array( $eur ) && '10.00' === $eur['signup_fee'] );

$usd = RWGCM_Price_Book::resolve_prices( $clean['USD'] );
assert_true( 'sale price is ignored when it is not lower', is_array( $usd ) && ! $usd['on_sale'] && '49.00' === $usd['active'] );

$dropped = RWGCM_Price_Book::sanitize_book(
	array(
		'CAD' => array( 'regular' => '55' ),
		'ZAR' => array(
			'regular' => '-5',
			'sale'    => '1',
		),
		'GBP' => array( 'regular' => 'not-a-price' ),
	)
);
assert_true( 'unknown currency and invalid amounts are dropped', array() === $dropped );

$extended = RWGCM_Price_Book::sanitize_book(
	array(
		'CAD' => array( 'regular' => '55' ),
	),
	array( 'ZAR', 'GBP', 'EUR', 'USD', 'CAD' )
);
assert_true( 'currency list extension accepts CAD', isset( $extended['CAD']['regular'] ) && '55.00' === $extended['CAD']['regular'] );

assert_true(
	'default currency list is ZAR GBP EUR USD',
	array( 'ZAR', 'GBP', 'EUR', 'USD' ) === RWGCM_Price_Book::currencies()
);

$za = RWGCM_Price_Book::resolve_context( 'ZA', 'US', 'GBP' );
assert_true( 'South Africa billing selects ZAR over a US IP', 'ZAR' === $za['currency'] && 'billing' === $za['source'] );

$de = RWGCM_Price_Book::resolve_context( 'de', 'ZA', 'GBP' );
assert_true( 'Germany billing selects EUR', 'EUR' === $de['currency'] && 'billing' === $de['source'] );

$bg = RWGCM_Price_Book::resolve_context( 'BG', 'GB', 'GBP' );
assert_true( 'Bulgaria billing selects EUR', 'EUR' === $bg['currency'] && 'billing' === $bg['source'] );

$us = RWGCM_Price_Book::resolve_context( '', 'us', 'GBP' );
assert_true( 'geo IP selects USD only when billing is empty', 'USD' === $us['currency'] && 'geo' === $us['source'] );

$jp = RWGCM_Price_Book::resolve_context( 'JP', 'ZA', 'GBP' );
assert_true( 'unmapped billing country falls back to store currency and ignores IP', 'GBP' === $jp['currency'] && 'billing_unmapped' === $jp['reason'] );

$none = RWGCM_Price_Book::resolve_context( '', '', 'GBP' );
assert_true( 'no country uses the store currency', 'GBP' === $none['currency'] && 'unknown' === $none['reason'] );

$renew = RWGCM_Price_Book::resolve_charge( 'GB', 'ZA', 'GBP', 'ZAR' );
assert_true( 'renewal keeps the original ZAR currency', 'ZAR' === $renew['currency'] && 'renewal' === $renew['source'] );

$cad_country = RWGCM_Price_Book::resolve_context(
	'CA',
	'',
	'GBP',
	array( 'CA' => 'CAD' ),
	array( 'ZAR', 'GBP', 'EUR', 'USD', 'CAD' )
);
assert_true( 'country map extension selects CAD', 'CAD' === $cad_country['currency'] && 'billing' === $cad_country['source'] );

$disabled = RWGCM_Price_Book::resolve_context(
	'ZA',
	'',
	'GBP',
	null,
	array( 'GBP', 'EUR', 'USD' )
);
assert_true( 'ZAR is ignored when removed from the currency list', 'GBP' === $disabled['currency'] && 'base' === $disabled['source'] );

assert_true( 'product has a ZAR price', RWGCM_Price_Book::has_price( $clean, 'zar' ) );
assert_true( 'product has no JPY price', ! RWGCM_Price_Book::has_price( $clean, 'JPY' ) );

$mixed = RWGCM_Price_Book::currency_for_lines( 'ZAR', 'GBP', array( true, false ) );
assert_true( 'cart falls back to store currency when any line has no price', 'GBP' === $mixed );

$uniform = RWGCM_Price_Book::currency_for_lines( 'EUR', 'GBP', array( true, true ) );
assert_true( 'cart stays in EUR when every line has a price', 'EUR' === $uniform );

$empty_cart = RWGCM_Price_Book::currency_for_lines( 'USD', 'GBP', array() );
assert_true( 'empty cart keeps the preferred currency', 'USD' === $empty_cart );

assert_true(
	'store-currency geo adjustment still runs without a book price',
	RWGCM_Price_Book::should_apply_base_adjustment( 'GBP', 'GBP', false, true )
);
assert_true(
	'fixed GBP book price is not also geo-adjusted',
	! RWGCM_Price_Book::should_apply_base_adjustment( 'GBP', 'GBP', true, true )
);
assert_true(
	'geo adjustment does not run in ZAR',
	! RWGCM_Price_Book::should_apply_base_adjustment( 'ZAR', 'GBP', false, true )
);
assert_true(
	'turning the migrated option off stops store-currency adjustments',
	! RWGCM_Price_Book::should_apply_base_adjustment( 'GBP', 'GBP', false, false )
);

$notes = RWGCM_Price_Book_Currency::revolut_constraint_notes();
assert_true(
	'Revolut notes name checkout currency and renewal order currency',
	false !== strpos( $notes, 'get_woocommerce_currency()' ) && false !== strpos( $notes, 'renewal order currency' )
);

$defaults = RWGCM_Price_Book::map_from_storage( null );
assert_true( 'missing map uses ZA ZAR', isset( $defaults['ZA'] ) && 'ZAR' === $defaults['ZA'] );
assert_true( 'missing map uses GB GBP', isset( $defaults['GB'] ) && 'GBP' === $defaults['GB'] );
assert_true( 'missing map uses US USD', isset( $defaults['US'] ) && 'USD' === $defaults['US'] );
assert_true( 'missing map uses DE EUR', isset( $defaults['DE'] ) && 'EUR' === $defaults['DE'] );
assert_true( 'saved empty map is not the defaults', array() === RWGCM_Price_Book::map_from_storage( array() ) );
assert_true(
	'stored map override is normalized',
	array( 'FR' => 'EUR' ) === RWGCM_Price_Book::map_from_storage( array( 'fr' => 'eur' ) )
);

$parsed = RWGCM_Price_Book::parse_country_map_text( "za zar\n# comment\nDE EUR\nbad line\nGB:GBP\n" );
assert_true(
	'country map text parses CC and currency',
	array(
		'ZA' => 'ZAR',
		'DE' => 'EUR',
		'GB' => 'GBP',
	) === $parsed
);
assert_true(
	'country map text round-trips in country order',
	"DE EUR\nGB GBP\nZA ZAR" === RWGCM_Price_Book::format_country_map_text( $parsed )
);

$fallback_billing = RWGCM_Price_Book::resolve_context( 'JP', 'ZA', 'GBP', null, null, 'EUR' );
assert_true(
	'unmapped billing uses the fallback and ignores geo',
	'EUR' === $fallback_billing['currency'] && 'fallback' === $fallback_billing['source'] && 'billing_unmapped' === $fallback_billing['reason']
);
$fallback_geo = RWGCM_Price_Book::resolve_context( '', 'JP', 'GBP', null, null, 'EUR' );
assert_true(
	'unmapped visitor country uses the fallback',
	'EUR' === $fallback_geo['currency'] && 'fallback' === $fallback_geo['source'] && 'geo_unmapped' === $fallback_geo['reason']
);
$fallback_unknown = RWGCM_Price_Book::resolve_context( 'JP', 'ZA', 'GBP', null, null, 'CAD' );
assert_true(
	'fallback outside the currency list stays on the store currency',
	'GBP' === $fallback_unknown['currency'] && 'base' === $fallback_unknown['source']
);

assert_true(
	'catalog ignores a remembered billing country',
	'' === RWGCM_Price_Book::effective_billing_country( '', 'ZA', false )
);
assert_true(
	'checkout keeps a remembered billing country',
	'ZA' === RWGCM_Price_Book::effective_billing_country( '', 'ZA', true )
);
assert_true(
	'a posted billing country decides even off checkout',
	'GB' === RWGCM_Price_Book::effective_billing_country( 'GB', 'ZA', false )
);

$_COOKIE['rwgc_cc'] = 'US';
assert_true(
	'visitor country comes from Geo Core and ignores rwgc_cc',
	'ZA' === RWGCM_Price_Book_Currency::visitor_country() && 'US' === $_COOKIE['rwgc_cc']
);
$cache_notes = RWGCM_Price_Book_Currency::cache_compatibility_notes();
assert_true(
	'cache notes name Geo Core country and the vary cookie',
	false !== strpos( $cache_notes, 'rwgc_get_visitor_country' ) && false !== strpos( $cache_notes, 'rwgc_cc' )
);

$gateways = array(
	'revolut_cc' => 'card',
	'bacs'       => 'bank',
	'paypal'     => 'paypal',
);
$country_rule = array(
	'countries' => array(
		'ZA' => array( 'revolut_cc' ),
	),
	'groups'    => array(
		'eu' => array( 'bacs', 'paypal' ),
	),
);
assert_true(
	'unset country keeps every enabled gateway',
	null === RWGCM_Gateway_Availability::resolve_allowed_ids( 'JP', $country_rule, array() )
);
assert_true(
	'country rule intersects the gateway list',
	array( 'revolut_cc' ) === array_keys( RWGCM_Gateway_Availability::select_gateway_ids( $gateways, RWGCM_Gateway_Availability::resolve_allowed_ids( 'ZA', $country_rule, array( array( 'bacs' ) ) ) ) )
);
$group_lists = RWGCM_Gateway_Availability::gateway_lists_for_country(
	'DE',
	$country_rule,
	array(
		'eu'  => array( 'DE', 'FR' ),
		'row' => array( 'ZA' ),
	)
);
$group_allowed = RWGCM_Gateway_Availability::resolve_allowed_ids( 'DE', $country_rule, $group_lists );
assert_true(
	'a matching country group limits gateways',
	array( 'bacs', 'paypal' ) === array_keys( RWGCM_Gateway_Availability::select_gateway_ids( $gateways, $group_allowed ) )
);
$union_rule = array(
	'countries' => array(),
	'groups'    => array(
		'eu'    => array( 'bacs' ),
		'cards' => array( 'revolut_cc' ),
	),
);
$union_lists = RWGCM_Gateway_Availability::gateway_lists_for_country(
	'DE',
	$union_rule,
	array(
		'eu'    => array( 'DE' ),
		'cards' => array( 'DE' ),
	)
);
assert_true(
	'matching groups are combined',
	array( 'bacs', 'revolut_cc' ) === RWGCM_Gateway_Availability::resolve_allowed_ids( 'DE', $union_rule, $union_lists )
);
assert_true(
	'a country rule replaces a group rule',
	array( 'revolut_cc' ) === RWGCM_Gateway_Availability::resolve_allowed_ids( 'ZA', $country_rule, array( array( 'bacs' ) ) )
);
assert_true(
	'an explicit empty country rule allows no gateways',
	array() === RWGCM_Gateway_Availability::resolve_allowed_ids(
		'ZA',
		array(
			'countries' => array( 'ZA' => array() ),
			'groups'    => array(),
		),
		array( array( 'bacs' ) )
	)
);
assert_true(
	'saving drops an empty gateway selection',
	array() === RWGCM_Gateway_Availability::sanitize_rules(
		array(
			'countries' => array( 'ZA' => array() ),
			'groups'    => array( 'eu' => array( '' ) ),
		)
	)['countries']
);
assert_true(
	'empty billing country does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'checkout'        => true,
			'billing_country' => '',
		)
	)
);
assert_true(
	'checkout with a billing country restricts gateways',
	RWGCM_Gateway_Availability::should_restrict(
		array(
			'checkout'        => true,
			'billing_country' => 'ZA',
		)
	)
);
assert_true(
	'a renewal does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'checkout'        => true,
			'renewal'         => true,
			'billing_country' => 'ZA',
		)
	)
);
assert_true(
	'paying a renewal order does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'checkout'        => true,
			'renewal_pay'     => true,
			'billing_country' => 'ZA',
		)
	)
);
assert_true(
	'changing the payment method still restricts by billing country',
	RWGCM_Gateway_Availability::should_restrict(
		array(
			'change_payment'  => true,
			'billing_country' => 'DE',
		)
	)
);
assert_true(
	'wp-admin does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'admin'           => true,
			'checkout'        => true,
			'billing_country' => 'ZA',
		)
	)
);
assert_true(
	'the Store API cart (block checkout payment list) restricts gateways',
	RWGCM_Gateway_Availability::should_restrict(
		array(
			'store_api_cart'  => true,
			'billing_country' => 'ZA',
		)
	)
);
assert_true(
	'the Store API cart without a billing country does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'store_api_cart'  => true,
			'billing_country' => '',
		)
	)
);
assert_true(
	'a Store API cart renewal does not restrict gateways',
	! RWGCM_Gateway_Availability::should_restrict(
		array(
			'store_api_cart'  => true,
			'renewal'         => true,
			'billing_country' => 'ZA',
		)
	)
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
