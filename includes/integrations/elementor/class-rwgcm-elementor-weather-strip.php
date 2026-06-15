<?php
/**
 * Elementor widget — visitor weather strip.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact weather facet strip for Elementor layouts.
 */
class RWGCM_Elementor_Weather_Strip extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'rwgcm-weather-strip';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Weather Strip', 'reactwoo-geo-commerce' );
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
	public function get_categories() {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'   => __( 'When weather unavailable', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'hide',
				'options' => array(
					'hide'    => __( 'Hide', 'reactwoo-geo-commerce' ),
					'message' => __( 'Show message', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'   => __( 'Link target', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''     => __( 'Use merchandising default', 'reactwoo-geo-commerce' ),
					'none' => __( 'None', 'reactwoo-geo-commerce' ),
					'shop' => __( 'Shop page', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		echo RWGCM_Weather_Strip::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'fallback' => isset( $settings['fallback'] ) ? (string) $settings['fallback'] : 'hide',
				'class'    => 'rwgcm-elementor-weather-strip',
				'link'     => isset( $settings['link'] ) ? (string) $settings['link'] : '',
			)
		);
	}
}
