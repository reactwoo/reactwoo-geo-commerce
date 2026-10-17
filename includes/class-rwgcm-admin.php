<?php
/**
 * Geo Commerce — wp-admin (submenus under ReactWoo Geo Core; optional summary on Geo Core dashboard).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo Commerce.
 */
class RWGCM_Admin {

	/**
	 * Parent admin page slug (first screen / dashboard).
	 */
	const MENU_PARENT = 'rwgcm-dashboard';

	/**
	 * Capability for Commerce admin screens (aligned with Geo Core hub).
	 *
	 * @return string
	 */
	public static function required_capability() {
		if ( class_exists( 'RWGC_Admin', false ) ) {
			return RWGC_Admin::required_capability();
		}
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return 'manage_woocommerce';
		}
		return 'manage_options';
	}

	/**
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::required_capability() );
	}

	/**
	 * WordPress admin menu parent (Geo Core hub).
	 *
	 * @return string
	 */
	private static function admin_menu_parent() {
		if ( function_exists( 'rwgc_admin_menu_parent' ) ) {
			return rwgc_admin_menu_parent();
		}
		return 'rwgc-dashboard';
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_wc_payments_notice' ) );
		add_filter( 'rwgc_section_hub_cards', array( __CLASS__, 'filter_section_hub_cards' ), 20, 2 );
		add_action( 'admin_init', array( __CLASS__, 'handle_license_actions' ) );
		add_action( 'admin_post_rwgcm_save_dashboard', array( __CLASS__, 'handle_save_dashboard' ) );
		add_action( 'rwgc_dashboard_satellite_panels', array( __CLASS__, 'render_geo_core_summary_card' ) );
		add_filter( 'rwgc_onboarding_platform_steps', array( __CLASS__, 'filter_onboarding_platform_steps' ), 20, 2 );
	}

	/**
	 * Optional WooCommerce geo step on the ReactWoo Geo Overview setup checklist.
	 *
	 * @param array<int, array<string, mixed>> $steps Onboarding steps.
	 * @param array<string, mixed>           $state Onboarding state.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_onboarding_platform_steps( $steps, $state ) {
		unset( $state );
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return $steps;
		}
		$commerce_done = false;
		if ( class_exists( 'RWGCM_Settings', false ) ) {
			$s = RWGCM_Settings::get_settings();
			if ( is_array( $s ) && '' !== trim( (string) ( $s['reactwoo_license_key'] ?? '' ) ) ) {
				$commerce_done = true;
			}
		}
		$steps   = is_array( $steps ) ? $steps : array();
		$steps[] = array(
			'id'       => 'geo_commerce',
			'label'    => __( 'Review Geo Commerce rules', 'reactwoo-geo-commerce' ),
			'done'     => $commerce_done,
			'url'      => admin_url( 'admin.php?page=rwgcm-dashboard' ),
			'optional' => true,
			'hint'     => __( 'Rules, pricing actions, and product display under Commerce.', 'reactwoo-geo-commerce' ),
		);
		return $steps;
	}

	/**
	 * When Geo Commerce is active, show a short summary + link on the Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! self::can_manage() ) {
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
					<p><?php esc_html_e( 'Manage country-based rules, cart fees, and geo-attributed WooCommerce behaviour.', 'reactwoo-geo-commerce' ); ?></p>
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
	 * @return bool
	 */
	public static function uses_platform_shell() {
		return function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
	}

	/**
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		if ( self::uses_platform_shell() ) {
			return;
		}

		$items = self::inner_nav_items();

		if ( function_exists( 'rw_geo_render_inner_nav' ) ) {
			rw_geo_render_inner_nav(
				$items,
				(string) $current,
				array(
					'filter'              => 'rwgcm_inner_nav_items',
					'aria_label'          => __( 'Geo Commerce section navigation', 'reactwoo-geo-commerce' ),
					'show_hub_breadcrumb' => 'rwgc-dashboard' === self::admin_menu_parent(),
					'hub_extension_label' => __( 'Geo Commerce', 'reactwoo-geo-commerce' ),
				)
			);
			return;
		}

		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo Commerce section navigation', 'reactwoo-geo-commerce' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Register a Commerce screen in the unified ReactWoo Geo app (reference satellite pattern).
	 *
	 * @param array<string, mixed> $args route, menu_slug, label, callback; optional section, order, page_title, menu_title.
	 * @return string|false
	 */
	private static function register_app_route( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'section'      => 'commerce',
				'provider'     => 'geo_commerce',
				'capability'   => self::required_capability(),
				'module'       => 'commerce',
				'page_title'   => '',
				'menu_title'   => '',
			)
		);
		if ( empty( $args['page_title'] ) && ! empty( $args['label'] ) ) {
			$args['page_title'] = (string) $args['label'];
		}
		if ( empty( $args['menu_title'] ) && ! empty( $args['label'] ) ) {
			$args['menu_title'] = (string) $args['label'];
		}
		if ( function_exists( 'rw_geo_register_app_route' ) ) {
			return rw_geo_register_app_route( $args );
		}
		if ( function_exists( 'rw_geo_register_admin_submenu' ) ) {
			return rw_geo_register_admin_submenu( $args );
		}
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		return add_submenu_page(
			self::admin_menu_parent(),
			(string) $args['page_title'],
			(string) $args['menu_title'],
			(string) $args['capability'],
			$slug,
			$args['callback']
		);
	}

	/**
	 * @return void
	 */
	public static function handle_save_dashboard() {
		if ( ! self::can_manage() ) {
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
		if ( ! is_admin() || ! self::can_manage() ) {
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
		if ( ! self::can_manage() ) {
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

		$on_rule_edit = false !== strpos( $hook, 'rwgcm-pricing' )
			&& isset( $_GET['rwgcm_edit'] )
			&& '' !== (string) wp_unslash( $_GET['rwgcm_edit'] );
		if ( $on_rule_edit ) {
			wp_enqueue_script(
				'rwgcm-condition-builder',
				RWGCM_URL . 'admin/js/rwgcm-condition-builder.js',
				array(),
				RWGCM_VERSION,
				true
			);
			wp_enqueue_script(
				'rwgcm-rule-builder',
				RWGCM_URL . 'admin/js/rwgcm-rule-builder.js',
				array( 'rwgcm-condition-builder' ),
				RWGCM_VERSION,
				true
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		$on_rule_edit_portable = $on_rule_edit;
		if ( $on_rule_edit_portable && class_exists( 'RWGC_Targeting_Rule_Builder_Assets', false ) ) {
			RWGC_Targeting_Rule_Builder_Assets::enqueue_admin();
			wp_add_inline_script(
				RWGC_Targeting_Rule_Builder_Assets::SCRIPT_HANDLE,
				RWGC_Targeting_Rule_Builder_Assets::get_mount_inline( '#rwgcm_portable_targeting' ),
				'after'
			);
		}
	}

	/**
	 * Commerce hub page in the Geo Core app shell.
	 */
	const COMMERCE_HUB_SLUG = 'rwgc-commerce-hub';

	/**
	 * Screens registered in the Commerce area. Not rule-builder IF/THEN rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function admin_routes() {
		return array(
			array(
				'menu_slug' => self::MENU_PARENT,
				'route'     => 'overview',
				'label'     => __( 'Dashboard', 'reactwoo-geo-commerce' ),
				'order'     => 10,
				'is_section_nav' => false,
				'callback'  => array( __CLASS__, 'render_dashboard' ),
			),
			array(
				'menu_slug' => 'rwgcm-pricing',
				'route'     => 'pricing',
				'label'     => __( 'Rules', 'reactwoo-geo-commerce' ),
				'order'     => 20,
				'callback'  => array( 'RWGCM_Admin_Rules', 'render' ),
			),
			array(
				'menu_slug' => 'rwgcm-price-books',
				'route'     => 'price-books',
				'label'     => __( 'Price books', 'reactwoo-geo-commerce' ),
				'order'     => 22,
				'callback'  => array( __CLASS__, 'render_price_books' ),
			),
			array(
				'menu_slug' => 'rwgcm-payments',
				'route'     => 'payments',
				'label'     => __( 'Payments by country', 'reactwoo-geo-commerce' ),
				'order'     => 24,
				'callback'  => array( __CLASS__, 'render_payments' ),
			),
			array(
				'menu_slug' => 'rwgcm-settings',
				'route'     => 'commerce-settings',
				'label'     => __( 'Settings', 'reactwoo-geo-commerce' ),
				'order'     => 26,
				'callback'  => array( __CLASS__, 'render_settings' ),
			),
			array(
				'menu_slug' => 'rwgcm-products',
				'route'     => 'products',
				'label'     => __( 'Products', 'reactwoo-geo-commerce' ),
				'order'     => 32,
				'callback'  => array( __CLASS__, 'render_products' ),
			),
			array(
				'menu_slug' => 'rwgcm-legacy-pricing',
				'route'     => 'legacy-pricing',
				'label'     => __( 'Legacy country rows', 'reactwoo-geo-commerce' ),
				'order'     => 26,
				'is_section_nav' => false,
				'callback'  => array( 'RWGCM_Admin_Pricing', 'render' ),
			),
			array(
				'menu_slug' => 'rwgcm-product-overlays',
				'route'     => 'legacy-overlays',
				'label'     => __( 'Legacy product overlays', 'reactwoo-geo-commerce' ),
				'order'     => 27,
				'is_section_nav' => false,
				'callback'  => array( __CLASS__, 'render_product_overlays' ),
			),
			array(
				'menu_slug' => 'rwgcm-fees',
				'route'     => 'offers',
				'label'     => __( 'Cart fees', 'reactwoo-geo-commerce' ),
				'order'     => 40,
				'callback'  => array( 'RWGCM_Admin_Fees', 'render' ),
			),
			array(
				'menu_slug' => 'rwgcm-merchandising',
				'route'     => 'merchandising',
				'label'     => __( 'Merchandising', 'reactwoo-geo-commerce' ),
				'order'     => 45,
				'callback'  => array( __CLASS__, 'render_merchandising' ),
			),
			array(
				'menu_slug' => 'rwgcm-availability',
				'route'     => 'availability',
				'label'     => __( 'Availability', 'reactwoo-geo-commerce' ),
				'order'     => 50,
				'callback'  => array( __CLASS__, 'render_availability' ),
			),
			array(
				'menu_slug' => 'rwgcm-attribution',
				'section'   => 'insights',
				'route'     => 'commerce-performance',
				'label'     => __( 'Commerce performance', 'reactwoo-geo-commerce' ),
				'order'     => 50,
				'callback'  => array( __CLASS__, 'render_attribution' ),
			),
			array(
				'menu_slug' => 'rwgcm-diagnostics',
				'section'   => 'settings',
				'route'     => 'commerce-diagnostics',
				'label'     => __( 'Logs', 'reactwoo-geo-commerce' ),
				'order'     => 60,
				'callback'  => array( __CLASS__, 'render_diagnostics' ),
			),
			array(
				'menu_slug' => 'rwgcm-license',
				'section'   => 'settings',
				'route'     => 'commerce-license',
				'label'     => __( 'Commerce license', 'reactwoo-geo-commerce' ),
				'order'     => 80,
				'callback'  => array( __CLASS__, 'render_license' ),
			),
			array(
				'menu_slug' => 'rwgcm-help',
				'section'   => 'settings',
				'route'     => 'commerce-help',
				'label'     => __( 'Commerce help', 'reactwoo-geo-commerce' ),
				'order'     => 90,
				'callback'  => array( __CLASS__, 'render_help' ),
			),
		);
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		foreach ( self::admin_routes() as $route ) {
			self::register_app_route( $route );
		}
	}

	/**
	 * Fallback in-page links when the Geo Core app shell is off.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function inner_nav_items() {
		return array(
			self::MENU_PARENT      => __( 'Dashboard', 'reactwoo-geo-commerce' ),
			'rwgcm-pricing'        => __( 'Rules', 'reactwoo-geo-commerce' ),
			'rwgcm-price-books'    => __( 'Price books', 'reactwoo-geo-commerce' ),
			'rwgcm-payments'       => __( 'Payments by country', 'reactwoo-geo-commerce' ),
			'rwgcm-settings'       => __( 'Settings', 'reactwoo-geo-commerce' ),
			'rwgcm-products'       => __( 'Products', 'reactwoo-geo-commerce' ),
			'rwgcm-legacy-pricing' => __( 'Legacy country rows', 'reactwoo-geo-commerce' ),
			'rwgcm-fees'           => __( 'Cart fees', 'reactwoo-geo-commerce' ),
			'rwgcm-attribution'    => __( 'Marketing attribution', 'reactwoo-geo-commerce' ),
			'rwgcm-diagnostics'    => __( 'Logs', 'reactwoo-geo-commerce' ),
			'rwgcm-license'        => __( 'License', 'reactwoo-geo-commerce' ),
			'rwgcm-help'           => __( 'Help', 'reactwoo-geo-commerce' ),
		);
	}

	/**
	 * Cards pinned to the front of the Commerce hub (admin.php?page=rwgc-commerce-hub).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function commerce_hub_primary_cards() {
		return array(
			array(
				'menu_slug'   => 'rwgcm-pricing',
				'label'       => __( 'Rules', 'reactwoo-geo-commerce' ),
				'description' => __( 'Conditions and actions for pricing, badges, notices, and overlays.', 'reactwoo-geo-commerce' ),
			),
			array(
				'menu_slug'   => 'rwgcm-price-books',
				'label'       => __( 'Price books', 'reactwoo-geo-commerce' ),
				'description' => __( 'Country to currency map, fallback currency, and fixed prices per currency.', 'reactwoo-geo-commerce' ),
			),
			array(
				'menu_slug'   => 'rwgcm-payments',
				'label'       => __( 'Configure payment gateways', 'reactwoo-geo-commerce' ),
				'description' => __( 'Payments by country: which gateways each billing country can use, and which currencies a gateway can charge.', 'reactwoo-geo-commerce' ),
			),
			array(
				'menu_slug'   => 'rwgcm-settings',
				'label'       => __( 'Settings', 'reactwoo-geo-commerce' ),
				'description' => __( 'Links to Geo Core, WooCommerce, and the Geo Commerce licence.', 'reactwoo-geo-commerce' ),
			),
		);
	}

	/**
	 * @param mixed  $cards      Hub cards.
	 * @param string $section_id Goal section id.
	 * @return mixed
	 */
	public static function filter_section_hub_cards( $cards, $section_id ) {
		if ( 'commerce' !== $section_id || ! is_array( $cards ) ) {
			return $cards;
		}
		return self::promote_hub_cards( $cards );
	}

	/**
	 * Put Rules, Price books, Configure payment gateways, and Settings first.
	 *
	 * @param array<int, array<string, mixed>> $cards Existing hub cards.
	 * @return array<int, array<string, mixed>>
	 */
	public static function promote_hub_cards( array $cards ) {
		$by_slug = array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) || empty( $card['menu_slug'] ) ) {
				continue;
			}
			$by_slug[ (string) $card['menu_slug'] ] = $card;
		}
		$primary = array();
		foreach ( self::commerce_hub_primary_cards() as $spec ) {
			$slug     = $spec['menu_slug'];
			$existing = isset( $by_slug[ $slug ] ) && is_array( $by_slug[ $slug ] ) ? $by_slug[ $slug ] : array();
			$url      = isset( $existing['url'] ) ? (string) $existing['url'] : '';
			if ( '' === $url && function_exists( 'admin_url' ) ) {
				$url = admin_url( 'admin.php?page=' . $slug );
			}
			$primary[] = array_merge(
				$existing,
				array(
					'menu_slug'   => $slug,
					'label'       => $spec['label'],
					'description' => $spec['description'],
					'url'         => $url,
					'provider'    => isset( $existing['provider'] ) ? $existing['provider'] : 'geo_commerce',
					'prominent'   => true,
				)
			);
			unset( $by_slug[ $slug ] );
		}
		return array_merge( $primary, array_values( $by_slug ) );
	}

	/**
	 * WooCommerce → Settings → Payments uses tab=checkout.
	 *
	 * @param array<string, mixed> $query page and tab.
	 * @return bool
	 */
	public static function is_wc_payments_screen( array $query ) {
		$page = isset( $query['page'] ) ? (string) $query['page'] : '';
		$tab  = isset( $query['tab'] ) ? (string) $query['tab'] : '';
		return 'wc-settings' === $page && 'checkout' === $tab;
	}

	/**
	 * @return void
	 */
	public static function render_wc_payments_notice() {
		if ( ! self::can_manage() || ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$query = array(
			'page' => sanitize_key( wp_unslash( $_GET['page'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'tab'  => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		if ( ! self::is_wc_payments_screen( $query ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=rwgcm-payments' );
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'Country availability for these gateways is set in ReactWoo Geo, not on this screen.', 'reactwoo-geo-commerce' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Set per-country availability in ReactWoo Geo', 'reactwoo-geo-commerce' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * @return void
	 */
	public static function render_price_books() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-price-books';
		include RWGCM_PATH . 'admin/views/price-books.php';
	}

	/**
	 * @return void
	 */
	public static function render_payments() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-payments';
		include RWGCM_PATH . 'admin/views/payments-by-country.php';
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! self::can_manage() ) {
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
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-help';
		include RWGCM_PATH . 'admin/views/help.php';
	}

	/**
	 * Commerce merchandising entry (overlays + contextual product messaging).
	 *
	 * @return void
	 */
	public static function render_merchandising() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-merchandising';
		include RWGCM_PATH . 'admin/views/commerce-merchandising.php';
	}

	/**
	 * Commerce availability entry (geo-based visibility and purchase eligibility).
	 *
	 * @return void
	 */
	public static function render_availability() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgcm-availability';
		include RWGCM_PATH . 'admin/views/commerce-availability.php';
	}

	/**
	 * Product-scoped rule assignments (Rules with product scope).
	 *
	 * @return void
	 */
	public static function render_products() {
		if ( ! self::can_manage() ) {
			return;
		}
		$product_rules = array();
		if ( class_exists( 'RWGCM_Rule_Store', false ) && RWGCM_DB::rules_table_exists() ) {
			foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['scope']['type'] ) || 'product' !== $rule['scope']['type'] ) {
					continue;
				}
				$product_rules[] = $rule;
			}
		}
		$rwgc_nav_current = 'rwgcm-products';
		include RWGCM_PATH . 'admin/views/products-rule-assignments.php';
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
		if ( ! self::can_manage() ) {
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
		if ( ! self::can_manage() ) {
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
		if ( ! self::can_manage() ) {
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
