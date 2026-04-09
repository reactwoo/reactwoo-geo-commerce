<?php
/**
 * Product overlay persistence (contextual display overrides on canonical products).
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_{prefix}rwgcm_product_overlays.
 */
class RWGCM_Product_Overlay_Store {

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_overlays() {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY product_id ASC, priority DESC, id ASC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = self::row_to_overlay( $row );
		}
		return $out;
	}

	/**
	 * @param int $id Overlay ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_overlay( $id ) {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return null;
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
		return is_array( $row ) ? self::row_to_overlay( $row ) : null;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return list<array<string, mixed>>
	 */
	public static function get_overlays_for_product( $product_id ) {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		$pid   = absint( $product_id );
		if ( $pid <= 0 ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d AND status = %s ORDER BY priority DESC, id ASC", $pid, 'active' ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = self::row_to_overlay( $row );
		}
		return $out;
	}

	/**
	 * Active overlays for admin list (all statuses optional).
	 *
	 * @param string|null $status If set, filter by status.
	 * @return list<array<string, mixed>>
	 */
	public static function get_all_for_admin( $status = null ) {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return array();
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		if ( null !== $status && '' !== (string) $status ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY product_id ASC, priority DESC, id ASC", sanitize_key( (string) $status ) ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY product_id ASC, priority DESC, id ASC", ARRAY_A );
		}
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = self::row_to_overlay( $row );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function row_to_overlay( array $row ) {
		$overlay = json_decode( isset( $row['overlay_json'] ) ? (string) $row['overlay_json'] : '{}', true );
		$conds   = json_decode( isset( $row['conditions_json'] ) ? (string) $row['conditions_json'] : '{}', true );
		$meta    = json_decode( isset( $row['meta_json'] ) ? (string) $row['meta_json'] : '{}', true );
		return array(
			'id'         => isset( $row['id'] ) ? (string) $row['id'] : '0',
			'product_id' => isset( $row['product_id'] ) ? (int) $row['product_id'] : 0,
			'label'      => isset( $row['label'] ) ? (string) $row['label'] : '',
			'status'     => isset( $row['status'] ) ? (string) $row['status'] : 'draft',
			'priority'   => isset( $row['priority'] ) ? (int) $row['priority'] : 100,
			'conditions' => is_array( $conds ) ? $conds : array( 'match' => 'all', 'items' => array() ),
			'overrides'  => is_array( $overlay ) ? $overlay : array(),
			'meta'       => is_array( $meta ) ? $meta : array(),
		);
	}

	/**
	 * @param array<string, mixed> $overlay Sanitized overlay (no id).
	 * @return int Insert ID or 0.
	 */
	public static function insert_overlay( array $overlay ) {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return 0;
		}
		$clean = RWGCM_Overlay_Sanitizer::sanitize( $overlay );
		if ( null === $clean ) {
			return 0;
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'product_id'      => $clean['product_id'],
			'label'             => $clean['label'],
			'status'            => $clean['status'],
			'priority'          => $clean['priority'],
			'conditions_json'   => wp_json_encode( $clean['conditions'] ),
			'overlay_json'      => wp_json_encode( $clean['overrides'] ),
			'meta_json'         => wp_json_encode( $clean['meta'] ),
			'created_at'        => $now,
			'updated_at'        => $now,
		);
		$wpdb->insert( $table, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int                  $id      Overlay ID.
	 * @param array<string, mixed> $overlay Payload.
	 * @return bool
	 */
	public static function update_overlay( $id, array $overlay ) {
		$id = absint( $id );
		if ( $id <= 0 || ! RWGCM_DB::overlays_table_exists() ) {
			return false;
		}
		$existing = self::get_overlay( $id );
		if ( null === $existing ) {
			return false;
		}
		$overlay['product_id'] = isset( $overlay['product_id'] ) ? $overlay['product_id'] : $existing['product_id'];
		$clean = RWGCM_Overlay_Sanitizer::sanitize( $overlay );
		if ( null === $clean ) {
			return false;
		}
		if ( ! empty( $existing['meta']['created_at'] ) ) {
			$clean['meta']['created_at'] = $existing['meta']['created_at'];
		} elseif ( empty( $clean['meta']['created_at'] ) ) {
			$clean['meta']['created_at'] = current_time( 'mysql', true );
		}
		$clean['meta']['updated_at'] = current_time( 'mysql', true );

		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'product_id'      => $clean['product_id'],
			'label'             => $clean['label'],
			'status'            => $clean['status'],
			'priority'          => $clean['priority'],
			'conditions_json'   => wp_json_encode( $clean['conditions'] ),
			'overlay_json'      => wp_json_encode( $clean['overrides'] ),
			'meta_json'         => wp_json_encode( $clean['meta'] ),
			'updated_at'        => $now,
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		return false !== $result && empty( $wpdb->last_error );
	}

	/**
	 * @param int $id Overlay ID.
	 * @return bool
	 */
	public static function delete_overlay( $id ) {
		if ( ! RWGCM_DB::overlays_table_exists() ) {
			return false;
		}
		global $wpdb;
		$table = RWGCM_DB::overlays_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$n = $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
		return $n > 0;
	}
}
