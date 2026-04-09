<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-product-overlays';
?>
<div class="wrap rwgc-wrap rwgcm-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Product overlays', 'reactwoo-geo-commerce' ),
			__( 'Contextual title, description, gallery, and badges on the canonical WooCommerce product (same SKU, inventory, reporting).', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Product overlays', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card" style="max-width: 720px;">
		<p class="description">
			<?php esc_html_e( 'Overlay rows are stored in the custom table wp_rwgcm_product_overlays. Management UI and storefront application hooks ship in upcoming releases; targeting conditions use Geo Core’s shared vocabulary.', 'reactwoo-geo-commerce' ); ?>
		</p>
		<?php if ( class_exists( 'RWGCM_DB', false ) && RWGCM_DB::overlays_table_exists() ) : ?>
			<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a;" aria-hidden="true"></span> <?php esc_html_e( 'Database table is installed.', 'reactwoo-geo-commerce' ); ?></p>
		<?php else : ?>
			<p><span class="dashicons dashicons-warning" style="color:#dba617;" aria-hidden="true"></span> <?php esc_html_e( 'Activate the plugin or load wp-admin once to run the database installer.', 'reactwoo-geo-commerce' ); ?></p>
		<?php endif; ?>
	</div>
</div>
