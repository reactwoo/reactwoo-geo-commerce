<?php
/**
 * Commerce availability entry.
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
			__( 'Availability', 'reactwoo-geo-commerce' ),
			__( 'Control geo-based product visibility and purchase eligibility.', 'reactwoo-geo-commerce' )
		);
	}
	?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Availability outcomes are driven by shared Geo Core targeting rules. Use commerce rules and overlays to hide, show, or adjust products for qualifying visitors.', 'reactwoo-geo-commerce' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-visibility-rules' ) ); ?>">
				<?php esc_html_e( 'Targeting rules', 'reactwoo-geo-commerce' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-product-overlays' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Product overlays', 'reactwoo-geo-commerce' ); ?>
			</a>
		</p>
	</div>
</div>
