<?php
/**
 * Lightweight tests for unified Rules model (run with: php tests/test-rules-unified.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

// Minimal bootstrap stubs when WordPress is not loaded.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
	function absint( $v ) {
		return abs( (int) $v );
	}
	function wp_kses_post( $data ) {
		return (string) $data;
	}
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgcm-action-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-condition-library.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgcm-rule-summary.php';

$passed = 0;
$failed = 0;

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

// Action resolver accepts new unified action types.
$badge = RWGCM_Action_Resolver::sanitize_action(
	'product_badge',
	array(
		'type'  => 'product_badge',
		'text'  => 'UK Stock',
		'style' => 'default',
	)
);
assert_true( 'product_badge sanitizes', is_array( $badge ) && 'UK Stock' === $badge['text'] );

$price = RWGCM_Action_Resolver::sanitize_action(
	'price_adjustment',
	array(
		'type'  => 'price_adjustment',
		'mode'  => 'percent',
		'value' => 10,
	)
);
assert_true( 'price_adjustment still works', is_array( $price ) && 10.0 === $price['value'] );

// Condition library maps country field.
$country_field = RWGCM_Condition_Library::get_field_by_key( 'visitor.country' );
assert_true(
	'condition library has visitor.country',
	is_array( $country_field ) && 'country' === $country_field['target'] && 'country_select' === $country_field['value_type']
);

// Rule summary generates readable text.
$rule = array(
	'label'      => 'UK hoodie uplift',
	'status'     => 'active',
	'priority'   => 100,
	'scope'      => array( 'type' => 'product_category', 'ids' => array( 123 ) ),
	'conditions' => array(
		'match' => 'all',
		'items' => array(
			array(
				'field'    => 'visitor.country',
				'target'   => 'country',
				'operator' => 'is',
				'value'    => 'GB',
				'label'    => 'United Kingdom',
			),
		),
	),
	'actions'    => array(
		array(
			'type'  => 'price_adjustment',
			'mode'  => 'percent',
			'value' => 10,
		),
		array(
			'type' => 'product_badge',
			'text' => 'UK Stock',
		),
	),
);

$summary = RWGCM_Rule_Summary::summarize_rule( $rule );
assert_true( 'summary mentions United Kingdom', false !== strpos( $summary, 'United Kingdom' ) );
assert_true( 'summary mentions badge', false !== strpos( $summary, 'UK Stock' ) );
assert_true( 'summary mentions price', false !== strpos( $summary, '10' ) );

$tags = RWGCM_Rule_Summary::get_action_tags( $rule );
assert_true( 'action tags include price and badge', in_array( 'price adjustment', $tags, true ) && in_array( 'product badge', $tags, true ) );

// Legacy pricing shape still maps to price_adjustment action type.
$legacy_action = RWGCM_Action_Resolver::sanitize_action(
	'price_adjustment',
	array(
		'type'  => 'percent',
		'value' => 5,
	)
);
assert_true( 'legacy percent pricing action sanitizes', is_array( $legacy_action ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
