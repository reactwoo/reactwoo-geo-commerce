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
