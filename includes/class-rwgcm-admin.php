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
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Geo Commerce (WooCommerce)', 'reactwoo-geo-commerce' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Country-based catalog pricing, cart fees, and order attribution run here — not under Geo Core settings. Open Geo Commerce for setup.', 'reactwoo-geo-commerce' ); ?>
			</p>
			<ul>
				<li>
					<strong><?php esc_html_e( 'Pricing rules', 'reactwoo-geo-commerce' ); ?>:</strong>
					<?php echo $ps['enabled'] ? esc_html__( 'On', 'reactwoo-geo-commerce' ) : esc_html__( 'Off', 'reactwoo-geo-commerce' ); ?>
					— <?php echo esc_html( (string) (int) $ps['rule_count'] ); ?> <?php esc_html_e( 'rows', 'reactwoo-geo-commerce' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( 'Fee rules', 'reactwoo-geo-commerce' ); ?>:</strong>
					<?php echo $fs['enabled'] ? esc_html__( 'On', 'reactwoo-geo-commerce' ) : esc_html__( 'Off', 'reactwoo-geo-commerce' ); ?>
					— <?php echo esc_html( (string) (int) $fs['rule_count'] ); ?> <?php esc_html_e( 'rows', 'reactwoo-geo-commerce' ); ?>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo Commerce', 'reactwoo-geo-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" class="button"><?php esc_html_e( 'Commerce pricing', 'reactwoo-geo-commerce' ); ?></a>
			</p>
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
			self::MENU_PARENT => __( 'Overview', 'reactwoo-geo-commerce' ),
			'rwgcm-pricing'   => __( 'Pricing rules', 'reactwoo-geo-commerce' ),
			'rwgcm-fees'      => __( 'Cart fees', 'reactwoo-geo-commerce' ),
			'rwgcm-help'      => __( 'Help', 'reactwoo-geo-commerce' ),
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
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_PARENT . '&updated=1' ) );
		exit;
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
		if ( defined( 'RWGC_URL' ) && defined( 'RWGC_VERSION' ) ) {
			wp_enqueue_style(
				'rwgc-admin',
				RWGC_URL . 'admin/css/admin.css',
				array(),
				RWGC_VERSION
			);
		}
		wp_enqueue_style(
			'rwgcm-admin',
			RWGCM_URL . 'admin/css/rwgcm-admin.css',
			array(),
			RWGCM_VERSION
		);
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
			__( 'Commerce pricing', 'reactwoo-geo-commerce' ),
			__( 'Pricing rules', 'reactwoo-geo-commerce' ),
			'manage_options',
			'rwgcm-pricing',
			array( 'RWGCM_Admin_Pricing', 'render' )
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
}
