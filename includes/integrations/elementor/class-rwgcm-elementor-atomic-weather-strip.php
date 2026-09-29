<?php
/**
 * Elementor Atomic twin of Weather Strip.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Modules\AtomicWidgets\Controls\Section;
use Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control;
use Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control;
use Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base;
use Elementor\Modules\AtomicWidgets\Elements\Base\Has_Template;
use Elementor\Modules\AtomicWidgets\PropTypes\Attributes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Classes_Prop_Type;
use Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type;
use Elementor\Modules\Components\PropTypes\Overridable_Prop_Type;

/**
 * V4 Weather Strip. Classic widget rwgcm-weather-strip stays registered.
 */
class RWGCM_Elementor_Atomic_Weather_Strip extends Atomic_Widget_Base {

	use Has_Template;

	/**
	 * @var string|null
	 */
	public static $widget_description = 'Visitor weather strip for the Elementor Atomic editor.';

	/**
	 * @return string
	 */
	public static function get_element_type(): string {
		return 'rwgcm-weather-strip-atomic';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Weather Strip', 'reactwoo-geo-commerce' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-info-circle';
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'weather', 'strip', 'woocommerce', 'reactwoo', 'geo', 'atomic' );
	}

	/**
	 * @return array<string, mixed>
	 */
	protected static function define_props_schema(): array {
		$string = String_Prop_Type::class;
		return array(
			'classes'    => Classes_Prop_Type::make()->default( array() ),
			'attributes' => Attributes_Prop_Type::make()->meta( Overridable_Prop_Type::ignore() ),
			'fallback'   => $string::make()->enum( array( 'hide', 'message' ) )->default( 'hide' ),
			'link'       => $string::make()->enum( array( '', 'none', 'shop' ) )->default( '' ),
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
			Select_Control::bind_to( 'fallback' )->set_label( __( 'When weather unavailable', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => 'hide', 'label' => __( 'Hide', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'message', 'label' => __( 'Show message', 'reactwoo-geo-commerce' ) ),
				)
			),
			Select_Control::bind_to( 'link' )->set_label( __( 'Link target', 'reactwoo-geo-commerce' ) )->set_options(
				array(
					array( 'value' => '', 'label' => __( 'Use merchandising default', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'none', 'label' => __( 'None', 'reactwoo-geo-commerce' ) ),
					array( 'value' => 'shop', 'label' => __( 'Shop page', 'reactwoo-geo-commerce' ) ),
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
			'rwgcm/weather-strip-atomic' => RWGCM_PATH . 'includes/integrations/elementor/templates/weather-strip.html.twig',
		);
	}

	/**
	 * Published and preview output uses the classic renderer.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_atomic_settings();
		echo RWGCM_Weather_Strip::render( RWGCM_Elementor_Atomic::strip_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
