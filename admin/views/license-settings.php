<?php
/**
 * License screen — ReactWoo product key for Geo Commerce (same pipeline as Geo AI).
 *
 * @package ReactWooGeoCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_key       = RWGCM_Settings::OPTION_KEY;
$settings         = RWGCM_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-license';
$lic_ok           = ! empty( $settings['reactwoo_license_key'] );

?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'License', 'reactwoo-geo-commerce' ),
			__( 'Activate your ReactWoo Geo Commerce plan. The key is stored on this site and used with Geo Core’s platform client (JWT) when features require it.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Commerce — License', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgcm_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 520px;">
			<h2><?php esc_html_e( 'Product license', 'reactwoo-geo-commerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'If you already entered a key in Geo AI, Geo Optimise, or Geo Core, it may be copied here automatically until you save a key specific to Geo Commerce.', 'reactwoo-geo-commerce' ); ?></p>

			<p style="margin: 12px 0;">
				<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
					<?php
					RWGC_Admin_UI::render_badge(
						$lic_ok ? __( 'Key on file', 'reactwoo-geo-commerce' ) : __( 'Not configured', 'reactwoo-geo-commerce' ),
						$lic_ok ? 'success' : 'warning'
					);
					?>
				<?php else : ?>
					<strong><?php echo $lic_ok ? esc_html__( 'Key on file', 'reactwoo-geo-commerce' ) : esc_html__( 'Not configured', 'reactwoo-geo-commerce' ); ?></strong>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php" class="rwgcm-license-form">
				<?php settings_fields( 'rwgcm_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgcm_form_scope]" value="license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwgcm_reactwoo_license_key"><?php esc_html_e( 'License key', 'reactwoo-geo-commerce' ); ?></label></th>
						<td>
							<input type="password" id="rwgcm_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-commerce' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-commerce' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save license', 'reactwoo-geo-commerce' ) ); ?>
			</form>

			<?php if ( $lic_ok ) : ?>
				<p class="rwgcm-license-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgcm-license&rwgcm_action=clear_license' ), 'rwgcm_clear_license' ) ); ?>" onclick="return window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-commerce' ) ); ?>);"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-commerce' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
