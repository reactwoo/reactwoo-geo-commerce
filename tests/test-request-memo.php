<?php
/**
 * Per-request memoisation for rule table/list lookups and resolved adjustments
 * (run with: php tests/test-request-memo.php).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

$GLOBALS['rw_options']         = array();
$GLOBALS['rw_visitor_country'] = 'ZA';
$GLOBALS['rw_currency']        = 'ZAR';
$GLOBALS['rw_context']         = array( 'country' => 'ZA' );
$GLOBALS['rw_filters']         = array();

function __( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}
function absint( $value ) { // phpcs:ignore
	return abs( (int) $value );
}
function sanitize_key( $key ) { // phpcs:ignore
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}
function sanitize_text_field( $text ) { // phpcs:ignore
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}
function wp_json_encode( $value ) { // phpcs:ignore
	return json_encode( $value );
}
function wp_kses_post( $html ) { // phpcs:ignore
	return (string) $html;
}
function current_time( $type, $gmt = 0 ) { // phpcs:ignore
	unset( $type, $gmt );
	return '2026-11-25 12:00:00';
}
function get_option( $key, $default = false ) { // phpcs:ignore
	if ( is_array( $GLOBALS['rw_options'] ) && array_key_exists( $key, $GLOBALS['rw_options'] ) ) {
		return $GLOBALS['rw_options'][ $key ];
	}
	return $default;
}
function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
	unset( $autoload );
	$GLOBALS['rw_options'][ $key ] = $value;
	return true;
}
function apply_filters( $tag, $value ) { // phpcs:ignore
	if ( empty( $GLOBALS['rw_filters'][ $tag ] ) || ! is_array( $GLOBALS['rw_filters'][ $tag ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['rw_filters'][ $tag ] as $cb ) {
		$value = call_user_func( $cb, $value );
	}
	return $value;
}
function get_woocommerce_currency() { // phpcs:ignore
	return (string) $GLOBALS['rw_currency'];
}
function rwgc_get_visitor_country() { // phpcs:ignore
	return (string) $GLOBALS['rw_visitor_country'];
}
function rwgc_get_context_snapshot() { // phpcs:ignore
	return is_array( $GLOBALS['rw_context'] ) ? $GLOBALS['rw_context'] : array();
}

class WC_Product {} // phpcs:ignore

class RW_Memo_Product extends WC_Product {
	public $id;
	public $cats;

	public function __construct( $id, $cats = array() ) {
		$this->id   = (int) $id;
		$this->cats = array_map( 'intval', $cats );
	}

	public function get_id() {
		return $this->id;
	}

	public function get_category_ids() {
		return $this->cats;
	}
}

class RW_Memo_Wpdb {
	public $prefix     = 'wp_';
	public $last_error = '';
	public $insert_id  = 0;
	public $queries    = array();
	public $tables     = array();
	public $next_id    = 1;

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback(
			'/%[sd]/',
			static function ( $m ) use ( $args, &$i ) {
				$v = isset( $args[ $i ] ) ? $args[ $i ] : '';
				++$i;
				if ( '%d' === $m[0] ) {
					return (string) (int) $v;
				}
				return "'" . str_replace( "'", "''", (string) $v ) . "'";
			},
			(string) $query
		);
	}

	public function get_var( $sql ) {
		$this->queries[] = (string) $sql;
		if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", (string) $sql, $m ) ) {
			return array_key_exists( $m[1], $this->tables ) ? $m[1] : null;
		}
		if ( preg_match( '/SELECT COUNT\(\*\) FROM (\S+) WHERE status = \'([^\']+)\'/', (string) $sql, $m ) ) {
			$n = 0;
			foreach ( $this->rows( $m[1] ) as $row ) {
				if ( isset( $row['status'] ) && (string) $row['status'] === $m[2] ) {
					++$n;
				}
			}
			return $n;
		}
		return null;
	}

	public function get_results( $sql, $output = ARRAY_A ) {
		unset( $output );
		$this->queries[] = (string) $sql;
		if ( preg_match( '/SELECT \* FROM (\S+)/', (string) $sql, $m ) ) {
			return $this->rows( $m[1] );
		}
		return array();
	}

	public function get_row( $sql, $output = ARRAY_A ) {
		unset( $output );
		$this->queries[] = (string) $sql;
		if ( preg_match( '/SELECT \* FROM (\S+) WHERE id = (\d+)/', (string) $sql, $m ) ) {
			foreach ( $this->rows( $m[1] ) as $row ) {
				if ( isset( $row['id'] ) && (int) $row['id'] === (int) $m[2] ) {
					return $row;
				}
			}
		}
		return null;
	}

	public function insert( $table, $data ) {
		$this->queries[] = 'INSERT ' . $table;
		$row             = is_array( $data ) ? $data : array();
		$row['id']       = $this->next_id++;
		$this->tables[ $table ][] = $row;
		$this->insert_id          = (int) $row['id'];
		return 1;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );
		$this->queries[] = 'UPDATE ' . $table;
		$id              = isset( $where['id'] ) ? (int) $where['id'] : 0;
		$found           = false;
		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}
		foreach ( $this->tables[ $table ] as $i => $row ) {
			if ( isset( $row['id'] ) && (int) $row['id'] === $id ) {
				$this->tables[ $table ][ $i ] = array_merge( $row, is_array( $data ) ? $data : array() );
				$found                        = true;
				break;
			}
		}
		return $found ? 1 : false;
	}

	public function delete( $table, $where, $where_format = null ) {
		unset( $where_format );
		$this->queries[] = 'DELETE ' . $table;
		$id              = isset( $where['id'] ) ? (int) $where['id'] : 0;
		$n               = 0;
		if ( ! isset( $this->tables[ $table ] ) ) {
			return 0;
		}
		$kept = array();
		foreach ( $this->tables[ $table ] as $row ) {
			if ( isset( $row['id'] ) && (int) $row['id'] === $id ) {
				++$n;
				continue;
			}
			$kept[] = $row;
		}
		$this->tables[ $table ] = $kept;
		return $n;
	}

	public function rows( $table ) {
		return isset( $this->tables[ $table ] ) && is_array( $this->tables[ $table ] ) ? $this->tables[ $table ] : array();
	}

	public function reset_queries() {
		$this->queries = array();
	}

	public function count_like( $needle ) {
		$n = 0;
		foreach ( $this->queries as $sql ) {
			if ( false !== stripos( $sql, $needle ) ) {
				++$n;
			}
		}
		return $n;
	}
}

$wpdb           = new RW_Memo_Wpdb();
$GLOBALS['wpdb'] = $wpdb;

$root = dirname( __DIR__ );
require_once $root . '/includes/class-rwgcm-db.php';
require_once $root . '/includes/class-rwgcm-action-resolver.php';
require_once $root . '/includes/class-rwgcm-rule-sanitizer.php';
require_once $root . '/includes/class-rwgcm-condition-library.php';
require_once $root . '/includes/class-rwgcm-condition-evaluator.php';
require_once $root . '/includes/class-rwgcm-targeting-adapter.php';
require_once $root . '/includes/class-rwgcm-rule-evaluator.php';
require_once $root . '/includes/class-rwgcm-rule-store.php';
require_once $root . '/includes/class-rwgcm-pricing-calc.php';
require_once $root . '/includes/class-rwgcm-diagnostics.php';
require_once $root . '/includes/class-rwgcm-pricing-resolution.php';

$passed = 0;
$failed = 0;

function assert_true( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}
	++$failed;
	echo "FAIL: {$label}\n";
}

function memo_rule_row( $id, $status, $priority, $value, $country = '' ) {
	$items = array();
	if ( '' !== $country ) {
		$items[] = array(
			'field'    => 'visitor.country',
			'target'   => 'country',
			'operator' => 'is',
			'value'    => $country,
		);
	}
	return array(
		'id'              => $id,
		'label'           => 'Rule ' . $id,
		'status'          => $status,
		'priority'        => $priority,
		'scope_type'      => 'global',
		'scope_ids'       => '[]',
		'conditions_json' => wp_json_encode( array( 'match' => 'all', 'items' => $items ) ),
		'actions_json'    => wp_json_encode(
			array(
				array(
					'type'  => 'price_adjustment',
					'mode'  => 'percent',
					'value' => $value,
				),
			)
		),
		'meta_json'       => '{}',
		'created_at'      => '2026-11-25 12:00:00',
		'updated_at'      => '2026-11-25 12:00:00',
	);
}

function seed_rules_table( array $rows ) {
	global $wpdb;
	$wpdb->tables[ RWGCM_DB::rules_table() ] = $rows;
	$wpdb->next_id                           = 1;
	foreach ( $rows as $row ) {
		if ( isset( $row['id'] ) && (int) $row['id'] >= $wpdb->next_id ) {
			$wpdb->next_id = (int) $row['id'] + 1;
		}
	}
}

function set_visitor( $country, $currency ) {
	$GLOBALS['rw_visitor_country'] = $country;
	$GLOBALS['rw_currency']        = $currency;
	$GLOBALS['rw_context']         = array( 'country' => $country );
}

$product = new RW_Memo_Product( 42 );

// --- 1. A false existence probe is not stored in the schema option. ---
RWGCM_Rule_Store::flush();
$GLOBALS['rw_options'] = array();
$wpdb->tables          = array();
$wpdb->reset_queries();

assert_true( 'missing table returns false', false === RWGCM_DB::rules_table_exists() );
assert_true( 'the first miss issues one SHOW TABLES', 1 === $wpdb->count_like( 'SHOW TABLES' ) );
$wpdb->reset_queries();
assert_true( 'a second miss uses the request static', false === RWGCM_DB::rules_table_exists() && 0 === $wpdb->count_like( 'SHOW TABLES' ) );
assert_true( 'a miss is not written to the schema-version option', ! array_key_exists( RWGCM_DB::VERSION_OPTION, $GLOBALS['rw_options'] ) );

seed_rules_table( array( memo_rule_row( 1, 'active', 100, -15, 'ZA' ) ) );
assert_true( 'the same request still reports a miss after the table appears', false === RWGCM_DB::rules_table_exists() );
RWGCM_Rule_Store::flush();
assert_true( 'flush lets a later probe recover to true', true === RWGCM_DB::rules_table_exists() );
assert_true( 'a recovered hit is still not persisted as a schema version', ! array_key_exists( RWGCM_DB::VERSION_OPTION, $GLOBALS['rw_options'] ) );

// --- 2. Schema version flag skips SHOW TABLES. ---
RWGCM_Rule_Store::flush();
$GLOBALS['rw_options'][ RWGCM_DB::VERSION_OPTION ] = RWGCM_DB::SCHEMA_VERSION;
$wpdb->reset_queries();
assert_true( 'a current schema version reports that the table exists', true === RWGCM_DB::rules_table_exists() );
assert_true( 'the schema-version flag issues no SHOW TABLES', 0 === $wpdb->count_like( 'SHOW TABLES' ) );
$wpdb->reset_queries();
assert_true( 'overlays_table_exists also trusts the schema version', true === RWGCM_DB::overlays_table_exists() && 0 === $wpdb->count_like( 'SHOW TABLES' ) );

// --- 3. Query counts drop; repeated reads match the first result. ---
RWGCM_Rule_Store::flush();
seed_rules_table(
	array(
		memo_rule_row( 1, 'active', 100, -15, 'ZA' ),
		memo_rule_row( 2, 'draft', 50, -50, '' ),
	)
);
set_visitor( 'ZA', 'ZAR' );
$wpdb->reset_queries();

$first_all    = RWGCM_Rule_Store::get_all_rules();
$first_count  = RWGCM_Rule_Store::count_by_status( 'active' );
$first_draft  = RWGCM_Rule_Store::count_by_status( 'draft' );
$first_adjust = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
$show_first   = $wpdb->count_like( 'SHOW TABLES' );
$select_first = $wpdb->count_like( 'SELECT * FROM' );
$count_first  = $wpdb->count_like( 'SELECT COUNT' );

assert_true( 'the first list load returns both rows', 2 === count( $first_all ) );
assert_true( 'active count is 1', 1 === $first_count );
assert_true( 'draft count is 1', 1 === $first_draft );
assert_true( 'ZA resolves a -15 percent rule', is_array( $first_adjust ) && 'percent' === $first_adjust['type'] && -15.0 === (float) $first_adjust['value'] );
assert_true( 'schema flag means the first load issues no SHOW TABLES', 0 === $show_first );
assert_true( 'the first load issues one SELECT * and no COUNT', 1 === $select_first && 0 === $count_first );

$wpdb->reset_queries();
$same = true;
for ( $i = 0; $i < 20; $i++ ) {
	$all = RWGCM_Rule_Store::get_all_rules();
	$cnt = RWGCM_Rule_Store::count_by_status( 'active' );
	$adj = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
	if ( $all !== $first_all || $cnt !== $first_count || $adj !== $first_adjust ) {
		$same = false;
		break;
	}
}
assert_true( 'twenty repeat lookups return the same list, count and adjustment', $same );
assert_true( 'repeat lookups issue no further SQL', 0 === count( $wpdb->queries ) );
assert_true( 'uses_generic_pricing_rules is true with an active rule', true === RWGCM_Diagnostics::uses_generic_pricing_rules() );

// --- 4. Cache key includes country / currency / product. ---
set_visitor( 'GB', 'GBP' );
$gb_adjust = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'GB does not match the ZA rule', null === $gb_adjust );
set_visitor( 'ZA', 'ZAR' );
$za_again = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'switching back to ZA reuses the earlier ZA adjustment', $za_again === $first_adjust );

$other = new RW_Memo_Product( 99, array( 7 ) );
$other_adj = RWGCM_Pricing_Resolution::find_price_adjustment( $other );
assert_true( 'a second product with a global ZA rule gets the same adjustment', $other_adj === $first_adjust );

set_visitor( 'ZA', 'USD' );
$usd_adj = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'the same country in another currency still resolves the same rule', $usd_adj === $first_adjust );
set_visitor( 'ZA', 'ZAR' );

// --- 5. Flush invalidates; results after flush still match a fresh resolve. ---
$wpdb->tables[ RWGCM_DB::rules_table() ][0]['actions_json'] = wp_json_encode(
	array(
		array(
			'type'  => 'price_adjustment',
			'mode'  => 'percent',
			'value' => -20,
		),
	)
);
$stale = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'without flush the previous adjustment is still returned', is_array( $stale ) && -15.0 === (float) $stale['value'] );
assert_true( 'without flush the active count is unchanged', 1 === RWGCM_Rule_Store::count_by_status( 'active' ) && 2 === count( RWGCM_Rule_Store::get_all_rules() ) );

$wpdb->reset_queries();
RWGCM_Rule_Store::flush();
$fresh_all    = RWGCM_Rule_Store::get_all_rules();
$fresh_count  = RWGCM_Rule_Store::count_by_status( 'active' );
$fresh_adjust = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'flush reloads the list from the database', 1 === $wpdb->count_like( 'SELECT * FROM' ) );
assert_true( 'the flushed list still has two rows', 2 === count( $fresh_all ) );
assert_true( 'the flushed adjustment is the updated -20 percent', is_array( $fresh_adjust ) && -20.0 === (float) $fresh_adjust['value'] );
assert_true( 'the flushed active count is still 1', 1 === $fresh_count );

// Re-resolve without cache by flushing and comparing again (identity after memo).
$wpdb->reset_queries();
$again = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'a second resolve after flush matches and issues no SQL', $again === $fresh_adjust && 0 === count( $wpdb->queries ) );

// --- 6. Writes flush the memo. ---
RWGCM_Rule_Store::set_purge_on_write( false );
$new_id = RWGCM_Rule_Store::insert_rule(
	array(
		'label'      => 'US bump',
		'status'     => 'active',
		'priority'   => 10,
		'scope'      => array( 'type' => 'global', 'ids' => array() ),
		'conditions' => array(
			'match' => 'all',
			'items' => array(
				array(
					'field'    => 'visitor.country',
					'target'   => 'country',
					'operator' => 'is',
					'value'    => 'US',
				),
			),
		),
		'actions'    => array(
			array(
				'type'  => 'price_adjustment',
				'mode'  => 'percent',
				'value' => 5,
			),
		),
	)
);
assert_true( 'insert_rule returns a new id', $new_id > 0 );
assert_true( 'insert_rule flush makes the new active count visible', 2 === RWGCM_Rule_Store::count_by_status( 'active' ) );

set_visitor( 'US', 'USD' );
$us_adj = RWGCM_Pricing_Resolution::find_price_adjustment( $product );
assert_true( 'the inserted US rule is visible after the write flush', is_array( $us_adj ) && 5.0 === (float) $us_adj['value'] );

$updated = RWGCM_Rule_Store::update_rule(
	$new_id,
	array(
		'id'         => $new_id,
		'label'      => 'US bump',
		'status'     => 'disabled',
		'priority'   => 10,
		'scope'      => array( 'type' => 'global', 'ids' => array() ),
		'conditions' => array(
			'match' => 'all',
			'items' => array(
				array(
					'field'    => 'visitor.country',
					'target'   => 'country',
					'operator' => 'is',
					'value'    => 'US',
				),
			),
		),
		'actions'    => array(
			array(
				'type'  => 'price_adjustment',
				'mode'  => 'percent',
				'value' => 5,
			),
		),
	)
);
assert_true( 'update_rule succeeds', true === $updated );
assert_true( 'a status change to disabled drops the active count', 1 === RWGCM_Rule_Store::count_by_status( 'active' ) );
assert_true( 'a disabled US rule no longer adjusts the price', null === RWGCM_Pricing_Resolution::find_price_adjustment( $product ) );

$deleted = RWGCM_Rule_Store::delete_rule( $new_id );
assert_true( 'delete_rule succeeds', true === $deleted );
assert_true( 'delete_rule removes the row from the memoised list', 2 === count( RWGCM_Rule_Store::get_all_rules() ) );

// --- 7. install() records the schema flag and flushes. ---
RWGCM_Rule_Store::flush();
unset( $GLOBALS['rw_options'][ RWGCM_DB::VERSION_OPTION ] );
$wpdb->tables = array();
assert_true( 'before install the table is missing', false === RWGCM_DB::rules_table_exists() );
// install() needs dbDelta and $wpdb->get_charset_collate. Skip calling the real
// installer; persist the same flag it writes and confirm flush + flag behaviour.
update_option( RWGCM_DB::VERSION_OPTION, RWGCM_DB::SCHEMA_VERSION, false );
RWGCM_Rule_Store::flush();
RWGCM_DB::flush_existence_cache();
// After a successful install the version option is set and existence is rememoised.
$wpdb->reset_queries();
assert_true( 'install-equivalent schema flag is trusted without SHOW TABLES', true === RWGCM_DB::rules_table_exists() && 0 === $wpdb->count_like( 'SHOW TABLES' ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
