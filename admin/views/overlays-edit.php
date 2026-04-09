<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $is_new ) ) {
	$is_new = true;
}
$overlay = isset( $overlay ) && is_array( $overlay ) ? $overlay : array();

$defaults = array(
	'id'         => '0',
	'product_id' => 0,
	'label'      => '',
	'status'     => 'active',
	'priority'   => 100,
	'conditions' => array(
		'match' => 'all',
		'items' => array(),
	),
	'overrides'  => array(),
);
$overlay = array_merge( $defaults, $overlay );

$cond_items = isset( $overlay['conditions']['items'] ) && is_array( $overlay['conditions']['items'] ) ? $overlay['conditions']['items'] : array();
while ( count( $cond_items ) < 8 ) {
	$cond_items[] = array(
		'target'   => '',
		'operator' => 'is',
		'value'    => '',
	);
}

$ov = isset( $overlay['overrides'] ) && is_array( $overlay['overrides'] ) ? $overlay['overrides'] : array();

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-product-overlays';
$target_defs      = isset( $target_defs ) && is_array( $target_defs ) ? $target_defs : array();
$operators        = isset( $operators ) && is_array( $operators ) ? $operators : array( 'is', 'is_not' );

$list_url = admin_url( 'admin.php?page=rwgcm-product-overlays' );
$form_url = admin_url( 'admin-post.php' );
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--overlays-edit">
	<h1><?php echo $is_new ? esc_html__( 'Add product overlay', 'reactwoo-geo-commerce' ) : esc_html__( 'Edit product overlay', 'reactwoo-geo-commerce' ); ?></h1>
	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Overlay saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to overlays', 'reactwoo-geo-commerce' ); ?></a></p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'rwgcm_save_overlay' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_overlay" />
		<input type="hidden" name="rwgcm_overlay_id" value="<?php echo esc_attr( (string) $overlay['id'] ); ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwgcm_overlay_product_id"><?php esc_html_e( 'Product ID', 'reactwoo-geo-commerce' ); ?></label></th>
				<td>
					<input name="rwgcm_overlay_product_id" id="rwgcm_overlay_product_id" type="number" min="1" class="small-text" required
						value="<?php echo esc_attr( (string) (int) $overlay['product_id'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Canonical WooCommerce product ID (use the parent for variable products).', 'reactwoo-geo-commerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgcm_overlay_label"><?php esc_html_e( 'Label', 'reactwoo-geo-commerce' ); ?></label></th>
				<td><input name="rwgcm_overlay_label" id="rwgcm_overlay_label" type="text" class="regular-text" value="<?php echo esc_attr( (string) $overlay['label'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<select name="rwgcm_overlay_status" id="rwgcm_overlay_status">
						<option value="active" <?php selected( $overlay['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geo-commerce' ); ?></option>
						<option value="draft" <?php selected( $overlay['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geo-commerce' ); ?></option>
						<option value="disabled" <?php selected( $overlay['status'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgcm_overlay_priority"><?php esc_html_e( 'Priority', 'reactwoo-geo-commerce' ); ?></label></th>
				<td><input name="rwgcm_overlay_priority" id="rwgcm_overlay_priority" type="number" min="0" max="999999" value="<?php echo esc_attr( (string) (int) $overlay['priority'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Conditions', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'When these match the current Geo Core context, this overlay applies. Empty rows are ignored.', 'reactwoo-geo-commerce' ); ?></p>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Target', 'reactwoo-geo-commerce' ); ?></th>
					<th><?php esc_html_e( 'Operator', 'reactwoo-geo-commerce' ); ?></th>
					<th><?php esc_html_e( 'Value', 'reactwoo-geo-commerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td colspan="3">
						<label><input type="radio" name="rwgcm_overlay_conditions_match" value="all" <?php checked( isset( $overlay['conditions']['match'] ) ? $overlay['conditions']['match'] : 'all', 'all' ); ?> /> <?php esc_html_e( 'Match all', 'reactwoo-geo-commerce' ); ?></label>
						&nbsp;&nbsp;
						<label><input type="radio" name="rwgcm_overlay_conditions_match" value="any" <?php checked( isset( $overlay['conditions']['match'] ) ? $overlay['conditions']['match'] : 'all', 'any' ); ?> /> <?php esc_html_e( 'Match any', 'reactwoo-geo-commerce' ); ?></label>
					</td>
				</tr>
				<?php
				foreach ( $cond_items as $row ) :
					if ( ! is_array( $row ) ) {
						$row = array();
					}
					$row = array_merge(
						array(
							'target'   => '',
							'operator' => 'is',
							'value'    => '',
						),
						$row
					);
					?>
				<tr>
					<td>
						<select name="rwgcm_overlay_cond_target[]">
							<option value=""><?php esc_html_e( '(optional)', 'reactwoo-geo-commerce' ); ?></option>
							<?php foreach ( $target_defs as $def ) : ?>
								<?php
								if ( ! is_array( $def ) || empty( $def['key'] ) ) {
									continue;
								}
								$k = (string) $def['key'];
								?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $row['target'], $k ); ?>><?php echo esc_html( isset( $def['label'] ) ? (string) $def['label'] : $k ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<select name="rwgcm_overlay_cond_operator[]">
							<?php foreach ( $operators as $op ) : ?>
								<option value="<?php echo esc_attr( (string) $op ); ?>" <?php selected( $row['operator'], (string) $op ); ?>><?php echo esc_html( (string) $op ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td><input type="text" name="rwgcm_overlay_cond_value[]" class="regular-text" value="<?php echo esc_attr( (string) $row['value'] ); ?>" /></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Display overrides', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enable only the fields you want to replace for matching visitors.', 'reactwoo-geo-commerce' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Title', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_title_enabled" value="1" <?php checked( ! empty( $ov['title']['enabled'] ) ); ?> /> <?php esc_html_e( 'Override', 'reactwoo-geo-commerce' ); ?></label>
					<input type="text" name="rwgcm_ov_title" class="large-text" value="<?php echo esc_attr( isset( $ov['title']['value'] ) ? (string) $ov['title']['value'] : '' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Short description', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_short_enabled" value="1" <?php checked( ! empty( $ov['short_description']['enabled'] ) ); ?> /> <?php esc_html_e( 'Override', 'reactwoo-geo-commerce' ); ?></label>
					<textarea name="rwgcm_ov_short" rows="4" class="large-text"><?php echo esc_textarea( isset( $ov['short_description']['value'] ) ? (string) $ov['short_description']['value'] : '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Description', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_desc_enabled" value="1" <?php checked( ! empty( $ov['description']['enabled'] ) ); ?> /> <?php esc_html_e( 'Override', 'reactwoo-geo-commerce' ); ?></label>
					<textarea name="rwgcm_ov_desc" rows="8" class="large-text"><?php echo esc_textarea( isset( $ov['description']['value'] ) ? (string) $ov['description']['value'] : '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Gallery image IDs', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_gallery_enabled" value="1" <?php checked( ! empty( $ov['gallery']['enabled'] ) ); ?> /> <?php esc_html_e( 'Override', 'reactwoo-geo-commerce' ); ?></label>
					<?php
					$gal_disp = '';
					if ( ! empty( $ov['gallery']['value'] ) ) {
						$gv = $ov['gallery']['value'];
						$gal_disp = is_array( $gv ) ? implode( ',', array_map( 'strval', $gv ) ) : (string) $gv;
					}
					?>
					<input type="text" name="rwgcm_ov_gallery" class="large-text" placeholder="<?php esc_attr_e( 'Comma-separated attachment IDs', 'reactwoo-geo-commerce' ); ?>" value="<?php echo esc_attr( $gal_disp ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Badge', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_badge_enabled" value="1" <?php checked( ! empty( $ov['badge']['enabled'] ) ); ?> /> <?php esc_html_e( 'Show badge', 'reactwoo-geo-commerce' ); ?></label>
					<input type="text" name="rwgcm_ov_badge" class="large-text" value="<?php echo esc_attr( isset( $ov['badge']['value'] ) ? (string) $ov['badge']['value'] : '' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'CTA', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="rwgcm_ov_cta_enabled" value="1" <?php checked( ! empty( $ov['cta']['enabled'] ) ); ?> /> <?php esc_html_e( 'After add to cart', 'reactwoo-geo-commerce' ); ?></label>
					<textarea name="rwgcm_ov_cta" rows="3" class="large-text"><?php echo esc_textarea( isset( $ov['cta']['value'] ) ? (string) $ov['cta']['value'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Small HTML allowed (links, emphasis).', 'reactwoo-geo-commerce' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_new ? __( 'Create overlay', 'reactwoo-geo-commerce' ) : __( 'Update overlay', 'reactwoo-geo-commerce' ) ); ?>
	</form>
</div>
