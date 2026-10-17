<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current         = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-payments';
$rwgcm_use_platform_shell = class_exists( 'RWGCM_Admin', false ) && RWGCM_Admin::uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--payments">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Payments by country', 'reactwoo-geo-commerce' ),
			__( 'Choose the gateways offered for each billing country, and the currencies a gateway is allowed to charge. This is not a rule in the rule builder.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Payments by country', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>
	<?php if ( ! $rwgcm_use_platform_shell ) : ?>
		<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>
	<?php
	if ( class_exists( 'RWGCM_Price_Book_Admin', false ) ) {
		RWGCM_Price_Book_Admin::render_payments_form();
	}
	?>
</div>
