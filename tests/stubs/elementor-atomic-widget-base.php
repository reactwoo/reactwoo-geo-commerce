<?php
/**
 * Stand-in loaded only by the autoloader in tests/test-elementor-atomic-autoload.php.
 *
 * @package ReactWoo_Geo_Commerce
 */

namespace Elementor\Modules\AtomicWidgets\Elements\Base;

trait Has_Template {
}

class Atomic_Widget_Base {
	public function __construct() {
	}

	public function get_name() {
		return static::get_element_type();
	}
}
