<?php
/**
 * Flattens post taxonomies and allowlisted meta into wp_omniloop_index.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Indexer {

	private const BATCH = 40;

	public static function init(): void {
		add_action( 'save_post', [ self::class, 'index_post' ], 20, 2 );
		add_action( 'before_delete_post', [ self::class, 'delete_post' ] );
		add_action( 'wp_trash_post', [ self::class, 'delete_post' ] );
		add_action( 'untrashed_post', [ self::class, 'index_post_id' ] );
		add_action( 'set_object_terms', [ self::class, 'on_terms' ], 10, 4 );
	}

	public static function on_terms( int $object_id, $terms, array $tt_ids, string $taxonomy ): void {
		unset( $terms, $tt_ids, $taxonomy );
		self::index_post_id( $object_id );
	}

	public static function index_post_id( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			self::index_post( $post_id, $post );
		}
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function index_post( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! self::is_indexable_type( $post->post_type ) ) {
			self::delete_post( $post_id );
			return;
		}

		self::delete_post( $post_id );

		if ( 'publish' !== $post->post_status || '' !== $post->post_password ) {
			CacheManager::bump();
			return;
		}

		global $wpdb;
		$table = Installer::table_name();
		$rows  = self::collect_rows( $post );

		foreach ( $rows as $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				$row,
				[
					'%d',
					'%s',
					'%s',
					'%s',
					is_null( $row['numeric_value'] ) ? '%s' : '%f',
				]
			);
		}

		CacheManager::bump();
	}

	public static function delete_post( $post_id ): void {
		global $wpdb;

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Installer::table_name(), [ 'post_id' => $post_id ], [ '%d' ] );
	}

	/**
	 * Cron / admin batch. Returns progress payload.
	 *
	 * @return array{processed:int,total:int,done:bool,offset:int}
	 */
	public static function process_batch( int $offset = -1 ): array {
		$settings = get_option( 'omniloop_settings', [] );
		$types    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? array_values( array_filter( array_map( 'sanitize_key', $settings['post_types'] ) ) )
			: Installer::default_post_types();

		if ( empty( $types ) ) {
			$types = [ 'post' ];
		}

		if ( $offset < 0 ) {
			$offset = (int) get_option( 'omniloop_reindex_cursor', 0 );
		}

		$query = new \WP_Query(
			[
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => self::BATCH,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		foreach ( $query->posts as $post_id ) {
			self::index_post_id( (int) $post_id );
		}

		$next = $offset + count( $query->posts );
		$done = $next >= (int) $query->found_posts || empty( $query->posts );

		if ( $done ) {
			delete_option( 'omniloop_reindex_cursor' );
			wp_clear_scheduled_hook( 'omniloop_reindex_batch' );
		} else {
			update_option( 'omniloop_reindex_cursor', $next, false );
			if ( ! wp_next_scheduled( 'omniloop_reindex_batch' ) ) {
				wp_schedule_single_event( time() + 5, 'omniloop_reindex_batch' );
			}
		}

		CacheManager::bump();

		return [
			'processed' => $next,
			'total'     => (int) $query->found_posts,
			'done'      => $done,
			'offset'    => $done ? 0 : $next,
		];
	}

	public static function is_indexable_type( string $post_type ): bool {
		$settings = get_option( 'omniloop_settings', [] );
		$types    = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? $settings['post_types']
			: Installer::default_post_types();

		$types = array_map( 'sanitize_key', $types );

		return in_array( $post_type, $types, true );
	}

	/**
	 * @return list<array{post_id:int,post_type:string,facet_key:string,facet_value:string,numeric_value:float|null}>
	 */
	private static function collect_rows( \WP_Post $post ): array {
		$rows     = [];
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$taxonomies = apply_filters( 'omniloop_indexable_taxonomies', $taxonomies, $post );

		if ( is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy = sanitize_key( (string) $taxonomy );
				if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = get_the_terms( $post, $taxonomy );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					if ( ! $term instanceof \WP_Term ) {
						continue;
					}
					$rows[] = self::row( $post, $taxonomy, $term->slug, null );
				}
			}
		}

		$meta_keys = apply_filters(
			'omniloop_indexable_meta_keys',
			[ '_price', '_regular_price', '_sale_price', '_stock_status' ],
			$post
		);

		if ( is_array( $meta_keys ) ) {
			foreach ( $meta_keys as $meta_key ) {
				$meta_key = self::sanitize_facet_key( (string) $meta_key );
				if ( '' === $meta_key ) {
					continue;
				}

				$raw = get_post_meta( $post->ID, $meta_key, true );
				if ( '' === $raw || false === $raw || is_array( $raw ) ) {
					continue;
				}

				$numeric = is_numeric( $raw ) ? (float) $raw : null;
				$value   = sanitize_text_field( (string) $raw );
				if ( strlen( $value ) > 191 ) {
					$value = substr( $value, 0, 191 );
				}

				$rows[] = self::row( $post, $meta_key, $value, $numeric );

				if ( '_price' === $meta_key && null !== $numeric ) {
					$rows[] = self::row( $post, 'price', (string) $numeric, $numeric );
				}
			}
		}

		return $rows;
	}

	/**
	 * @return array{post_id:int,post_type:string,facet_key:string,facet_value:string,numeric_value:float|null}
	 */
	private static function row( \WP_Post $post, string $key, string $value, ?float $numeric ): array {
		return [
			'post_id'       => (int) $post->ID,
			'post_type'     => substr( sanitize_key( $post->post_type ), 0, 20 ),
			'facet_key'     => substr( $key, 0, 50 ),
			'facet_value'   => $value,
			'numeric_value' => $numeric,
		];
	}

	public static function sanitize_facet_key( string $key ): string {
		$key = strtolower( $key );
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';

		return substr( $key, 0, 50 );
	}
}
