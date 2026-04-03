<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGCM_Admin::MENU_PARENT;

$ps = isset( $rwgcm_pricing_status ) && is_array( $rwgcm_pricing_status ) ? $rwgcm_pricing_status : array( 'enabled' => false, 'rule_count' => 0 );
$fs = isset( $rwgcm_fee_status ) && is_array( $rwgcm_fee_status ) ? $rwgcm_fee_status : array( 'enabled' => false, 'rule_count' => 0 );

$pricing_enabled_rows = 0;
$fee_enabled_rows     = 0;
if ( class_exists( 'RWGCM_Pricing_Rules', false ) ) {
	$p = RWGCM_Pricing_Rules::get_all();
	if ( ! empty( $p['rules'] ) ) {
		foreach ( $p['rules'] as $r ) {
			if ( ! is_array( $r ) || empty( $r['country'] ) ) {
				continue;
			}
			if ( isset( $r['active'] ) && ! $r['active'] ) {
				continue;
			}
			$pricing_enabled_rows++;
		}
	}
}
if ( class_exists( 'RWGCM_Fee_Rules', false ) ) {
	$f = RWGCM_Fee_Rules::get_all();
	if ( ! empty( $f['rules'] ) ) {
		foreach ( $f['rules'] as $r ) {
			if ( ! is_array( $r ) || empty( $r['country'] ) || empty( $r['name'] ) ) {
				continue;
			}
			if ( isset( $r['active'] ) && ! $r['active'] ) {
				continue;
			}
			if ( isset( $r['amount'] ) && 0.0 === (float) $r['amount'] ) {
				continue;
			}
			$fee_enabled_rows++;
		}
	}
}

$attr_url = admin_url( 'admin.php?page=rwgcm-attribution' );
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--dashboard">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo Commerce', 'reactwoo-geo-commerce' ),
			__( 'Country-based catalog pricing, cart fees, and order context — built on Geo Core visitor detection.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Commerce', 'reactwoo-geo-commerce' ); ?></h1>
		<p class="description"><?php esc_html_e( 'WooCommerce store rules from visitor country.', 'reactwoo-geo-commerce' ); ?></p>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_stat_grid_open();
		RWGC_Admin_UI::render_stat_card(
			__( 'Pricing rules', 'reactwoo-geo-commerce' ),
			(string) (int) $pricing_enabled_rows,
			array(
				'hint' => ! empty( $ps['enabled'] ) ? __( 'Master switch on', 'reactwoo-geo-commerce' ) : __( 'Master switch off', 'reactwoo-geo-commerce' ),
				'tone' => ( $pricing_enabled_rows > 0 && ! empty( $ps['enabled'] ) ) ? 'success' : 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Fee rules', 'reactwoo-geo-commerce' ),
			(string) (int) $fee_enabled_rows,
			array(
				'hint' => ! empty( $fs['enabled'] ) ? __( 'Master switch on', 'reactwoo-geo-commerce' ) : __( 'Master switch off', 'reactwoo-geo-commerce' ),
				'tone' => ( $fee_enabled_rows > 0 && ! empty( $fs['enabled'] ) ) ? 'success' : 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Attribution', 'reactwoo-geo-commerce' ),
			class_exists( 'RWGCM_Attribution', false ) && RWGCM_Attribution::is_storage_enabled() ? __( 'On', 'reactwoo-geo-commerce' ) : __( 'Off', 'reactwoo-geo-commerce' ),
			array(
				'hint' => __( 'UTM / click IDs on orders', 'reactwoo-geo-commerce' ),
				'tone' => class_exists( 'RWGCM_Attribution', false ) && RWGCM_Attribution::is_storage_enabled() ? 'success' : 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'WooCommerce', 'reactwoo-geo-commerce' ),
			__( 'Ready', 'reactwoo-geo-commerce' ),
			array(
				'hint' => __( 'Store rules run here — not in Geo Core Settings', 'reactwoo-geo-commerce' ),
				'tone' => 'default',
			)
		);
		RWGC_Admin_UI::render_stat_grid_close();
		?>
	<?php endif; ?>

	<div class="rwgcm-hero">
		<h2><?php esc_html_e( 'Next steps', 'reactwoo-geo-commerce' ); ?></h2>
		<ol class="rwgcm-steps">
			<li><?php esc_html_e( 'Build pricing rules (card list — specific categories above broad rows).', 'reactwoo-geo-commerce' ); ?></li>
			<li><?php esc_html_e( 'Add cart fees for countries that need surcharges or labels.', 'reactwoo-geo-commerce' ); ?></li>
			<li><?php esc_html_e( 'Turn on attribution if you want UTM and click IDs stored on orders.', 'reactwoo-geo-commerce' ); ?></li>
		</ol>
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_quick_actions(
				array(
					array(
						'url'     => admin_url( 'admin.php?page=rwgcm-pricing' ),
						'label'   => __( 'Add pricing rule', 'reactwoo-geo-commerce' ),
						'primary' => true,
					),
					array(
						'url'   => admin_url( 'admin.php?page=rwgcm-fees' ),
						'label' => __( 'Add fee rule', 'reactwoo-geo-commerce' ),
					),
					array(
						'url'   => $attr_url,
						'label' => __( 'Attribution & orders', 'reactwoo-geo-commerce' ),
					),
					array(
						'url'   => admin_url( 'edit.php?post_type=shop_order' ),
						'label' => __( 'View orders', 'reactwoo-geo-commerce' ),
					),
				)
			);
		} else {
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ) . '">' . esc_html__( 'Pricing rules', 'reactwoo-geo-commerce' ) . '</a></p>';
		}
		?>
	</div>

	<div class="rwgc-card" style="max-width: 640px;">
		<h2><?php esc_html_e( 'Attribution', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure UTM storage and review recent orders with visitor country on the Attribution screen.', 'reactwoo-geo-commerce' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( $attr_url ); ?>"><?php esc_html_e( 'Open Attribution', 'reactwoo-geo-commerce' ); ?></a></p>
	</div>

	<details class="rwgcm-dev-details">
		<summary><?php esc_html_e( 'Technical details', 'reactwoo-geo-commerce' ); ?></summary>
		<?php
		$sample = array();
		if ( function_exists( 'rwgc_get_visitor_data' ) ) {
			$sample = rwgc_get_visitor_data();
		}
		$rwgcm_rest_capabilities = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
		?>
		<?php if ( is_string( $rwgcm_rest_capabilities ) && '' !== $rwgcm_rest_capabilities ) : ?>
			<p>
				<a href="<?php echo esc_url( $rwgcm_rest_capabilities ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Geo Core capabilities JSON', 'reactwoo-geo-commerce' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $sample ) && is_array( $sample ) ) : ?>
			<p class="description"><?php esc_html_e( 'Sample visitor payload (admin context may differ):', 'reactwoo-geo-commerce' ); ?></p>
			<pre><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		<?php endif; ?>
	</details>
</div>
