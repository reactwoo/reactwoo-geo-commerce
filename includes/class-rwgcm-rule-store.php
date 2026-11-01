<?php
/**
 * Persistence for generic Geo Commerce rules (custom table).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_{prefix}rwgcm_rules.
 */
class RWGCM_Rule_Store {

	/**
	 * @var bool
	 */
	private static $purge_on_write = true;

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_rules() {
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority DESC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = self::row_to_rule( $row );
		}
		return $out;
	}

	/**
	 * @param string $status Rule status.
	 * @return int
	 */
	public static function count_by_status( $status = 'active' ) {
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return 0;
		}
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
		return absint( $n );
	}

	/**
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_rule( $id ) {
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return null;
		}
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		return self::row_to_rule( $row );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function row_to_rule( array $row ) {
		$scope_ids = array();
		if ( ! empty( $row['scope_ids'] ) ) {
			$dec = json_decode( (string) $row['scope_ids'], true );
			$scope_ids = is_array( $dec ) ? $dec : array();
		}
		$conditions = json_decode( isset( $row['conditions_json'] ) ? (string) $row['conditions_json'] : '{}', true );
		$actions    = json_decode( isset( $row['actions_json'] ) ? (string) $row['actions_json'] : '[]', true );
		$meta       = json_decode( isset( $row['meta_json'] ) ? (string) $row['meta_json'] : '{}', true );
		return array(
			'id'         => isset( $row['id'] ) ? (string) $row['id'] : '0',
			'status'     => isset( $row['status'] ) ? (string) $row['status'] : 'draft',
			'label'      => isset( $row['label'] ) ? (string) $row['label'] : '',
			'priority'   => isset( $row['priority'] ) ? (int) $row['priority'] : 100,
			'scope'      => array(
				'type' => isset( $row['scope_type'] ) ? (string) $row['scope_type'] : 'global',
				'ids'  => array_map( 'intval', $scope_ids ),
			),
			'conditions' => is_array( $conditions ) ? $conditions : array( 'match' => 'all', 'items' => array() ),
			'actions'    => is_array( $actions ) ? $actions : array(),
			'meta'       => is_array( $meta ) ? $meta : array(),
		);
	}

	/**
	 * Insert a new rule row.
	 *
	 * @param array<string, mixed> $rule Rule payload.
	 * @return int Insert ID or 0.
	 */
	public static function insert_rule( array $rule ) {
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return 0;
		}
		$clean = RWGCM_Rule_Sanitizer::sanitize_rule( $rule );
		if ( empty( $clean ) ) {
			return 0;
		}
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'label'           => $clean['label'],
			'status'          => $clean['status'],
			'priority'        => $clean['priority'],
			'scope_type'      => $clean['scope']['type'],
			'scope_ids'       => wp_json_encode( array_values( $clean['scope']['ids'] ) ),
			'conditions_json' => wp_json_encode( $clean['conditions'] ),
			'actions_json'    => wp_json_encode( $clean['actions'] ),
			'meta_json'       => wp_json_encode( $clean['meta'] ),
			'created_at'      => $now,
			'updated_at'      => $now,
		);
		$wpdb->insert( $table, $data );
		$id = (int) $wpdb->insert_id;
		if ( $id > 0 ) {
			$clean['id'] = $id;
			self::purge_after_write( $clean, null );
		}
		return $id;
	}

	/**
	 * Update an existing rule row.
	 *
	 * @param int                  $id   Rule ID.
	 * @param array<string, mixed> $rule Rule payload (include same shape as insert).
	 * @return bool
	 */
	public static function update_rule( $id, array $rule ) {
		$id = absint( $id );
		if ( $id <= 0 || ! RWGCM_DB::rules_table_exists() ) {
			return false;
		}
		$existing = self::get_rule( $id );
		if ( null === $existing ) {
			return false;
		}
		$rule['id'] = $id;
		$clean      = RWGCM_Rule_Sanitizer::sanitize_rule( $rule );
		if ( empty( $clean ) ) {
			return false;
		}
		if ( ! empty( $existing['meta']['created_at'] ) ) {
			$clean['meta']['created_at'] = $existing['meta']['created_at'];
		} elseif ( empty( $clean['meta']['created_at'] ) ) {
			$clean['meta']['created_at'] = current_time( 'mysql', true );
		}
		$clean['meta']['updated_at'] = current_time( 'mysql', true );

		global $wpdb;
		$table = RWGCM_DB::rules_table();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'label'           => $clean['label'],
			'status'          => $clean['status'],
			'priority'        => $clean['priority'],
			'scope_type'      => $clean['scope']['type'],
			'scope_ids'       => wp_json_encode( array_values( $clean['scope']['ids'] ) ),
			'conditions_json' => wp_json_encode( $clean['conditions'] ),
			'actions_json'    => wp_json_encode( $clean['actions'] ),
			'meta_json'       => wp_json_encode( $clean['meta'] ),
			'updated_at'      => $now,
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		$ok = false !== $result && empty( $wpdb->last_error );
		if ( $ok ) {
			self::purge_after_write( $clean, $existing );
		}
		return $ok;
	}

	/**
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( $id ) {
		if ( ! RWGCM_DB::rules_table_exists() ) {
			return false;
		}
		$existing = self::get_rule( $id );
		global $wpdb;
		$table = RWGCM_DB::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
		if ( $n > 0 ) {
			self::purge_after_write( null, $existing );
		}
		return $n > 0;
	}

	/**
	 * @param bool $purge Whether writes should clear cached prices.
	 * @return void
	 */
	public static function set_purge_on_write( $purge ) {
		self::$purge_on_write = (bool) $purge;
	}

	/**
	 * @param array<string, mixed>|null $rule     Saved rule.
	 * @param array<string, mixed>|null $previous Previous rule.
	 * @return void
	 */
	private static function purge_after_write( $rule, $previous ) {
		if ( ! self::$purge_on_write || ! class_exists( 'RWGCM_Cache_Purge', false ) ) {
			return;
		}
		RWGCM_Cache_Purge::on_pricing_rule_changed( $rule, $previous );
	}

	/**
	 * @param string $scope_type Scope type filter.
	 * @return list<array<string, mixed>>
	 */
	public static function get_rules_by_scope_type( $scope_type ) {
		$all = self::get_all_rules();
		$st  = sanitize_key( (string) $scope_type );
		$out = array();
		foreach ( $all as $r ) {
			if ( isset( $r['scope']['type'] ) && (string) $r['scope']['type'] === $st ) {
				$out[] = $r;
			}
		}
		return $out;
	}
}
