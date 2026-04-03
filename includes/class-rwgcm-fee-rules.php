<?php
/**
 * Stored cart fee rows by visitor country (Geo Commerce admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option: rwgcm_fee_rules — enable + country → fee label / amount / taxable.
 */
class RWGCM_Fee_Rules {

	const OPTION_KEY = 'rwgcm_fee_rules';

	/**
	 * @return array{enabled: bool, rules: list<array{country: string, name: string, amount: float, taxable: bool, tax_class: string}>}
	 */
	public static function get_all() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return self::sanitize( $raw );
	}

	/**
	 * @param array<string, mixed> $data Raw.
	 * @return array{enabled: bool, rules: list<array{country: string, name: string, amount: float, taxable: bool, tax_class: string}>}
	 */
	public static function sanitize( $data ) {
		$enabled = ! empty( $data['enabled'] );
		$rules   = isset( $data['rules'] ) && is_array( $data['rules'] ) ? $data['rules'] : array();
		$out     = array();
		foreach ( $rules as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cc = isset( $row['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['country'] ), 0, 2 ) ) : '';
			if ( '' === $cc || strlen( $cc ) !== 2 || ! self::is_allowed_country( $cc ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$amount = isset( $row['amount'] ) ? floatval( $row['amount'] ) : 0.0;
			$amount = max( -999999.0, min( 999999.0, $amount ) );
			if ( 0.0 === $amount ) {
				continue;
			}
			$taxable = ! empty( $row['taxable'] );
			$tax_class = '';
			if ( $taxable ) {
				$tax_class = self::sanitize_tax_class_slug( isset( $row['tax_class'] ) ? (string) $row['tax_class'] : '' );
			}
			$out[]   = array(
				'country'   => $cc,
				'name'      => $name,
				'amount'    => $amount,
				'taxable'   => $taxable,
				'tax_class' => $tax_class,
			);
			if ( count( $out ) >= 40 ) {
				break;
			}
		}
		return array(
			'enabled' => $enabled,
			'rules'   => $out,
		);
	}

	/**
	 * Valid WooCommerce product tax class slug (key from `wc_get_product_tax_class_options()`).
	 *
	 * @param string $slug Slug (may be empty for Standard).
	 * @return string
	 */
	private static function sanitize_tax_class_slug( $slug ) {
		$slug = sanitize_text_field( (string) $slug );
		if ( ! function_exists( 'wc_get_product_tax_class_options' ) ) {
			return '';
		}
		$opts = wc_get_product_tax_class_options();
		if ( ! is_array( $opts ) ) {
			return '';
		}
		return array_key_exists( $slug, $opts ) ? $slug : '';
	}

	/**
	 * @param string $iso2 Two-letter code.
	 * @return bool
	 */
	private static function is_allowed_country( $iso2 ) {
		if ( ! function_exists( 'WC' ) ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $iso2 );
		}
		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->countries ) || ! is_object( $wc->countries ) ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $iso2 );
		}
		$countries = $wc->countries->get_countries();
		return is_array( $countries ) && isset( $countries[ $iso2 ] );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$all = self::get_all();
		return ! empty( $all['enabled'] ) && ! empty( $all['rules'] );
	}

	/**
	 * Fee rows for rwgcm_cart_fees (visitor country).
	 *
	 * @param string $country_iso2 Two-letter uppercase.
	 * @return list<array{name: string, amount: float, taxable: bool, tax_class?: string}>
	 */
	public static function get_rows_for_country( $country_iso2 ) {
		$country_iso2 = strtoupper( substr( (string) $country_iso2, 0, 2 ) );
		if ( strlen( $country_iso2 ) !== 2 ) {
			return array();
		}
		$all = self::get_all();
		if ( empty( $all['enabled'] ) || empty( $all['rules'] ) ) {
			return array();
		}
		$rows = array();
		foreach ( $all['rules'] as $rule ) {
			if ( ! isset( $rule['country'] ) || $rule['country'] !== $country_iso2 ) {
				continue;
			}
			$row_out = array(
				'name'    => $rule['name'],
				'amount'  => (float) $rule['amount'],
				'taxable' => ! empty( $rule['taxable'] ),
			);
			if ( ! empty( $rule['taxable'] ) && isset( $rule['tax_class'] ) && '' !== (string) $rule['tax_class'] ) {
				$row_out['tax_class'] = (string) $rule['tax_class'];
			}
			$rows[] = $row_out;
		}
		/**
		 * Filter fee rows from saved Geo Commerce fee rules before they merge into rwgcm_cart_fees.
		 *
		 * @param list<array{name: string, amount: float, taxable: bool, tax_class?: string}> $rows    Rows.
		 * @param string                                                  $country Visitor ISO2.
		 */
		return apply_filters( 'rwgcm_fee_rule_rows', $rows, $country_iso2 );
	}
}
