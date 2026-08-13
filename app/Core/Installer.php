<?php
/**
 * Creates and upgrades the custom index table.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'omniloop_index';
	}

	public static function activate(): void {
		self::create_table();
		update_option( 'omniloop_db_version', OMNILOOP_DB_VERSION, false );

		if ( false === get_option( 'omniloop_settings', false ) ) {
			update_option(
				'omniloop_settings',
				[
					'post_types' => self::default_post_types(),
				],
				false
			);
		}

		if ( ! wp_next_scheduled( 'omniloop_reindex_batch' ) ) {
			wp_schedule_single_event( time() + 15, 'omniloop_reindex_batch' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'omniloop_reindex_batch' );
	}

	public static function maybe_upgrade(): void {
		$installed = (string) get_option( 'omniloop_db_version', '' );
		if ( $installed !== OMNILOOP_DB_VERSION ) {
			self::create_table();
			update_option( 'omniloop_db_version', OMNILOOP_DB_VERSION, false );
		}
	}

	/**
	 * @return string[]
	 */
	public static function default_post_types(): array {
		$types = [ 'post' ];
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}

		return $types;
	}

	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			post_type VARCHAR(20) NOT NULL,
			facet_key VARCHAR(50) NOT NULL,
			facet_value VARCHAR(191) NOT NULL,
			numeric_value DECIMAL(12,4) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_post (post_id),
			KEY idx_lookup (facet_key, numeric_value),
			KEY idx_type_key_val (post_type, facet_key, facet_value)
		) {$charset};";

		dbDelta( $sql );
	}
}
