<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-diagnostics';
$rwgcm_diag       = isset( $rwgcm_diag ) && is_array( $rwgcm_diag ) ? $rwgcm_diag : array();
?>
<div class="wrap rwgc-wrap rwgcm-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Diagnostics', 'reactwoo-geo-commerce' ),
			__( 'Geo Core context snapshot, available targets, and generic pricing rule resolution (admin request).', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Diagnostics', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Summary', 'reactwoo-geo-commerce' ); ?></h2>
		<ul class="ul-disc">
			<li>
				<?php esc_html_e( 'Generic pricing rules active:', 'reactwoo-geo-commerce' ); ?>
				<strong><?php echo ! empty( $rwgcm_diag['use_generic_pricing'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></strong>
			</li>
			<li>
				<?php esc_html_e( 'Active generic rules (DB):', 'reactwoo-geo-commerce' ); ?>
				<strong><?php echo isset( $rwgcm_diag['generic_rule_count'] ) ? (int) $rwgcm_diag['generic_rule_count'] : 0; ?></strong>
			</li>
			<li>
				<?php esc_html_e( 'Legacy pricing option enabled:', 'reactwoo-geo-commerce' ); ?>
				<strong><?php echo ! empty( $rwgcm_diag['legacy_pricing_enabled'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></strong>
			</li>
		</ul>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Geo Core context snapshot', 'reactwoo-geo-commerce' ); ?></h2>
		<pre style="max-height:20em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( isset( $rwgcm_diag['context_snapshot'] ) ? $rwgcm_diag['context_snapshot'] : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Available target keys', 'reactwoo-geo-commerce' ); ?></h2>
		<p><?php echo esc_html( implode( ', ', isset( $rwgcm_diag['available_target_keys'] ) && is_array( $rwgcm_diag['available_target_keys'] ) ? $rwgcm_diag['available_target_keys'] : array() ) ); ?></p>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Winning price rule (no product context in admin)', 'reactwoo-geo-commerce' ); ?></h2>
		<pre style="max-height:16em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( isset( $rwgcm_diag['winning_price_rule'] ) ? $rwgcm_diag['winning_price_rule'] : null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</div>
</div>
