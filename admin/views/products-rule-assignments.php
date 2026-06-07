<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$product_rules            = isset( $product_rules ) && is_array( $product_rules ) ? $product_rules : array();
$rwgc_nav_current         = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgcm-products';
$rwgcm_use_platform_shell = class_exists( 'RWGCM_Admin', false ) && RWGCM_Admin::uses_platform_shell();
$new_rule_url             = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=new' );
$rules_url                = admin_url( 'admin.php?page=rwgcm-pricing' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgcm-wrap rwgcm-wrap--products">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Rule assignments', 'reactwoo-geo-commerce' ),
			__( 'Rules scoped to individual products. Create display actions (badges, notices, overlays) or pricing adjustments per product.', 'reactwoo-geo-commerce' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Rule assignments', 'reactwoo-geo-commerce' ); ?></h1>
	<?php endif; ?>

	<?php if ( ! $rwgcm_use_platform_shell ) : ?>
		<?php RWGCM_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Product-scoped rules live in the unified Rules model. Use Add rule and choose Products in Step 2, or filter rules by product below.', 'reactwoo-geo-commerce' ); ?>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_rule_url ); ?>"><?php esc_html_e( 'Add product rule', 'reactwoo-geo-commerce' ); ?></a>
		<a class="button" href="<?php echo esc_url( $rules_url ); ?>"><?php esc_html_e( 'All rules', 'reactwoo-geo-commerce' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Rule', 'reactwoo-geo-commerce' ); ?></th>
				<th><?php esc_html_e( 'Product', 'reactwoo-geo-commerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'reactwoo-geo-commerce' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'reactwoo-geo-commerce' ); ?></th>
				<th><?php esc_html_e( 'Summary', 'reactwoo-geo-commerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $product_rules ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No product-scoped rules yet.', 'reactwoo-geo-commerce' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $product_rules as $rule ) : ?>
				<?php
				if ( ! is_array( $rule ) ) {
					continue;
				}
				$rid       = isset( $rule['id'] ) ? (int) $rule['id'] : 0;
				$pid       = ! empty( $rule['scope']['ids'][0] ) ? (int) $rule['scope']['ids'][0] : 0;
				$pname     = $pid > 0 ? get_the_title( $pid ) : '';
				$tags      = class_exists( 'RWGCM_Rule_Summary', false ) ? RWGCM_Rule_Summary::get_action_tags( $rule ) : array();
				$summary   = class_exists( 'RWGCM_Rule_Summary', false ) ? RWGCM_Rule_Summary::summarize_rule( $rule ) : '';
				$edit_url  = admin_url( 'admin.php?page=rwgcm-pricing&rwgcm_edit=' . $rid );
				?>
				<tr>
					<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( isset( $rule['label'] ) ? (string) $rule['label'] : '' ); ?></a></td>
					<td><?php echo esc_html( $pname ? $pname : (string) $pid ); ?></td>
					<td><?php echo esc_html( isset( $rule['status'] ) ? (string) $rule['status'] : '' ); ?></td>
					<td><?php echo esc_html( implode( ', ', $tags ) ); ?></td>
					<td><?php echo esc_html( $summary ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
