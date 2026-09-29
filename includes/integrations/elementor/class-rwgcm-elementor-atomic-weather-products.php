<?php
/**
 * Elementor Atomic twin of Weather Products.
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
 * V4 Weather Products. Classic widget rwgcm-weather-products stays registered.
 */
class RWGCM_Elementor_Atomic_Weather_Products extends Atomic_Widget_Base {

	use Has_Template;

	/**
	 * @var string|null
	 */
	public static $widget_description = 'Weather-matched products for the Elementor Atomic editor.';

	/**
	 * @return string
	 */
	public static function get_element_type(): string {
		return 'rwgcm-weather-products-atomic';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Weather Products', 'reactwoo-geo-commerce' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-products';
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'weather', 'products', 'woocommerce', 'reactwoo', 'geo', 'atomic' );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected static function define_props_schema(): array {
		$string = String_Prop_Type::class;
		$number = Number_Prop_Type::class;
		return array(
			'classes'             => Classes_Prop_Type::make()->default( array() ),
			'attributes'          => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
			'title'               => $string::make()->default( '' ),
			'limit'               => $number::make()->default( 8 ),
			'columns'             => $number::make()->default( 4 ),
			'category'            => $string::make()->default( '' ),
			'ids'                 => $string::make()->default( '' ),
			'orderby'             => $string::make()->enum( array( 'relevance', 'date', 'menu_order' ) )->default( 'relevance' ),
			'fallback'            => $string::make()->enum( array( 'hide', 'category', 'message' ) )->default( 'hide' ),
			'fallback_category'   => $string::make()->default( '' ),
			'fallback_message'    => $string::make()->default( '' ),
			'weather_unavailable' => $string::make()->enum( array( 'hide', 'category', 'message' ) )->default( 'hide' ),
		);
	}

	/**
	 * @return array<int, mixed>
	 */
	protected function define_atomic_controls(): array {
		return array(
			Section::make()
				->set_label( __( 'Content', 'reactwoo-geo-commerce' ) )
				->set_id( 'settings' )
				->set_items( $this->content_controls() ),
		);
	}

	/**
	 * @return array<int, mixed>
	 */
	private function content_controls() {
		return array(
			Text_Control::bind_to( 'title' )->set_label( __( 'Heading', 'reactwoo-geo-commerce' ) ),
			Number_Control::bind_to( 'limit' )->set_label( __( 'Max products', 'reactwoo-geo-commerce' ) )->set_min( 1 )->set_max( 24 ),
			Number_Control::bind_to( 'columns' )->set_label( __( 'Columns', 'reactwoo-geo-commerce' ) )->set_min( 1 )->set_max( 6 ),
			Text_Control::bind_to( 'category' )->set_label( __( 'Category slug or ID', 'reactwoo-geo-commerce' ) )->set_placeholder( 'rain-gear' ),
			Text_Control::bind_to( 'ids' )->set_label( __( 'Product IDs', 'reactwoo-geo-commerce' ) ),
			Select_Control::bind_to( 'orderby' )->set_label( __( 'Order by', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'relevance', 'label' => __( 'Weather relevance', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'date', 'label' => __( 'Date', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'menu_order', 'label' => __( 'Menu order', 'reactwoo-geo-commerce' ) ),
				)
			),
			Select_Control::bind_to( 'fallback' )->set_label( __( 'When no weather match', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'hide', 'label' => __( 'Hide', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'category', 'label' => __( 'Fallback category', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'message', 'label' => __( 'Message', 'reactwoo-geo-commerce' ) ),
				)
			),
			Text_Control::bind_to( 'fallback_category' )->set_label( __( 'Fallback category', 'reactwoo-geo-commerce' ) ),
			Text_Control::bind_to( 'fallback_message' )->set_label( __( 'Fallback message', 'reactwoo-geo-commerce' ) ),
			Select_Control::bind_to( 'weather_unavailable' )->set_label( __( 'When weather unavailable', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'hide', 'label' => __( 'Hide', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'category', 'label' => __( 'Fallback category', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'message', 'label' => __( 'Message', 'reactwoo-geo-commerce' ) ),
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
			'rwgcm/weather-products-atomic' => RWGCM_PATH . 'includes/integrations/elementor/templates/weather-products.html.twig',
		);
	}

	/**
	 * Published and preview output uses the classic renderer.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_atomic_settings();
		echo RWGCM_Weather_Products_Display::render( RWGCM_Elementor_Atomic::products_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
