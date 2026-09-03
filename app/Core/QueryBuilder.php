<?php
/**
 * Faceted SQL against wp_omniloop_index — avoids stacked postmeta JOINs.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class QueryBuilder {

	public const MAX_PER_PAGE         = 48;
	public const MAX_FACET_KEYS       = 24;
	public const MAX_VALUES_PER_FACET = 20;
	public const SORTS                = [ 'date', 'title', 'price_asc', 'price_desc', 'popularity', 'rating', 'meta' ];
	public const FACET_LOGICS         = [ 'smart', 'or', 'and' ];

	/**
	 * Human labels for SORTS (admin + presets).
	 *
	 * @return array<string,string> slug => label
	 */
	public static function sort_labels(): array {
		return [
			'date'       => __( 'Newest', 'omniloop' ),
			'title'      => __( 'Title A–Z', 'omniloop' ),
			'price_asc'  => __( 'Price: low to high', 'omniloop' ),
			'price_desc' => __( 'Price: high to low', 'omniloop' ),
			'popularity' => __( 'Popularity', 'omniloop' ),
			'rating'     => __( 'Rating', 'omniloop' ),
			'meta'       => __( 'Custom meta', 'omniloop' ),
		];
	}

	/** @var array<string,bool> */
	private $has_index = [];

	/** @var array<string,string[]> */
	private $keys_cache = [];

	/** @var array<string,int[]> */
	private $ids_cache = [];

	/** @var array<string,array{sql:string,params:array<int,mixed>}> */
	private $base_filter_cache = [];

	/**
	 * @param array<string,mixed> $args Sanitized query args.
	 * @return array{post_ids:int[],total:int,facets:array<string,array<string,int>>,price_min:float,price_max:float}
	 */
	public function query( array $args ): array {
		$post_type   = (string) $args['post_type'];
		$per_page    = max( 1, min( self::MAX_PER_PAGE, (int) $args['per_page'] ) );
		$page        = max( 1, (int) $args['page'] );
		$search      = (string) $args['search'];
		$facets      = is_array( $args['facets'] ?? null ) ? $args['facets'] : [];
		$facet_logic = self::sanitize_facet_logic( (string) ( $args['facet_logic'] ?? 'smart' ) );

		$ids = $this->ids( $args );

		$total     = count( $ids );
		$offset    = ( $page - 1 ) * $per_page;
		$page_ids  = array_slice( $ids, $offset, $per_page );
		$facet_map = $this->facet_counts( $post_type, $facets, $search, $ids, $facet_logic );
		$bounds    = $this->price_bounds( $post_type );

		return [
			'post_ids'  => $page_ids,
			'total'     => $total,
			'facets'    => $facet_map,
			'price_min' => $bounds['min'],
			'price_max' => $bounds['max'],
		];
	}

	/**
	 * All matching IDs (unpaged), with merchandising applied when enabled.
	 *
	 * @param array<string,mixed> $args Sanitized query args.
	 * @return int[]
	 */
	public function ids( array $args ): array {
		$post_type   = (string) $args['post_type'];
		$search      = (string) $args['search'];
		$sort        = (string) $args['sort_by'];
		$facets      = is_array( $args['facets'] ?? null ) ? $args['facets'] : [];
		$facet_logic = self::sanitize_facet_logic( (string) ( $args['facet_logic'] ?? 'smart' ) );
		$sort_meta   = Indexer::sanitize_facet_key( (string) ( $args['sort_meta_key'] ?? '' ) );

		$ids = $this->matching_ids( $post_type, $facets, $search, $sort, $facet_logic, $sort_meta );
		if ( Merchandising::is_enabled( $args['merchandising'] ?? true ) ) {
			$ids = Merchandising::reorder( $post_type, $ids, $args['extra_pin_ids'] ?? [] );
		}

		return $ids;
	}

	public static function uses_fuzzy_search(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return true;
		}
		$settings = get_option( 'omniloop_settings', [] );
		$search   = is_array( $settings ) && isset( $settings['search'] ) && is_array( $settings['search'] )
			? $settings['search']
			: [];

		return ! isset( $search['fuzzy'] ) || 'off' !== $search['fuzzy'];
	}

	public static function sanitize_facet_logic( string $logic ): string {
		$logic = sanitize_key( $logic );

		return in_array( $logic, self::FACET_LOGICS, true ) ? $logic : 'smart';
	}

	/**
	 * @param array<string,mixed> $facets
	 * @return int[]
	 */
	private function matching_ids( string $post_type, array $facets, string $search, string $sort, string $facet_logic = 'smart', string $sort_meta = '' ): array {
		$cache_key = md5(
			wp_json_encode(
				[
					$post_type,
					$facets,
					$search,
					$sort,
					$facet_logic,
					$sort_meta,
					I18n::current_language(),
					self::uses_fuzzy_search(),
				]
			)
		);
		if ( isset( $this->ids_cache[ $cache_key ] ) ) {
			return $this->ids_cache[ $cache_key ];
		}

		global $wpdb;

		$table     = Installer::table_name();
		$has_index = $this->has_index_rows( $post_type );

		if ( 'meta' === $sort && '' === $sort_meta ) {
			$sort = 'date';
		}

		$sort_facet = $this->sort_facet_key( $sort, $sort_meta );
		$sort_dir   = $this->sort_direction( $sort );

		if ( $has_index && null !== $sort_facet ) {
			$sql    = "SELECT DISTINCT p.ID, MIN(ip.numeric_value) AS ol_sort
				FROM {$wpdb->posts} p
				LEFT JOIN {$table} ip ON ip.post_id = p.ID AND ip.facet_key = %s
				WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''";
			$params = [ $sort_facet, $post_type ];
		} else {
			$sql    = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''";
			$params = [ $post_type ];
		}

		$this->apply_post_filters( $sql, $params, $post_type, $facets, $search, $facet_logic, $table, $has_index );

		if ( $has_index && null !== $sort_facet ) {
			$sql .= " GROUP BY p.ID ORDER BY ol_sort IS NULL ASC, ol_sort {$sort_dir}, p.ID ASC";
		} elseif ( 'title' === $sort ) {
			$sql .= ' ORDER BY p.post_title ASC, p.ID ASC';
		} else {
			$sql .= ' ORDER BY p.post_date DESC, p.ID DESC';
		}

		$prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_string( $prepared ) ) {
			$this->ids_cache[ $cache_key ] = [];
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$col = $wpdb->get_col( $prepared );

		$ids = array_map( 'intval', is_array( $col ) ? $col : [] );
		$this->ids_cache[ $cache_key ] = $ids;

		return $ids;
	}

	/**
	 * @param array<string,mixed> $facets
	 * @param string[]            $params
	 */
	private function append_facet_filters( string &$sql, array &$params, string $post_type, array $facets, string $facet_logic, string $table ): void {
		foreach ( $facets as $key => $values ) {
			$key    = Indexer::sanitize_facet_key( (string) $key );
			$values = $this->normalize_values( $values );
			if ( '' === $key || empty( $values ) ) {
				continue;
			}

			$op = $this->operator_for_key( $key, $facet_logic );
			if ( 'and' === $op ) {
				foreach ( $values as $value ) {
					$sql     .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = %s AND i.facet_value = %s)";
					$params[] = $post_type;
					$params[] = $key;
					$params[] = $value;
				}
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
			$sql         .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = %s AND i.facet_value IN ({$placeholders}))";
			$params[]     = $post_type;
			$params[]     = $key;
			foreach ( $values as $value ) {
				$params[] = $value;
			}
		}
	}

	private function operator_for_key( string $key, string $facet_logic ): string {
		if ( 'and' === $facet_logic ) {
			return 'and';
		}
		if ( 'or' === $facet_logic ) {
			return 'or';
		}

		return WooFacets::is_attribute_taxonomy( $key ) ? 'and' : 'or';
	}

	private function sort_facet_key( string $sort, string $sort_meta ): ?string {
		if ( 'price_asc' === $sort || 'price_desc' === $sort ) {
			return 'price';
		}
		if ( 'popularity' === $sort ) {
			return 'popularity';
		}
		if ( 'rating' === $sort ) {
			return 'rating';
		}
		if ( 'meta' === $sort && '' !== $sort_meta ) {
			return $sort_meta;
		}

		return null;
	}

	private function sort_direction( string $sort ): string {
		if ( 'price_asc' === $sort || 'meta' === $sort ) {
			return 'ASC';
		}

		return 'DESC';
	}

	private function has_index_rows( string $post_type ): bool {
		if ( isset( $this->has_index[ $post_type ] ) ) {
			return $this->has_index[ $post_type ];
		}

		global $wpdb;

		$table    = Installer::table_name();
		$prepared = $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE post_type = %s LIMIT 1",
			$post_type
		);
		if ( ! is_string( $prepared ) ) {
			$this->has_index[ $post_type ] = false;
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$found = (bool) $wpdb->get_var( $prepared );
		$this->has_index[ $post_type ] = $found;

		return $found;
	}

	/**
	 * Counts per facet value. Inactive facets share the current result IDs;
	 * an active facet is counted with that facet removed so other values stay available.
	 *
	 * @param array<string,mixed> $facets
	 * @param int[]               $ids_all Matching IDs with every current filter applied.
	 * @return array<string,array<string,int>>
	 */
	private function facet_counts( string $post_type, array $facets, string $search, array $ids_all, string $facet_logic = 'smart' ): array {
		$keys = $this->facet_keys_for_type( $post_type );
		$out  = [];
		foreach ( $keys as $key ) {
			$out[ $key ] = [];
		}

		$active = [];
		foreach ( $facets as $key => $values ) {
			if ( 'price_range' === $key ) {
				continue;
			}
			$key    = Indexer::sanitize_facet_key( (string) $key );
			$values = $this->normalize_values( $values );
			if ( '' !== $key && $values ) {
				$active[ $key ] = true;
			}
		}

		$shared_keys = [];
		foreach ( $keys as $facet_key ) {
			if ( ! isset( $active[ $facet_key ] ) ) {
				$shared_keys[] = $facet_key;
			}
		}

		foreach ( $this->count_values_for_keys( $post_type, $shared_keys, $ids_all ) as $key => $map ) {
			$out[ $key ] = $map;
		}

		$active_keys = [];
		foreach ( $keys as $facet_key ) {
			if ( isset( $active[ $facet_key ] ) ) {
				$active_keys[] = $facet_key;
			}
		}
		$active_keys = array_slice( $active_keys, 0, self::MAX_FACET_KEYS );

		foreach ( $this->count_active_facet_values( $post_type, $facets, $search, $facet_logic, $active_keys ) as $key => $map ) {
			$out[ $key ] = $map;
		}

		return $out;
	}

	/**
	 * Search + price filters reused by ID matching and batched facet counts.
	 *
	 * @param array<string,mixed> $facets
	 * @return array{sql:string,params:array<int,mixed>}
	 */
	private function base_post_filter_fragment( string $post_type, array $facets, string $search, string $table, bool $has_index ): array {
		$price     = $facets['price_range'] ?? null;
		$cache_key = md5(
			(string) wp_json_encode(
				[
					$post_type,
					$search,
					$price,
					$has_index,
					$table,
					I18n::current_language(),
					self::uses_fuzzy_search(),
				]
			)
		);
		if ( isset( $this->base_filter_cache[ $cache_key ] ) ) {
			return $this->base_filter_cache[ $cache_key ];
		}

		global $wpdb;

		$sql    = '';
		$params = [];

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( self::uses_fuzzy_search() && strlen( $search ) >= 3 && preg_match( '/[a-z]/i', $search ) ) {
				$sql     .= " AND (p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR SOUNDEX(p.post_title) = SOUNDEX(%s) OR p.ID IN (SELECT osi.post_id FROM {$table} osi WHERE osi.post_type = %s AND osi.facet_key = '_sku' AND osi.facet_value LIKE %s))";
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $search;
				$params[] = $post_type;
				$params[] = $like;
			} else {
				$sql     .= " AND (p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR p.ID IN (SELECT osi.post_id FROM {$table} osi WHERE osi.post_type = %s AND osi.facet_key = '_sku' AND osi.facet_value LIKE %s))";
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
				$params[] = $post_type;
				$params[] = $like;
			}
		}

		$price_range = $this->parse_price_range( $price );
		if ( $has_index && $price_range ) {
			$sql     .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = 'price' AND i.numeric_value IS NOT NULL GROUP BY i.post_id HAVING MIN(i.numeric_value) <= %f AND MAX(i.numeric_value) >= %f)";
			$params[] = $post_type;
			$params[] = $price_range[1];
			$params[] = $price_range[0];
		}

		$fragment = [
			'sql'    => $sql,
			'params' => $params,
		];
		$this->base_filter_cache[ $cache_key ] = $fragment;

		return $fragment;
	}

	/**
	 * @param array<string,mixed> $facets
	 * @param mixed[]             $params
	 */
	private function apply_post_filters( string &$sql, array &$params, string $post_type, array $facets, string $search, string $facet_logic, string $table, bool $has_index ): void {
		$base    = $this->base_post_filter_fragment( $post_type, $facets, $search, $table, $has_index );
		$sql    .= $base['sql'];
		$params  = array_merge( $params, $base['params'] );
		$variant_keys = $this->append_variation_filters( $sql, $params, $post_type, $facets, $table );
		foreach ( $variant_keys as $variant_key ) {
			unset( $facets[ $variant_key ] );
		}
		I18n::append_post_language( $sql, $params, $post_type );
		$this->append_woocommerce_visibility( $sql, $params, $post_type, $search );

		unset( $facets['price_range'] );
		if ( $has_index ) {
			$this->append_facet_filters( $sql, $params, $post_type, $facets, $facet_logic, $table );
		}
	}

	/**
	 * Keep selected attributes, stock, sale state, and price on one concrete
	 * variation instead of independently matching rows flattened on the parent.
	 *
	 * @param mixed[]            $params
	 * @param array<string,mixed> $facets
	 * @return string[] Facet keys handled here.
	 */
	private function append_variation_filters( string &$sql, array &$params, string $post_type, array $facets, string $table ): array {
		if ( 'product' !== $post_type ) {
			return [];
		}

		$attribute_keys = [];
		foreach ( $facets as $key => $values ) {
			$key = Indexer::sanitize_facet_key( (string) $key );
			if ( WooFacets::is_attribute_taxonomy( $key ) && $this->normalize_values( $values ) ) {
				$attribute_keys[] = $key;
			}
		}
		if ( ! $attribute_keys ) {
			return [];
		}

		$handled        = $attribute_keys;
		$variant_sql    = "EXISTS (SELECT 1 FROM {$table} olv WHERE olv.post_id = p.ID AND olv.post_type = %s AND olv.source_id > 0";
		$variant_params = [ $post_type ];
		$parent_sql     = '(1=1';
		$parent_params  = [];

		foreach ( array_merge( $attribute_keys, [ '_stock_status', 'on_sale' ] ) as $key ) {
			if ( ! array_key_exists( $key, $facets ) ) {
				continue;
			}
			$values = $this->normalize_values( $facets[ $key ] );
			if ( ! $values ) {
				continue;
			}
			$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
			$variant_sql      .= " AND EXISTS (SELECT 1 FROM {$table} olvx WHERE olvx.post_id = olv.post_id AND olvx.source_id = olv.source_id AND olvx.facet_key = %s AND olvx.facet_value IN ({$placeholders}))";
			$variant_params[]  = $key;
			$parent_sql       .= " AND EXISTS (SELECT 1 FROM {$table} olpx WHERE olpx.post_id = p.ID AND olpx.post_type = %s AND olpx.source_id = 0 AND olpx.facet_key = %s AND olpx.facet_value IN ({$placeholders}))";
			$parent_params[]   = $post_type;
			$parent_params[]   = $key;
			foreach ( $values as $value ) {
				$variant_params[] = $value;
			}
			foreach ( $values as $value ) {
				$parent_params[] = $value;
			}
			$handled[] = $key;
		}

		$price = $this->parse_price_range( $facets['price_range'] ?? null );
		if ( $price ) {
			$variant_sql     .= " AND EXISTS (SELECT 1 FROM {$table} olvp WHERE olvp.post_id = olv.post_id AND olvp.source_id = olv.source_id AND olvp.facet_key = 'price' AND olvp.numeric_value BETWEEN %f AND %f)";
			$variant_params[] = $price[0];
			$variant_params[] = $price[1];
			$parent_sql      .= " AND EXISTS (SELECT 1 FROM {$table} olpp WHERE olpp.post_id = p.ID AND olpp.post_type = %s AND olpp.source_id = 0 AND olpp.facet_key = 'price' AND olpp.numeric_value BETWEEN %f AND %f)";
			$parent_params[] = $post_type;
			$parent_params[] = $price[0];
			$parent_params[] = $price[1];
		}
		$variant_sql .= ' LIMIT 1)';
		$parent_sql  .= ')';
		$sql         .= " AND ({$variant_sql} OR {$parent_sql})";
		$params       = array_merge( $params, $variant_params, $parent_params );

		return array_values( array_unique( $handled ) );
	}

	/**
	 * Match WooCommerce's public catalog visibility rules even though this query
	 * bypasses WC_Product_Query and WP_Query.
	 *
	 * @param mixed[] $params
	 */
	private function append_woocommerce_visibility( string &$sql, array &$params, string $post_type, string $search ): void {
		if ( 'product' !== $post_type || ! function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			return;
		}

		$terms = wc_get_product_visibility_term_ids();
		if ( ! is_array( $terms ) ) {
			return;
		}

		$excluded = [];
		foreach ( [ 'exclude-from-catalog' ] as $key ) {
			if ( ! empty( $terms[ $key ] ) ) {
				$excluded[] = (int) $terms[ $key ];
			}
		}
		if ( '' !== $search && ! empty( $terms['exclude-from-search'] ) ) {
			$excluded[] = (int) $terms['exclude-from-search'];
		}
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) && ! empty( $terms['outofstock'] ) ) {
			$excluded[] = (int) $terms['outofstock'];
		}
		$excluded = array_values( array_unique( array_filter( $excluded ) ) );
		if ( ! $excluded ) {
			return;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $excluded ), '%d' ) );
		$sql .= " AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->term_relationships} oltr
			INNER JOIN {$wpdb->term_taxonomy} oltt ON oltt.term_taxonomy_id = oltr.term_taxonomy_id
			WHERE oltr.object_id = p.ID AND oltt.taxonomy = 'product_visibility' AND oltt.term_id IN ({$placeholders})
		)";
		foreach ( $excluded as $term_id ) {
			$params[] = $term_id;
		}
	}

	/**
	 * One UNION ALL instead of matching_ids() + count per active facet.
	 *
	 * @param array<string,mixed> $facets
	 * @param string[]            $active_keys
	 * @return array<string,array<string,int>>
	 */
	private function count_active_facet_values( string $post_type, array $facets, string $search, string $facet_logic, array $active_keys ): array {
		$out = [];
		foreach ( $active_keys as $key ) {
			$out[ $key ] = [];
		}
		if ( ! $active_keys ) {
			return $out;
		}

		global $wpdb;

		$table     = Installer::table_name();
		$has_index = $this->has_index_rows( $post_type );
		$branches  = [];
		$params    = [];

		foreach ( $active_keys as $facet_key ) {
			$others = $facets;
			unset( $others[ $facet_key ] );

			$sql           = "SELECT i.facet_key, i.facet_value, COUNT(DISTINCT i.post_id) AS c
				FROM {$table} i
				INNER JOIN {$wpdb->posts} p ON p.ID = i.post_id
				WHERE i.post_type = %s AND i.facet_key = %s
				AND p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''";
			$branch_params = [ $post_type, $facet_key, $post_type ];
			$this->apply_post_filters( $sql, $branch_params, $post_type, $others, $search, $facet_logic, $table, $has_index );
			$sql .= ' GROUP BY i.facet_key, i.facet_value';

			$branches[] = '(' . $sql . ')';
			$params     = array_merge( $params, $branch_params );
		}

		$prepared = $wpdb->prepare( implode( ' UNION ALL ', $branches ), $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_string( $prepared ) ) {
			return $out;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $prepared );
		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			$key = (string) $row->facet_key;
			if ( ! isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ][ (string) $row->facet_value ] = (int) $row->c;
		}

		foreach ( $out as $key => $map ) {
			arsort( $map, SORT_NUMERIC );
			$out[ $key ] = array_slice( $map, 0, 80, true );
		}

		return $out;
	}

	/**
	 * @param string[] $keys
	 * @param int[]    $ids
	 * @return array<string,array<string,int>>
	 */
	private function count_values_for_keys( string $post_type, array $keys, array $ids ): array {
		$out = [];
		foreach ( $keys as $key ) {
			$out[ $key ] = [];
		}
		if ( ! $keys || ! $ids ) {
			return $out;
		}

		global $wpdb;

		$table  = Installer::table_name();
		$ids    = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$key_ph = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		foreach ( array_chunk( $ids, 400 ) as $chunk ) {
			$id_ph  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$sql    = "SELECT i.facet_key, i.facet_value, COUNT(DISTINCT i.post_id) AS c
				FROM {$table} i
				WHERE i.post_type = %s AND i.facet_key IN ({$key_ph}) AND i.post_id IN ({$id_ph})
				GROUP BY i.facet_key, i.facet_value";
			$params = array_merge( [ $post_type ], $keys, $chunk );
			$prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $prepared ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $prepared );
			foreach ( is_array( $rows ) ? $rows : [] as $row ) {
				$key = (string) $row->facet_key;
				if ( ! isset( $out[ $key ] ) ) {
					continue;
				}
				$value = (string) $row->facet_value;
				$out[ $key ][ $value ] = ( $out[ $key ][ $value ] ?? 0 ) + (int) $row->c;
			}
		}

		foreach ( $out as $key => $map ) {
			arsort( $map, SORT_NUMERIC );
			$out[ $key ] = array_slice( $map, 0, 80, true );
		}

		return $out;
	}

	/**
	 * @return array{min:float,max:float}
	 */
	public function price_bounds( string $post_type ): array {
		global $wpdb;

		$table = Installer::table_name();
		$sql   = $wpdb->prepare(
			"SELECT MIN(numeric_value) AS mn, MAX(numeric_value) AS mx FROM {$table} WHERE post_type = %s AND facet_key = 'price' AND numeric_value IS NOT NULL",
			$post_type
		);

		if ( ! is_string( $sql ) ) {
			return [ 'min' => 0.0, 'max' => 0.0 ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $sql );
		$min = $row && null !== $row->mn ? (float) $row->mn : 0.0;
		$max = $row && null !== $row->mx ? (float) $row->mx : 0.0;

		return [ 'min' => $min, 'max' => $max ];
	}

	/**
	 * @return string[]
	 */
	public function facet_keys_for_type( string $post_type ): array {
		if ( isset( $this->keys_cache[ $post_type ] ) ) {
			return $this->keys_cache[ $post_type ];
		}
		$taxes = get_object_taxonomies( $post_type, 'objects' );
		$keys  = [];

		if ( is_array( $taxes ) ) {
			foreach ( $taxes as $tax ) {
				if ( ! $tax instanceof \WP_Taxonomy || WooFacets::is_skip_taxonomy( $tax->name ) ) {
					continue;
				}

				$include = ! empty( $tax->public ) || WooFacets::is_attribute_taxonomy( $tax->name );
				if ( $include ) {
					$keys[] = $tax->name;
				}
			}
		}

		$keys = WooFacets::facet_keys( $post_type, $keys );
		$keys = AcfFacets::facet_keys( $post_type, $keys );

		/**
		 * Filter which facet keys appear in counts / UI.
		 *
		 * @param string[] $keys
		 * @param string   $post_type
		 */
		$keys = apply_filters( 'omniloop_facet_keys', $keys, $post_type );

		$clean = array_values( array_unique( array_filter( array_map( [ Indexer::class, 'sanitize_facet_key' ], (array) $keys ) ) ) );
		$this->keys_cache[ $post_type ] = $clean;

		return $clean;
	}

	/**
	 * @param mixed $range
	 * @return array{0:float,1:float}|null
	 */
	private function parse_price_range( $range ): ?array {
		if ( ! is_array( $range ) || count( $range ) < 2 ) {
			return null;
		}

		$min = (float) $range[0];
		$max = (float) $range[1];
		if ( $min > $max ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}

		$converted = MultiCurrency::range_to_base( [ $min, $max ] );

		return [ $converted[0], $converted[1] ];
	}

	/**
	 * @param mixed $values
	 * @return string[]
	 */
	private function normalize_values( $values ): array {
		if ( is_string( $values ) || is_numeric( $values ) ) {
			$values = [ $values ];
		}
		if ( ! is_array( $values ) ) {
			return [];
		}

		$out = [];
		foreach ( array_slice( $values, 0, self::MAX_VALUES_PER_FACET ) as $value ) {
			if ( is_array( $value ) ) {
				continue;
			}
			$clean = sanitize_text_field( (string) $value );
			if ( '' !== $clean ) {
				$out[] = substr( $clean, 0, 191 );
			}
		}

		return array_values( array_unique( $out ) );
	}
}
