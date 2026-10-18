<?php
// Standalone tests for RWGCM_Gateway_Log (0.4.8).
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['opt'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['lines'] = array(); $GLOBALS['filter'] = null; $GLOBALS['now'] = 1000;
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function apply_filters( $h, $v ) { if ( 'rwgcm_log_gateway_warnings' === $h && null !== $GLOBALS['filter'] ) { return call_user_func( $GLOBALS['filter'], $v ); } return $v; }
function get_transient( $k ) { if ( isset( $GLOBALS['tr'][ $k ] ) && $GLOBALS['tr'][ $k ][1] > $GLOBALS['now'] ) { return $GLOBALS['tr'][ $k ][0]; } return false; }
function set_transient( $k, $v, $ttl ) { $GLOBALS['tr'][ $k ] = array( $v, $GLOBALS['now'] + $ttl, $ttl ); return true; }
class L { function warning( $m, $c ) { $GLOBALS['lines'][] = array( $m, $c ); } }
function wc_get_logger() { static $l; return $l ?: ( $l = new L() ); }
require __DIR__ . '/../includes/class-rwgcm-gateway-log.php';
$p = 0; $f = 0;
function assert_true( $n, $c ) { global $p, $f; if ( $c ) { $p++; } else { $f++; echo "FAIL: $n\n"; } }
function fresh() { $GLOBALS['opt'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['lines'] = array(); $GLOBALS['filter'] = null; RWGCM_Gateway_Log::reset_request_cache(); }
function w( $t = 'fallback', $c = 'ZA', $cur = 'GBP', $g = array( 'rule:cheque', 'offered:bacs' ) ) { return RWGCM_Gateway_Log::warn( $t, $c, $cur, $g, "msg $t $c $cur" ); }

fresh();
assert_true( 'first warning logs', true === w() );
for ( $i = 0; $i < 20; $i++ ) { w(); }
assert_true( 'same request: 1 line after 21 calls', 1 === count( $GLOBALS['lines'] ) );
assert_true( 'source is rwgcm-gateways', 'rwgcm-gateways' === $GLOBALS['lines'][0][1]['source'] );
assert_true( 'message passed through', 'msg fallback ZA GBP' === $GLOBALS['lines'][0][0] );
assert_true( 'one transient with 24h TTL', 1 === count( $GLOBALS['tr'] ) && 86400 === current( $GLOBALS['tr'] )[2] );
assert_true( 'transient uses prefix', 0 === strpos( key( $GLOBALS['tr'] ), 'rwgcm_gwlog_' ) && strlen( key( $GLOBALS['tr'] ) ) <= 172 );
for ( $r = 0; $r < 11; $r++ ) { RWGCM_Gateway_Log::reset_request_cache(); w(); }
assert_true( '12 requests: still 1 line (transient)', 1 === count( $GLOBALS['lines'] ) );
RWGCM_Gateway_Log::reset_request_cache(); $GLOBALS['tr'] = array();
for ( $i = 0; $i < 5; $i++ ) { w(); }
assert_true( 'transient gone: logs once more, then static array dedupes the rest', 2 === count( $GLOBALS['lines'] ) );
fresh(); w(); RWGCM_Gateway_Log::reset_request_cache(); $GLOBALS['now'] += 86401; w();
assert_true( 'after 24h the warning is logged again', 2 === count( $GLOBALS['lines'] ) ); $GLOBALS['now'] = 1000;

fresh();
w(); w( 'no_gateway_for_currency', '', 'GBP', array( 'bacs' ) ); w( 'fallback', 'NG' ); w( 'fallback', 'ZA', 'ZAR' ); w( 'fallback', 'ZA', 'GBP', array( 'rule:cheque', 'offered:bacs', 'offered:cod' ) );
assert_true( 'distinct type/country/currency/gateway set log separately', 5 === count( $GLOBALS['lines'] ) );
w( 'fallback', 'za', 'gbp', array( 'offered:bacs', 'rule:cheque', 'rule:cheque' ) );
assert_true( 'case, order and duplicates in gateway set do not create a new key', 5 === count( $GLOBALS['lines'] ) );
assert_true( 'key is stable', RWGCM_Gateway_Log::key( 'a', 'za', 'gbp', array( 'y', 'x' ) ) === RWGCM_Gateway_Log::key( 'a', 'ZA', 'GBP', array( 'x', 'y' ) ) );
assert_true( 'key differs by type', RWGCM_Gateway_Log::key( 'a', 'ZA', 'GBP', array() ) !== RWGCM_Gateway_Log::key( 'b', 'ZA', 'GBP', array() ) );

fresh(); $GLOBALS['filter'] = function () { return false; };
assert_true( 'filter false: warn returns false', false === w() );
for ( $i = 0; $i < 12; $i++ ) { RWGCM_Gateway_Log::reset_request_cache(); w(); }
assert_true( 'filter false: 0 lines, no transients', 0 === count( $GLOBALS['lines'] ) && 0 === count( $GLOBALS['tr'] ) );
fresh(); $GLOBALS['filter'] = function ( $v ) { return $v; };
assert_true( 'filter receives default true', true === RWGCM_Gateway_Log::enabled() );
fresh(); $GLOBALS['opt']['rwgcm_log_gateway_warnings'] = 'no'; $GLOBALS['filter'] = function ( $v ) { return true; };
assert_true( 'filter can re-enable over the setting', true === RWGCM_Gateway_Log::enabled() );

fresh();
assert_true( 'default (no option) is on', true === RWGCM_Gateway_Log::enabled() );
RWGCM_Gateway_Log::save_setting( false );
assert_true( 'save_setting(false) stores no', 'no' === $GLOBALS['opt']['rwgcm_log_gateway_warnings'] );
for ( $i = 0; $i < 12; $i++ ) { RWGCM_Gateway_Log::reset_request_cache(); w(); }
assert_true( 'setting off: 0 lines', 0 === count( $GLOBALS['lines'] ) );
RWGCM_Gateway_Log::save_setting( true );
assert_true( 'save_setting(true) stores yes', 'yes' === $GLOBALS['opt']['rwgcm_log_gateway_warnings'] );
assert_true( 'setting on again logs', true === w() );

// Source wiring.
$src = function ( $f ) { return file_get_contents( __DIR__ . '/../includes/' . $f ); };
$av = $src( 'class-rwgcm-gateway-availability.php' ); $cu = $src( 'class-rwgcm-gateway-currency.php' ); $ad = $src( 'class-rwgcm-price-book-admin.php' ); $pl = $src( 'class-rwgcm-plugin.php' );
assert_true( 'availability uses RWGCM_Gateway_Log::warn', false !== strpos( $av, "RWGCM_Gateway_Log::warn(\n\t\t\t\t'fallback'" ) && false === strpos( $av, 'wc_get_logger' ) );
assert_true( 'currency uses RWGCM_Gateway_Log::warn', false !== strpos( $cu, "'no_gateway_for_currency'" ) && false === strpos( $cu, 'wc_get_logger' ) );
assert_true( 'plugin requires the log class before the gateway classes', false !== ( $a = strpos( $pl, 'class-rwgcm-gateway-log.php' ) ) && $a < strpos( $pl, 'class-rwgcm-gateway-availability.php' ) );
assert_true( 'admin label present', false !== strpos( $ad, "'Log payment-rule fallbacks'" ) );
assert_true( 'admin checkbox defaults on', false !== strpos( $ad, "get_option( RWGCM_Gateway_Log::OPTION_ENABLED, 'yes' )" ) );
assert_true( 'admin save only when its hidden field is posted', false !== strpos( $ad, "if ( isset( \$_POST['rwgcm_gateway_log_present'] ) ) {\n\t\t\tRWGCM_Gateway_Log::save_setting( ! empty( \$_POST['rwgcm_gateway_log_warnings'] ) );" ) );
assert_true( 'admin renders hidden present field', false !== strpos( $ad, 'name="rwgcm_gateway_log_present"' ) );
assert_true( 'no literal placeholder text domain', false === strpos( $av . $cu . $ad . $src( 'class-rwgcm-price-book-store-api.php' ), '[RE' . 'DACTED]' ) );

echo "$p passed, $f failed\n"; exit( $f ? 1 : 0 );
