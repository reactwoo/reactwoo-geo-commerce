<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-settings';
$rwgc_settings    = admin_url( 'admin.php?page=rwgc-settings' );
$wc_settings      = admin_url( 'admin.php?page=wc-settings' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Settings', 'reactwoo-geo-commerce' ),
			__( 'Geo Commerce builds on Geo Core targeting and WooCommerce. Geo Core resolves the visitor country. Price-book currencies and checkout gateways are configured below. Tax stays in WooCommerce.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Settings', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card" style="max-width: 560px;">
		<ul class="ul-disc">
			<li><a href="<?php echo esc_url( $rwgc_settings ); ?>"><?php esc_html_e( 'ReactWoo Geo Core — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( $wc_settings ); ?>"><?php esc_html_e( 'WooCommerce — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-license' ) ); ?>"><?php esc_html_e( 'Geo Commerce — License', 'reactwoo-geo-commerce' ); ?></a></li>
		</ul>
	</div>

	<?php
	if ( class_exists( 'RWGCM_Price_Book_Admin', false ) ) {
		RWGCM_Price_Book_Admin::render_settings_card();
	}
	?>
</div>
