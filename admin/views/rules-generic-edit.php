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
if ( ! isset( $rule['actions'] ) || ! is_array( $rule['actions'] ) || empty( $rule['actions'] ) ) {
	$rule['actions'] = $defaults['actions'];
}

$cond_items = isset( $rule['conditions']['items'] ) && is_array( $rule['conditions']['items'] ) ? $rule['conditions']['items'] : array();
if ( empty( $cond_items ) ) {
	$cond_items[] = array(
		'field'    => '',
		'target'   => '',
		'operator' => 'is',
		'value'    => '',
		'label'    => '',
	);
}

$rwgc_nav_current         = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-pricing';
$wc_cats                  = isset( $wc_cats ) && is_array( $wc_cats ) ? $wc_cats : array();
$condition_fields         = isset( $condition_fields ) && is_array( $condition_fields ) ? $condition_fields : array();
$condition_groups         = isset( $condition_groups ) && is_array( $condition_groups ) ? $condition_groups : array();
$operator_labels          = isset( $operator_labels ) && is_array( $operator_labels ) ? $operator_labels : array();
$value_sources            = isset( $value_sources ) && is_array( $value_sources ) ? $value_sources : array();
$action_options           = isset( $action_options ) && is_array( $action_options ) ? $action_options : array();
$list_url                 = admin_url( 'admin.php?page=rwgcm-pricing' );
$form_url                 = admin_url( 'admin-post.php' );
$rwgcm_use_platform_shell = class_exists( 'RWGCM_Admin', false ) && RWGCM_Admin::uses_platform_shell();
$rule_meta                = isset( $rule['meta'] ) && is_array( $rule['meta'] ) ? $rule['meta'] : array();
$rwgcm_use_portable       = ! empty( $rule_meta['use_portable_targeting'] );
$rwgcm_portable_raw       = isset( $rule_meta['portable_targeting'] ) ? (string) $rule_meta['portable_targeting'] : '';
if ( '' !== trim( $rwgcm_portable_raw ) && class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
	$decoded = json_decode( $rwgcm_portable_raw, true );
	if ( is_array( $decoded ) ) {
		$rwgcm_portable_raw = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
$summary_text = class_exists( 'RWGCM_Rule_Summary', false ) ? RWGCM_Rule_Summary::summarize_rule( $rule ) : '';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--rules-edit">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$is_new ? __( 'Add rule', 'reactwoo-geo-commerce' ) : __( 'Edit rule', 'reactwoo-geo-commerce' ),
			__( 'Rules combine conditions (WHEN) and actions (THEN). Pricing, badges, notices, and overlays are all actions in one builder.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php echo $is_new ? esc_html__( 'Add rule', 'reactwoo-geo-commerce' ) : esc_html__( 'Edit rule', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>
	<?php if ( ! $rwgcm_use_platform_shell ) : ?>
		<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All rules', 'reactwoo-geo-commerce' ); ?></a></p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="rwgcm-generic-rule-form rwgcm-rule-builder-form">
		<?php wp_nonce_field( 'rwgcm_save_generic_rule' ); ?>
		<input type="hidden" name="action" value="rwgcm_save_generic_rule" />
		<input type="hidden" name="rwgcm_rule_id" value="<?php echo esc_attr( (string) ( isset( $rule['id'] ) ? $rule['id'] : '0' ) ); ?>" />

		<div class="rwgcm-rule-steps">
			<section class="rwgcm-rule-step">
				<h2><?php esc_html_e( 'Step 1 — Name the rule', 'reactwoo-geo-commerce' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwgcm_rule_label"><?php esc_html_e( 'Rule name', 'reactwoo-geo-commerce' ); ?></label></th>
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
				</table>
			</section>

			<section class="rwgcm-rule-step">
				<h2><?php esc_html_e( 'Step 2 — Choose where it applies', 'reactwoo-geo-commerce' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Applies to', 'reactwoo-geo-commerce' ); ?></th>
						<td>
							<select name="rwgcm_scope_type" id="rwgcm_scope_type">
								<option value="global" <?php selected( $rule['scope']['type'], 'global' ); ?>><?php esc_html_e( 'Site-wide (all products)', 'reactwoo-geo-commerce' ); ?></option>
								<option value="product_category" <?php selected( $rule['scope']['type'], 'product_category' ); ?>><?php esc_html_e( 'Product categories', 'reactwoo-geo-commerce' ); ?></option>
								<option value="product" <?php selected( $rule['scope']['type'], 'product' ); ?>><?php esc_html_e( 'Products', 'reactwoo-geo-commerce' ); ?></option>
								<option value="cart" <?php selected( $rule['scope']['type'], 'cart' ); ?>><?php esc_html_e( 'Cart / checkout', 'reactwoo-geo-commerce' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Narrower scopes beat broader ones when multiple rules match.', 'reactwoo-geo-commerce' ); ?></p>
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
						<th scope="row"><label for="rwgcm_scope_product"><?php esc_html_e( 'Product', 'reactwoo-geo-commerce' ); ?></label></th>
						<td>
							<select name="rwgcm_scope_product" id="rwgcm_scope_product" class="regular-text">
								<option value=""><?php esc_html_e( '— Select product —', 'reactwoo-geo-commerce' ); ?></option>
								<?php
								$products = get_posts(
									array(
										'post_type'      => 'product',
										'posts_per_page' => 200,
										'post_status'    => 'publish',
										'orderby'        => 'title',
										'order'          => 'ASC',
									)
								);
								$selected_pid = ! empty( $rule['scope']['ids'][0] ) ? (int) $rule['scope']['ids'][0] : 0;
								foreach ( $products as $product_post ) {
									printf(
										'<option value="%1$d"%2$s>%3$s</option>',
										(int) $product_post->ID,
										selected( $selected_pid, (int) $product_post->ID, false ),
										esc_html( get_the_title( $product_post ) )
									);
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</section>

			<section class="rwgcm-rule-step">
				<h2><?php esc_html_e( 'Step 3 — Add conditions (WHEN)', 'reactwoo-geo-commerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose conditions from the library. Values are stored internally; labels show friendly names like United Kingdom instead of GB.', 'reactwoo-geo-commerce' ); ?></p>

				<table class="form-table rwgcm-portable-toggle" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Advanced builder', 'reactwoo-geo-commerce' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="rwgcm_use_portable_targeting" id="rwgcm_use_portable_targeting" value="1" <?php checked( $rwgcm_use_portable ); ?> />
								<?php esc_html_e( 'Use Geo Core visibility rule builder (campaigns, audiences, grouped logic)', 'reactwoo-geo-commerce' ); ?>
							</label>
						</td>
					</tr>
					<tr class="rwgcm-portable-builder-row" style="<?php echo $rwgcm_use_portable ? '' : 'display:none;'; ?>">
						<th scope="row"><label for="rwgcm_portable_targeting"><?php esc_html_e( 'Visibility rules', 'reactwoo-geo-commerce' ); ?></label></th>
						<td>
							<div class="rwgc-rb-mount-wrap">
								<textarea name="rwgcm_portable_targeting" id="rwgcm_portable_targeting" rows="3" class="large-text code"><?php echo esc_textarea( $rwgcm_portable_raw ); ?></textarea>
							</div>
						</td>
					</tr>
				</table>

				<div id="rwgcm-guided-conditions" style="<?php echo $rwgcm_use_portable ? 'display:none;' : ''; ?>">
					<p>
						<label>
							<input type="radio" name="rwgcm_conditions_match" value="all" <?php checked( isset( $rule['conditions']['match'] ) ? $rule['conditions']['match'] : 'all', 'all' ); ?> />
							<?php esc_html_e( 'Match all conditions (AND)', 'reactwoo-geo-commerce' ); ?>
						</label>
						&nbsp;&nbsp;
						<label>
							<input type="radio" name="rwgcm_conditions_match" value="any" <?php checked( isset( $rule['conditions']['match'] ) ? $rule['conditions']['match'] : 'all', 'any' ); ?> />
							<?php esc_html_e( 'Match any condition (OR)', 'reactwoo-geo-commerce' ); ?>
						</label>
					</p>
					<table class="widefat" id="rwgcm-conditions-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Condition', 'reactwoo-geo-commerce' ); ?></th>
								<th><?php esc_html_e( 'Operator', 'reactwoo-geo-commerce' ); ?></th>
								<th><?php esc_html_e( 'Value', 'reactwoo-geo-commerce' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cond_items as $row ) : ?>
								<?php
								if ( ! is_array( $row ) ) {
									$row = array();
								}
								$row = array_merge(
									array(
										'field'    => '',
										'target'   => '',
										'operator' => 'is',
										'value'    => '',
										'label'    => '',
									),
									$row
								);
								$field_id = ! empty( $row['field'] ) ? (string) $row['field'] : (string) $row['target'];
								?>
								<tr class="rwgcm-condition-row" data-operator="<?php echo esc_attr( (string) $row['operator'] ); ?>" data-value="<?php echo esc_attr( is_scalar( $row['value'] ) ? (string) $row['value'] : '' ); ?>" data-label="<?php echo esc_attr( (string) $row['label'] ); ?>">
									<td>
										<select name="rwgcm_cond_field[]" class="rwgcm-cond-field regular-text">
											<option value=""><?php esc_html_e( '— Choose condition —', 'reactwoo-geo-commerce' ); ?></option>
											<?php foreach ( $condition_groups as $group_key => $group_label ) : ?>
												<optgroup label="<?php echo esc_attr( (string) $group_label ); ?>">
													<?php foreach ( $condition_fields as $field ) : ?>
														<?php
														if ( ! is_array( $field ) || empty( $field['id'] ) || ( isset( $field['group'] ) && $field['group'] !== $group_key ) ) {
															continue;
														}
														?>
														<option value="<?php echo esc_attr( (string) $field['id'] ); ?>" <?php selected( $field_id, (string) $field['id'] ); ?> <?php selected( $field_id, isset( $field['target'] ) ? (string) $field['target'] : '' ); ?>><?php echo esc_html( isset( $field['label'] ) ? (string) $field['label'] : (string) $field['id'] ); ?></option>
													<?php endforeach; ?>
												</optgroup>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="rwgcm-cond-operator-cell"></td>
									<td class="rwgcm-cond-value-cell"></td>
									<td><button type="button" class="button-link-delete rwgcm-remove-condition"><?php esc_html_e( 'Remove', 'reactwoo-geo-commerce' ); ?></button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="button" class="button" id="rwgcm-add-condition"><?php esc_html_e( 'Add condition', 'reactwoo-geo-commerce' ); ?></button></p>
				</div>
			</section>

			<section class="rwgcm-rule-step">
				<h2><?php esc_html_e( 'Step 4 — Add actions (THEN)', 'reactwoo-geo-commerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose one or more outcomes. Price adjustments, badges, notices, overlays, and visibility are all actions.', 'reactwoo-geo-commerce' ); ?></p>
				<div id="rwgcm-actions-list" class="rwgcm-actions-list">
					<?php foreach ( $rule['actions'] as $idx => $action ) : ?>
						<?php
						if ( ! is_array( $action ) || empty( $action['type'] ) ) {
							continue;
						}
						$type = sanitize_key( (string) $action['type'] );
						if ( 'badge_override' === $type ) {
							$type = 'product_badge';
							if ( ! isset( $action['text'] ) && isset( $action['value'] ) ) {
								$action['text'] = $action['value'];
							}
						}
						?>
						<div class="rwgcm-action-row" data-action="<?php echo esc_attr( wp_json_encode( $action ) ); ?>">
							<select name="rwgcm_action_type[]" class="rwgcm-action-type">
								<?php foreach ( $action_options as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $type, (string) $slug ); ?>><?php echo esc_html( (string) $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<div class="rwgcm-action-fields-wrap"></div>
							<button type="button" class="button-link-delete rwgcm-remove-action"><?php esc_html_e( 'Remove', 'reactwoo-geo-commerce' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="rwgcm-add-action"><?php esc_html_e( 'Add action', 'reactwoo-geo-commerce' ); ?></button></p>
			</section>

			<section class="rwgcm-rule-step">
				<h2><?php esc_html_e( 'Step 5 — Review summary', 'reactwoo-geo-commerce' ); ?></h2>
				<div class="rwgcm-rule-summary-box">
					<p id="rwgcm-rule-summary-preview"><?php echo esc_html( $summary_text ); ?></p>
				</div>
			</section>
		</div>

		<?php submit_button( $is_new ? __( 'Step 6 — Create rule', 'reactwoo-geo-commerce' ) : __( 'Step 6 — Update rule', 'reactwoo-geo-commerce' ) ); ?>
	</form>

	<script>
	window.rwgcmConditionBuilder = <?php echo wp_json_encode(
		array(
			'fields'         => $condition_fields,
			'groups'         => $condition_groups,
			'sources'        => $value_sources,
			'operatorLabels' => $operator_labels,
			'i18n'           => array(
				'chooseField' => __( '— Choose condition —', 'reactwoo-geo-commerce' ),
				'chooseValue' => __( '— Choose value —', 'reactwoo-geo-commerce' ),
				'remove'      => __( 'Remove', 'reactwoo-geo-commerce' ),
			),
		)
	); ?>;
	window.rwgcmRuleBuilder = <?php echo wp_json_encode(
		array(
			'actionOptions' => $action_options,
			'summarySeed'   => $summary_text,
			'i18n'          => array(
				'mode'           => __( 'Mode', 'reactwoo-geo-commerce' ),
				'percent'        => __( 'Percent', 'reactwoo-geo-commerce' ),
				'fixed'          => __( 'Fixed amount (per unit)', 'reactwoo-geo-commerce' ),
				'value'          => __( 'Value', 'reactwoo-geo-commerce' ),
				'badgeText'      => __( 'Badge text', 'reactwoo-geo-commerce' ),
				'message'        => __( 'Message', 'reactwoo-geo-commerce' ),
				'overlayField'   => __( 'Overlay field', 'reactwoo-geo-commerce' ),
				'overlayValue'   => __( 'Value', 'reactwoo-geo-commerce' ),
				'visibility'     => __( 'Visibility', 'reactwoo-geo-commerce' ),
				'cta'            => __( 'CTA HTML', 'reactwoo-geo-commerce' ),
				'html'           => __( 'HTML', 'reactwoo-geo-commerce' ),
				'remove'         => __( 'Remove', 'reactwoo-geo-commerce' ),
				'noConditions'   => __( 'always', 'reactwoo-geo-commerce' ),
				'noActions'      => __( 'nothing happens', 'reactwoo-geo-commerce' ),
				'summaryPrefix'  => __( 'If', 'reactwoo-geo-commerce' ),
			),
		)
	); ?>;
	(function(){
		var st = document.getElementById('rwgcm_scope_type');
		if (st) {
			function syncScope(){
				var v = st.value;
				var trc = document.querySelector('.rwgcm-scope-cats');
				var trp = document.querySelector('.rwgcm-scope-product');
				if (trc) trc.style.display = (v === 'product_category') ? '' : 'none';
				if (trp) trp.style.display = (v === 'product') ? '' : 'none';
			}
			st.addEventListener('change', syncScope);
			syncScope();
		}
		var portableToggle = document.getElementById('rwgcm_use_portable_targeting');
		var guidedBlock = document.getElementById('rwgcm-guided-conditions');
		var builderRow = document.querySelector('.rwgcm-portable-builder-row');
		if (portableToggle && guidedBlock) {
			function syncPortable(){
				var on = portableToggle.checked;
				guidedBlock.style.display = on ? 'none' : '';
				if (builderRow) builderRow.style.display = on ? '' : 'none';
			}
			portableToggle.addEventListener('change', syncPortable);
			syncPortable();
		}
		document.querySelectorAll('.rwgcm-remove-condition').forEach(function(btn){
			btn.addEventListener('click', function(){ btn.closest('tr').remove(); });
		});
		document.querySelectorAll('.rwgcm-remove-action').forEach(function(btn){
			btn.addEventListener('click', function(){ btn.closest('.rwgcm-action-row').remove(); });
		});
	})();
	</script>
</div>
