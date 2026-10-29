<?php
/**
 * Elementor Atomic twin of Product Price.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Number_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

/**
 * V4 Product Price. Classic widget rwgcm-product-price stays registered.
 */
class RWGCM_Elementor_Atomic_Product_Price extends Atomic_Widget_Base {

	use Has_Template;

	/**
	 * @var string|null
	 */
	public static $widget_description = 'Localised product price for the Elementor Atomic editor.';

	/**
	 * @return string
	 */
	public static function get_element_type(): string {
		return 'rwgcm-product-price-atomic';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Product Price', 'reactwoo-geo-commerce' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-product-price';
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'price', 'currency', 'product', 'woocommerce', 'reactwoo', 'geo', 'atomic' );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected static function define_props_schema(): array {
		$string = String_Prop_Type::class;
		$number = Number_Prop_Type::class;
		return array(
			'classes'      => Classes_Prop_Type::make()->default( array() ),
			'attributes'   => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
			'product_id'   => $number::make()->default( 0 ),
			'variation_id' => $number::make()->default( 0 ),
			'format'       => $string::make()->enum( array( 'symbol', 'amount', 'code' ) )->default( 'symbol' ),
			'period'       => $string::make()->default( '' ),
			'from'         => $string::make()->enum( array( 'auto', 'yes', 'no' ) )->default( 'auto' ),
			'sale'         => $string::make()->enum( array( 'yes', 'no' ) )->default( 'yes' ),
			'fallback'     => $string::make()->default( 'Unavailable' ),
			'color'        => $string::make()->enum( array( 'inherit', 'primary', 'secondary', 'accent', 'text' ) )->default( 'inherit' ),
		);
	}

	/**
	 * @return array<int, mixed>
	 */
	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( __( 'Product Price', 'reactwoo-geo-commerce' ) )
				->set_id( 'settings' )
				->set_items( $this->content_controls() ),
		);
	}

	/**
	 * @return array<int, mixed>
	 */
	private function content_controls() {
		return array(
			Number_Control::bind_to( 'product_id' )->set_label( __( 'Product ID', 'reactwoo-geo-commerce' ) )->set_min( 0 ),
			Number_Control::bind_to( 'variation_id' )->set_label( __( 'Variation ID', 'reactwoo-geo-commerce' ) )->set_min( 0 ),
			Select_Control::bind_to( 'format' )->set_label( __( 'Format', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'symbol', 'label' => __( 'Currency symbol', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'amount', 'label' => __( 'Amount only', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'code', 'label' => __( 'Currency code', 'reactwoo-geo-commerce' ) ),
				)
			),
			Text_Control::bind_to( 'period' )->set_label( __( 'Billing period suffix', 'reactwoo-geo-commerce' ) )->set_placeholder( '/year' ),
			Select_Control::bind_to( 'from' )->set_label( __( 'From prefix', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'auto', 'label' => __( 'Automatic', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'yes', 'label' => __( 'Always', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'no', 'label' => __( 'Never', 'reactwoo-geo-commerce' ) ),
				)
			),
			Select_Control::bind_to( 'sale' )->set_label( __( 'Sale price', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'yes', 'label' => __( 'Show struck-through regular price', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'no', 'label' => __( 'Active price only', 'reactwoo-geo-commerce' ) ),
				)
			),
			Text_Control::bind_to( 'fallback' )->set_label( __( 'Fallback text', 'reactwoo-geo-commerce' ) ),
			Select_Control::bind_to( 'color' )->set_label( __( 'Colour', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'inherit', 'label' => __( 'Inherit', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'primary', 'label' => __( 'Primary', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'secondary', 'label' => __( 'Secondary', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'accent', 'label' => __( 'Accent', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'text', 'label' => __( 'Text', 'reactwoo-geo-commerce' ) ),
				)
			),
			Text_Control::bind_to( '_cssid' )->set_label( __( 'ID', 'reactwoo-geo-commerce' ) )->set_meta( $this->get_css_id_control_meta() ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	protected function get_templates(): array {
		return array(
			'rwgcm/product-price-atomic' => RWGCM_PATH . 'includes/integrations/elementor/templates/product-price.html.twig',
		);
	}

	/**
	 * Published and preview output uses the shared renderer.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_atomic_settings();
		echo RWGCM_Product_Price::render( is_array( $settings ) ? $settings : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
