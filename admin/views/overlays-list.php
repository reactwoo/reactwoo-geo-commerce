<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$overlays         = isset( $overlays ) && is_array( $overlays ) ? $overlays : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-product-overlays';
$new_url          = admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_overlay_edit=new' );
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--overlays">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Product overlays', 'reactwoo-geo-commerce' ),
			__( 'Contextual title, descriptions, gallery, badge, and CTA on the canonical WooCommerce product. Inventory, SKU, and orders stay on one product ID.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Product overlays', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Overlay saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php elseif ( isset( $_GET['updated'] ) && '0' === $_GET['updated'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not save overlay.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Overlay deleted.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgcm_error'] ) && 'notfound' === $_GET['rwgcm_error'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Overlay not found.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php elseif ( ! empty( $_GET['rwgcm_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Invalid product or missing data.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add overlay', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Product', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Label', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Manage', 'reactwoo-geo-commerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $overlays ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No overlays yet.', 'reactwoo-geo-commerce' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $overlays as $o ) : ?>
				<?php
				if ( ! is_array( $o ) ) {
					continue;
				}
				$oid = isset( $o['id'] ) ? (int) $o['id'] : 0;
				$pid = isset( $o['product_id'] ) ? (int) $o['product_id'] : 0;
				$pt  = $pid > 0 ? get_the_title( $pid ) : '';
				if ( '' === $pt ) {
					$pt = '—';
				}
				$edit_url = admin_url( 'admin.php?page=rwgcm-product-overlays&rwgcm_overlay_edit=' . $oid );
				$del_url  = wp_nonce_url(
					admin_url( 'admin-post.php?action=rwgcm_delete_overlay&overlay_id=' . $oid ),
					'rwgcm_delete_overlay_' . $oid
				);
				?>
				<tr>
					<td><?php echo esc_html( (string) $oid ); ?></td>
					<td>
						<?php if ( $pid > 0 ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php echo esc_html( $pt ); ?></a>
							<code>#<?php echo esc_html( (string) $pid ); ?></code>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( isset( $o['label'] ) ? (string) $o['label'] : '' ); ?></a></td>
					<td><?php echo esc_html( isset( $o['status'] ) ? (string) $o['status'] : '' ); ?></td>
					<td><?php echo esc_html( (string) ( isset( $o['priority'] ) ? (int) $o['priority'] : 0 ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-commerce' ); ?></a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this overlay?', 'reactwoo-geo-commerce' ) ); ?>');"><?php esc_html_e( 'Delete', 'reactwoo-geo-commerce' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
