<?php
/**
 * Lightweight facet click / empty-result analytics.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FacetAnalytics {

	public const CRON = 'omniloop_prune_facet_events';

	public static function init(): void {
		add_action( self::CRON, [ self::class, 'prune' ] );
		add_action( 'wp_dashboard_setup', [ self::class, 'widget' ] );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'omniloop_facet_events';
	}

	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			post_type VARCHAR(20) NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			facet_key VARCHAR(50) NOT NULL DEFAULT '',
			facet_value VARCHAR(191) NOT NULL DEFAULT '',
			result_count INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY idx_created (created_at),
			KEY idx_facet (facet_key, facet_value),
			KEY idx_type (event_type)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array{post_type:string,event_type:string,facet_key:string,facet_value:string,result_count:int}|null
	 */
	public static function sanitize_event( array $raw ): ?array {
		$type = sanitize_key( (string) ( $raw['event_type'] ?? '' ) );
		if ( ! in_array( $type, [ 'click', 'empty' ], true ) ) {
			return null;
		}

		$post_type = sanitize_key( (string) ( $raw['post_type'] ?? 'post' ) );
		if ( '' === $post_type ) {
			$post_type = 'post';
		}

		return [
			'post_type'    => substr( $post_type, 0, 20 ),
			'event_type'   => $type,
			'facet_key'    => Indexer::sanitize_facet_key( (string) ( $raw['facet_key'] ?? '' ) ),
			'facet_value'  => substr( sanitize_text_field( (string) ( $raw['facet_value'] ?? '' ) ), 0, 191 ),
			'result_count' => max( 0, (int) ( $raw['result_count'] ?? 0 ) ),
		];
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	public static function record( array $raw ): bool {
		$event = self::sanitize_event( $raw );
		if ( ! $event ) {
			return false;
		}
		if ( ! self::allow_write() ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table_name(),
			[
				'created_at'   => current_time( 'mysql' ),
				'post_type'    => $event['post_type'],
				'event_type'   => $event['event_type'],
				'facet_key'    => $event['facet_key'],
				'facet_value'  => $event['facet_value'],
				'result_count' => $event['result_count'],
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d' ]
		);

		return false !== $ok;
	}

	public static function prune(): void {
		global $wpdb;

		$table = self::table_name();
		$cut   = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cut ) );
	}

	/**
	 * @return list<array{facet_key:string,facet_value:string,clicks:int}>
	 */
	public static function top_clicks( int $limit = 10 ): array {
		global $wpdb;

		$table = self::table_name();
		$limit = max( 1, min( 50, $limit ) );
		$sql   = $wpdb->prepare(
			"SELECT facet_key, facet_value, COUNT(*) AS clicks
			FROM {$table}
			WHERE event_type = 'click' AND created_at >= %s AND facet_key <> ''
			GROUP BY facet_key, facet_value
			ORDER BY clicks DESC
			LIMIT %d",
			gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ),
			$limit
		);
		if ( ! is_string( $sql ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];
		foreach ( $rows as $row ) {
			$out[] = [
				'facet_key'   => (string) $row->facet_key,
				'facet_value' => (string) $row->facet_value,
				'clicks'      => (int) $row->clicks,
			];
		}

		return $out;
	}

	public static function empty_count(): int {
		global $wpdb;

		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event_type = 'empty' AND created_at >= %s",
			gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
		);
		if ( ! is_string( $sql ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	public static function widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'omniloop_facet_analytics',
			__( 'OmniLoop filter analytics', 'omniloop' ),
			[ self::class, 'render_widget' ]
		);
	}

	public static function render_widget(): void {
		$clicks = self::top_clicks( 8 );
		$empty  = self::empty_count();
		echo '<p>';
		echo esc_html(
			sprintf(
				/* translators: %d: empty-result events in 30 days */
				_n( '%d empty-result filter in the last 30 days.', '%d empty-result filters in the last 30 days.', $empty, 'omniloop' ),
				$empty
			)
		);
		echo '</p>';
		if ( ! $clicks ) {
			echo '<p>' . esc_html__( 'No facet clicks recorded yet. They appear as shoppers use filters.', 'omniloop' ) . '</p>';

			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Facet', 'omniloop' ) . '</th>';
		echo '<th>' . esc_html__( 'Value', 'omniloop' ) . '</th>';
		echo '<th>' . esc_html__( 'Clicks', 'omniloop' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $clicks as $row ) {
			echo '<tr><td>' . esc_html( I18n::facet_label( $row['facet_key'] ) ) . '</td>';
			echo '<td>' . esc_html( I18n::value_label( $row['facet_key'], $row['facet_value'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['clicks'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function allow_write(): bool {
		$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$key = 'ol_ev_' . md5( $ip !== '' ? $ip : 'anon' );
		$n   = (int) get_transient( $key );
		if ( $n > 120 ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		return true;
	}
}
