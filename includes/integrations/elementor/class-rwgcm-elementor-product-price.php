<?php
/**
 * Elementor widget — localised product price.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic Product Price widget. The Atomic twin is rwgcm-product-price-atomic.
 */
class RWGCM_Elementor_Product_Price extends \Elementor\Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'rwgcm-product-price';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Product Price', 'reactwoo-geo-commerce' );
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
	public function get_categories() {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'price', 'currency', 'product', 'woocommerce', 'reactwoo', 'geo' );
	}

	/**
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( 'rwgcm-product-price' );
	}

	/**
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Product Price', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'       => __( 'Product ID', 'reactwoo-geo-commerce' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => __( 'Leave empty to use the current product.', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'variation_id',
			array(
				'label'   => __( 'Variation ID', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 0,
			)
		);

		$this->add_control(
			'format',
			array(
				'label'   => __( 'Format', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'symbol',
				'options' => array(
					'symbol' => __( 'Currency symbol', 'reactwoo-geo-commerce' ),
					'amount' => __( 'Amount only', 'reactwoo-geo-commerce' ),
					'code'   => __( 'Currency code', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'period',
			array(
				'label'       => __( 'Billing period suffix', 'reactwoo-geo-commerce' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '/year',
				'description' => __( 'Leave empty to use the subscription period when the product has one.', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'from',
			array(
				'label'   => __( 'From prefix', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto' => __( 'Automatic (lowest variation differs)', 'reactwoo-geo-commerce' ),
					'yes'  => __( 'Always on variable products', 'reactwoo-geo-commerce' ),
					'no'   => __( 'Never', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'sale',
			array(
				'label'   => __( 'Sale price', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'yes',
				'options' => array(
					'yes' => __( 'Show struck-through regular price', 'reactwoo-geo-commerce' ),
					'no'  => __( 'Active price only', 'reactwoo-geo-commerce' ),
				),
			)
		);

		$this->add_control(
			'fallback',
			array(
				'label'       => __( 'Fallback text', 'reactwoo-geo-commerce' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Unavailable', 'reactwoo-geo-commerce' ),
				'description' => __( 'Shown when the product is missing, not purchasable, or out of stock.', 'reactwoo-geo-commerce' ),
			)
		);

		$this->add_control(
			'color',
			array(
				'label'   => __( 'Colour', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inherit',
				'options' => array(
					'inherit'   => __( 'Inherit', 'reactwoo-geo-commerce' ),
					'primary'   => __( 'Primary', 'reactwoo-geo-commerce' ),
					'secondary' => __( 'Secondary', 'reactwoo-geo-commerce' ),
					'accent'    => __( 'Accent', 'reactwoo-geo-commerce' ),
					'text'      => __( 'Text', 'reactwoo-geo-commerce' ),
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
		echo RWGCM_Product_Price::render( is_array( $settings ) ? $settings : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
