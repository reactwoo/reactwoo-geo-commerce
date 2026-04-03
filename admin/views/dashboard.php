<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGCM_Admin::MENU_PARENT;
?>
<div class="wrap rwgc-wrap rwgcm-wrap">
	<h1><?php esc_html_e( 'Geo Commerce', 'reactwoo-geo-commerce' ); ?></h1>
	<p class="description"><?php esc_html_e( 'WooCommerce store pricing and fees based on the visitor’s country from Geo Core. Use Pricing rules and Cart fees; Geo Core → Settings only manages the IP database (MaxMind), not store rules.', 'reactwoo-geo-commerce' ); ?></p>
	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgcm-hero">
		<h2><?php esc_html_e( 'Get started in three steps', 'reactwoo-geo-commerce' ); ?></h2>
		<ol class="rwgcm-steps">
			<li><?php esc_html_e( 'Open Pricing rules and add at least one country row (specific categories first, broad rows last).', 'reactwoo-geo-commerce' ); ?></li>
			<li><?php esc_html_e( 'Turn on “Enable geo pricing rules”, then save.', 'reactwoo-geo-commerce' ); ?></li>
			<li><?php esc_html_e( 'Preview the storefront with Geo Core’s country preview (?rwgc_preview_country=XX) or test from another country.', 'reactwoo-geo-commerce' ); ?></li>
		</ol>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open pricing rules', 'reactwoo-geo-commerce' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-fees' ) ); ?>" class="button"><?php esc_html_e( 'Open cart fees', 'reactwoo-geo-commerce' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-help' ) ); ?>" class="button"><?php esc_html_e( 'Help: licenses & setup', 'reactwoo-geo-commerce' ); ?></a>
		</p>
	</div>

	<div class="rwgc-card" style="max-width: 720px;">
		<h2><?php esc_html_e( 'Order attribution', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Optional: keep first-touch and last-touch marketing parameters on orders.', 'reactwoo-geo-commerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rwgcm_save_dashboard' ); ?>
			<input type="hidden" name="action" value="rwgcm_save_dashboard" />
			<p>
				<label>
					<input type="checkbox" name="rwgcm_store_utm" value="1" <?php checked( get_option( RWGCM_Attribution::OPTION_STORE_UTM, 'yes' ), 'yes' ); ?> />
					<?php esc_html_e( 'Store UTM and click-id parameters on orders (cookies; written to order meta at checkout).', 'reactwoo-geo-commerce' ); ?>
				</label>
			</p>
			<?php submit_button( __( 'Save attribution setting', 'reactwoo-geo-commerce' ), 'primary', 'submit', false ); ?>
		</form>
	</div>

	<?php
	$ps = isset( $rwgcm_pricing_status ) && is_array( $rwgcm_pricing_status ) ? $rwgcm_pricing_status : array( 'enabled' => false, 'rule_count' => 0 );
	$fs = isset( $rwgcm_fee_status ) && is_array( $rwgcm_fee_status ) ? $rwgcm_fee_status : array( 'enabled' => false, 'rule_count' => 0 );
	?>
	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Pricing status', 'reactwoo-geo-commerce' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rules turned on', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo ! empty( $ps['enabled'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved rows', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo esc_html( (string) (int) ( $ps['rule_count'] ?? 0 ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Fee rules status', 'reactwoo-geo-commerce' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fees turned on', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo ! empty( $fs['enabled'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved rows', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo esc_html( (string) (int) ( $fs['rule_count'] ?? 0 ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Data merged into visitor payload', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Geo Core can expose extra keys when WooCommerce is active — for example cart item counts for integrations.', 'reactwoo-geo-commerce' ); ?></p>
	</div>

	<details class="rwgcm-dev-details">
		<summary><?php esc_html_e( 'Technical details (developers)', 'reactwoo-geo-commerce' ); ?></summary>
		<?php
		$rwgcm_rest_capabilities = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
		?>
		<?php if ( is_string( $rwgcm_rest_capabilities ) && '' !== $rwgcm_rest_capabilities ) : ?>
			<p>
				<a href="<?php echo esc_url( $rwgcm_rest_capabilities ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Geo Core capabilities JSON', 'reactwoo-geo-commerce' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $sample ) && is_array( $sample ) ) : ?>
			<p class="description"><?php esc_html_e( 'Sample visitor payload for this request (admin context may differ from the storefront):', 'reactwoo-geo-commerce' ); ?></p>
			<pre><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		<?php else : ?>
			<p><?php esc_html_e( 'No geo sample in this admin context.', 'reactwoo-geo-commerce' ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Hooks (reference): rwgcm_geo_data; rwgcm_before_cart_totals; rwgcm_skip_pricing_for_cart_item; rwgcm_apply_catalog_price; rwgcm_cart_fees; rwgcm_package_rates; rwgcm_coupon_allowed_for_visitor; rwgcm_store_utm_on_orders.', 'reactwoo-geo-commerce' ); ?></p>
	</details>
</div>
