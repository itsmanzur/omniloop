<?php
/**
 * Drop OmniLoop data when the plugin is deleted from wp-admin.
 *
 * @package OmniLoop
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table = $wpdb->prefix . 'omniloop_index';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'omniloop_db_version' );
delete_option( 'omniloop_settings' );
delete_option( 'omniloop_cache_gen' );
delete_option( 'omniloop_reindex_cursor' );

wp_clear_scheduled_hook( 'omniloop_reindex_batch' );
