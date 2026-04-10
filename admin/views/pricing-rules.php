<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$config             = isset( $config ) && is_array( $config ) ? $config : array( 'enabled' => false, 'rules' => array() );
$wc_countries       = isset( $wc_countries ) && is_array( $wc_countries ) ? $wc_countries : array();
$product_categories = isset( $product_categories ) && is_array( $product_categories ) ? $product_categories : array();
$rules              = isset( $config['rules'] ) && is_array( $config['rules'] ) ? $config['rules'] : array();
$rules[]            = array(
	'country'        => '',
	'type'           => 'percent',
	'value'          => 0,
	'category_ids'   => array(),
	'label'          => '',
	'active'         => true,
);
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-legacy-pricing';

$sim_preview = null;
if ( isset( $_GET['rwgcm_sim_pricing'] ) && '1' === $_GET['rwgcm_sim_pricing'] && class_exists( 'RWGCM_Simulator', false ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sc = isset( $_GET['rwgcm_sim_country'] ) ? sanitize_text_field( wp_unslash( $_GET['rwgcm_sim_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sb = isset( $_GET['rwgcm_sim_base'] ) ? floatval( wp_unslash( $_GET['rwgcm_sim_base'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$st = isset( $_GET['rwgcm_sim_cat'] ) && is_array( $_GET['rwgcm_sim_cat'] ) ? array_map( 'intval', wp_unslash( $_GET['rwgcm_sim_cat'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$sim_preview = RWGCM_Simulator::pricing_preview( $sc, $st, $sb );
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--pricing">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Rules', 'reactwoo-geo-commerce' ),
			__( 'Legacy country rows are migrated into generic rules (Geo Core targets) on upgrade. Rules still evaluate in list order for equivalent country + category matching.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Commerce — pricing rules', 'reactwoo-geo-commerce' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Rules are evaluated in list order. Put specific category rules above broad rows.', 'reactwoo-geo-commerce' ); ?></p>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pricing rules saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<p class="rwgcm-precedence-note">
		<span class="dashicons dashicons-info"></span>
		<?php esc_html_e( 'Precedence: higher cards are evaluated first. Narrow rules (categories) should sit above “all products” rules for the same country.', 'reactwoo-geo-commerce' ); ?>
	</p>

	<div class="rwgcm-builder-layout">
		<div class="rwgcm-builder-layout__main">
			<div class="rwgc-card rwgc-card--full">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rwgcm-pricing-form">
					<?php wp_nonce_field( 'rwgcm_save_pricing' ); ?>
					<input type="hidden" name="action" value="rwgcm_save_pricing" />

					<p>
						<label>
							<input type="checkbox" name="rwgcm_pricing_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
							<?php esc_html_e( 'Enable geo pricing rules', 'reactwoo-geo-commerce' ); ?>
						</label>
					</p>

					<div class="rwgcm-rule-stack" id="rwgcm-pricing-rule-stack">
						<?php
						foreach ( $rules as $idx => $row ) {
							$cc       = isset( $row['country'] ) ? (string) $row['country'] : '';
							$type     = isset( $row['type'] ) && 'fixed_line' === $row['type'] ? 'fixed_line' : 'percent';
							$value    = isset( $row['value'] ) ? $row['value'] : 0;
							$sel_cats = isset( $row['category_ids'] ) && is_array( $row['category_ids'] ) ? array_map( 'intval', $row['category_ids'] ) : array();
							$label    = isset( $row['label'] ) ? (string) $row['label'] : '';
							$active   = ! isset( $row['active'] ) || ! empty( $row['active'] );
							$summary  = '';
							if ( class_exists( 'RWGCM_Simulator', false ) && '' !== $cc ) {
								$summary = RWGCM_Simulator::summarize_pricing_rule( $row );
							}
							include RWGCM_PATH . 'admin/views/partials/pricing-rule-card.php';
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="rwgcm-pricing-add-card"><?php esc_html_e( 'Add rule', 'reactwoo-geo-commerce' ); ?></button>
					</p>

					<?php submit_button( __( 'Save rules', 'reactwoo-geo-commerce' ) ); ?>
				</form>
			</div>
		</div>

		<aside class="rwgcm-builder-layout__aside">
			<div class="rwgc-card rwgcm-sim-panel">
				<h2><?php esc_html_e( 'Preview pricing outcome', 'reactwoo-geo-commerce' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Pick a country, optional categories, and a sample base price to see which rule wins.', 'reactwoo-geo-commerce' ); ?></p>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwgcm-sim-form">
					<input type="hidden" name="page" value="rwgcm-legacy-pricing" />
					<input type="hidden" name="rwgcm_sim_pricing" value="1" />
					<p>
						<label for="rwgcm_sim_country"><?php esc_html_e( 'Visitor country', 'reactwoo-geo-commerce' ); ?></label><br />
						<select name="rwgcm_sim_country" id="rwgcm_sim_country" style="min-width: 220px;">
							<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-commerce' ); ?></option>
							<?php foreach ( $wc_countries as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( isset( $_GET['rwgcm_sim_country'] ) ? sanitize_text_field( wp_unslash( $_GET['rwgcm_sim_country'] ) ) : '', $code ); ?>><?php echo esc_html( $code . ' — ' . $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="rwgcm_sim_base"><?php esc_html_e( 'Base unit price', 'reactwoo-geo-commerce' ); ?></label><br />
						<input type="number" name="rwgcm_sim_base" id="rwgcm_sim_base" step="0.01" min="0" style="width:8em" value="<?php echo isset( $_GET['rwgcm_sim_base'] ) ? esc_attr( (string) floatval( wp_unslash( $_GET['rwgcm_sim_base'] ) ) ) : '29.99'; ?>" />
					</p>
					<p>
						<label for="rwgcm_sim_cat"><?php esc_html_e( 'Product categories (optional)', 'reactwoo-geo-commerce' ); ?></label><br />
						<select name="rwgcm_sim_cat[]" id="rwgcm_sim_cat" multiple size="5" style="min-width: 100%;">
							<?php foreach ( $product_categories as $term ) : ?>
								<?php
								if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
									continue;
								}
								$sel_get = isset( $_GET['rwgcm_sim_cat'] ) && is_array( $_GET['rwgcm_sim_cat'] ) ? array_map( 'intval', wp_unslash( $_GET['rwgcm_sim_cat'] ) ) : array();
								?>
								<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php echo in_array( (int) $term->term_id, $sel_get, true ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<?php submit_button( __( 'Run preview', 'reactwoo-geo-commerce' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( is_array( $sim_preview ) ) : ?>
					<div class="rwgcm-sim-result">
						<p><strong><?php esc_html_e( 'Result', 'reactwoo-geo-commerce' ); ?></strong></p>
						<p><?php echo esc_html( (string) ( $sim_preview['explanation'] ?? '' ) ); ?></p>
						<p class="description"><?php esc_html_e( 'Adjusted unit price (store formatting):', 'reactwoo-geo-commerce' ); ?> <code><?php echo esc_html( function_exists( 'wc_format_decimal' ) ? wc_format_decimal( (float) ( $sim_preview['adjusted'] ?? 0 ), wc_get_price_decimals() ) : (string) ( $sim_preview['adjusted'] ?? '' ) ); ?></code></p>
					</div>
				<?php endif; ?>
			</div>
		</aside>
	</div>

	<details class="rwgcm-dev-details">
		<summary><?php esc_html_e( 'Developer reference', 'reactwoo-geo-commerce' ); ?></summary>
		<p class="description"><?php esc_html_e( 'Filter rwgcm_adjusted_unit_price; action rwgcm_before_cart_totals.', 'reactwoo-geo-commerce' ); ?></p>
	</details>
</div>
