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

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';

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

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
