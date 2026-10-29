<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-settings';
$rwgc_settings    = admin_url( 'admin.php?page=rwgc-settings' );
$wc_settings      = admin_url( 'admin.php?page=wc-settings' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Settings', 'reactwoo-geo-commerce' ),
			__( 'Geo Commerce builds on Geo Core targeting and WooCommerce. Geo Core resolves the visitor country. Price books and payment gateways have their own screens. Tax stays in WooCommerce.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Settings', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( isset( $_GET['rwgcm_cache_cleared'] ) && '1' === (string) $_GET['rwgcm_cache_cleared'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Geo cache cleared.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgcm_cache_saved'] ) && '1' === (string) $_GET['rwgcm_cache_saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Cache-safe price mode saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgcm_exchange_saved'] ) && '1' === (string) $_GET['rwgcm_exchange_saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Exchange rates saved. Cached prices were cleared.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgcm_exchange_saved'] ) && '0' === (string) $_GET['rwgcm_exchange_saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Exchange settings saved, but the provider did not return rates. Prices stay in the store currency until a rate is available. Manual rates are used if you entered them.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card rwgcm-panel">
		<ul class="ul-disc">
			<li><a href="<?php echo esc_url( $rwgc_settings ); ?>"><?php esc_html_e( 'ReactWoo Geo Core — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( $wc_settings ); ?>"><?php esc_html_e( 'WooCommerce — Settings', 'reactwoo-geo-commerce' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-license' ) ); ?>"><?php esc_html_e( 'Geo Commerce — License', 'reactwoo-geo-commerce' ); ?></a></li>
		</ul>
	</div>

	<div class="rwgc-card rwgcm-panel">
		<h2><?php esc_html_e( 'Price books and payments', 'reactwoo-geo-commerce' ); ?></h2>
		<p><?php esc_html_e( 'Currencies and checkout gateways are their own screens, not rules in the rule builder.', 'reactwoo-geo-commerce' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-price-books' ) ); ?>"><?php esc_html_e( 'Price books', 'reactwoo-geo-commerce' ); ?></a>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcm-payments' ) ); ?>"><?php esc_html_e( 'Configure payment gateways', 'reactwoo-geo-commerce' ); ?></a>
		</p>
	</div>

	<?php if ( current_user_can( 'manage_woocommerce' ) && class_exists( 'RWGCM_Exchange', false ) ) : ?>
		<?php
		$rwgcm_fx          = RWGCM_Exchange::admin_state();
		$rwgcm_fx_manual   = ( isset( $rwgcm_fx['manual']['rates'] ) && is_array( $rwgcm_fx['manual']['rates'] ) ) ? $rwgcm_fx['manual']['rates'] : array();
		$rwgcm_fx_base     = class_exists( 'RWGCM_Price_Book_Currency', false ) ? RWGCM_Price_Book_Currency::base_currency() : 'GBP';
		$rwgcm_fx_codes    = class_exists( 'RWGCM_Price_Book', false ) ? RWGCM_Price_Book::currencies() : array( 'ZAR', 'GBP', 'EUR', 'USD' );
		$rwgcm_fx_fetched  = ( isset( $rwgcm_fx['cache']['fetched_at'] ) ) ? (int) $rwgcm_fx['cache']['fetched_at'] : 0;
		$rwgcm_fx_provider = isset( $rwgcm_fx['cache']['provider'] ) ? (string) $rwgcm_fx['cache']['provider'] : '';
		?>
		<div class="rwgc-card rwgcm-panel">
			<h2><?php esc_html_e( 'Exchange rates', 'reactwoo-geo-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Auto converts a product into the visitor currency when that currency has no fixed price-book amount and a rate exists. The store price is the price-book amount in the store currency when one is set, otherwise the regular or sale price. The geo pricing rule is applied after conversion. A fixed visitor-currency price is left as entered and is not discounted again. Off keeps today’s store-currency fallback even if rates are saved. With no rate, Auto does the same and does not stop the page.', 'reactwoo-geo-commerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwgcm_save_exchange_rates" />
				<?php wp_nonce_field( 'rwgcm_save_exchange_rates' ); ?>
				<p>
					<label for="rwgcm_exchange_mode"><?php esc_html_e( 'Conversion', 'reactwoo-geo-commerce' ); ?></label><br />
					<select id="rwgcm_exchange_mode" name="rwgcm_exchange_mode">
						<option value="auto" <?php selected( $rwgcm_fx['mode'], 'auto' ); ?>><?php esc_html_e( 'Auto (only when a rate exists)', 'reactwoo-geo-commerce' ); ?></option>
						<option value="off" <?php selected( $rwgcm_fx['mode'], 'off' ); ?>><?php esc_html_e( 'Off', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</p>
				<p>
					<label for="rwgcm_exchange_source"><?php esc_html_e( 'Rate source', 'reactwoo-geo-commerce' ); ?></label><br />
					<select id="rwgcm_exchange_source" name="rwgcm_exchange_source">
						<option value="manual" <?php selected( $rwgcm_fx['source'], 'manual' ); ?>><?php esc_html_e( 'Manual rates', 'reactwoo-geo-commerce' ); ?></option>
						<option value="provider" <?php selected( $rwgcm_fx['source'], 'provider' ); ?>><?php esc_html_e( 'Automatic provider', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</p>
				<p>
					<label for="rwgcm_exchange_provider"><?php esc_html_e( 'Provider', 'reactwoo-geo-commerce' ); ?></label><br />
					<select id="rwgcm_exchange_provider" name="rwgcm_exchange_provider">
						<option value="ecb" <?php selected( $rwgcm_fx['provider'], 'ecb' ); ?>><?php esc_html_e( 'ECB reference rates (no key)', 'reactwoo-geo-commerce' ); ?></option>
						<option value="open_er" <?php selected( $rwgcm_fx['provider'], 'open_er' ); ?>><?php esc_html_e( 'open.er-api.com', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</p>
				<p>
					<label for="rwgcm_exchange_api_key"><?php esc_html_e( 'API key (optional)', 'reactwoo-geo-commerce' ); ?></label><br />
					<input type="password" class="regular-text" id="rwgcm_exchange_api_key" name="rwgcm_exchange_api_key" value="<?php echo esc_attr( (string) $rwgcm_fx['api_key'] ); ?>" autocomplete="off" />
					<br />
					<label><input type="checkbox" name="rwgcm_exchange_clear_key" value="1" /> <?php esc_html_e( 'Clear the saved API key', 'reactwoo-geo-commerce' ); ?></label>
					<br />
					<span class="description"><?php esc_html_e( 'ECB does not use a key. open.er-api.com works without one. A key uses the ExchangeRate-API v6 endpoint instead.', 'reactwoo-geo-commerce' ); ?></span>
				</p>
				<p>
					<label for="rwgcm_exchange_rounding"><?php esc_html_e( 'Rounding', 'reactwoo-geo-commerce' ); ?></label><br />
					<select id="rwgcm_exchange_rounding" name="rwgcm_exchange_rounding">
						<option value="none" <?php selected( $rwgcm_fx['rounding'], 'none' ); ?>><?php esc_html_e( 'None (WooCommerce decimals)', 'reactwoo-geo-commerce' ); ?></option>
						<option value="whole" <?php selected( $rwgcm_fx['rounding'], 'whole' ); ?>><?php esc_html_e( 'Whole units', 'reactwoo-geo-commerce' ); ?></option>
						<option value="99" <?php selected( $rwgcm_fx['rounding'], '99' ); ?>><?php esc_html_e( '.99', 'reactwoo-geo-commerce' ); ?></option>
					</select>
					<br />
					<span class="description"><?php esc_html_e( '.99 rounds to the nearest whole unit, then subtracts 0.01.', 'reactwoo-geo-commerce' ); ?></span>
				</p>
				<p>
					<label for="rwgcm_exchange_markup"><?php esc_html_e( 'Markup %', 'reactwoo-geo-commerce' ); ?></label><br />
					<input type="text" inputmode="decimal" class="small-text" id="rwgcm_exchange_markup" name="rwgcm_exchange_markup" value="<?php echo esc_attr( (string) $rwgcm_fx['markup'] ); ?>" />
					<span class="description"><?php esc_html_e( 'Applied to the rate before the geo pricing rule. 0 leaves the rate unchanged.', 'reactwoo-geo-commerce' ); ?></span>
				</p>
				<h3><?php esc_html_e( 'Manual rates', 'reactwoo-geo-commerce' ); ?></h3>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: store currency code */
							__( 'Units of each currency per 1 %s. Used when the source is Manual, and as the fallback when an automatic fetch has no last-good rates.', 'reactwoo-geo-commerce' ),
							$rwgcm_fx_base
						)
					);
					?>
				</p>
				<table class="widefat striped" style="max-width: 28em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Currency', 'reactwoo-geo-commerce' ); ?></th>
							<th><?php esc_html_e( 'Rate', 'reactwoo-geo-commerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rwgcm_fx_codes as $rwgcm_fx_code ) : ?>
							<?php if ( $rwgcm_fx_code === $rwgcm_fx_base ) { continue; } ?>
							<tr>
								<td><label for="rwgcm_exchange_manual_<?php echo esc_attr( $rwgcm_fx_code ); ?>"><?php echo esc_html( $rwgcm_fx_code ); ?></label></td>
								<td><input type="text" inputmode="decimal" id="rwgcm_exchange_manual_<?php echo esc_attr( $rwgcm_fx_code ); ?>" name="rwgcm_exchange_manual[<?php echo esc_attr( $rwgcm_fx_code ); ?>]" value="<?php echo esc_attr( isset( $rwgcm_fx_manual[ $rwgcm_fx_code ] ) ? (string) $rwgcm_fx_manual[ $rwgcm_fx_code ] : '' ); ?>" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $rwgcm_fx_fetched > 0 ) : ?>
					<p>
						<?php
						$rwgcm_fx_when = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $rwgcm_fx_fetched ) : gmdate( 'Y-m-d H:i:s', $rwgcm_fx_fetched ) . ' UTC';
						echo esc_html(
							sprintf(
								/* translators: 1: provider id, 2: fetch time */
								__( 'Last provider rates (%1$s): %2$s. They refresh every 12 hours.', 'reactwoo-geo-commerce' ),
								'' !== $rwgcm_fx_provider ? $rwgcm_fx_provider : 'provider',
								$rwgcm_fx_when
							)
						);
						?>
					</p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No provider rates have been stored yet. Automatic rates refresh every 12 hours, and again when you save this form.', 'reactwoo-geo-commerce' ); ?></p>
				<?php endif; ?>
				<?php submit_button( __( 'Save exchange rates', 'reactwoo-geo-commerce' ) ); ?>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( current_user_can( 'manage_woocommerce' ) ) : ?>
		<?php
		$rwgcm_cache_mode = class_exists( 'RWGCM_Cache_Safe_Price', false ) ? RWGCM_Cache_Safe_Price::mode() : 'auto';
		$rwgcm_last_purge = class_exists( 'RWGCM_Cache_Purge', false ) ? RWGCM_Cache_Purge::last_purge() : array();
		$rwgcm_purge_time = isset( $rwgcm_last_purge['time'] ) ? (int) $rwgcm_last_purge['time'] : 0;
		$rwgcm_purge_counts = ( isset( $rwgcm_last_purge['counts'] ) && is_array( $rwgcm_last_purge['counts'] ) ) ? $rwgcm_last_purge['counts'] : array();
		?>
		<div class="rwgc-card rwgcm-panel">
			<h2><?php esc_html_e( 'Cache-safe prices', 'reactwoo-geo-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Auto keeps prices in the page when no cache plugin is active, and when LiteSpeed varies by country. It loads prices over AJAX when another known page cache is active and does not vary by country. Cart and checkout stay in the page HTML.', 'reactwoo-geo-commerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwgcm_save_cache_safe_price" />
				<?php wp_nonce_field( 'rwgcm_save_cache_safe_price' ); ?>
				<p>
					<label for="rwgcm_cache_safe_price_mode"><?php esc_html_e( 'Cache-safe price mode', 'reactwoo-geo-commerce' ); ?></label><br />
					<select id="rwgcm_cache_safe_price_mode" name="rwgcm_cache_safe_price_mode">
						<option value="off" <?php selected( $rwgcm_cache_mode, 'off' ); ?>><?php esc_html_e( 'Off', 'reactwoo-geo-commerce' ); ?></option>
						<option value="auto" <?php selected( $rwgcm_cache_mode, 'auto' ); ?>><?php esc_html_e( 'Auto', 'reactwoo-geo-commerce' ); ?></option>
						<option value="ajax" <?php selected( $rwgcm_cache_mode, 'ajax' ); ?>><?php esc_html_e( 'AJAX', 'reactwoo-geo-commerce' ); ?></option>
					</select>
				</p>
				<?php submit_button( __( 'Save cache-safe price mode', 'reactwoo-geo-commerce' ) ); ?>
			</form>
		</div>

		<div class="rwgc-card rwgcm-panel">
			<h2><?php esc_html_e( 'Geo cache', 'reactwoo-geo-commerce' ); ?></h2>
			<p><?php esc_html_e( 'Clear cached copies of products, the shop, category archives, and pages that show the price element. Saving a price book, country pricing rules, or exchange rates clears those pages as well.', 'reactwoo-geo-commerce' ); ?></p>
			<?php if ( $rwgcm_purge_time > 0 ) : ?>
				<p>
					<?php
					$rwgcm_purge_shown = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $rwgcm_purge_time ) : gmdate( 'Y-m-d H:i:s', $rwgcm_purge_time ) . ' UTC';
					echo esc_html(
						sprintf(
							/* translators: 1: purge time, 2: post count, 3: URL count, 4: Elementor CSS count */
							__( 'Last purge: %1$s. Posts %2$d, URLs %3$d, Elementor CSS %4$d.', 'reactwoo-geo-commerce' ),
							$rwgcm_purge_shown,
							isset( $rwgcm_purge_counts['posts'] ) ? (int) $rwgcm_purge_counts['posts'] : 0,
							isset( $rwgcm_purge_counts['urls'] ) ? (int) $rwgcm_purge_counts['urls'] : 0,
							isset( $rwgcm_purge_counts['elementor_css'] ) ? (int) $rwgcm_purge_counts['elementor_css'] : 0
						)
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No geo cache purge has been recorded yet.', 'reactwoo-geo-commerce' ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rwgcm_clear_geo_cache" />
				<?php wp_nonce_field( 'rwgcm_clear_geo_cache' ); ?>
				<?php submit_button( __( 'Clear Geo cache', 'reactwoo-geo-commerce' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
	<?php endif; ?>
</div>
