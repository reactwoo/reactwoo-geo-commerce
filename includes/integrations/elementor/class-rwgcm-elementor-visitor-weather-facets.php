<?php
/**
 * Elementor dynamic tag — visitor shopping weather facets.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs current visitor weather facet slugs or labels in Elementor templates.
 */
class RWGCM_Elementor_Visitor_Weather_Facets extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'rwgcm-visitor-weather-facets';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Visitor Weather Facets', 'reactwoo-geo-commerce' );
	}

	/**
	 * @return string
	 */
	public function get_group() {
		return 'woocommerce';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * @return void
	 */
	protected function register_controls() {
		$this->add_control(
			'format',
			array(
				'label'   => __( 'Format', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'labels',
				'options' => array(
					'labels' => __( 'Human labels', 'reactwoo-geo-commerce' ),
					'slugs'  => __( 'Facet slugs', 'reactwoo-geo-commerce' ),
				),
			)
		);
		$this->add_control(
			'separator',
			array(
				'label'   => __( 'Separator', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => ', ',
			)
		);
		$this->add_control(
			'fallback',
			array(
				'label'   => __( 'When unavailable', 'reactwoo-geo-commerce' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);
	}

	/**
	 * @return void
	 */
	public function render() {
		$facets = RWGCM_Weather_Affinity::get_visitor_facets();
		if ( empty( $facets ) ) {
			echo esc_html( (string) $this->get_settings( 'fallback' ) );
			return;
		}
		$sep = (string) $this->get_settings( 'separator' );
		if ( 'slugs' === $this->get_settings( 'format' ) ) {
			echo esc_html( implode( $sep, $facets ) );
			return;
		}
		$labels = array();
		foreach ( $facets as $slug ) {
			$labels[] = RWGCM_Weather_Affinity::format_facet_value_label( $slug );
		}
		echo esc_html( implode( $sep, $labels ) );
	}
}
