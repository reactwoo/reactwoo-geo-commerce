<?php
/**
 * Elementor widget — weather products.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor weather products widget.
 */
class RWGCM_Elementor_Weather_Products extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'rwgcm-weather-products';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Weather Products', 'reactwoo-geo-commerce' );
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
	public function get_categories() {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'weather', 'products', 'woocommerce', 'reactwoo', 'geo' );
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
			'title',
			array(
				'label'   => __( 'Heading', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Max products', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 8,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 6,
				'default' => 4,
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => __( 'Category slug or ID', 'reactwoo-geo-commerce' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'rain-gear',
			)
		);

		$this->add_control(
			'ids',
			array(
				'label'       => __( 'Product IDs', 'reactwoo-geo-commerce' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Comma-separated product IDs (optional manual pool).', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'relevance',
				'options' => array(
					'relevance'  => __( 'Weather relevance', 'reactwoo-geo-commerce' ),
					'date'       => __( 'Date', 'reactwoo-geo-commerce' ),
					'menu_order' => __( 'Menu order', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'   => __( 'When no weather match', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'hide',
				'options' => array(
					'hide'     => __( 'Hide', 'reactwoo-geo-commerce' ),
					'category' => __( 'Fallback category', 'reactwoo-geo-commerce' ),
					'message'  => __( 'Message', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'fallback_category',
			array(
				'label'   => __( 'Fallback category', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'fallback_message',
			array(
				'label'   => __( 'Fallback message', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->add_control(
			'weather_unavailable',
			array(
				'label'   => __( 'When weather unavailable', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'hide',
				'options' => array(
					'hide'     => __( 'Hide', 'reactwoo-geo-commerce' ),
					'category' => __( 'Fallback category', 'reactwoo-geo-commerce' ),
					'message'  => __( 'Message', 'reactwoo-geo-commerce' ),
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
		$args     = array(
			'title'               => isset( $settings['title'] ) ? (string) $settings['title'] : '',
			'limit'               => isset( $settings['limit'] ) ? (int) $settings['limit'] : 8,
			'columns'             => isset( $settings['columns'] ) ? (int) $settings['columns'] : 4,
			'category'            => isset( $settings['category'] ) ? (string) $settings['category'] : '',
			'ids'                 => isset( $settings['ids'] ) ? (string) $settings['ids'] : '',
			'orderby'             => isset( $settings['orderby'] ) ? (string) $settings['orderby'] : 'relevance',
			'fallback'            => isset( $settings['fallback'] ) ? (string) $settings['fallback'] : 'hide',
			'fallback_category'   => isset( $settings['fallback_category'] ) ? (string) $settings['fallback_category'] : '',
			'fallback_message'    => isset( $settings['fallback_message'] ) ? (string) $settings['fallback_message'] : '',
			'weather_unavailable' => isset( $settings['weather_unavailable'] ) ? (string) $settings['weather_unavailable'] : 'hide',
			'class'               => 'rwgcm-elementor-weather-products',
		);
		echo RWGCM_Weather_Products_Display::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
