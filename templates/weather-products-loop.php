<?php
/**
 * Weather products loop template.
 *
 * @package ReactWoo_Geo_Commerce
 * @var array<string, mixed> $args
 * @var array{product_ids: int[], mode: string, message: string} $result
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes = array( 'rwgcm-weather-products', 'woocommerce', 'columns-' . (int) $args['columns'] );
if ( ! empty( $args['class'] ) ) {
	$classes[] = $args['class'];
}
?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<?php if ( ! empty( $args['title'] ) ) : ?>
		<h2 class="rwgcm-weather-products__title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
	<?php endif; ?>
	<ul class="products columns-<?php echo esc_attr( (string) (int) $args['columns'] ); ?>">
		<?php
		foreach ( $result['product_ids'] as $pid ) :
			$product = wc_get_product( (int) $pid );
			if ( ! $product ) {
				continue;
			}
			$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wc_get_template_part( 'content', 'product' );
		endforeach;
		?>
	</ul>
</div>
