<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rules            = isset( $rules ) && is_array( $rules ) ? $rules : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-pricing';
$legacy_url       = admin_url( 'admin.php?page=rwgcm-legacy-pricing' );
$new_url          = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=new' );
?>
<div class="wrap rwgc-wrap rwgcm-wrap rwgcm-wrap--rules-generic">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Rules', 'reactwoo-geo-commerce' ),
			__( 'Commerce rules use Geo Core target types (country, device, language, …). More specific scopes and conditions win over broader ones.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Rules', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>

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

	<p class="description">
		<?php esc_html_e( 'Legacy country-only rows (option-based) remain available under “Legacy country rows”. New rules are stored in the database and drive storefront pricing when at least one rule is active.', 'reactwoo-geo-commerce' ); ?>
		<a href="<?php echo esc_url( $legacy_url ); ?>"><?php esc_html_e( 'Open legacy country rows', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add rule', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Label', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Scope', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conditions', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Price adjust', 'reactwoo-geo-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Manage', 'reactwoo-geo-commerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rules ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No generic rules yet.', 'reactwoo-geo-commerce' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rules as $r ) : ?>
				<?php
				if ( ! is_array( $r ) ) {
					continue;
				}
				$rid    = isset( $r['id'] ) ? (int) $r['id'] : 0;
				$scope = isset( $r['scope']['type'] ) ? (string) $r['scope']['type'] : '';
				$sids  = isset( $r['scope']['ids'] ) && is_array( $r['scope']['ids'] ) ? implode( ',', array_map( 'intval', $r['scope']['ids'] ) ) : '';
				$condn = isset( $r['conditions']['items'] ) && is_array( $r['conditions']['items'] ) ? count( $r['conditions']['items'] ) : 0;
				$act   = '';
				if ( ! empty( $r['actions'] ) && is_array( $r['actions'] ) ) {
					foreach ( $r['actions'] as $a ) {
						if ( is_array( $a ) && isset( $a['type'] ) && 'price_adjustment' === $a['type'] ) {
							$act = isset( $a['mode'] ) ? (string) $a['mode'] : '';
							$act .= ' ' . ( isset( $a['value'] ) ? (string) $a['value'] : '' );
							break;
						}
					}
				}
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
					<td><code><?php echo esc_html( $scope ); ?></code><?php echo $sids !== '' ? ' <span class="description">(' . esc_html( $sids ) . ')</span>' : ''; ?></td>
					<td><?php echo esc_html( (string) $condn ); ?></td>
					<td><code><?php echo esc_html( $act ); ?></code></td>
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
