<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$config               = isset( $config ) && is_array( $config ) ? $config : array( 'enabled' => false, 'rules' => array() );
$wc_countries         = isset( $wc_countries ) && is_array( $wc_countries ) ? $wc_countries : array();
$product_categories   = isset( $product_categories ) && is_array( $product_categories ) ? $product_categories : array();
$rules                = isset( $config['rules'] ) && is_array( $config['rules'] ) ? $config['rules'] : array();
$rules[]              = array(
	'country'      => '',
	'type'         => 'percent',
	'value'        => 0,
	'category_ids' => array(),
);
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-pricing';
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Commerce — pricing rules', 'reactwoo-geo-commerce' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Rules are evaluated in list order: the first row that matches visitor country and (if set) product categories wins. Put specific category rules above broad “all products” rows (leave categories empty to match all products in that country).', 'reactwoo-geo-commerce' ); ?>
	</p>
	<?php if ( class_exists( 'RWGC_Admin', false ) ) : ?>
		<?php RWGC_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pricing rules saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card rwgc-card--full">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'rwgcm_save_pricing' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_pricing" />

		<p>
			<label>
				<input type="checkbox" name="rwgcm_pricing_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable geo pricing rules', 'reactwoo-geo-commerce' ); ?>
			</label>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Country', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Product categories', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Adjustment', 'reactwoo-geo-commerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Value', 'reactwoo-geo-commerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rules as $idx => $row ) {
					$cc       = isset( $row['country'] ) ? (string) $row['country'] : '';
					$type     = isset( $row['type'] ) && 'fixed_line' === $row['type'] ? 'fixed_line' : 'percent';
					$value    = isset( $row['value'] ) ? $row['value'] : 0;
					$sel_cats = isset( $row['category_ids'] ) && is_array( $row['category_ids'] ) ? array_map( 'intval', $row['category_ids'] ) : array();
					?>
					<tr>
						<td>
							<select name="rwgcm_rule_country[]" style="min-width: 220px;">
								<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
								<?php foreach ( $wc_countries as $code => $name ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $cc, $code ); ?>><?php echo esc_html( $code . ' — ' . $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<select name="rwgcm_rule_category_ids[<?php echo esc_attr( (string) $idx ); ?>][]" multiple size="6" style="min-width: 240px;">
								<?php foreach ( $product_categories as $term ) : ?>
									<?php
									if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
										continue;
									}
									?>
									<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php echo in_array( (int) $term->term_id, $sel_cats, true ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description" style="margin:4px 0 0"><?php esc_html_e( 'None = all products in country.', 'reactwoo-geo-commerce' ); ?></p>
						</td>
						<td>
							<select name="rwgcm_rule_type[]">
								<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Percent (%)', 'reactwoo-geo-commerce' ); ?></option>
								<option value="fixed_line" <?php selected( $type, 'fixed_line' ); ?>><?php esc_html_e( 'Fixed per unit (store currency)', 'reactwoo-geo-commerce' ); ?></option>
							</select>
						</td>
						<td>
							<input type="number" name="rwgcm_rule_value[]" value="<?php echo esc_attr( (string) $value ); ?>" step="0.01" style="width:8em" />
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save rules', 'reactwoo-geo-commerce' ) ); ?>
	</form>

	<p class="description">
		<?php esc_html_e( 'Developers: filter rwgcm_adjusted_unit_price; action rwgcm_before_cart_totals runs before adjustments.', 'reactwoo-geo-commerce' ); ?>
	</p>
	</div>
</div>
