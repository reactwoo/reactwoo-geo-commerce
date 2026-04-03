<?php
/**
 * Single fee rule card (expects $idx, $cc, $name, $amt, $tax, $tclass, $active, $summary, $wc_countries, $tax_class_options).
 *
 * @var int $idx
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$card_idx = (int) $idx;
?>
<div class="rwgcm-fee-card" data-rwgcm-card>
	<div class="rwgcm-rule-card__head rwgcm-fee-card__head">
		<span class="rwgcm-fee-card__order"><?php echo esc_html( (string) ( $card_idx + 1 ) ); ?></span>
		<label class="rwgcm-rule-card__toggle">
			<input type="hidden" name="rwgcm_fee_active[<?php echo esc_attr( (string) $card_idx ); ?>]" value="0" />
			<input type="checkbox" name="rwgcm_fee_active[<?php echo esc_attr( (string) $card_idx ); ?>]" value="1" <?php checked( $active ); ?> />
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
			<select name="rwgcm_fee_country[]" style="min-width: 220px;">
				<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
				<?php foreach ( $wc_countries as $code => $cname ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $cc, $code ); ?>><?php echo esc_html( $code . ' — ' . $cname ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="rwgcm-rule-card__field">
			<label><?php esc_html_e( 'Fee name (checkout label)', 'reactwoo-geo-commerce' ); ?></label>
			<input type="text" name="rwgcm_fee_name[]" value="<?php echo esc_attr( $name ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Regional handling', 'reactwoo-geo-commerce' ); ?>" />
		</div>
		<div class="rwgcm-rule-card__field rwgcm-rule-card__field--inline">
			<div>
				<label><?php esc_html_e( 'Amount', 'reactwoo-geo-commerce' ); ?></label>
				<input type="text" name="rwgcm_fee_amount[]" value="<?php echo esc_attr( is_numeric( $amt ) ? (string) $amt : '' ); ?>" class="small-text" />
			</div>
			<div>
				<label><?php esc_html_e( 'Taxable', 'reactwoo-geo-commerce' ); ?></label><br />
				<input type="checkbox" name="rwgcm_fee_taxable[<?php echo esc_attr( (string) $card_idx ); ?>]" value="1" <?php checked( $tax ); ?> />
			</div>
			<div>
				<label><?php esc_html_e( 'Tax class', 'reactwoo-geo-commerce' ); ?></label>
				<select name="rwgcm_fee_tax_class[]" style="min-width: 140px;">
					<?php foreach ( $tax_class_options as $slug => $tlabel ) : ?>
						<option value="<?php echo esc_attr( (string) $slug ); ?>" <?php selected( $tclass, (string) $slug ); ?>><?php echo esc_html( (string) $tlabel ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php if ( '' !== $summary ) : ?>
			<p class="rwgcm-rule-card__summary"><strong><?php esc_html_e( 'Summary:', 'reactwoo-geo-commerce' ); ?></strong> <?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
	</div>
</div>
