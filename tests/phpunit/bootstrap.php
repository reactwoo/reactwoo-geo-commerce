<?php
/**
 * WordPress and WooCommerce stand-ins for order geo attribution tests.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['rw_actions']          = array();
$GLOBALS['rw_filters']          = array();
$GLOBALS['rw_orders']           = array();
$GLOBALS['rw_country']          = 'ZA';
$GLOBALS['rw_visitor']          = array();
$GLOBALS['rw_geo_throw']        = false;
$GLOBALS['rw_postmeta_calls']   = 0;
$GLOBALS['rw_wc_get_order_calls'] = 0;
$GLOBALS['rw_did']              = array();

/**
 * @param string   $hook          Hook.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted args.
 * @return void
 */
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['rw_actions'][ $hook ][ (int) $priority ][] = array( $callback, (int) $accepted_args );
}

/**
 * @param string $hook Hook.
 * @return void
 */
function do_action( $hook ) {
	$args = func_get_args();
	array_shift( $args );
	$GLOBALS['rw_did'][ $hook ][] = $args;
	if ( empty( $GLOBALS['rw_actions'][ $hook ] ) || ! is_array( $GLOBALS['rw_actions'][ $hook ] ) ) {
		return;
	}
	ksort( $GLOBALS['rw_actions'][ $hook ] );
	foreach ( $GLOBALS['rw_actions'][ $hook ] as $callbacks ) {
		foreach ( $callbacks as $cb ) {
			call_user_func_array( $cb[0], array_slice( $args, 0, $cb[1] ) );
		}
	}
}

/**
 * @param string   $hook     Hook.
 * @param callable $callback Callback.
 * @return void
 */
function add_filter( $hook, $callback ) {
	$GLOBALS['rw_filters'][ $hook ][] = $callback;
}

/**
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	$args = func_get_args();
	if ( empty( $GLOBALS['rw_filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['rw_filters'][ $hook ] as $callback ) {
		$args[1] = $value;
		$value   = call_user_func_array( $callback, array_slice( $args, 1 ) );
	}
	return $value;
}

/**
 * @param mixed $maybeint Value.
 * @return int
 */
function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

/**
 * @param string $str Text.
 * @return string
 */
function sanitize_text_field( $str ) {
	$str = is_string( $str ) ? $str : '';
	return trim( strip_tags( $str ) );
}

/**
 * @param mixed $order Order or ID.
 * @return \WC_Order|false
 */
function wc_get_order( $order ) {
	++$GLOBALS['rw_wc_get_order_calls'];
	if ( is_object( $order ) && is_a( $order, 'WC_Order' ) ) {
		return $order;
	}
	$id = (int) $order;
	return isset( $GLOBALS['rw_orders'][ $id ] ) ? $GLOBALS['rw_orders'][ $id ] : false;
}

/**
 * @return void
 */
function update_post_meta() {
	++$GLOBALS['rw_postmeta_calls'];
}

/**
 * @return void
 */
function add_post_meta() {
	++$GLOBALS['rw_postmeta_calls'];
}

/**
 * @return void
 */
function delete_post_meta() {
	++$GLOBALS['rw_postmeta_calls'];
}

/**
 * @param int    $post_id Post ID.
 * @param string $key     Key.
 * @param bool   $single  Single.
 * @return mixed
 */
function get_post_meta( $post_id = 0, $key = '', $single = false ) {
	unset( $key );
	++$GLOBALS['rw_postmeta_calls'];
	$id = (int) $post_id;
	if ( isset( $GLOBALS['rw_books'] ) && is_array( $GLOBALS['rw_books'] ) && array_key_exists( $id, $GLOBALS['rw_books'] ) ) {
		return $GLOBALS['rw_books'][ $id ];
	}
	return $single ? '' : array();
}

/**
 * @return string
 */
function rwgc_get_visitor_country() {
	if ( ! empty( $GLOBALS['rw_geo_throw'] ) ) {
		throw new RuntimeException( 'visitor country was read after the order was already captured' );
	}
	return (string) $GLOBALS['rw_country'];
}

/**
 * @return array<string, mixed>
 */
function rwgc_get_visitor_data() {
	if ( ! empty( $GLOBALS['rw_geo_throw'] ) ) {
		throw new RuntimeException( 'visitor data was read after the order was already captured' );
	}
	return is_array( $GLOBALS['rw_visitor'] ) ? $GLOBALS['rw_visitor'] : array();
}

/**
 * HPOS-style order. Meta lives on the object, not in post meta.
 */
class WC_Order {

	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var array<string, mixed>
	 */
	private $meta = array();

	/**
	 * @var int
	 */
	public $saves = 0;

	/**
	 * @var string
	 */
	private $currency = '';

	/**
	 * @var string
	 */
	private $billing_country = '';

	/**
	 * @var array<int, object>
	 */
	private $items = array();

	/**
	 * @param int $id Order ID.
	 */
	public function __construct( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * @param string $currency Currency code.
	 * @return void
	 */
	public function set_currency( $currency ) {
		$this->currency = (string) $currency;
	}

	/**
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @param string $country Billing ISO2.
	 * @return void
	 */
	public function set_billing_country( $country ) {
		$this->billing_country = (string) $country;
	}

	/**
	 * @return string
	 */
	public function get_billing_country() {
		return $this->billing_country;
	}

	/**
	 * @param array<int, object> $items Line items.
	 * @return void
	 */
	public function set_line_items( array $items ) {
		$this->items = $items;
	}

	/**
	 * @param string $type Item type.
	 * @return array<int, object>
	 */
	public function get_items( $type = 'line_item' ) {
		if ( 'shipping' === $type ) {
			return array();
		}
		unset( $type );
		return $this->items;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param string $key    Meta key.
	 * @param bool   $single Single value.
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		unset( $single );
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}

	/**
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * @return int
	 */
	public function save() {
		++$this->saves;
		$GLOBALS['rw_orders'][ $this->id ] = $this;
		return $this->id;
	}
}

require dirname( __DIR__, 2 ) . '/includes/class-rwgcm-order-geo.php';
