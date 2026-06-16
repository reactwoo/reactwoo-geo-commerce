<?php
/**
 * Geo Commerce capability row for Geo Core Insights dashboard.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers commerce rules, outcomes, and attribution metrics.
 */
class RWGCM_Insights_Provider {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_insights_providers', array( __CLASS__, 'register' ) );
	}

	/**
	 * @param array<int, callable(): array<string, mixed>> $providers Provider callables.
	 * @return array<int, callable(): array<string, mixed>>
	 */
	public static function register( $providers ) {
		if ( ! is_array( $providers ) ) {
			$providers = array();
		}
		$providers[] = array( __CLASS__, 'build' );
		return $providers;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build() {
		if ( ! class_exists( 'RWGC_Insights', false ) ) {
			return array();
		}

		$plugin_file = 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
		$installed   = class_exists( 'RWGC_Admin_UI', false ) && RWGC_Admin_UI::is_plugin_active( $plugin_file );
		if ( ! $installed ) {
			return RWGC_Insights::normalize_provider(
				array(
					'id'            => 'geo-commerce',
					'label'         => __( 'Geo Commerce', 'reactwoo-geo-commerce' ),
					'status'        => 'missing',
					'summary'       => __( 'Install Geo Commerce to unlock geo pricing, checkout rules, and order attribution.', 'reactwoo-geo-commerce' ),
					'capabilities'  => self::feature_list( 'missing' ),
					'empty_state'   => array(
						'type'  => 'not_installed',
						'title' => __( 'Install Geo Commerce to unlock geo pricing and checkout rules', 'reactwoo-geo-commerce' ),
						'body'  => __( 'WooCommerce stores can apply country-based pricing, fees, coupons, shipping filters, and overlays.', 'reactwoo-geo-commerce' ),
					),
					'actions'       => array(
						array(
							'url'     => admin_url( 'plugin-install.php?s=reactwoo-geo-commerce&tab=search&type=term' ),
							'label'   => __( 'Install Geo Commerce', 'reactwoo-geo-commerce' ),
							'primary' => true,
						),
					),
					'recommendations' => array(
						array(
							'label'    => __( 'Add Geo Commerce for WooCommerce geo rules', 'reactwoo-geo-commerce' ),
							'priority' => 20,
							'reason'   => __( 'Apply pricing, fees, and attribution on top of Geo Core detection.', 'reactwoo-geo-commerce' ),
						),
					),
				)
			);
		}

		if ( ! class_exists( 'WooCommerce', false ) ) {
			return RWGC_Insights::normalize_provider(
				array(
					'id'            => 'geo-commerce',
					'label'         => __( 'Geo Commerce', 'reactwoo-geo-commerce' ),
					'status'        => 'requires_dependency',
					'summary'       => __( 'Geo Commerce is installed but WooCommerce is required for storefront outcomes.', 'reactwoo-geo-commerce' ),
					'capabilities'  => self::feature_list( 'inactive' ),
					'missing_setup' => array( __( 'WooCommerce plugin', 'reactwoo-geo-commerce' ) ),
					'empty_state'   => array(
						'type'  => 'not_configured',
						'title' => __( 'Activate WooCommerce to use Geo Commerce', 'reactwoo-geo-commerce' ),
						'body'  => __( 'Commerce rules apply pricing, fees, and attribution during checkout.', 'reactwoo-geo-commerce' ),
					),
					'actions'       => array(
						array(
							'url'     => admin_url( 'plugins.php' ),
							'label'   => __( 'Manage plugins', 'reactwoo-geo-commerce' ),
							'primary' => true,
						),
					),
				)
			);
		}

		$rules_active = 0;
		$by_action    = array();
		if ( class_exists( 'RWGCM_Rule_Store', false ) && class_exists( 'RWGCM_DB', false ) && RWGCM_DB::rules_table_exists() ) {
			$rules_active = RWGCM_Rule_Store::count_by_status( 'active' );
			foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
				if ( ! is_array( $rule ) || 'active' !== ( $rule['status'] ?? '' ) ) {
					continue;
				}
				$actions = isset( $rule['actions'] ) && is_array( $rule['actions'] ) ? $rule['actions'] : array();
				foreach ( $actions as $action ) {
					if ( ! is_array( $action ) || empty( $action['type'] ) ) {
						continue;
					}
					$type = sanitize_key( (string) $action['type'] );
					if ( ! isset( $by_action[ $type ] ) ) {
						$by_action[ $type ] = 0;
					}
					++$by_action[ $type ];
				}
			}
		}

		$attributed = self::count_attributed_orders();
		$utm_on     = class_exists( 'RWGCM_Attribution', false ) && RWGCM_Attribution::is_storage_enabled();

		$capabilities = self::feature_list(
			'active',
			array(
				'pricing'   => ! empty( $by_action['pricing'] ),
				'fee'       => ! empty( $by_action['fee'] ),
				'coupon'    => ! empty( $by_action['coupon'] ),
				'shipping'  => ! empty( $by_action['shipping'] ),
				'overlay'   => ! empty( $by_action['overlay'] ),
				'attribution' => $attributed > 0 || $utm_on,
			)
		);

		$missing = array();
		if ( $rules_active <= 0 ) {
			$missing[] = __( 'Active commerce rules', 'reactwoo-geo-commerce' );
		}
		if ( ! $utm_on ) {
			$missing[] = __( 'UTM / click-id attribution storage', 'reactwoo-geo-commerce' );
		}

		$status = 'active';
		if ( $rules_active <= 0 ) {
			$status = 'inactive';
		} elseif ( $attributed <= 0 && $rules_active > 0 ) {
			$status = 'no_data';
		}

		$rules_url = admin_url( 'admin.php?page=rwgcm-rules' );

		return RWGC_Insights::normalize_provider(
			array(
				'id'              => 'geo-commerce',
				'label'           => __( 'Geo Commerce', 'reactwoo-geo-commerce' ),
				'status'          => $status,
				'summary'         => $rules_active > 0
					? __( 'Commerce rules are active and applying geo outcomes on this store.', 'reactwoo-geo-commerce' )
					: __( 'Geo Commerce is ready — create your first commerce rule to personalize checkout.', 'reactwoo-geo-commerce' ),
				'metrics'         => array(
					array(
						'label' => __( 'Active rules', 'reactwoo-geo-commerce' ),
						'value' => (string) $rules_active,
					),
					array(
						'label' => __( 'Attributed orders', 'reactwoo-geo-commerce' ),
						'value' => (string) $attributed,
					),
					array(
						'label' => __( 'UTM storage', 'reactwoo-geo-commerce' ),
						'value' => $utm_on ? __( 'On', 'reactwoo-geo-commerce' ) : __( 'Off', 'reactwoo-geo-commerce' ),
					),
				),
				'capabilities'    => $capabilities,
				'missing_setup'   => $missing,
				'recommendations' => $rules_active <= 0
					? array(
						array(
							'label'    => __( 'Create commerce rule', 'reactwoo-geo-commerce' ),
							'priority' => 14,
							'reason'   => __( 'Start with geo pricing or a cart fee for a priority country.', 'reactwoo-geo-commerce' ),
						),
					)
					: ( ! $utm_on
						? array(
							array(
								'label'    => __( 'Review checkout restrictions', 'reactwoo-geo-commerce' ),
								'priority' => 22,
								'reason'   => __( 'Enable attribution storage to connect campaigns with geo-attributed orders.', 'reactwoo-geo-commerce' ),
							),
						)
						: array() ),
				'actions'         => array(
					array(
						'url'     => $rules_url,
						'label'   => __( 'Create commerce rule', 'reactwoo-geo-commerce' ),
						'primary' => $rules_active <= 0,
					),
					array(
						'url'   => admin_url( 'admin.php?page=rwgcm-attribution' ),
						'label' => __( 'Attribution settings', 'reactwoo-geo-commerce' ),
					),
				),
				'empty_state'     => $rules_active <= 0
					? array(
						'type'  => 'not_configured',
						'title' => __( 'Geo Commerce is active, but no commerce rules exist yet', 'reactwoo-geo-commerce' ),
						'body'  => __( 'Create a rule to apply geo pricing, fees, coupons, shipping filters, or product overlays.', 'reactwoo-geo-commerce' ),
					)
					: ( $attributed <= 0
						? array(
							'type'  => 'no_data',
							'title' => __( 'Tracking is ready — orders will appear once checkout runs', 'reactwoo-geo-commerce' ),
							'body'  => __( 'Commerce rules are live. Geo-attributed orders will show after customers complete checkout.', 'reactwoo-geo-commerce' ),
						)
						: array() ),
			)
		);
	}

	/**
	 * @param string               $default_status Default capability status when not installed.
	 * @param array<string, bool>  $enabled        Optional per-feature flags.
	 * @return array<int, array<string, string>>
	 */
	private static function feature_list( $default_status, array $enabled = array() ) {
		$map = array(
			'pricing'     => __( 'Geo pricing', 'reactwoo-geo-commerce' ),
			'fee'         => __( 'Cart fees', 'reactwoo-geo-commerce' ),
			'coupon'      => __( 'Coupons', 'reactwoo-geo-commerce' ),
			'shipping'    => __( 'Shipping filters', 'reactwoo-geo-commerce' ),
			'attribution' => __( 'Order attribution', 'reactwoo-geo-commerce' ),
			'overlay'     => __( 'Product overlays', 'reactwoo-geo-commerce' ),
		);
		$out = array();
		foreach ( $map as $key => $label ) {
			$status = $default_status;
			if ( 'active' === $default_status && isset( $enabled[ $key ] ) ) {
				$status = $enabled[ $key ] ? 'active' : 'inactive';
			}
			$out[] = array(
				'label'  => $label,
				'status' => $status,
			);
		}
		return $out;
	}

	/**
	 * @return int
	 */
	private static function count_attributed_orders() {
		if ( ! class_exists( 'RWGCM_Order_Geo', false ) ) {
			return 0;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
				RWGCM_Order_Geo::META_COUNTRY
			)
		);
		return max( 0, (int) $count );
	}
}
