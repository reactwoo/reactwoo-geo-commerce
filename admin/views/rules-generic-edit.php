<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $is_new ) ) {
	$is_new = true;
}
$rule = isset( $rule ) && is_array( $rule ) ? $rule : array();

$defaults = array(
	'id'         => '0',
	'label'      => '',
	'status'     => 'active',
	'priority'   => 100,
	'scope'      => array(
		'type' => 'global',
		'ids'  => array(),
	),
	'conditions' => array(
		'match' => 'all',
		'items' => array(),
	),
	'actions'    => array(
		array(
			'type'  => 'price_adjustment',
			'mode'  => 'percent',
			'value' => 0,
		),
	),
);
$rule = array_merge( $defaults, $rule );
if ( ! isset( $rule['scope'] ) || ! is_array( $rule['scope'] ) ) {
	$rule['scope'] = $defaults['scope'];
}
if ( ! isset( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
	$rule['conditions'] = $defaults['conditions'];
}
if ( ! isset( $rule['actions'] ) || ! is_array( $rule['actions'] ) ) {
	$rule['actions'] = $defaults['actions'];
}

$pa_mode  = 'percent';
$pa_value = 0.0;
foreach ( $rule['actions'] as $a ) {
	if ( is_array( $a ) && isset( $a['type'] ) && 'price_adjustment' === $a['type'] ) {
		$pa_mode  = isset( $a['mode'] ) ? (string) $a['mode'] : 'percent';
		$pa_value = isset( $a['value'] ) ? floatval( $a['value'] ) : 0.0;
		break;
	}
}

$cond_items = isset( $rule['conditions']['items'] ) && is_array( $rule['conditions']['items'] ) ? $rule['conditions']['items'] : array();
while ( count( $cond_items ) < 8 ) {
	$cond_items[] = array(
		'target'   => '',
		'operator' => 'is',
		'value'    => '',
	);
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-pricing';
$wc_cats          = isset( $wc_cats ) && is_array( $wc_cats ) ? $wc_cats : array();
$target_defs      = isset( $target_defs ) && is_array( $target_defs ) ? $target_defs : array();
$operators        = isset( $operators ) && is_array( $operators ) ? $operators : array( 'is', 'is_not' );

$list_url = admin_url( 'admin.php?page=rwgcm-pricing' );
$form_url = admin_url( 'admin-post.php' );
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--rules-edit">
	<h1><?php echo $is_new ? esc_html__( 'Add rule', 'reactwoo-geo-commerce' ) : esc_html__( 'Edit rule', 'reactwoo-geo-commerce' ); ?></h1>
	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to rules', 'reactwoo-geo-commerce' ); ?></a></p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="rwgcm-generic-rule-form">
		<?php wp_nonce_field( 'rwgcm_save_generic_rule' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_generic_rule" />
		<input type="hidden" name="rwgcm_rule_id" value="<?php echo esc_attr( (string) ( isset( $rule['id'] ) ? $rule['id'] : '0' ) ); ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwgcm_rule_label"><?php esc_html_e( 'Label', 'reactwoo-geo-commerce' ); ?></label></th>
				<td><input name="rwgcm_rule_label" id="rwgcm_rule_label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $rule['label'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<select name="rwgcm_rule_status" id="rwgcm_rule_status">
						<option value="active" <?php selected( $rule['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geo-commerce' ); ?></option>
						<option value="draft" <?php selected( $rule['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geo-commerce' ); ?></option>
						<option value="disabled" <?php selected( $rule['status'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgcm_rule_priority"><?php esc_html_e( 'Priority', 'reactwoo-geo-commerce' ); ?></label></th>
				<td><input name="rwgcm_rule_priority" id="rwgcm_rule_priority" type="number" min="0" max="999999" step="1" value="<?php echo esc_attr( (string) (int) $rule['priority'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Scope', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<select name="rwgcm_scope_type" id="rwgcm_scope_type">
						<option value="global" <?php selected( $rule['scope']['type'], 'global' ); ?>><?php esc_html_e( 'Global (all products)', 'reactwoo-geo-commerce' ); ?></option>
						<option value="product_category" <?php selected( $rule['scope']['type'], 'product_category' ); ?>><?php esc_html_e( 'Product categories', 'reactwoo-geo-commerce' ); ?></option>
						<option value="product" <?php selected( $rule['scope']['type'], 'product' ); ?>><?php esc_html_e( 'Single product', 'reactwoo-geo-commerce' ); ?></option>
						<option value="cart" <?php selected( $rule['scope']['type'], 'cart' ); ?>><?php esc_html_e( 'Cart (whole order)', 'reactwoo-geo-commerce' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Narrower scopes beat global when multiple rules match.', 'reactwoo-geo-commerce' ); ?></p>
				</td>
			</tr>
			<tr class="rwgcm-scope-cats" style="<?php echo 'product_category' === $rule['scope']['type'] ? '' : 'display:none;'; ?>">
				<th scope="row"><?php esc_html_e( 'Categories', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<select name="rwgcm_scope_cats[]" multiple size="8" style="min-width:280px;">
						<?php foreach ( $wc_cats as $term ) : ?>
							<?php
							if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
								continue;
							}
							$sel = in_array( (int) $term->term_id, array_map( 'intval', $rule['scope']['ids'] ), true );
							?>
							<option value="<?php echo esc_attr( (string) (int) $term->term_id ); ?>" <?php selected( $sel ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr class="rwgcm-scope-product" style="<?php echo 'product' === $rule['scope']['type'] ? '' : 'display:none;'; ?>">
				<th scope="row"><label for="rwgcm_scope_product"><?php esc_html_e( 'Product ID', 'reactwoo-geo-commerce' ); ?></label></th>
				<td>
					<input name="rwgcm_scope_product" id="rwgcm_scope_product" type="number" min="1" class="small-text"
						value="<?php echo esc_attr( ! empty( $rule['scope']['ids'][0] ) ? (string) (int) $rule['scope']['ids'][0] : '' ); ?>" />
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Conditions', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'All rows with a target key apply. Uses Geo Core context (see Geo Core → Target types).', 'reactwoo-geo-commerce' ); ?></p>
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
						<label>
							<input type="radio" name="rwgcm_conditions_match" value="all" <?php checked( isset( $rule['conditions']['match'] ) ? $rule['conditions']['match'] : 'all', 'all' ); ?> />
							<?php esc_html_e( 'Match all conditions', 'reactwoo-geo-commerce' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="radio" name="rwgcm_conditions_match" value="any" <?php checked( isset( $rule['conditions']['match'] ) ? $rule['conditions']['match'] : 'all', 'any' ); ?> />
							<?php esc_html_e( 'Match any condition', 'reactwoo-geo-commerce' ); ?>
						</label>
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
						<select name="rwgcm_cond_target[]" class="rwgcm-cond-target">
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
						<select name="rwgcm_cond_operator[]">
							<?php foreach ( $operators as $op ) : ?>
								<option value="<?php echo esc_attr( (string) $op ); ?>" <?php selected( $row['operator'], (string) $op ); ?>><?php echo esc_html( (string) $op ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td><input type="text" name="rwgcm_cond_value[]" class="regular-text" value="<?php echo esc_attr( (string) $row['value'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. DE or mobile', 'reactwoo-geo-commerce' ); ?>" /></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Price adjustment', 'reactwoo-geo-commerce' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', 'reactwoo-geo-commerce' ); ?></th>
				<td>
					<select name="rwgcm_pa_mode" id="rwgcm_pa_mode">
						<option value="percent" <?php selected( $pa_mode, 'percent' ); ?>><?php esc_html_e( 'Percent', 'reactwoo-geo-commerce' ); ?></option>
						<option value="fixed_line" <?php selected( $pa_mode, 'fixed_line' ); ?>><?php esc_html_e( 'Fixed amount (per unit)', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgcm_pa_value"><?php esc_html_e( 'Value', 'reactwoo-geo-commerce' ); ?></label></th>
				<td><input name="rwgcm_pa_value" id="rwgcm_pa_value" type="text" inputmode="decimal" value="<?php echo esc_attr( (string) $pa_value ); ?>" /></td>
			</tr>
		</table>

		<?php submit_button( $is_new ? __( 'Create rule', 'reactwoo-geo-commerce' ) : __( 'Update rule', 'reactwoo-geo-commerce' ) ); ?>
	</form>

	<script>
	(function(){
		var st = document.getElementById('rwgcm_scope_type');
		if (!st) return;
		function syncScope(){
			var v = st.value;
			var trc = document.querySelector('.rwgcm-scope-cats');
			var trp = document.querySelector('.rwgcm-scope-product');
			if (trc) trc.style.display = (v === 'product_category') ? '' : 'none';
			if (trp) trp.style.display = (v === 'product') ? '' : 'none';
		}
		st.addEventListener('change', syncScope);
		syncScope();
	})();
	</script>
</div>
