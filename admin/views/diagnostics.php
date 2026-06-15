<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-diagnostics';
$rwgcm_diag       = isset( $rwgcm_diag ) && is_array( $rwgcm_diag ) ? $rwgcm_diag : array();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap">
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
		<h2><?php esc_html_e( 'Weather merchandising', 'reactwoo-geo-commerce' ); ?></h2>
		<?php
		$wx = isset( $rwgcm_diag['weather_merchandising'] ) && is_array( $rwgcm_diag['weather_merchandising'] ) ? $rwgcm_diag['weather_merchandising'] : array();
		?>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Weather connected:', 'reactwoo-geo-commerce' ); ?> <strong><?php echo ! empty( $wx['connected'] ) ? esc_html__( 'Yes', 'reactwoo-geo-commerce' ) : esc_html__( 'No', 'reactwoo-geo-commerce' ); ?></strong></li>
			<li><?php esc_html_e( 'Visitor facets (this request):', 'reactwoo-geo-commerce' ); ?> <strong><?php echo esc_html( ! empty( $wx['visitor_facets'] ) && is_array( $wx['visitor_facets'] ) ? implode( ', ', $wx['visitor_facets'] ) : '—' ); ?></strong></li>
			<li><?php esc_html_e( 'Weather location source:', 'reactwoo-geo-commerce' ); ?> <strong><?php echo esc_html( ! empty( $wx['location_source'] ) ? (string) $wx['location_source'] : '—' ); ?></strong></li>
			<li><?php esc_html_e( 'Coordinate mode (GeoCore Pro):', 'reactwoo-geo-commerce' ); ?> <strong><?php echo esc_html( ! empty( $wx['coordinate_mode'] ) ? (string) $wx['coordinate_mode'] : '—' ); ?></strong></li>
			<li><?php esc_html_e( 'US EPA air quality index:', 'reactwoo-geo-commerce' ); ?> <strong><?php echo isset( $wx['air_quality_epa'] ) && '' !== (string) $wx['air_quality_epa'] ? esc_html( (string) $wx['air_quality_epa'] ) : '—'; ?></strong></li>
			<li><?php esc_html_e( 'Max pollen index:', 'reactwoo-geo-commerce' ); ?> <strong><?php echo isset( $wx['pollen_index_max'] ) && '' !== (string) $wx['pollen_index_max'] ? esc_html( (string) $wx['pollen_index_max'] ) : '—'; ?></strong></li>
			<li><?php esc_html_e( 'Products tagged:', 'reactwoo-geo-commerce' ); ?> <strong><?php echo isset( $wx['tagged_products'] ) ? (int) $wx['tagged_products'] : 0; ?></strong></li>
			<li><?php esc_html_e( 'Catalog boost (shop / category / collection):', 'reactwoo-geo-commerce' ); ?>
				<strong><?php
				printf(
					'%s / %s / %s',
					esc_html( isset( $wx['boost_shop'] ) ? (string) $wx['boost_shop'] : 'off' ),
					esc_html( isset( $wx['boost_category'] ) ? (string) $wx['boost_category'] : 'off' ),
					esc_html( isset( $wx['boost_collection'] ) ? (string) $wx['boost_collection'] : 'off' )
				);
				?></strong>
			</li>
		</ul>
		<p class="description"><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-merchandising' ) ); ?>"><?php esc_html_e( 'Merchandising settings', 'reactwoo-geo-commerce' ); ?></a>
		<?php if ( class_exists( 'RWGCP_Weather_Service', false ) ) : ?>
			&middot; <a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ); ?>"><?php esc_html_e( 'GeoCore Pro weather', 'reactwoo-geo-commerce' ); ?></a>
		<?php endif; ?>
		</p>
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
