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

	public const MAX_PER_PAGE = 48;
	public const SORTS        = [ 'date', 'title', 'price_asc', 'price_desc', 'popularity', 'rating', 'meta' ];
	public const FACET_LOGICS = [ 'smart', 'or', 'and' ];

	/** @var array<string,bool> */
	private $has_index = [];

	/** @var array<string,string[]> */
	private $keys_cache = [];

	/** @var array<string,int[]> */
	private $ids_cache = [];

	/**
	 * @param array<string,mixed> $args Sanitized query args.
	 * @return array{post_ids:int[],total:int,facets:array<string,array<string,int>>,price_min:float,price_max:float}
	 */
	public function query( array $args ): array {
		$post_type   = (string) $args['post_type'];
		$per_page    = max( 1, min( self::MAX_PER_PAGE, (int) $args['per_page'] ) );
		$page        = max( 1, (int) $args['page'] );
		$search      = (string) $args['search'];
		$sort        = (string) $args['sort_by'];
		$facets      = is_array( $args['facets'] ?? null ) ? $args['facets'] : [];
		$facet_logic = self::sanitize_facet_logic( (string) ( $args['facet_logic'] ?? 'smart' ) );
		$sort_meta   = Indexer::sanitize_facet_key( (string) ( $args['sort_meta_key'] ?? '' ) );

		$ids = $this->matching_ids( $post_type, $facets, $search, $sort, $facet_logic, $sort_meta );
		if ( Merchandising::is_enabled( $args['merchandising'] ?? true ) ) {
			$ids = Merchandising::reorder( $post_type, $ids, $args['extra_pin_ids'] ?? [] );
		}

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
				]
			)
		);
		if ( isset( $this->ids_cache[ $cache_key ] ) ) {
			return $this->ids_cache[ $cache_key ];
		}

		global $wpdb;

		$table     = Installer::table_name();
		$has_index = $this->has_index_rows( $post_type );

		$price_range = $this->parse_price_range( $facets['price_range'] ?? null );
		unset( $facets['price_range'] );

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

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$sql     .= ' AND p.post_title LIKE %s';
			$params[] = $like;
		}

		if ( $has_index && $price_range ) {
			$sql     .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = 'price' AND i.numeric_value BETWEEN %f AND %f)";
			$params[] = $post_type;
			$params[] = $price_range[0];
			$params[] = $price_range[1];
		}

		if ( $has_index ) {
			$this->append_facet_filters( $sql, $params, $post_type, $facets, $facet_logic, $table );
		}

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

		foreach ( $keys as $facet_key ) {
			if ( ! isset( $active[ $facet_key ] ) ) {
				continue;
			}
			$others = $facets;
			unset( $others[ $facet_key ] );
			$ids     = $this->matching_ids( $post_type, $others, $search, 'date', $facet_logic, '' );
			$counted = $this->count_values_for_keys( $post_type, [ $facet_key ], $ids );
			$out[ $facet_key ] = $counted[ $facet_key ] ?? [];
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

		$table     = Installer::table_name();
		$ids       = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$id_ph     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$key_ph    = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$sql       = "SELECT i.facet_key, i.facet_value, COUNT(DISTINCT i.post_id) AS c
			FROM {$table} i
			WHERE i.post_type = %s AND i.facet_key IN ({$key_ph}) AND i.post_id IN ({$id_ph})
			GROUP BY i.facet_key, i.facet_value";
		$params    = array_merge( [ $post_type ], $keys, $ids );
		$prepared  = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		return [ $min, $max ];
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
		foreach ( array_slice( $values, 0, 20 ) as $value ) {
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
