<?php
/**
 * Gateway currency compatibility. No WordPress, WooCommerce, or database.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-price-book-currency.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-gateway-currency.php';

$passed = 0;
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Result.
 * @return void
 */
function assert_currency( $label, $ok ) {
	global $passed, $failed;
	if ( $ok ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

$paystack = array( 'NGN', 'GHS', 'USD', 'KES', 'RWF', 'ZAR', 'XOF', 'EGP' );
$lists    = array(
	'paystack' => array( 'ZAR', 'USD' ),
);
$charged  = array( 'ZAR', 'GBP', 'USD', 'EUR' );

assert_currency(
	'Paystack publishes woocommerce_paystack_supported_currencies',
	'woocommerce_paystack_supported_currencies' === RWGCM_Gateway_Currency::PAYSTACK_CURRENCIES_FILTER
);

$settings = array(
	'page'     => 'wc-settings',
	'tab'      => 'checkout',
	'section'  => 'paystack',
	'lists'    => $lists,
	'charged'  => $charged,
);
assert_currency(
	'the Paystack settings screen sees ZAR when the store currency is GBP',
	'ZAR' === RWGCM_Gateway_Currency::currency_override_for_request( 'GBP', $settings )
);
assert_currency(
	'a gateway with no currency list is not overridden',
	null === RWGCM_Gateway_Currency::currency_override_for_request(
		'GBP',
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'revolut',
			'lists'   => $lists,
			'charged' => $charged,
		)
	)
);
assert_currency(
	'the payments list is not a gateway settings section',
	null === RWGCM_Gateway_Currency::currency_override_for_request(
		'GBP',
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => '',
			'lists'   => $lists,
			'charged' => $charged,
		)
	)
);
assert_currency(
	'a product edit screen keeps GBP',
	null === RWGCM_Gateway_Currency::currency_override_for_request(
		'GBP',
		array(
			'page'    => 'post',
			'tab'     => '',
			'section' => 'paystack',
			'lists'   => $lists,
			'charged' => $charged,
		)
	)
);
foreach ( array( 'cron', 'cli', 'rest', 'syncing' ) as $flag ) {
	$context         = $settings;
	$context[ $flag ] = true;
	assert_currency(
		$flag . ' keeps the store currency even on the Paystack settings URL',
		null === RWGCM_Gateway_Currency::currency_override_for_request( 'GBP', $context )
	);
}
assert_currency(
	'an already supported store currency is not replaced',
	null === RWGCM_Gateway_Currency::settings_currency( 'ZAR', $paystack, $charged )
);

assert_currency(
	'checkout shows Paystack the visitor currency',
	'ZAR' === RWGCM_Gateway_Currency::currency_seen_by_gateway(
		'GBP',
		'ZAR',
		array( 'payment' => true )
	)
);
assert_currency(
	'the Paystack settings check sees the mapped currency',
	'ZAR' === RWGCM_Gateway_Currency::currency_seen_by_gateway( 'GBP', 'GBP', $settings )
);
assert_currency(
	'admin outside that settings screen still sees the store currency',
	'GBP' === RWGCM_Gateway_Currency::currency_seen_by_gateway(
		'GBP',
		'ZAR',
		array(
			'page' => 'post',
		)
	)
);

$gateways = array(
	'paystack'   => 'paystack',
	'revolut_cc' => 'revolut',
	'bacs'       => 'bacs',
);
assert_currency(
	'ZAR checkout keeps Paystack and untouched gateways',
	array( 'paystack', 'revolut_cc', 'bacs' ) === array_keys( RWGCM_Gateway_Currency::select_for_currency( $gateways, 'ZAR', $lists ) )
);
assert_currency(
	'GBP checkout does not offer Paystack',
	array( 'revolut_cc', 'bacs' ) === array_keys( RWGCM_Gateway_Currency::select_for_currency( $gateways, 'GBP', $lists ) )
);
assert_currency(
	'EUR checkout does not offer Paystack',
	! RWGCM_Gateway_Currency::offered_for_currency( 'paystack', 'EUR', $lists )
);
assert_currency(
	'a gateway with no list is still offered',
	RWGCM_Gateway_Currency::offered_for_currency( 'bacs', 'GBP', $lists )
);
assert_currency( 'checkout filters the gateway list', RWGCM_Gateway_Currency::should_filter_offer( array( 'admin' => false ) ) );
assert_currency( 'Store API checkout filters the gateway list', RWGCM_Gateway_Currency::should_filter_offer( array( 'rest' => true, 'store_api' => true ) ) );
assert_currency( 'wp-admin does not filter the gateway list', ! RWGCM_Gateway_Currency::should_filter_offer( array( 'admin' => true ) ) );
assert_currency( 'WooCommerce REST does not filter the gateway list', ! RWGCM_Gateway_Currency::should_filter_offer( array( 'rest' => true ) ) );
assert_currency( 'cron does not filter the gateway list', ! RWGCM_Gateway_Currency::should_filter_offer( array( 'cron' => true ) ) );

