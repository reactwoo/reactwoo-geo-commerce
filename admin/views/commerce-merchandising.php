<?php
/**
 * Commerce merchandising entry.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_page_header(
			__( 'Merchandising', 'reactwoo-geo-commerce' ),
			__( 'Geo-based product messaging, overlays, and contextual merchandising outcomes.', 'reactwoo-geo-commerce' )
		);
	}
	?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Use product overlays and pricing rules together to tailor how products are presented by visitor location and campaign context.', 'reactwoo-geo-commerce' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-product-overlays' ) ); ?>">
				<?php esc_html_e( 'Product overlays', 'reactwoo-geo-commerce' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Pricing rules', 'reactwoo-geo-commerce' ); ?>
			</a>
		</p>
	</div>
</div>
