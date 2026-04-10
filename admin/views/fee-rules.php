<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$config       = isset( $config ) && is_array( $config ) ? $config : array( 'enabled' => false, 'rules' => array() );
$wc_countries = isset( $wc_countries ) && is_array( $wc_countries ) ? $wc_countries : array();
$rules        = isset( $config['rules'] ) && is_array( $config['rules'] ) ? $config['rules'] : array();
$rules[]      = array(
	'country'   => '',
	'name'      => '',
	'amount'    => 0,
	'taxable'   => false,
	'tax_class' => '',
	'active'    => true,
);
$tax_class_options = isset( $tax_class_options ) && is_array( $tax_class_options ) ? $tax_class_options : array( '' => __( 'Standard', 'woocommerce' ) );
$rwgc_nav_current  = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-fees';

$fee_sim_country = isset( $_GET['rwgcm_sim_fee_country'] ) ? sanitize_text_field( wp_unslash( $_GET['rwgcm_sim_fee_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$fee_sim_rows    = array();
if ( '' !== $fee_sim_country && class_exists( 'RWGCM_Simulator', false ) ) {
	$fee_sim_rows = RWGCM_Simulator::fee_rows_for_country( $fee_sim_country );
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--fees">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Cart fee rules', 'reactwoo-geo-commerce' ),
			__( 'Fees apply to the cart when the visitor’s country matches. All enabled rows for that country stack — order only matters for your own clarity.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Commerce — cart fees', 'reactwoo-geo-commerce' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Country-based cart fees from Geo Core visitor context.', 'reactwoo-geo-commerce' ); ?></p>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fee rules saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p class="rwgcm-precedence-note">
		<span class="dashicons dashicons-info"></span>
		<?php esc_html_e( 'All enabled fee rows for a country apply together. Use clear labels so customers understand each line on the checkout.', 'reactwoo-geo-commerce' ); ?>
	</p>

	<div class="rwgcm-builder-layout">
		<div class="rwgcm-builder-layout__main">
			<div class="rwgc-card rwgc-card--full">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rwgcm-fee-form">
					<?php wp_nonce_field( 'rwgcm_save_fees' ); ?>
					<input type="hidden" name="action" value="rwgcm_save_fees" />

					<p>
						<label>
							<input type="checkbox" name="rwgcm_fees_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable geo fee rules', 'reactwoo-geo-commerce' ); ?>
						</label>
					</p>

					<div class="rwgcm-rule-stack" id="rwgcm-fee-rule-stack">
						<?php
						foreach ( $rules as $idx => $row ) {
							$cc    = isset( $row['country'] ) ? (string) $row['country'] : '';
							$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
							$amt   = isset( $row['amount'] ) ? $row['amount'] : 0;
							$tax   = ! empty( $row['taxable'] );
							$tclass = isset( $row['tax_class'] ) ? (string) $row['tax_class'] : '';
							$active = ! isset( $row['active'] ) || ! empty( $row['active'] );
							$summary = '';
							if ( class_exists( 'RWGCM_Simulator', false ) && '' !== $cc && '' !== $name ) {
								$summary = RWGCM_Simulator::summarize_fee_rule( $row );
							}
							include RWGCM_PATH . 'admin/views/partials/fee-rule-card.php';
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="rwgcm-fee-add-card"><?php esc_html_e( 'Add fee rule', 'reactwoo-geo-commerce' ); ?></button>
					</p>

					<?php submit_button( __( 'Save fee rules', 'reactwoo-geo-commerce' ) ); ?>
				</form>
			</div>
		</div>

		<aside class="rwgcm-builder-layout__aside">
			<div class="rwgc-card rwgcm-sim-panel">
				<h2><?php esc_html_e( 'Preview fees for country', 'reactwoo-geo-commerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'See which fee lines would apply for a visitor from the selected country.', 'reactwoo-geo-commerce' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwgcm-sim-form">
					<input type="hidden" name="page" value="rwgcm-fees" />
					<p>
						<label for="rwgcm_sim_fee_country"><?php esc_html_e( 'Country', 'reactwoo-geo-commerce' ); ?></label><br />
						<select name="rwgcm_sim_fee_country" id="rwgcm_sim_fee_country" style="min-width: 220px;">
							<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
							<?php foreach ( $wc_countries as $code => $cname ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $fee_sim_country, $code ); ?>><?php echo esc_html( $code . ' — ' . $cname ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<?php submit_button( __( 'Preview', 'reactwoo-geo-commerce' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( '' !== $fee_sim_country ) : ?>
					<div class="rwgcm-sim-result">
						<?php if ( empty( $fee_sim_rows ) ) : ?>
							<p><?php esc_html_e( 'No matching fee rules for this country (or rules disabled).', 'reactwoo-geo-commerce' ); ?></p>
						<?php else : ?>
							<ul class="rwgcm-fee-sim-list">
								<?php foreach ( $fee_sim_rows as $fr ) : ?>
									<li>
										<strong><?php echo esc_html( isset( $fr['name'] ) ? (string) $fr['name'] : '' ); ?></strong>
										— <?php echo esc_html( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( isset( $fr['amount'] ) ? (float) $fr['amount'] : 0, wc_get_price_decimals() ) : (string) ( isset( $fr['amount'] ) ? (float) $fr['amount'] : 0 ) ); ?>
										<?php echo ! empty( $fr['taxable'] ) ? esc_html( ' (' . __( 'taxable', 'reactwoo-geo-commerce' ) . ')' ) : ''; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</aside>
	</div>
</div>
