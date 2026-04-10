<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-help';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap">
	<h1><?php esc_html_e( 'Geo Commerce — help', 'reactwoo-geo-commerce' ); ?></h1>
	<p class="description"><?php esc_html_e( 'What belongs where, and how store pricing fits together.', 'reactwoo-geo-commerce' ); ?></p>
	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'Two different “licenses”', 'reactwoo-geo-commerce' ); ?></h2>
		<ul class="rwgc-docs-list">
			<li>
				<strong><?php esc_html_e( 'MaxMind (GeoLite2) license key', 'reactwoo-geo-commerce' ); ?></strong>
				— <?php esc_html_e( 'Used only by Geo Core to download the IP-to-country database. Configure it under Geo Core → Settings. It is not your WooCommerce or ReactWoo product key.', 'reactwoo-geo-commerce' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'ReactWoo commercial product keys', 'reactwoo-geo-commerce' ); ?></strong>
				— <?php esc_html_e( 'Some ReactWoo add-ons (for example Geo AI) ask for an API base and product license on their own screens. Geo Commerce pricing and fee rules in this plugin work from Geo Core visitor country data and do not use the Geo Core settings form for product licensing.', 'reactwoo-geo-commerce' ); ?>
			</li>
		</ul>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'What to configure first', 'reactwoo-geo-commerce' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'In Geo Core: confirm the MaxMind database is present (Tools) so visitor country is reliable.', 'reactwoo-geo-commerce' ); ?></li>
				<li><?php esc_html_e( 'In WooCommerce: set currencies, tax, and shipping as you normally would.', 'reactwoo-geo-commerce' ); ?></li>
				<li><?php esc_html_e( 'In Geo Commerce → Pricing rules: add country rows (and optional product categories), then enable the rule set.', 'reactwoo-geo-commerce' ); ?></li>
				<li><?php esc_html_e( 'In Geo Commerce → Cart fees: optional fixed or percentage-style fees per country.', 'reactwoo-geo-commerce' ); ?></li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-dashboard' ) ); ?>" class="button"><?php esc_html_e( 'Geo Core dashboard', 'reactwoo-geo-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings' ) ); ?>" class="button"><?php esc_html_e( 'WooCommerce settings', 'reactwoo-geo-commerce' ); ?></a>
			</p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Words merchants use', 'reactwoo-geo-commerce' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Pricing rules', 'reactwoo-geo-commerce' ); ?></strong> — <?php esc_html_e( 'Adjust catalog prices (e.g. percentage markup) when the shopper’s country matches a row.', 'reactwoo-geo-commerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Cart fees', 'reactwoo-geo-commerce' ); ?></strong> — <?php esc_html_e( 'Extra line items on the cart/checkout for matching countries (e.g. handling or eco fee).', 'reactwoo-geo-commerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Attribution (overview)', 'reactwoo-geo-commerce' ); ?></strong> — <?php esc_html_e( 'Optional: store UTM / click IDs on orders for reporting.', 'reactwoo-geo-commerce' ); ?></li>
			</ul>
		</div>
	</div>
</div>
