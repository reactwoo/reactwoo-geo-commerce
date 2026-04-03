<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$config       = isset( $config ) && is_array( $config ) ? $config : array( 'enabled' => false, 'rules' => array() );
$wc_countries = isset( $wc_countries ) && is_array( $wc_countries ) ? $wc_countries : array();
$rules        = isset( $config['rules'] ) && is_array( $config['rules'] ) ? $config['rules'] : array();
$rules[]            = array(
	'country'   => '',
	'name'      => '',
	'amount'    => 0,
	'taxable'   => false,
	'tax_class' => '',
);
$tax_class_options = isset( $tax_class_options ) && is_array( $tax_class_options ) ? $tax_class_options : array( '' => __( 'Standard', 'woocommerce' ) );
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-fees';
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Commerce — cart fees', 'reactwoo-geo-commerce' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Adds WooCommerce cart fees when the visitor’s country (Geo Core) matches a row. All matching rows for that country are applied. Use negative amounts for credits if your tax settings allow. For taxable fees, pick the WooCommerce tax class so fee tax matches your rates.', 'reactwoo-geo-commerce' ); ?>
	</p>
	<?php if ( class_exists( 'RWGC_Admin', false ) ) : ?>
		<?php RWGC_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fee rules saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card rwgc-card--full">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'rwgcm_save_fees' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_fees" />

		<p>
			<label>
				<input type="checkbox" name="rwgcm_fees_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable geo fee rules', 'reactwoo-geo-commerce' ); ?>
			</label>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Country', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fee name', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Amount', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Taxable', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tax class', 'reactwoo-geo-commerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rules as $idx => $row ) : ?>
					<?php
					$cc    = isset( $row['country'] ) ? (string) $row['country'] : '';
					$name  = isset( $row['name'] ) ? (string) $row['name'] : '';
					$amt   = isset( $row['amount'] ) ? $row['amount'] : 0;
					$tax   = ! empty( $row['taxable'] );
					$tclass = isset( $row['tax_class'] ) ? (string) $row['tax_class'] : '';
					?>
					<tr>
						<td>
							<select name="rwgcm_fee_country[]" style="min-width: 220px;">
								<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
								<?php foreach ( $wc_countries as $code => $cname ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $cc, $code ); ?>><?php echo esc_html( $code . ' — ' . $cname ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input type="text" name="rwgcm_fee_name[]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Regional handling', 'reactwoo-geo-commerce' ); ?>" />
						</td>
						<td>
							<input type="text" name="rwgcm_fee_amount[]" value="<?php echo esc_attr( is_numeric( $amt ) ? (string) $amt : '' ); ?>" class="small-text" />
						</td>
						<td>
							<label><input type="checkbox" name="rwgcm_fee_taxable[<?php echo esc_attr( (string) (int) $idx ); ?>]" value="1" <?php checked( $tax ); ?> /></label>
						</td>
						<td>
							<select name="rwgcm_fee_tax_class[]" style="min-width: 140px;">
								<?php foreach ( $tax_class_options as $slug => $tlabel ) : ?>
									<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $tclass, (string) $slug ); ?>><?php echo esc_html( (string) $tlabel ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save fee rules', 'reactwoo-geo-commerce' ) ); ?>
	</form>
	</div>
</div>
