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
$events = $wpdb->prefix . 'omniloop_facet_events';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DROP TABLE IF EXISTS {$events}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$options = [
	'omniloop_db_version',
	'omniloop_settings',
	'omniloop_cache_gen',
	'omniloop_reindex_cursor',
	'omniloop_reindex_processed',
	'omniloop_last_index_sync',
	'omniloop_asset_version',
	'omniloop_sort_meta_keys',
	'omniloop_rewrite_version',
	'omniloop_product_bootstrapped',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_olq_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_olq_' ) . '%',
		$wpdb->esc_like( '_transient_ol_ev_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ol_ev_' ) . '%',
		$wpdb->esc_like( '_transient_ol_qr_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ol_qr_' ) . '%'
	)
);

wp_clear_scheduled_hook( 'omniloop_reindex_batch' );
wp_clear_scheduled_hook( 'omniloop_prune_facet_events' );
