<?php
/**
 * Custom tables for Geo Commerce rules and product overlays.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema install via dbDelta.
 */
class RWGCM_DB {

	const VERSION_OPTION = 'rwgcm_db_version';
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * @return string Rules table name (with prefix).
	 */
	public static function rules_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwgcm_rules';
	}

	/**
	 * @return string Product overlays table name (with prefix).
	 */
	public static function overlays_table() {
		global $wpdb;
		return $wpdb->prefix . 'rwgcm_product_overlays';
	}

	/**
	 * Install or upgrade tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$rules_sql = 'CREATE TABLE ' . self::rules_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			priority int NOT NULL DEFAULT 100,
			scope_type varchar(32) NOT NULL DEFAULT 'global',
			scope_ids longtext NULL,
			conditions_json longtext NOT NULL,
			actions_json longtext NOT NULL,
			meta_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_priority (status, priority)
		) $charset_collate;";

		$overlays_sql = 'CREATE TABLE ' . self::overlays_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			priority int NOT NULL DEFAULT 100,
			conditions_json longtext NOT NULL,
			overlay_json longtext NOT NULL,
			meta_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_status (product_id, status)
		) $charset_collate;";

		dbDelta( $rules_sql );
		dbDelta( $overlays_sql );

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
		if ( class_exists( 'RWGCM_Rule_Store', false ) ) {
			RWGCM_Rule_Store::flush();
		} else {
			self::flush_existence_cache();
		}
		self::mark_tables_ready();
	}

	/**
	 * Persist a positive “tables exist” flag. Never stores a negative result.
	 *
	 * @return void
	 */
	private static function mark_tables_ready() {
		self::$rules_table_exists    = true;
		self::$overlays_table_exists = true;
	}

	/**
	 * Clear the per-request table-existence memo. A missing table is never
	 * written to the schema-version option, so the next request can recover.
	 *
	 * @return void
	 */
	public static function flush_existence_cache() {
		self::$rules_table_exists    = null;
		self::$overlays_table_exists = null;
	}

	/**
	 * @return bool Whether install (or a later upgrade) has recorded this schema version.
	 */
	public static function schema_is_current() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		return (string) get_option( self::VERSION_OPTION, '' ) === self::SCHEMA_VERSION;
	}

	/**
	 * Per-request memo: null = unknown, true/false = already probed this request.
	 *
	 * @var bool|null
	 */
	private static $rules_table_exists = null;

	/**
	 * @var bool|null
	 */
	private static $overlays_table_exists = null;

	/**
	 * Whether rules table appears to exist.
	 *
	 * A current schema-version option (set on install or upgrade) is treated as
	 * a positive flag and skips SHOW TABLES. A negative probe is stored only in
	 * the request static so a later install can recover.
	 *
	 * @return bool
	 */
	public static function rules_table_exists() {
		if ( null !== self::$rules_table_exists ) {
			return self::$rules_table_exists;
		}
		if ( self::schema_is_current() ) {
			self::$rules_table_exists = true;
			return true;
		}
		self::$rules_table_exists = self::probe_table( self::rules_table() );
		return self::$rules_table_exists;
	}

	/**
	 * @return bool
	 */
	public static function overlays_table_exists() {
		if ( null !== self::$overlays_table_exists ) {
			return self::$overlays_table_exists;
		}
		if ( self::schema_is_current() ) {
			self::$overlays_table_exists = true;
			return true;
		}
		self::$overlays_table_exists = self::probe_table( self::overlays_table() );
		return self::$overlays_table_exists;
	}

	/**
	 * @param string $table Table name with prefix.
	 * @return bool
	 */
	private static function probe_table( $table ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $table === $found;
	}
}
