<?php
/**
 * Single pricing rule card (expects $idx, $row, $wc_countries, $product_categories, $cc, $type, $value, $sel_cats, $label, $active, $summary).
 *
 * @var int|string $idx
 * @var array      $row
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$card_idx = is_numeric( $idx ) ? (int) $idx : 0;
?>
<div class="rwgcm-rule-card" data-rwgcm-card>
	<div class="rwgcm-rule-card__head">
		<span class="rwgcm-rule-card__order"><?php echo esc_html( (string) ( $card_idx + 1 ) ); ?></span>
		<input type="text" class="rwgcm-rule-card__title regular-text" name="rwgcm_rule_label[]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Rule name (optional)', 'reactwoo-geo-commerce' ); ?>" />
		<label class="rwgcm-rule-card__toggle">
			<input type="hidden" name="rwgcm_rule_active[<?php echo esc_attr( (string) $card_idx ); ?>]" value="0" />
			<input type="checkbox" name="rwgcm_rule_active[<?php echo esc_attr( (string) $card_idx ); ?>]" value="1" <?php checked( $active ); ?> />
			<?php esc_html_e( 'Enabled', 'reactwoo-geo-commerce' ); ?>
		</label>
		<span class="rwgcm-rule-card__move">
			<button type="button" class="button button-small rwgcm-move-up" aria-label="<?php esc_attr_e( 'Move up', 'reactwoo-geo-commerce' ); ?>">↑</button>
			<button type="button" class="button button-small rwgcm-move-down" aria-label="<?php esc_attr_e( 'Move down', 'reactwoo-geo-commerce' ); ?>">↓</button>
			<button type="button" class="button button-small rwgcm-duplicate-card"><?php esc_html_e( 'Duplicate', 'reactwoo-geo-commerce' ); ?></button>
			<button type="button" class="button button-small rwgcm-remove-card"><?php esc_html_e( 'Remove', 'reactwoo-geo-commerce' ); ?></button>
		</span>
	</div>
	<div class="rwgcm-rule-card__body">
		<div class="rwgcm-rule-card__field">
			<label><?php esc_html_e( 'Country', 'reactwoo-geo-commerce' ); ?></label>
			<select name="rwgcm_rule_country[]" style="min-width: 220px;">
				<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
				<?php foreach ( $wc_countries as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $cc, $code ); ?>><?php echo esc_html( $code . ' — ' . $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="rwgcm-rule-card__field">
			<label><?php esc_html_e( 'Product categories', 'reactwoo-geo-commerce' ); ?></label>
			<select name="rwgcm_rule_category_ids[<?php echo esc_attr( (string) $card_idx ); ?>][]" multiple size="5" style="min-width: 240px;">
				<?php foreach ( $product_categories as $term ) : ?>
					<?php
					if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
						continue;
					}
					?>
					<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php echo in_array( (int) $term->term_id, $sel_cats, true ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'None = all products in that country.', 'reactwoo-geo-commerce' ); ?></p>
		</div>
		<div class="rwgcm-rule-card__field rwgcm-rule-card__field--inline">
			<div>
				<label><?php esc_html_e( 'Adjustment', 'reactwoo-geo-commerce' ); ?></label>
				<select name="rwgcm_rule_type[]">
					<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Percent (%)', 'reactwoo-geo-commerce' ); ?></option>
					<option value="fixed_line" <?php selected( $type, 'fixed_line' ); ?>><?php esc_html_e( 'Fixed per unit', 'reactwoo-geo-commerce' ); ?></option>
				</select>
			</div>
			<div>
				<label><?php esc_html_e( 'Value', 'reactwoo-geo-commerce' ); ?></label>
				<input type="number" name="rwgcm_rule_value[]" value="<?php echo esc_attr( (string) $value ); ?>" step="0.01" style="width:8em" />
			</div>
		</div>
		<?php if ( '' !== $summary ) : ?>
			<p class="rwgcm-rule-card__summary"><strong><?php esc_html_e( 'Summary:', 'reactwoo-geo-commerce' ); ?></strong> <?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
	</div>
</div>
