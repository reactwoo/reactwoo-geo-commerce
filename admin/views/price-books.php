<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current         = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-price-books';
$rwgcm_use_platform_shell = class_exists( 'RWGCM_Admin', false ) && RWGCM_Admin::uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--price-books">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Price books', 'reactwoo-geo-commerce' ),
			__( 'Map each country to a currency. Fixed prices for those currencies are entered on each product. This is not a rule in the rule builder.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Price books', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>
	<?php if ( ! $rwgcm_use_platform_shell ) : ?>
		<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>
	<?php
	if ( class_exists( 'RWGCM_Price_Book_Admin', false ) ) {
		RWGCM_Price_Book_Admin::render_price_books_form();
	}
	?>
</div>
