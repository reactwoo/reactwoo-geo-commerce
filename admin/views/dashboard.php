<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-dashboard';
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Commerce', 'reactwoo-geo-commerce' ); ?></h1>
	<p class="description"><?php esc_html_e( 'WooCommerce-aware personalization extends Geo Core visitor data. Configure country-based unit price rules under Commerce pricing.', 'reactwoo-geo-commerce' ); ?></p>
	<?php if ( class_exists( 'RWGC_Admin', false ) ) : ?>
		<?php RWGC_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card" style="max-width: 720px;">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'rwgcm_save_dashboard' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_dashboard" />
		<p><label><input type="checkbox" name="rwgcm_store_utm" value="1" <?php checked( get_option( RWGCM_Attribution::OPTION_STORE_UTM, 'yes' ), 'yes' ); ?> />
			<?php esc_html_e( 'Store UTM and click-id parameters on orders (first-touch and last-touch cookies, written to order meta at checkout).', 'reactwoo-geo-commerce' ); ?></label></p>
		<?php submit_button( __( 'Save settings', 'reactwoo-geo-commerce' ), 'primary', 'submit', false ); ?>
	</form>
	</div>

	<p class="rwui-cta-row">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Commerce pricing rules', 'reactwoo-geo-commerce' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-fees' ) ); ?>" class="button"><?php esc_html_e( 'Commerce fee rules', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<?php
	$rwgcm_rest_capabilities = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
	?>
	<?php if ( is_string( $rwgcm_rest_capabilities ) && '' !== $rwgcm_rest_capabilities ) : ?>
		<div class="rwgc-card">
		<h2><?php esc_html_e( 'REST discovery', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Geo Core exposes integration hook names and satellite versions for tools and CI/CD.', 'reactwoo-geo-commerce' ); ?></p>
		<p><a href="<?php echo esc_url( $rwgcm_rest_capabilities ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open capabilities JSON', 'reactwoo-geo-commerce' ); ?></a></p>
		</div>
	<?php endif; ?>

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
						<th scope="row"><?php esc_html_e( 'Rules active', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo ! empty( $ps['enabled'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved rules', 'reactwoo-geo-commerce' ); ?></th>
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
						<th scope="row"><?php esc_html_e( 'Fee rules active', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo ! empty( $fs['enabled'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Saved fee rows', 'reactwoo-geo-commerce' ); ?></th>
						<td><?php echo esc_html( (string) (int) ( $fs['rule_count'] ?? 0 ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="rwgc-card">
	<h2><?php esc_html_e( 'Merged fields', 'reactwoo-geo-commerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Key rwgc_commerce_cart_items — number of items in the cart when WooCommerce session is available.', 'reactwoo-geo-commerce' ); ?></p>
	</div>

	<div class="rwgc-card rwgc-snippet-table">
	<h2><?php esc_html_e( 'Sample payload (current request)', 'reactwoo-geo-commerce' ); ?></h2>
	<?php if ( ! empty( $sample ) && is_array( $sample ) ) : ?>
		<pre style="overflow:auto;"><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	<?php else : ?>
		<p><?php esc_html_e( 'No geo data available in this context.', 'reactwoo-geo-commerce' ); ?></p>
	<?php endif; ?>
	</div>

	<div class="rwgc-card rwgc-card--full">
	<h2><?php esc_html_e( 'Hooks', 'reactwoo-geo-commerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Visitor payload: rwgcm_geo_data (runs after cart hints). Cart / pricing: rwgcm_before_cart_totals (priority 5); rwgcm_skip_pricing_for_cart_item (bundle children). Storefront: rwgcm_apply_catalog_price; variable products use Woo variation price filters (RWGCM_Catalog_Price_Variable). Fees: rwgcm_cart_fees, rwgcm_fee_rule_rows. Shipping: rwgcm_package_rates. Coupons: coupon screen + rwgcm_coupon_allowed_for_visitor. Attribution: rwgcm_store_utm_on_orders, rwgcm_checkout_order_meta.', 'reactwoo-geo-commerce' ); ?></p>
	</div>
</div>
