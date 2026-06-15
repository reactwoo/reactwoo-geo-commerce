<?php
/**
 * Commerce merchandising entry.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_key         = RWGCM_Settings::OPTION_KEY;
$settings           = RWGCM_Settings::get_settings();
$boost_labels       = RWGCM_Settings::get_weather_catalog_boost_mode_labels();
$weather_connected  = RWGCM_Condition_Library::is_weather_available();
$shop_mode          = RWGCM_Settings::get_weather_catalog_boost_mode( 'shop' );
$category_mode      = RWGCM_Settings::get_weather_catalog_boost_mode( 'category' );
$collection_mode    = RWGCM_Settings::get_weather_catalog_boost_mode( 'collection' );
$auto_category      = RWGCM_Settings::is_weather_auto_category_defaults_enabled();
$meta_badge         = RWGCM_Settings::is_weather_meta_badge_enabled();
$meta_badge_text    = RWGCM_Settings::get_weather_meta_badge_text();
$strip_link         = RWGCM_Settings::get_weather_strip_link_mode();
$strip_link_custom  = RWGCM_Settings::get_weather_strip_link_custom_url();
$store_lat          = isset( $settings['weather_store_lat'] ) ? (string) $settings['weather_store_lat'] : '';
$store_lon          = isset( $settings['weather_store_lon'] ) ? (string) $settings['weather_store_lon'] : '';
$coord_mode         = class_exists( 'RWGCP_Weather_Service', false ) ? RWGCP_Weather_Service::get_coordinate_mode() : '';
$coverage           = class_exists( 'RWGCM_Weather_Tagging', false ) ? RWGCM_Weather_Tagging::get_coverage_stats() : array();
$has_geo_ai         = class_exists( 'RWGA_Weather_Facet_Suggester', false );
$audit_report       = class_exists( 'RWGA_Weather_Catalog_Audit', false ) ? RWGA_Weather_Catalog_Audit::get_report() : array();
$audit_started      = isset( $_GET['rwga_audit_started'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_page_header(
			__( 'Merchandising', 'reactwoo-geo-commerce' ),
			__( 'Geo-based product messaging, overlays, contextual merchandising, and weather-aware catalog ordering.', 'reactwoo-geo-commerce' )
		);
	}
	?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Use product overlays and pricing rules together to tailor how products are presented by visitor location and campaign context.', 'reactwoo-geo-commerce' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-product-overlays' ) ); ?>">
				<?php esc_html_e( 'Product overlays', 'reactwoo-geo-commerce' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-pricing' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Pricing rules', 'reactwoo-geo-commerce' ); ?>
			</a>
		</p>
	</div>

	<div class="rwgc-card" style="max-width: 640px; margin-top: 16px;">
		<h2><?php esc_html_e( 'Weather merchandising', 'reactwoo-geo-commerce' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Tag products with shopping weather facets, then boost or filter shop and category archives when GeoCore Pro weather is available for the visitor.', 'reactwoo-geo-commerce' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-diagnostics' ) ); ?>"><?php esc_html_e( 'Diagnostics', 'reactwoo-geo-commerce' ); ?></a>
		</p>

		<?php if ( ! $weather_connected ) : ?>
			<p class="description">
				<?php esc_html_e( 'Connect GeoCore Pro weather to enable catalog boost and weather conditions.', 'reactwoo-geo-commerce' ); ?>
				<?php if ( class_exists( 'RWGCP_Weather_Service', false ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ); ?>"><?php esc_html_e( 'Weather settings', 'reactwoo-geo-commerce' ); ?></a>
				<?php endif; ?>
			</p>
		<?php elseif ( class_exists( 'RWGCP_Weather_Service', false ) ) : ?>
			<?php
			$wx_caps = RWGCP_Weather_Service::get_provider_capabilities();
			if ( empty( $wx_caps['air_quality'] ) || empty( $wx_caps['pollen'] ) ) :
				?>
			<p class="description">
				<?php
				if ( empty( $wx_caps['air_quality'] ) && empty( $wx_caps['pollen'] ) ) {
					esc_html_e( 'Open-Meteo does not supply air quality or pollen. Poor air / high pollen facets are hidden until you switch to WeatherAPI.com in GeoCore Pro.', 'reactwoo-geo-commerce' );
				} elseif ( empty( $wx_caps['pollen'] ) ) {
					esc_html_e( 'Pollen (high_pollen facet) requires WeatherAPI.com with a plan that includes pollen data.', 'reactwoo-geo-commerce' );
				} else {
					esc_html_e( 'Air quality (poor_air facet) requires WeatherAPI.com.', 'reactwoo-geo-commerce' );
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ); ?>"><?php esc_html_e( 'Weather settings', 'reactwoo-geo-commerce' ); ?></a>
			</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $coverage ) ) : ?>
		<div class="rwgcm-weather-coverage" style="margin: 12px 0 20px; padding: 12px 14px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;">
			<strong><?php esc_html_e( 'Catalog coverage', 'reactwoo-geo-commerce' ); ?></strong>
			<p style="margin: 8px 0 0;">
				<?php
				printf(
					/* translators: 1: tagged count, 2: total count, 3: percent */
					esc_html__( '%1$d of %2$d published products tagged (%3$s%%).', 'reactwoo-geo-commerce' ),
					(int) ( $coverage['tagged'] ?? 0 ),
					(int) ( $coverage['total'] ?? 0 ),
					esc_html( (string) ( $coverage['percent'] ?? 0 ) )
				);
				?>
				<?php if ( ! empty( $coverage['untagged'] ) ) : ?>
					<a href="<?php echo esc_url( RWGCM_Weather_Tagging::untagged_products_admin_url() ); ?>"><?php esc_html_e( 'View untagged products', 'reactwoo-geo-commerce' ); ?></a>
					&middot;
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>"><?php esc_html_e( 'Bulk suggest on products list', 'reactwoo-geo-commerce' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( $audit_started ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Weather catalog audit started. Suggestions will appear below as batches complete.', 'reactwoo-geo-commerce' ); ?></p></div>
		<?php endif; ?>

		<?php if ( class_exists( 'RWGA_Weather_Catalog_Audit', false ) ) : ?>
		<div class="rwgcm-weather-audit" style="margin: 12px 0 20px; padding: 12px 14px; background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px;">
			<strong><?php esc_html_e( 'Geo AI catalog audit', 'reactwoo-geo-commerce' ); ?></strong>
			<?php if ( ! empty( $audit_report ) ) : ?>
			<p style="margin: 8px 0;">
				<?php
				printf(
					/* translators: 1: scanned count, 2: suggestion count */
					esc_html__( 'Scanned %1$d untagged products — %2$d suggestions ready.', 'reactwoo-geo-commerce' ),
					(int) ( $audit_report['scanned'] ?? 0 ),
					isset( $audit_report['suggestions'] ) && is_array( $audit_report['suggestions'] ) ? count( $audit_report['suggestions'] ) : 0
				);
				if ( empty( $audit_report['complete'] ) ) {
					echo ' ' . esc_html__( '(audit in progress)', 'reactwoo-geo-commerce' );
				}
				?>
			</p>
			<?php else : ?>
			<p style="margin: 8px 0;"><?php esc_html_e( 'Run a background audit to suggest weather facets for untagged products (weekly cron or manual).', 'reactwoo-geo-commerce' ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $audit_report['suggestions'] ) && is_array( $audit_report['suggestions'] ) ) : ?>
			<ul style="margin: 0 0 10px; max-height: 180px; overflow: auto;">
				<?php foreach ( array_slice( $audit_report['suggestions'], 0, 15 ) as $row ) : ?>
					<?php if ( ! is_array( $row ) || empty( $row['product_id'] ) ) { continue; } ?>
					<li>
						<?php if ( ! empty( $row['edit_url'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"><?php echo esc_html( (string) ( $row['title'] ?? '#' . $row['product_id'] ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( (string) ( $row['title'] ?? '#' . $row['product_id'] ) ); ?>
						<?php endif; ?>
						&mdash;
						<?php echo esc_html( RWGCM_Weather_Affinity::format_facet_value_label( implode( ',', (array) ( $row['facets'] ?? array() ) ) ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'rwga_weather_catalog_audit' ); ?>
				<input type="hidden" name="action" value="rwga_run_weather_catalog_audit" />
				<?php submit_button( __( 'Run catalog audit now', 'reactwoo-geo-commerce' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'rwgcm_license_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgcm_form_scope]" value="weather_merchandising" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Product tagging', 'reactwoo-geo-commerce' ); ?></th>
					<td>
						<label for="rwgcm_weather_auto_category_defaults">
							<input type="checkbox" id="rwgcm_weather_auto_category_defaults" name="<?php echo esc_attr( $option_key ); ?>[weather_auto_category_defaults]" value="1" <?php checked( $auto_category ); ?> />
							<?php esc_html_e( 'Auto-apply category weather defaults when a product has no facets on save', 'reactwoo-geo-commerce' ); ?>
						</label>
						<p class="description">
							<?php
							if ( $has_geo_ai ) {
								esc_html_e( 'Set category defaults under Products → Categories. Use bulk actions Suggest weather facets (Geo AI + categories) on the products list to backfill existing catalog.', 'reactwoo-geo-commerce' );
							} else {
								esc_html_e( 'Set category defaults under Products → Categories. Install Geo AI for keyword-based bulk suggestions.', 'reactwoo-geo-commerce' );
							}
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgcm_weather_boost_shop"><?php esc_html_e( 'Shop page', 'reactwoo-geo-commerce' ); ?></label></th>
					<td>
						<select id="rwgcm_weather_boost_shop" name="<?php echo esc_attr( $option_key ); ?>[weather_boost_shop]">
							<?php foreach ( $boost_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $shop_mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgcm_weather_boost_category"><?php esc_html_e( 'Category archives', 'reactwoo-geo-commerce' ); ?></label></th>
					<td>
						<select id="rwgcm_weather_boost_category" name="<?php echo esc_attr( $option_key ); ?>[weather_boost_category]">
							<?php foreach ( $boost_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $category_mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgcm_weather_boost_collection"><?php esc_html_e( 'Product Collection block', 'reactwoo-geo-commerce' ); ?></label></th>
					<td>
						<select id="rwgcm_weather_boost_collection" name="<?php echo esc_attr( $option_key ); ?>[weather_boost_collection]">
							<?php foreach ( $boost_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $collection_mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Boost moves weather-matched products to the top. Filter hides products with no facet overlap. Weather must be available or the catalog order is unchanged.', 'reactwoo-geo-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Store weather coordinates', 'reactwoo-geo-commerce' ); ?></th>
					<td>
						<label for="rwgcm_weather_store_lat"><?php esc_html_e( 'Latitude', 'reactwoo-geo-commerce' ); ?></label>
						<input type="text" class="regular-text" id="rwgcm_weather_store_lat" name="<?php echo esc_attr( $option_key ); ?>[weather_store_lat]" value="<?php echo esc_attr( $store_lat ); ?>" />
						<label for="rwgcm_weather_store_lon"><?php esc_html_e( 'Longitude', 'reactwoo-geo-commerce' ); ?></label>
						<input type="text" class="regular-text" id="rwgcm_weather_store_lon" name="<?php echo esc_attr( $option_key ); ?>[weather_store_lon]" value="<?php echo esc_attr( $store_lon ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Optional fallback for GeoCore Pro store weather (click-and-collect). Used when Pro store coordinates are empty.', 'reactwoo-geo-commerce' ); ?>
							<?php if ( class_exists( 'RWGCP_Weather_Service', false ) ) : ?>
								<?php
								printf(
									/* translators: %s: coordinate mode slug */
									esc_html__( 'Pro weather location mode: %s.', 'reactwoo-geo-commerce' ),
									esc_html( $coord_mode ? $coord_mode : 'visitor' )
								);
								?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-weather' ) ); ?>"><?php esc_html_e( 'Change in GeoCore Pro', 'reactwoo-geo-commerce' ); ?></a>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Storefront badges', 'reactwoo-geo-commerce' ); ?></th>
					<td>
						<label for="rwgcm_weather_meta_badge">
							<input type="checkbox" id="rwgcm_weather_meta_badge" name="<?php echo esc_attr( $option_key ); ?>[weather_meta_badge]" value="1" <?php checked( $meta_badge ); ?> />
							<?php esc_html_e( 'Show “Good for today’s weather” badge on loops when product facets match the visitor', 'reactwoo-geo-commerce' ); ?>
						</label>
						<p class="description">
							<label for="rwgcm_weather_meta_badge_text"><?php esc_html_e( 'Badge template (optional)', 'reactwoo-geo-commerce' ); ?></label><br />
							<input type="text" class="regular-text" id="rwgcm_weather_meta_badge_text" name="<?php echo esc_attr( $option_key ); ?>[weather_meta_badge_text]" value="<?php echo esc_attr( $meta_badge_text ); ?>" placeholder="<?php esc_attr_e( 'Good for {facets}', 'reactwoo-geo-commerce' ); ?>" />
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgcm_weather_strip_link"><?php esc_html_e( 'Weather strip link', 'reactwoo-geo-commerce' ); ?></label></th>
					<td>
						<select id="rwgcm_weather_strip_link" name="<?php echo esc_attr( $option_key ); ?>[weather_strip_link]">
							<option value="none" <?php selected( $strip_link, 'none' ); ?>><?php esc_html_e( 'None', 'reactwoo-geo-commerce' ); ?></option>
							<option value="shop" <?php selected( $strip_link, 'shop' ); ?>><?php esc_html_e( 'Shop page', 'reactwoo-geo-commerce' ); ?></option>
						</select>
						<p class="description">
							<label for="rwgcm_weather_strip_link_custom"><?php esc_html_e( 'Custom URL (optional override)', 'reactwoo-geo-commerce' ); ?></label><br />
							<input type="url" class="regular-text" id="rwgcm_weather_strip_link_custom" name="<?php echo esc_attr( $option_key ); ?>[weather_strip_link_custom]" value="<?php echo esc_attr( $strip_link_custom ); ?>" placeholder="https://" />
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save weather settings', 'reactwoo-geo-commerce' ) ); ?>
		</form>

		<p class="description">
			<?php esc_html_e( 'Display widgets:', 'reactwoo-geo-commerce' ); ?>
			<code>[rwgcm_weather_products]</code>,
			<code>[rwgcm_weather_strip]</code>,
			<code>[rwgcm_weather_filter]</code>,
			<?php esc_html_e( 'Elementor dynamic tag Visitor Weather Facets, widgets Weather Products + Weather Strip.', 'reactwoo-geo-commerce' ); ?>
		</p>
	</div>
</div>
