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
		add_action( 'save_post', [ self::class, 'maybe_index_post' ], 20, 2 );
		add_action( 'before_delete_post', [ self::class, 'delete_post' ] );
		add_action( 'wp_trash_post', [ self::class, 'delete_post' ] );
		add_action( 'untrashed_post', [ self::class, 'maybe_index_post_id' ] );
		add_action( 'set_object_terms', [ self::class, 'on_terms' ], 10, 4 );
		add_action( 'updated_post_meta', [ self::class, 'on_meta_updated' ], 10, 4 );
		add_action( 'comment_post', [ self::class, 'on_comment_post' ], 20, 3 );
		add_action( 'wp_set_comment_status', [ self::class, 'on_comment_status' ], 20, 2 );
		add_action( 'deleted_comment', [ self::class, 'on_comment_deleted' ], 20, 2 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_update_product', [ self::class, 'maybe_index_post_id' ] );
			add_action( 'woocommerce_product_set_stock_status', [ self::class, 'on_wc_stock' ], 10, 2 );
		}

		AcfFacets::init();
	}

	public static function auto_sync_enabled(): bool {
		$settings = get_option( 'omniloop_settings', [] );
		if ( ! is_array( $settings ) ) {
			return true;
		}

		return ! isset( $settings['auto_sync'] ) || 'off' !== $settings['auto_sync'];
	}

	/**
	 * @return array{indexed_rows:int,indexed_posts:int,reindex_pending:bool,reindex_offset:int,last_sync:int,last_sync_human:string,auto_sync:bool}
	 */
	public static function status(): array {
		global $wpdb;

		$table = Installer::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table}" );
		$cursor = (int) get_option( 'omniloop_reindex_cursor', 0 );
		$last   = (int) get_option( 'omniloop_last_index_sync', 0 );

		return [
			'indexed_rows'    => $rows,
			'indexed_posts'   => $posts,
			'reindex_pending' => $cursor > 0,
			'reindex_offset'  => $cursor,
			'last_sync'       => $last,
			'last_sync_human' => $last > 0 ? human_time_diff( $last, time() ) : '',
			'auto_sync'       => self::auto_sync_enabled(),
		];
	}

	public static function touch_sync_time(): void {
		update_option( 'omniloop_last_index_sync', time(), false );
	}

	public static function maybe_index_post_id( int $post_id ): void {
		if ( ! self::auto_sync_enabled() ) {
			return;
		}

		self::index_post_id( $post_id );
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function maybe_index_post( $post_id, $post = null ): void {
		if ( ! self::auto_sync_enabled() ) {
			return;
		}

		self::index_post( $post_id, $post );
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $status     Stock status.
	 */
	public static function on_wc_stock( $product_id, $status ): void {
		unset( $status );
		self::maybe_index_post_id( (int) $product_id );
	}

	/**
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public static function on_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id, $meta_value );

		if ( ! self::auto_sync_enabled() ) {
			return;
		}

		$meta_key = (string) $meta_key;
		if ( ! self::is_relevant_meta_key( $meta_key ) ) {
			return;
		}

		$post = get_post( (int) $object_id );
		if ( ! $post instanceof \WP_Post || ! self::is_indexable_type( $post->post_type ) ) {
			return;
		}

		self::index_post_id( (int) $object_id );
	}

	private static function is_relevant_meta_key( string $meta_key ): bool {
		static $keys = null;

		if ( null === $keys ) {
			$keys = [
				'_price'         => true,
				'_regular_price' => true,
				'_sale_price'    => true,
				'_stock_status'  => true,
				'_stock'         => true,
				'_featured'      => true,
				'_visibility'    => true,
			];

			$extra = get_option( 'omniloop_sort_meta_keys', [] );
			if ( is_array( $extra ) ) {
				foreach ( $extra as $extra_key ) {
					$extra_key = self::sanitize_facet_key( (string) $extra_key );
					if ( '' !== $extra_key ) {
						$keys[ $extra_key ] = true;
					}
				}
			}
		}

		if ( isset( $keys[ $meta_key ] ) ) {
			return true;
		}

		return AcfFacets::is_indexed_meta_key( $meta_key );
	}

	public static function on_terms( int $object_id, $terms, array $tt_ids, string $taxonomy ): void {
		unset( $terms, $tt_ids, $taxonomy );
		self::maybe_index_post_id( $object_id );
	}

	/**
	 * @param int               $comment_id
	 * @param int|string        $approved
	 * @param array<string,mixed> $commentdata
	 */
	public static function on_comment_post( $comment_id, $approved, $commentdata ): void {
		unset( $comment_id, $approved );
		$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
		self::reindex_if_product( $post_id );
	}

	/**
	 * @param int    $comment_id
	 * @param string $status
	 */
	public static function on_comment_status( $comment_id, $status ): void {
		unset( $status );
		$comment = get_comment( $comment_id );
		if ( $comment && isset( $comment->comment_post_ID ) ) {
			self::reindex_if_product( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * @param int              $comment_id
	 * @param \WP_Comment|null $comment
	 */
	public static function on_comment_deleted( $comment_id, $comment = null ): void {
		unset( $comment_id );
		if ( is_object( $comment ) && isset( $comment->comment_post_ID ) ) {
			self::reindex_if_product( (int) $comment->comment_post_ID );
		}
	}

	private static function reindex_if_product( int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			self::maybe_index_post_id( $post_id );
		}
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

		self::touch_sync_time();
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
		self::touch_sync_time();
	}

	/**
	 * Cron / admin batch. Returns progress payload.
	 *
	 * @return array{processed:int,total:int,done:bool,offset:int,indexed_rows:int}
	 */
	public static function process_batch( int $offset = -1 ): array {
		$types = Installer::allowed_post_types();

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
			self::touch_sync_time();
		} else {
			update_option( 'omniloop_reindex_cursor', $next, false );
			if ( ! wp_next_scheduled( 'omniloop_reindex_batch' ) ) {
				wp_schedule_single_event( time() + 5, 'omniloop_reindex_batch' );
			}
		}

		CacheManager::bump();

		$indexed = 0;
		if ( $done ) {
			global $wpdb;
			$table = Installer::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return [
			'processed'    => $next,
			'total'        => (int) $query->found_posts,
			'done'         => $done,
			'offset'       => $done ? 0 : $next,
			'indexed_rows' => $indexed,
		];
	}

	public static function is_indexable_type( string $post_type ): bool {
		return in_array( $post_type, Installer::allowed_post_types(), true );
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
				if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) || WooFacets::is_skip_taxonomy( $taxonomy ) ) {
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

		$meta_keys = [ '_price', '_regular_price', '_sale_price', '_stock_status', '_stock' ];
		$extra     = get_option( 'omniloop_sort_meta_keys', [] );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $extra_key ) {
				$extra_key = self::sanitize_facet_key( (string) $extra_key );
				if ( '' !== $extra_key ) {
					$meta_keys[] = $extra_key;
				}
			}
		}
		$meta_keys = apply_filters( 'omniloop_indexable_meta_keys', $meta_keys, $post );

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

		foreach ( WooFacets::extra_index_values( $post ) as $extra ) {
			$rows[] = self::row( $post, $extra[0], $extra[1], $extra[2] );
		}

		foreach ( AcfFacets::extra_index_values( $post ) as $extra ) {
			$rows[] = self::row( $post, $extra[0], $extra[1], $extra[2] );
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
