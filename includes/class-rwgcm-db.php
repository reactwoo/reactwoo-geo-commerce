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
	}

	/**
	 * Whether rules table appears to exist.
	 *
	 * @return bool
	 */
	public static function rules_table_exists() {
		global $wpdb;
		$table = self::rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted prefix.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $table === $found;
	}

	/**
	 * @return bool
	 */
	public static function overlays_table_exists() {
		global $wpdb;
		$table = self::overlays_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $table === $found;
	}
}
