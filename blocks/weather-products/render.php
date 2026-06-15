<?php
/**
 * Server render for weather products block.
 *
 * @package ReactWoo_Geo_Commerce
 * @var array<string, mixed> $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attrs = is_array( $attributes ) ? $attributes : array();
$args  = array(
	'title'               => isset( $attrs['title'] ) ? (string) $attrs['title'] : '',
	'limit'               => isset( $attrs['limit'] ) ? (int) $attrs['limit'] : 8,
	'columns'             => isset( $attrs['columns'] ) ? (int) $attrs['columns'] : 4,
	'category'            => isset( $attrs['category'] ) ? (string) $attrs['category'] : '',
	'ids'                 => isset( $attrs['ids'] ) ? (string) $attrs['ids'] : '',
	'orderby'             => isset( $attrs['orderby'] ) ? (string) $attrs['orderby'] : 'relevance',
	'fallback'            => isset( $attrs['fallback'] ) ? (string) $attrs['fallback'] : 'hide',
	'fallback_category'   => isset( $attrs['fallback_category'] ) ? (string) $attrs['fallback_category'] : '',
	'fallback_message'    => isset( $attrs['fallback_message'] ) ? (string) $attrs['fallback_message'] : '',
	'weather_unavailable' => isset( $attrs['weather_unavailable'] ) ? (string) $attrs['weather_unavailable'] : 'hide',
	'class'               => isset( $attrs['className'] ) ? (string) $attrs['className'] : '',
);

echo RWGCM_Weather_Products_Display::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
