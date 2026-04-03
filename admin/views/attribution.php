<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-attribution';
$orders           = isset( $rwgcm_attr_orders ) && is_array( $rwgcm_attr_orders ) ? $rwgcm_attr_orders : array();
$utm_on           = class_exists( 'RWGCM_Attribution', false ) && RWGCM_Attribution::is_storage_enabled();
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--attribution">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Attribution', 'reactwoo-geo-commerce' ),
			__( 'Visitor country on orders and optional UTM / click-id capture for reporting.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Attribution', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 560px;">
			<h2><?php esc_html_e( 'UTM &amp; click IDs on orders', 'reactwoo-geo-commerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'When enabled, first-touch and last-touch marketing parameters are stored in cookies and copied into order meta at checkout.', 'reactwoo-geo-commerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rwgcm_save_dashboard' ); ?>
				<input type="hidden" name="action" value="rwgcm_save_dashboard" />
				<input type="hidden" name="rwgcm_return" value="attribution" />
				<p>
					<label>
						<input type="checkbox" name="rwgcm_store_utm" value="1" <?php checked( get_option( RWGCM_Attribution::OPTION_STORE_UTM, 'yes' ), 'yes' ); ?> />
						<?php esc_html_e( 'Store UTM and click-id parameters on orders', 'reactwoo-geo-commerce' ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Save', 'reactwoo-geo-commerce' ), 'primary', 'submit', false ); ?>
			</form>
			<p>
				<?php echo $utm_on ? esc_html__( 'Status: storage is on.', 'reactwoo-geo-commerce' ) : esc_html__( 'Status: storage is off — orders will not receive new attribution meta from cookies.', 'reactwoo-geo-commerce' ); ?>
			</p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Recent orders with visitor country', 'reactwoo-geo-commerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Geo Commerce records the visitor’s country at checkout (order meta).', 'reactwoo-geo-commerce' ); ?></p>
			<?php if ( empty( $orders ) ) : ?>
				<p><?php esc_html_e( 'No recent orders with a stored visitor country yet.', 'reactwoo-geo-commerce' ); ?></p>
			<?php else : ?>
				<table class="widefat striped rwgcm-table-comfortable">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'reactwoo-geo-commerce' ); ?></th>
							<th><?php esc_html_e( 'Country', 'reactwoo-geo-commerce' ); ?></th>
							<th><?php esc_html_e( 'Date', 'reactwoo-geo-commerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $o ) : ?>
							<?php
							if ( ! is_a( $o, 'WC_Order' ) ) {
								continue;
							}
							$oid = $o->get_id();
							$cc  = $o->get_meta( RWGCM_Order_Geo::META_COUNTRY, true );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( (string) $oid ); ?></a>
								</td>
								<td><code><?php echo esc_html( is_string( $cc ) ? strtoupper( $cc ) : '—' ); ?></code></td>
								<td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><?php esc_html_e( 'All orders', 'reactwoo-geo-commerce' ); ?></a>
			</p>
		</div>
	</div>
</div>
