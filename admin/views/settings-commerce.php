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
			__( 'Geo Commerce builds on Geo Core targeting and WooCommerce. Geo Core resolves the visitor country. Price books and payment gateways have their own screens. Tax stays in WooCommerce.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Settings', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgcm-panel">
		<ul class="ul-disc">
			<li><a href="<?php echo esc_url( $rwgc_settings ); ?>"><?php esc_html_e( 'ReactWoo Geo Core — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( $wc_settings ); ?>"><?php esc_html_e( 'WooCommerce — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-license' ) ); ?>"><?php esc_html_e( 'Geo Commerce — License', 'reactwoo-geo-commerce' ); ?></a></li>
		</ul>
	</div>

	<div class="rwgc-card rwgcm-panel">
		<h2><?php esc_html_e( 'Price books and payments', 'reactwoo-geo-commerce' ); ?></h2>
		<p><?php esc_html_e( 'Currencies and checkout gateways are their own screens, not rules in the rule builder.', 'reactwoo-geo-commerce' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-price-books' ) ); ?>"><?php esc_html_e( 'Price books', 'reactwoo-geo-commerce' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-payments' ) ); ?>"><?php esc_html_e( 'Configure payment gateways', 'reactwoo-geo-commerce' ); ?></a>
		</p>
	</div>
</div>
