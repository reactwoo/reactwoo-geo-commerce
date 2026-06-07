<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rules                    = isset( $rules ) && is_array( $rules ) ? $rules : array();
$filter                   = isset( $filter ) ? (string) $filter : 'all';
$rwgc_nav_current         = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-pricing';
$rwgcm_use_platform_shell = class_exists( 'RWGCM_Admin', false ) && RWGCM_Admin::uses_platform_shell();
$new_url                  = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=new' );
$legacy_url               = admin_url( 'admin.php?page=rwgcm-legacy-pricing' );
$overlays_url             = admin_url( 'admin.php?page=rwgcm-product-overlays' );
$base_url                 = admin_url( 'admin.php?page=rwgcm-pricing' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--rules-generic">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Rules', 'reactwoo-geo-commerce' ),
			__( 'Unified commerce automation: conditions define WHEN a rule applies; actions define THEN outcomes (pricing, badges, notices, overlays, visibility).', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Rules', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php if ( ! $rwgcm_use_platform_shell ) : ?>
		<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGCM_Rule_Migration', false ) && RWGCM_Rule_Migration::has_legacy_pricing_rows() ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'Legacy country-only pricing rows were detected. They remain available for backwards compatibility.', 'reactwoo-geo-commerce' ); ?>
			<a href="<?php echo esc_url( $legacy_url ); ?>"><?php esc_html_e( 'Open legacy country rows', 'reactwoo-geo-commerce' ); ?></a>
		</p></div>
	<?php endif; ?>
	<?php if ( class_exists( 'RWGCM_Rule_Migration', false ) && RWGCM_Rule_Migration::has_legacy_overlay_rows() ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'Legacy product overlay records remain in the database. Overlays are now managed as rule actions. Existing overlay behaviour is preserved.', 'reactwoo-geo-commerce' ); ?>
			<a href="<?php echo esc_url( $overlays_url ); ?>"><?php esc_html_e( 'View legacy overlays', 'reactwoo-geo-commerce' ); ?></a>
		</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php elseif ( isset( $_GET['updated'] ) && '0' === $_GET['updated'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Could not save rule.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule deleted.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgcm_error'] ) && 'notfound' === $_GET['rwgcm_error'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Rule not found.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php elseif ( ! empty( $_GET['rwgcm_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Something went wrong.', 'reactwoo-geo-commerce' ); ?></p></div>
	<?php endif; ?>

	<nav class="rwgcm-rules-filter-nav" aria-label="<?php esc_attr_e( 'Rule filters', 'reactwoo-geo-commerce' ); ?>">
		<a class="<?php echo 'all' === $filter ? 'is-active' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'All rules', 'reactwoo-geo-commerce' ); ?></a>
		<a class="<?php echo 'pricing' === $filter ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'rwgcm_filter', 'pricing', $base_url ) ); ?>"><?php esc_html_e( 'Pricing actions', 'reactwoo-geo-commerce' ); ?></a>
		<a class="<?php echo 'display' === $filter ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'rwgcm_filter', 'display', $base_url ) ); ?>"><?php esc_html_e( 'Display actions', 'reactwoo-geo-commerce' ); ?></a>
	</nav>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add rule', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<table class="widefat striped rwgcm-rules-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Scope', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Summary', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Manage', 'reactwoo-geo-commerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rules ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No rules yet. Add your first rule to combine conditions and actions.', 'reactwoo-geo-commerce' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rules as $r ) : ?>
				<?php
				if ( ! is_array( $r ) ) {
					continue;
				}
				$rid     = isset( $r['id'] ) ? (int) $r['id'] : 0;
				$scope   = isset( $r['scope']['type'] ) ? (string) $r['scope']['type'] : '';
				$tags    = class_exists( 'RWGCM_Rule_Summary', false ) ? RWGCM_Rule_Summary::get_action_tags( $r ) : array();
				$summary = class_exists( 'RWGCM_Rule_Summary', false ) ? RWGCM_Rule_Summary::summarize_rule( $r ) : '';
				$edit_url = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=' . $rid );
				$del_url  = wp_nonce_url(
					admin_url( 'admin-post.php?action=rwgcm_delete_generic_rule&rule_id=' . $rid ),
					'rwgcm_delete_rule_' . $rid
				);
				?>
				<tr>
					<td><?php echo esc_html( (string) $rid ); ?></td>
					<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( isset( $r['label'] ) ? (string) $r['label'] : '' ); ?></a></td>
					<td><?php echo esc_html( isset( $r['status'] ) ? (string) $r['status'] : '' ); ?></td>
					<td><?php echo esc_html( (string) ( isset( $r['priority'] ) ? (int) $r['priority'] : 0 ) ); ?></td>
					<td><code><?php echo esc_html( $scope ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', $tags ) ); ?></td>
					<td class="rwgcm-rule-summary-cell"><?php echo esc_html( $summary ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-commerce' ); ?></a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'reactwoo-geo-commerce' ) ); ?>');"><?php esc_html_e( 'Delete', 'reactwoo-geo-commerce' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
