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
		echo '<p class="form-field" style="padding: 0 12px;">' . esc_html__( 'Fixed prices in each currency. The shopper’s billing country picks the currency. Before checkout, Geo Core’s country is used only when no billing country is known. A currency with no price falls back to the store currency. These amounts are not converted.', 'reactwoo-geo-commerce' ) . '</p>';
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
		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'admin.php?page=rwgcm-settings' );
		}
		wp_safe_redirect( add_query_arg( 'rwgcm_price_book_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Settings card for the migrated base-currency adjustment option.
	 *
	 * @return void
	 */
	public static function render_settings_card() {
		$enabled = RWGCM_Price_Book::base_adjustments_enabled();
		if ( isset( $_GET['rwgcm_price_book_saved'] ) && '1' === $_GET['rwgcm_price_book_saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Price book settings saved.', 'reactwoo-geo-commerce' ) . '</p></div>';
		}
		echo '<div class="rwgc-card" style="max-width: 720px; margin-top: 16px;">';
		echo '<h2>' . esc_html__( 'Price books', 'reactwoo-geo-commerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Set fixed ZAR, GBP, EUR, and USD prices on each product. The list can be extended with the rwgcm_price_book_currencies filter. Billing country selects the currency; Geo Core is only the fallback before a billing country is known.', 'reactwoo-geo-commerce' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'rwgcm_price_book_settings' );
		echo '<input type="hidden" name="rwgcm_price_book_settings_save" value="1" />';
		echo '<label><input type="checkbox" name="rwgcm_price_book_base_adjustments" value="yes" ' . checked( $enabled, true, false ) . ' /> ';
		echo esc_html__( 'Keep store-currency geo price adjustments', 'reactwoo-geo-commerce' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'On for existing sites. Pricing rules still adjust the store currency (for example GBP) when a product has no fixed price in that currency. Fixed price-book prices are not adjusted and are not converted.', 'reactwoo-geo-commerce' ) . '</p>';
		submit_button( __( 'Save price book settings', 'reactwoo-geo-commerce' ) );
		echo '</form></div>';
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
