<?php
/**
 * Coupon usage restriction by Geo Core visitor country (WooCommerce multiselect).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta: _rwgcm_allowed_countries — list of ISO2 codes; empty = no geo restriction.
 */
class RWGCM_Coupon_Geo {

	const META_ALLOWED = '_rwgcm_allowed_countries';

	/**
	 * @var string
	 */
	private static $reject_reason = '';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_coupon_options_usage_restriction', array( __CLASS__, 'render_usage_restriction' ), 15, 2 );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'save_coupon_geo' ), 15, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( __CLASS__, 'coupon_is_valid' ), 15, 3 );
		add_filter( 'woocommerce_coupon_error', array( __CLASS__, 'coupon_error_message' ), 15, 3 );
	}

	/**
	 * @param int        $coupon_id Coupon ID.
	 * @param \WC_Coupon $coupon    Coupon.
	 * @return void
	 */
	public static function render_usage_restriction( $coupon_id, $coupon ) {
		unset( $coupon_id );
		if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
			return;
		}
		$countries = array();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$countries = WC()->countries->get_countries();
		}
		if ( empty( $countries ) ) {
			return;
		}
		$selected = $coupon->get_meta( self::META_ALLOWED, true );
		if ( ! is_array( $selected ) ) {
			$selected = array();
		}
		$selected = array_map(
			static function ( $c ) {
				return strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
			},
			$selected
		);
		?>
		<div class="options_group">
			<p class="form-field">
				<label for="rwgcm_allowed_countries"><?php esc_html_e( 'Allowed countries (Geo Commerce)', 'reactwoo-geo-commerce' ); ?></label>
				<select id="rwgcm_allowed_countries" name="rwgcm_allowed_countries[]" class="wc-enhanced-select" multiple="multiple" style="width:50%;max-width:100%;" data-placeholder="<?php esc_attr_e( 'Any country (no geo restriction)', 'reactwoo-geo-commerce' ); ?>">
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"<?php echo in_array( (string) $code, $selected, true ) ? ' selected="selected"' : ''; ?>><?php echo esc_html( $name . ' (' . $code . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php echo wp_kses_post( wc_help_tip( __( 'If you select one or more countries, the coupon applies only when Geo Core detects the visitor in that region. Leave empty for no geo restriction.', 'reactwoo-geo-commerce' ) ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * @param int        $post_id Post ID.
	 * @param \WC_Coupon $coupon  Coupon.
	 * @return void
	 */
	public static function save_coupon_geo( $post_id, $coupon ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_coupons' ) ) {
			return;
		}
		if ( ! is_a( $coupon, 'WC_Coupon' ) ) {
			return;
		}
		$raw = isset( $_POST['rwgcm_allowed_countries'] ) ? (array) wp_unslash( $_POST['rwgcm_allowed_countries'] ) : array();
		$out = array();
		foreach ( $raw as $cc ) {
			$cc = strtoupper( substr( sanitize_text_field( (string) $cc ), 0, 2 ) );
			if ( strlen( $cc ) === 2 && self::is_wc_country( $cc ) ) {
				$out[] = $cc;
			}
		}
		$out = array_values( array_unique( $out ) );
		if ( empty( $out ) ) {
			$coupon->delete_meta_data( self::META_ALLOWED );
		} else {
			$coupon->update_meta_data( self::META_ALLOWED, $out );
		}
		$coupon->save();
	}

	/**
	 * @param string $iso2 Two-letter code.
	 * @return bool
	 */
	private static function is_wc_country( $iso2 ) {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return (bool) preg_match( '/^[A-Z]{2}$/', $iso2 );
		}
		$all = WC()->countries->get_countries();
		return is_array( $all ) && isset( $all[ $iso2 ] );
	}

	/**
	 * @param bool           $valid    Whether valid so far.
	 * @param \WC_Coupon     $coupon   Coupon.
	 * @param \WC_Discounts  $discount Discounts instance.
	 * @return bool
	 */
	public static function coupon_is_valid( $valid, $coupon, $discount ) {
		unset( $discount );
		self::$reject_reason = '';
		if ( ! $valid || ! is_a( $coupon, 'WC_Coupon' ) ) {
			return $valid;
		}
		$allowed = $coupon->get_meta( self::META_ALLOWED, true );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			return $valid;
		}
		$iso2 = self::visitor_iso2();
		if ( '' === $iso2 ) {
			return (bool) apply_filters( 'rwgcm_coupon_valid_when_country_unknown', $valid, $coupon );
		}
		$allowed = array_map(
			static function ( $c ) {
				return strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
			},
			$allowed
		);
		$pass = in_array( $iso2, $allowed, true );
		$pass = (bool) apply_filters( 'rwgcm_coupon_allowed_for_visitor', $pass, $coupon, $iso2, $allowed );
		if ( ! $pass ) {
			self::$reject_reason = 'geo';
			return false;
		}
		return $valid;
	}

	/**
	 * @param string     $message   Message.
	 * @param int|string $err_code  Error code.
	 * @param \WC_Coupon $coupon    Coupon.
	 * @return string
	 */
	public static function coupon_error_message( $message, $err_code, $coupon ) {
		if ( 'geo' !== self::$reject_reason || ! is_a( $coupon, 'WC_Coupon' ) ) {
			return $message;
		}
		$filtered_code = class_exists( 'WC_Coupon', false ) ? (int) WC_Coupon::E_WC_COUPON_INVALID_FILTERED : 100;
		if ( (int) $err_code !== $filtered_code ) {
			return $message;
		}
		self::$reject_reason = '';
		return __( 'This coupon is not available in your region.', 'reactwoo-geo-commerce' );
	}

	/**
	 * @return string Two-letter uppercase or empty.
	 */
	private static function visitor_iso2() {
		$iso2 = '';
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$iso2 = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( strlen( $iso2 ) !== 2 && function_exists( 'rwgc_get_visitor_data' ) ) {
			$v = rwgc_get_visitor_data();
			if ( is_array( $v ) && isset( $v['country_code'] ) ) {
				$iso2 = strtoupper( substr( sanitize_text_field( (string) $v['country_code'] ), 0, 2 ) );
			}
		}
		return strlen( $iso2 ) === 2 ? $iso2 : '';
	}
}
