<?php
/**
 * Product editor fields for fixed per-currency prices.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple products, simple subscriptions, and variable-subscription variations.
 */
class RWGCM_Price_Book_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product' ), 20, 2 );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_variation_fields' ), 15, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 20, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save_settings' ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs Product data tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function product_tab( $tabs ) {
		$tabs['rwgcm_price_book'] = array(
			'label'    => __( 'Price book', 'reactwoo-geo-commerce' ),
			'target'   => 'rwgcm_price_book_product_data',
			'class'    => array( 'show_if_simple', 'show_if_subscription' ),
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * @return void
	 */
	public static function render_panel() {
		echo '<div id="rwgcm_price_book_product_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';
		echo '<p class="form-field" style="padding: 0 12px;">' . esc_html__( 'Fixed prices in each currency. Before checkout the currency follows Geo Core’s visitor country. Once a billing country is entered, that country decides. The country map is edited under Geo Commerce → Settings. These amounts are not converted.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<input type="hidden" name="rwgcm_price_book_present" value="1" />';
		self::render_currency_inputs( 'rwgcm_price_book', self::product_book_from_global(), true );
		echo '</div></div>';
	}

	/**
	 * @param int     $post_id Product ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function save_product( $post_id, $post ) {
		unset( $post );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['rwgcm_price_book_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$raw  = isset( $_POST['rwgcm_price_book'] ) ? wp_unslash( $_POST['rwgcm_price_book'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$book = RWGCM_Price_Book::sanitize_book( $raw );
		if ( empty( $book ) ) {
			delete_post_meta( $post_id, RWGCM_Price_Book::META_KEY );
			return;
		}
		update_post_meta( $post_id, RWGCM_Price_Book::META_KEY, $book );
	}

	/**
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation meta.
	 * @param WP_Post $variation      Variation post.
	 * @return void
	 */
	public static function render_variation_fields( $loop, $variation_data, $variation ) {
		unset( $variation_data );
		if ( ! is_object( $variation ) || empty( $variation->ID ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$parent_id = (int) wp_get_post_parent_id( $variation->ID );
		$parent    = $parent_id > 0 ? wc_get_product( $parent_id ) : null;
		if ( ! $parent || ! $parent->is_type( 'variable-subscription' ) ) {
			return;
		}
		$book = RWGCM_Price_Book_Currency::get_book( $variation->ID );
		echo '<div class="form-row form-row-full rwgcm-price-book-variation">';
		echo '<p class="form-row form-row-full"><strong>' . esc_html__( 'Price book', 'reactwoo-geo-commerce' ) . '</strong><br/>';
		echo '<span class="description">' . esc_html__( 'Recurring price, optional sale price, and sign-up fee in each currency. Leave a currency blank to fall back to the store currency.', 'reactwoo-geo-commerce' ) . '</span></p>';
		echo '<input type="hidden" name="variable_rwgcm_price_book_present[' . esc_attr( (string) $loop ) . ']" value="1" />';
		self::render_currency_inputs( 'variable_rwgcm_price_book[' . (int) $loop . ']', $book, true, true );
		echo '</div>';
	}

	/**
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop index.
	 * @return void
	 */
	public static function save_variation( $variation_id, $loop ) {
		$variation_id = (int) $variation_id;
		if ( $variation_id <= 0 || ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}
		if ( ! isset( $_POST['variable_rwgcm_price_book_present'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$parent_id = (int) wp_get_post_parent_id( $variation_id );
		$parent    = function_exists( 'wc_get_product' ) ? wc_get_product( $parent_id ) : null;
		if ( ! $parent || ! $parent->is_type( 'variable-subscription' ) ) {
			return;
		}
		$all = isset( $_POST['variable_rwgcm_price_book'] ) ? wp_unslash( $_POST['variable_rwgcm_price_book'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = ( is_array( $all ) && isset( $all[ $loop ] ) && is_array( $all[ $loop ] ) ) ? $all[ $loop ] : array();
		$book = RWGCM_Price_Book::sanitize_book( $raw );
		if ( empty( $book ) ) {
			delete_post_meta( $variation_id, RWGCM_Price_Book::META_KEY );
			return;
		}
		update_post_meta( $variation_id, RWGCM_Price_Book::META_KEY, $book );
	}

	/**
	 * @return void
	 */
	public static function maybe_save_settings() {
		if ( ! isset( $_POST['rwgcm_price_book_settings_save'] ) ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'rwgcm_price_book_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$enabled = isset( $_POST['rwgcm_price_book_base_adjustments'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['rwgcm_price_book_base_adjustments'] ) );
		update_option( RWGCM_Price_Book::OPTION_BASE_ADJUSTMENTS, $enabled ? 'yes' : 'no' );

		$map_text = isset( $_POST['rwgcm_country_map_text'] ) ? wp_unslash( $_POST['rwgcm_country_map_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_option( RWGCM_Price_Book::OPTION_COUNTRY_MAP, RWGCM_Price_Book::parse_country_map_text( $map_text ), false );

		$fallback = isset( $_POST['rwgcm_price_book_fallback_currency'] ) ? RWGCM_Price_Book::normalize_currency( wp_unslash( $_POST['rwgcm_price_book_fallback_currency'] ) ) : '';
		$base     = function_exists( 'get_option' ) ? RWGCM_Price_Book::normalize_currency( get_option( 'woocommerce_currency', '' ) ) : '';
		if ( '' !== $fallback && $fallback !== $base && ! in_array( $fallback, RWGCM_Price_Book::currencies(), true ) ) {
			$fallback = '';
		}
		update_option( RWGCM_Price_Book::OPTION_FALLBACK_CURRENCY, $fallback, false );

		if ( isset( $_POST['rwgcm_gateway_rules_present'] ) ) {
			$gateway_raw = array(
				'countries' => array(),
				'groups'    => array(),
			);
			if ( isset( $_POST['rwgcm_gateway_country'] ) && is_array( $_POST['rwgcm_gateway_country'] ) ) {
				$gateway_raw['countries'] = wp_unslash( $_POST['rwgcm_gateway_country'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			$new_country = isset( $_POST['rwgcm_gateway_country_new'] ) ? RWGCM_Price_Book::normalize_country( wp_unslash( $_POST['rwgcm_gateway_country_new'] ) ) : '';
			if ( '' !== $new_country && isset( $_POST['rwgcm_gateway_country_new_ids'] ) && is_array( $_POST['rwgcm_gateway_country_new_ids'] ) ) {
				$gateway_raw['countries'][ $new_country ] = wp_unslash( $_POST['rwgcm_gateway_country_new_ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			if ( isset( $_POST['rwgcm_gateway_group'] ) && is_array( $_POST['rwgcm_gateway_group'] ) ) {
				$gateway_raw['groups'] = wp_unslash( $_POST['rwgcm_gateway_group'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			update_option( RWGCM_Gateway_Availability::OPTION_KEY, RWGCM_Gateway_Availability::sanitize_rules( $gateway_raw ), false );
		}
		if ( isset( $_POST['rwgcm_gateway_currencies_present'] ) ) {
			$currency_raw = array();
			if ( isset( $_POST['rwgcm_gateway_currencies'] ) && is_array( $_POST['rwgcm_gateway_currencies'] ) ) {
				$currency_raw = wp_unslash( $_POST['rwgcm_gateway_currencies'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			update_option( RWGCM_Gateway_Currency::OPTION_KEY, RWGCM_Gateway_Currency::sanitize_lists( $currency_raw ), false );
		}
		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'admin.php?page=rwgcm-settings' );
		}
		wp_safe_redirect( add_query_arg( 'rwgcm_price_book_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Settings card for the country map, fallback currency, and gateway rules.
	 *
	 * @return void
	 */
	public static function render_settings_card() {
		$enabled  = RWGCM_Price_Book::base_adjustments_enabled();
		$map_text = RWGCM_Price_Book::format_country_map_text( RWGCM_Price_Book::country_currency_map() );
		$fallback = function_exists( 'get_option' ) ? RWGCM_Price_Book::normalize_currency( get_option( RWGCM_Price_Book::OPTION_FALLBACK_CURRENCY, '' ) ) : '';
		if ( isset( $_GET['rwgcm_price_book_saved'] ) && '1' === $_GET['rwgcm_price_book_saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Price book settings saved.', 'reactwoo-geo-commerce' ) . '</p></div>';
		}
		echo '<div class="rwgc-card" style="max-width: 960px; margin-top: 16px;">';
		echo '<h2>' . esc_html__( 'Price books', 'reactwoo-geo-commerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Set fixed ZAR, GBP, EUR, and USD prices on each product. The list can be extended with the rwgcm_price_book_currencies filter. Before checkout, Geo Core’s visitor country selects the currency. Once a billing country is entered at cart or checkout, that country decides.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'rwgcm_price_book_settings' );
		echo '<input type="hidden" name="rwgcm_price_book_settings_save" value="1" />';
		echo '<label><input type="checkbox" name="rwgcm_price_book_base_adjustments" value="yes" ' . checked( $enabled, true, false ) . ' /> ';
		echo esc_html__( 'Keep store-currency geo price adjustments', 'reactwoo-geo-commerce' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'On for existing sites. Pricing rules still adjust the store currency (for example GBP) when a product has no fixed price in that currency. Fixed price-book prices are not adjusted and are not converted.', 'reactwoo-geo-commerce' ) . '</p>';

		echo '<h3>' . esc_html__( 'Country to currency', 'reactwoo-geo-commerce' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'One country and currency per line, for example ZA ZAR. Saving replaces the built-in map (South Africa ZAR, United Kingdom GBP, United States USD, eurozone EUR). A blank map means every country uses the fallback. Countries that are not listed use the fallback currency.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<textarea name="rwgcm_country_map_text" rows="12" cols="24" style="font-family: monospace;" class="large-text code">' . esc_textarea( $map_text ) . '</textarea>';
		echo '<p><label for="rwgcm_price_book_fallback_currency">' . esc_html__( 'Fallback currency', 'reactwoo-geo-commerce' ) . '</label><br/>';
		echo '<input type="text" id="rwgcm_price_book_fallback_currency" name="rwgcm_price_book_fallback_currency" value="' . esc_attr( $fallback ) . '" maxlength="3" class="regular-text" style="width: 6em;" placeholder="' . esc_attr__( 'Store', 'reactwoo-geo-commerce' ) . '" /></p>';
		echo '<p class="description">' . esc_html__( 'Used when the country is known but not in the map. Leave blank to use the WooCommerce store currency. The code must be the store currency or one of the price-book currencies.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<p class="description">' . esc_html( RWGCM_Price_Book_Currency::cache_compatibility_notes() ) . '</p>';

		echo '<h3>' . esc_html__( 'Payment gateways by country', 'reactwoo-geo-commerce' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Checked gateways are the only ones offered at checkout for that billing country. A country or group with nothing checked keeps every enabled gateway. Subscription renewals keep charging the gateway already on the subscription. Changing the payment method uses the subscription billing country.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'A gateway that cannot charge the store currency needs its own currency list below. Checking it for a country does not change the currency it sees.', 'reactwoo-geo-commerce' ) . '</p>';
		self::render_gateway_rules();

		submit_button( __( 'Save price book settings', 'reactwoo-geo-commerce' ) );
		echo '</form></div>';
	}

	/**
	 * @return void
	 */
	private static function render_gateway_rules() {
		$choices = RWGCM_Gateway_Availability::gateway_choices();
		$rules   = RWGCM_Gateway_Availability::stored_rules();
		$groups  = RWGCM_Gateway_Availability::country_group_choices();
		if ( empty( $choices ) ) {
			echo '<p>' . esc_html__( 'No enabled WooCommerce payment gateways were found.', 'reactwoo-geo-commerce' ) . '</p>';
			return;
		}
		echo '<input type="hidden" name="rwgcm_gateway_rules_present" value="1" />';

		$codes = array_values(
			array_unique(
				array_merge(
					array_keys( RWGCM_Price_Book::country_currency_map() ),
					array_keys( $rules['countries'] )
				)
			)
		);
		sort( $codes );

		echo '<div style="overflow-x: auto;">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Billing country', 'reactwoo-geo-commerce' ) . '</th>';
		foreach ( $choices as $id => $title ) {
			echo '<th>' . esc_html( $title ) . '<br/><code>' . esc_html( $id ) . '</code></th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $codes as $code ) {
			$selected = isset( $rules['countries'][ $code ] ) ? $rules['countries'][ $code ] : array();
			self::render_gateway_row( $code, 'rwgcm_gateway_country[' . $code . '][]', $choices, $selected, false );
		}
		self::render_gateway_row( '', 'rwgcm_gateway_country_new_ids[]', $choices, array(), true );
		echo '</tbody></table></div>';

		self::render_gateway_currencies( $choices, $rules );

		echo '<h4>' . esc_html__( 'Country groups', 'reactwoo-geo-commerce' ) . '</h4>';
		if ( empty( $groups ) ) {
			echo '<p class="description">' . esc_html__( 'Geo Core has no country groups yet. Assign gateways per country above. Groups from Geo Core’s country group registry show up here when they exist.', 'reactwoo-geo-commerce' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'A group applies when the billing country is in that Geo Core group and the country has no rule of its own. Several matching groups are combined.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<div style="overflow-x: auto;">';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Group', 'reactwoo-geo-commerce' ) . '</th>';
		foreach ( $choices as $id => $title ) {
			echo '<th>' . esc_html( $title ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $groups as $slug => $label ) {
			$selected = isset( $rules['groups'][ $slug ] ) ? $rules['groups'][ $slug ] : array();
			echo '<tr><th scope="row">' . esc_html( $label ) . '<br/><code>' . esc_html( $slug ) . '</code></th>';
			foreach ( $choices as $id => $title ) {
				unset( $title );
				echo '<td><input type="checkbox" name="' . esc_attr( 'rwgcm_gateway_group[' . $slug . '][]' ) . '" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $selected, true ), true, false ) . ' /></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * @param array<string, string> $choices Gateway id => title.
	 * @param array<string, mixed>  $rules   Country gateway rules, for the hint.
	 * @return void
	 */
	private static function render_gateway_currencies( array $choices, array $rules ) {
		$saved = RWGCM_Gateway_Currency::stored_currencies();
		$map   = RWGCM_Price_Book::country_currency_map();
		echo '<h4>' . esc_html__( 'Gateway currencies', 'reactwoo-geo-commerce' ) . '</h4>';
		echo '<p class="description">' . esc_html__( 'Optional. Leave a gateway blank and Geo Commerce will not change it. Enter the currencies that gateway can charge, separated by spaces. On that gateway’s own WooCommerce settings screen, if the store currency is not in the list, its currency check sees one of these currencies so the settings can load. Checkout only offers the gateway when the cart or order currency is in the list. Paystack (id paystack) accepts NGN, GHS, USD, KES, RWF, ZAR, XOF, and EGP.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<input type="hidden" name="rwgcm_gateway_currencies_present" value="1" />';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Gateway', 'reactwoo-geo-commerce' ) . '</th><th>' . esc_html__( 'Currencies it can charge', 'reactwoo-geo-commerce' ) . '</th></tr></thead><tbody>';
		foreach ( $choices as $id => $title ) {
			$list    = isset( $saved[ $id ] ) ? implode( ' ', $saved[ $id ] ) : '';
			$covered = RWGCM_Gateway_Currency::currencies_covered_by_country_rules( $id, $rules, $map );
			echo '<tr><th scope="row">' . esc_html( $title ) . '<br/><code>' . esc_html( $id ) . '</code></th><td>';
			echo '<input type="text" class="regular-text" name="' . esc_attr( 'rwgcm_gateway_currencies[' . $id . ']' ) . '" value="' . esc_attr( $list ) . '" placeholder="' . esc_attr__( 'ZAR USD', 'reactwoo-geo-commerce' ) . '" />';
			if ( ! empty( $covered ) ) {
				echo '<p class="description">' . esc_html(
					sprintf(
						/* translators: %s: currency codes from the country rules. */
						__( 'Country rules for this gateway use %s.', 'reactwoo-geo-commerce' ),
						implode( ', ', $covered )
					)
				) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param string                $code     ISO2 or empty for the add row.
	 * @param string                $name     Checkbox name.
	 * @param array<string, string> $choices  Gateway id => title.
	 * @param array<int, string>    $selected Selected ids.
	 * @param bool                  $is_new   Editable country code.
	 * @return void
	 */
	private static function render_gateway_row( $code, $name, array $choices, array $selected, $is_new ) {
		echo '<tr><th scope="row">';
		if ( $is_new ) {
			echo '<label>' . esc_html__( 'Add country', 'reactwoo-geo-commerce' ) . '<br/>';
			echo '<input type="text" name="rwgcm_gateway_country_new" value="" maxlength="2" style="width: 4em;" placeholder="' . esc_attr__( 'CC', 'reactwoo-geo-commerce' ) . '" /></label>';
		} else {
			echo '<code>' . esc_html( $code ) . '</code>';
		}
		echo '</th>';
		foreach ( $choices as $id => $title ) {
			unset( $title );
			echo '<td><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $selected, true ), true, false ) . ' /></td>';
		}
		echo '</tr>';
	}

	/**
	 * @param string               $name_prefix Field name prefix.
	 * @param array<string, mixed> $book        Stored book.
	 * @param bool                 $signup      Include sign-up fee.
	 * @param bool                 $compact     Variation layout.
	 * @return void
	 */
	private static function render_currency_inputs( $name_prefix, array $book, $signup, $compact = false ) {
		foreach ( RWGCM_Price_Book::currencies() as $code ) {
			$entry   = ( isset( $book[ $code ] ) && is_array( $book[ $code ] ) ) ? $book[ $code ] : array();
			$regular = isset( $entry['regular'] ) ? (string) $entry['regular'] : '';
			$sale    = isset( $entry['sale'] ) ? (string) $entry['sale'] : '';
			$fee     = isset( $entry['signup_fee'] ) ? (string) $entry['signup_fee'] : '';
			if ( $compact ) {
				echo '<p class="form-row form-row-first">';
				echo '<label>' . esc_html( sprintf( /* translators: %s: currency code. */ __( '%s recurring price', 'reactwoo-geo-commerce' ), $code ) ) . '</label>';
				echo '<input type="text" class="wc_input_price" name="' . esc_attr( $name_prefix . '[' . $code . '][regular]' ) . '" value="' . esc_attr( $regular ) . '" placeholder="' . esc_attr( $code ) . '" />';
				echo '</p>';
				echo '<p class="form-row form-row-last">';
				echo '<label>' . esc_html( sprintf( /* translators: %s: currency code. */ __( '%s sale price', 'reactwoo-geo-commerce' ), $code ) ) . '</label>';
				echo '<input type="text" class="wc_input_price" name="' . esc_attr( $name_prefix . '[' . $code . '][sale]' ) . '" value="' . esc_attr( $sale ) . '" />';
				echo '</p>';
				if ( $signup ) {
					echo '<p class="form-row form-row-first">';
					echo '<label>' . esc_html( sprintf( /* translators: %s: currency code. */ __( '%s sign-up fee', 'reactwoo-geo-commerce' ), $code ) ) . '</label>';
					echo '<input type="text" class="wc_input_price" name="' . esc_attr( $name_prefix . '[' . $code . '][signup_fee]' ) . '" value="' . esc_attr( $fee ) . '" />';
					echo '</p>';
				}
				continue;
			}
			if ( function_exists( 'woocommerce_wp_text_input' ) ) {
				woocommerce_wp_text_input(
					array(
						'id'          => 'rwgcm_price_book_' . $code . '_regular',
						'name'        => $name_prefix . '[' . $code . '][regular]',
						'label'       => sprintf( /* translators: %s: currency code. */ __( '%s regular / recurring price', 'reactwoo-geo-commerce' ), $code ),
						'value'       => $regular,
						'data_type'   => 'price',
						'description' => __( 'Charged price when no sale price is set.', 'reactwoo-geo-commerce' ),
						'desc_tip'    => true,
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'        => 'rwgcm_price_book_' . $code . '_sale',
						'name'      => $name_prefix . '[' . $code . '][sale]',
						'label'     => sprintf( /* translators: %s: currency code. */ __( '%s sale price', 'reactwoo-geo-commerce' ), $code ),
						'value'     => $sale,
						'data_type' => 'price',
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'          => 'rwgcm_price_book_' . $code . '_signup',
						'name'        => $name_prefix . '[' . $code . '][signup_fee]',
						'label'       => sprintf( /* translators: %s: currency code. */ __( '%s sign-up fee', 'reactwoo-geo-commerce' ), $code ),
						'value'       => $fee,
						'data_type'   => 'price',
						'wrapper_class' => 'show_if_subscription',
						'description' => __( 'Subscriptions only. Blank in a non-store currency means no sign-up fee.', 'reactwoo-geo-commerce' ),
						'desc_tip'    => true,
					)
				);
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function product_book_from_global() {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) && isset( $GLOBALS['post']->ID ) ) {
			$post_id = (int) $GLOBALS['post']->ID;
		}
		return RWGCM_Price_Book_Currency::get_book( $post_id );
	}
}