$charge = RWGCM_Gateway_Currency::charge_payload( '699.00', 'ZAR' );
assert_currency(
	'a ZAR order is charged in ZAR minor units',
	'ZAR' === $charge['currency'] && 69900 === $charge['amount'] && 2 === $charge['minor_unit_exponent']
);
assert_currency(
	'the charge does not use the GBP store amount',
	3900 !== $charge['amount']
);
assert_currency(
	'verify accepts the ZAR minor amount for that order',
	RWGCM_Gateway_Currency::payment_matches_order( 69900, '699.00', 'ZAR', 'ZAR' )
);
assert_currency(
	'verify rejects a GBP payment against a ZAR order',
	! RWGCM_Gateway_Currency::payment_matches_order( 69900, '699.00', 'GBP', 'ZAR' )
);
assert_currency(
	'verify rejects a short ZAR payment',
	! RWGCM_Gateway_Currency::payment_matches_order( 100, '699.00', 'ZAR', 'ZAR' )
);
assert_currency( 'ZAR minor units are cents', 2 === RWGCM_Gateway_Currency::minor_unit_exponent( 'ZAR' ) );
assert_currency( 'XOF is ISO zero-decimal', 0 === RWGCM_Gateway_Currency::minor_unit_exponent( 'XOF' ) );

$merged = RWGCM_Gateway_Currency::merge_currencies( $paystack, array( 'ZAR' ) );
assert_currency(
	'the Paystack allow-list keeps ZAR and does not gain GBP',
	in_array( 'ZAR', $merged, true ) && ! in_array( 'GBP', $merged, true )
);
assert_currency(
	'paystack-one currencies are included in the Paystack family filter',
		array( 'USD', 'ZAR' ) === RWGCM_Gateway_Currency::paystack_family_currencies(
		array(
			'paystack-one' => array( 'USD' ),
			'paystack'     => array( 'ZAR' ),
			'revolut_cc'   => array( 'GBP' ),
		)
	)
);

$saved = RWGCM_Gateway_Currency::sanitize_lists(
	array(
		'paystack' => 'ZAR, usd NGN',
		'bacs'     => '   ',
		'bad id'   => 'EUR',
	)
);
assert_currency(
	'a blank gateway currency list is opt-in and stays unset',
	isset( $saved['paystack'] ) && array( 'ZAR', 'USD', 'NGN' ) === $saved['paystack'] && ! isset( $saved['bacs'] ) && isset( $saved['badid'] )
);

$covered = RWGCM_Gateway_Currency::currencies_covered_by_country_rules(
	'paystack',
	array(
		'countries' => array(
			'ZA' => array( 'paystack' ),
			'US' => array( 'revolut_cc' ),
		),
	),
	array(
		'ZA' => 'ZAR',
		'US' => 'USD',
	)
);
assert_currency( 'a ZA Paystack country rule covers ZAR', array( 'ZAR' ) === $covered );

$merged_lists = RWGCM_Gateway_Currency::merge_posted_lists(
	array(
		'paystack' => array( 'ZAR' ),
		'cheque'   => array( 'GBP' ),
	),
	array(
		'bacs'   => '   ',
		'cheque' => 'GBP EUR',
	)
);
assert_currency(
	'saving without a Paystack currency field keeps the Paystack list',
	isset( $merged_lists['paystack'] ) && array( 'ZAR' ) === $merged_lists['paystack']
);
assert_currency(
	'a submitted currency field replaces only that gateway',
	isset( $merged_lists['cheque'] ) && array( 'GBP', 'EUR' ) === $merged_lists['cheque'] && ! isset( $merged_lists['bacs'] )
);
$cleared = RWGCM_Gateway_Currency::merge_posted_lists(
	array( 'paystack' => array( 'ZAR' ) ),
	array( 'paystack' => '' )
);
assert_currency( 'a blank submitted currency field clears only that gateway', array() === $cleared );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
