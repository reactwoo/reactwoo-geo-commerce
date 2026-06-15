<?php
/**
 * WooCommerce CSV import/export for shopping weather facets.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds weather_facets column to product CSV flows.
 */
class RWGCM_Weather_Csv {

	const COLUMN = 'weather_facets';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'export_columns' ) );
		add_filter( 'woocommerce_product_export_product_column_' . self::COLUMN, array( __CLASS__, 'export_value' ), 10, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'import_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'import_default_columns' ) );
		add_filter( 'woocommerce_product_importer_parsed_data', array( __CLASS__, 'import_parsed_data' ), 10, 2 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( __CLASS__, 'import_save_product' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function export_columns( $columns ) {
		$columns[ self::COLUMN ] = __( 'Weather facets (comma-separated)', 'reactwoo-geo-commerce' );
		return $columns;
	}

	/**
	 * @param mixed      $value   Existing value.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function export_value( $value, $product ) {
		unset( $value );
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}
		$facets = RWGCM_Weather_Affinity::get_product_facets( $product->get_id() );
		return implode( ',', $facets );
	}

	/**
	 * @param array<string, string> $options Options.
	 * @return array<string, string>
	 */
	public static function import_mapping_options( $options ) {
		$options[ self::COLUMN ] = __( 'Weather facets (comma-separated)', 'reactwoo-geo-commerce' );
		return $options;
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function import_default_columns( $columns ) {
		$columns[ __( 'Weather facets (comma-separated)', 'reactwoo-geo-commerce' ) ] = self::COLUMN;
		$columns['Weather facets'] = self::COLUMN;
		$columns['weather_facets'] = self::COLUMN;
		return $columns;
	}

	/**
	 * @param array<string, mixed> $parsed Parsed row.
	 * @param array<string, mixed> $importer Importer instance data.
	 * @return array<string, mixed>
	 */
	public static function import_parsed_data( $parsed, $importer ) {
		unset( $importer );
		if ( empty( $parsed['meta_data'] ) || ! is_array( $parsed['meta_data'] ) ) {
			$parsed['meta_data'] = array();
		}
		if ( ! empty( $parsed[ self::COLUMN ] ) ) {
			$parsed['meta_data'][] = array(
				'key'   => RWGCM_Weather_Affinity::META_KEY,
				'value' => wp_json_encode( RWGCM_Weather_Affinity::sanitize_facet_list( $parsed[ self::COLUMN ] ) ),
			);
		}
		return $parsed;
	}

	/**
	 * @param WC_Product             $product Product object.
	 * @param array<string, mixed>   $data    Import row.
	 * @return void
	 */
	public static function import_save_product( $product, $data ) {
		if ( ! is_a( $product, 'WC_Product' ) || ! is_array( $data ) || empty( $data[ self::COLUMN ] ) ) {
			return;
		}
		RWGCM_Weather_Affinity::save_product_facets( $product->get_id(), explode( ',', (string) $data[ self::COLUMN ] ) );
	}
}
