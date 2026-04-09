<?php
/**
 * Geo Commerce — wp-admin (own top-level menu; optional summary on Geo Core dashboard).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo Commerce.
 */
class RWGCM_Admin {

	/**
	 * Parent admin page slug (top-level menu).
	 */
	const MENU_PARENT = 'rwgcm-dashboard';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_license_actions' ) );
		add_action( 'admin_post_rwgcm_save_dashboard', array( __CLASS__, 'handle_save_dashboard' ) );
		add_action( 'rwgc_dashboard_satellite_panels', array( __CLASS__, 'render_geo_core_summary_card' ) );
	}

	/**
	 * When Geo Commerce is active, show a short summary + link on the Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ps = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		$fs = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		if ( class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			$p           = RWGCM_Pricing_Rules::get_all();
			$ps['enabled']    = ! empty( $p['enabled'] );
			$ps['rule_count'] = isset( $p['rules'] ) && is_array( $p['rules'] ) ? count( $p['rules'] ) : 0;
		}
		if ( class_exists( 'RWGCM_Fee_Rules', false ) ) {
			$f           = RWGCM_Fee_Rules::get_all();
			$fs['enabled']    = ! empty( $f['enabled'] );
			$fs['rule_count'] = isset( $f['rules'] ) && is_array( $f['rules'] ) ? count( $f['rules'] ) : 0;
		}
		$url = admin_url( 'admin.php?page=' . self::MENU_PARENT );
		?>
		<div class="rwgc-addon-card">
			<div class="rwgc-addon-card__header">
				<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-cart"></span></div>
				<div class="rwgc-addon-card__heading">
					<h3><?php esc_html_e( 'Geo Commerce (WooCommerce)', 'reactwoo-geo-commerce' ); ?></h3>
					<p><?php esc_html_e( 'Manage country-based pricing, cart fees, and geo-attributed WooCommerce behaviour.', 'reactwoo-geo-commerce' ); ?></p>
				</div>
			</div>
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
			<div class="rwgc-addon-card__meta">
				<?php
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %s: On or Off */
						__( 'Pricing: %s', 'reactwoo-geo-commerce' ),
						$ps['enabled'] ? __( 'On', 'reactwoo-geo-commerce' ) : __( 'Off', 'reactwoo-geo-commerce' )
					),
					$ps['enabled'] ? 'success' : 'danger'
				);
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %d: rule count */
						__( 'Rules: %d', 'reactwoo-geo-commerce' ),
						(int) $ps['rule_count']
					),
					'neutral'
				);
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %s: On or Off */
						__( 'Fees: %s', 'reactwoo-geo-commerce' ),
						$fs['enabled'] ? __( 'On', 'reactwoo-geo-commerce' ) : __( 'Off', 'reactwoo-geo-commerce' )
					),
					$fs['enabled'] ? 'success' : 'danger'
				);
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %d: fee rule count */
						__( 'Fee rules: %d', 'reactwoo-geo-commerce' ),
						(int) $fs['rule_count']
					),
					'neutral'
				);
				?>
			</div>
			<?php endif; ?>
			<div class="rwgc-addon-card__actions">
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo Commerce', 'reactwoo-geo-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-license' ) ); ?>" class="button"><?php esc_html_e( 'License', 'reactwoo-geo-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" class="button"><?php esc_html_e( 'Rules', 'reactwoo-geo-commerce' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Section navigation for Geo Commerce screens (reuses Geo Core nav styling when available).
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		$items = array(
			self::MENU_PARENT        => __( 'Overview', 'reactwoo-geo-commerce' ),
			'rwgcm-pricing'          => __( 'Rules', 'reactwoo-geo-commerce' ),
			'rwgcm-legacy-pricing'   => __( 'Legacy country rows', 'reactwoo-geo-commerce' ),
			'rwgcm-product-overlays' => __( 'Product overlays', 'reactwoo-geo-commerce' ),
			'rwgcm-fees'             => __( 'Cart fees', 'reactwoo-geo-commerce' ),
			'rwgcm-attribution'      => __( 'Marketing attribution', 'reactwoo-geo-commerce' ),
			'rwgcm-diagnostics'      => __( 'Diagnostics', 'reactwoo-geo-commerce' ),
			'rwgcm-settings'         => __( 'Settings', 'reactwoo-geo-commerce' ),
			'rwgcm-license'          => __( 'License', 'reactwoo-geo-commerce' ),
			'rwgcm-help'             => __( 'Help', 'reactwoo-geo-commerce' ),
		);
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo Commerce section navigation', 'reactwoo-geo-commerce' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * @return void
	 */
	public static function handle_save_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-commerce' ) );
		}
		check_admin_referer( 'rwgcm_save_dashboard' );
		$store = isset( $_POST['rwgcm_store_utm'] ) ? 'yes' : 'no';
		update_option( RWGCM_Attribution::OPTION_STORE_UTM, $store, false );
		$return = isset( $_POST['rwgcm_return'] ) ? sanitize_key( wp_unslash( $_POST['rwgcm_return'] ) ) : '';
		if ( 'attribution' === $return ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-attribution&updated=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&updated=1' ) );
		}
		exit;
	}

	/**
	 * License screen GET actions (disconnect).
	 *
	 * @return void
	 */
	public static function handle_license_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'rwgcm-license' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['rwgcm_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['rwgcm_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'clear_license' === $action ) {
			if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgcm_clear_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			if ( class_exists( 'RWGCM_Settings', false ) ) {
				RWGCM_Settings::clear_license_key();
			}
			wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-license&rwgcm_disconnected=1' ) );
			exit;
		}
		if ( 'import_license' !== $action ) {
			return;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgcm_import_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = class_exists( 'RWGCM_Settings', false ) ? RWGCM_Settings::import_license_from_source( $source ) : new WP_Error( 'rwgcm_missing_settings', __( 'Geo Commerce settings are not available.', 'reactwoo-geo-commerce' ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'rwgcm-license',
						'rwgcm_import_err' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgcm-license&rwgcm_imported=' . rawurlencode( $source ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-license';
		include RWGCM_PATH . 'admin/views/license-settings.php';
	}

	/**
	 * Load Geo Core admin skin + Geo Commerce tweaks on our pages.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rwgcm-' ) === false && strpos( $hook, self::MENU_PARENT ) === false ) {
			return;
		}
		$deps = array();
		if ( defined( 'RWGC_URL' ) && defined( 'RWGC_VERSION' ) ) {
			wp_enqueue_style(
				'rwgc-admin',
				RWGC_URL . 'admin/css/admin.css',
				array(),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-admin';
			wp_enqueue_style(
				'rwgc-suite',
				RWGC_URL . 'admin/css/rwgc-suite.css',
				array( 'rwgc-admin' ),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-suite';
		}
		wp_enqueue_style(
			'rwgcm-admin',
			RWGCM_URL . 'admin/css/rwgcm-admin.css',
			$deps,
			RWGCM_VERSION
		);
		if ( false !== strpos( $hook, 'rwgcm-pricing' ) || false !== strpos( $hook, 'rwgcm-legacy-pricing' ) || false !== strpos( $hook, 'rwgcm-fees' ) ) {
			wp_enqueue_script(
				'rwgcm-rule-cards',
				RWGCM_URL . 'admin/js/rwgcm-rule-cards.js',
				array(),
				RWGCM_VERSION,
				true
			);
		}
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Geo Commerce', 'reactwoo-geo-commerce' ),
			__( 'Geo Commerce', 'reactwoo-geo-commerce' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-cart',
			56
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Overview', 'reactwoo-geo-commerce' ),
			__( 'Overview', 'reactwoo-geo-commerce' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — License', 'reactwoo-geo-commerce' ),
			__( 'License', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-license',
			array( __CLASS__, 'render_license' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Rules', 'reactwoo-geo-commerce' ),
			__( 'Rules', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-pricing',
			array( 'RWGCM_Admin_Rules', 'render' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Legacy country pricing', 'reactwoo-geo-commerce' ),
			__( 'Legacy country rows', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-legacy-pricing',
			array( 'RWGCM_Admin_Pricing', 'render' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Product overlays', 'reactwoo-geo-commerce' ),
			__( 'Product overlays', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-product-overlays',
			array( __CLASS__, 'render_product_overlays' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Commerce fees', 'reactwoo-geo-commerce' ),
			__( 'Cart fees', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-fees',
			array( 'RWGCM_Admin_Fees', 'render' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Marketing attribution', 'reactwoo-geo-commerce' ),
			__( 'Marketing attribution', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-attribution',
			array( __CLASS__, 'render_attribution' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Diagnostics', 'reactwoo-geo-commerce' ),
			__( 'Diagnostics', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-diagnostics',
			array( __CLASS__, 'render_diagnostics' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce — Settings', 'reactwoo-geo-commerce' ),
			__( 'Settings', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Commerce help', 'reactwoo-geo-commerce' ),
			__( 'Help', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-help',
			array( __CLASS__, 'render_help' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sample = array();
		if ( function_exists( 'rwgc_get_visitor_data' ) ) {
			$sample = rwgc_get_visitor_data();
		}
		$rwgcm_pricing_status = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		if ( class_exists( 'RWGCM_Pricing_Rules', false ) ) {
			$p = RWGCM_Pricing_Rules::get_all();
			$rwgcm_pricing_status['enabled']    = ! empty( $p['enabled'] );
			$rwgcm_pricing_status['rule_count'] = isset( $p['rules'] ) && is_array( $p['rules'] ) ? count( $p['rules'] ) : 0;
		}
		$rwgcm_fee_status = array(
			'enabled'    => false,
			'rule_count' => 0,
		);
		if ( class_exists( 'RWGCM_Fee_Rules', false ) ) {
			$f = RWGCM_Fee_Rules::get_all();
			$rwgcm_fee_status['enabled']    = ! empty( $f['enabled'] );
			$rwgcm_fee_status['rule_count'] = isset( $f['rules'] ) && is_array( $f['rules'] ) ? count( $f['rules'] ) : 0;
		}
		$rwgc_nav_current = self::MENU_PARENT;
		include RWGCM_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Plain-language help: licenses vs MaxMind, where to configure what.
	 *
	 * @return void
	 */
	public static function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-help';
		include RWGCM_PATH . 'admin/views/help.php';
	}

	/**
	 * Product overlays (contextual display on canonical products).
	 *
	 * @return void
	 */
	public static function render_product_overlays() {
		if ( ! class_exists( 'RWGCM_Admin_Overlays', false ) ) {
			return;
		}
		RWGCM_Admin_Overlays::render();
	}

	/**
	 * Diagnostics: Geo Core context + rule evaluation notes.
	 *
	 * @return void
	 */
	public static function render_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgcm_diag = class_exists( 'RWGCM_Diagnostics', false ) ? RWGCM_Diagnostics::collect() : array();
		$rwgc_nav_current = 'rwgcm-diagnostics';
		include RWGCM_PATH . 'admin/views/diagnostics.php';
	}

	/**
	 * Plugin settings hub (links to WooCommerce + Geo Core).
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-settings';
		include RWGCM_PATH . 'admin/views/settings-commerce.php';
	}

	/**
	 * Marketing attribution: UTM storage + recent orders with visitor country.
	 *
	 * @return void
	 */
	public static function render_attribution() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgcm_attr_orders = array();
		if ( function_exists( 'wc_get_orders' ) && class_exists( 'RWGCM_Order_Geo', false ) ) {
			$rwgcm_attr_orders = wc_get_orders(
				array(
					'limit'    => 12,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'meta_query' => array(
						array(
							'key'     => RWGCM_Order_Geo::META_COUNTRY,
							'compare' => 'EXISTS',
						),
					),
				)
			);
		}
		$rwgc_nav_current = 'rwgcm-attribution';
		include RWGCM_PATH . 'admin/views/attribution.php';
	}
}
