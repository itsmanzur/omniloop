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
	public const SORTS        = [ 'date', 'title', 'price_asc', 'price_desc' ];

	/**
	 * @param array<string,mixed> $args Sanitized query args.
	 * @return array{post_ids:int[],total:int,facets:array<string,array<string,int>>,price_min:float,price_max:float}
	 */
	public function query( array $args ): array {
		$post_type = (string) $args['post_type'];
		$per_page  = max( 1, min( self::MAX_PER_PAGE, (int) $args['per_page'] ) );
		$page      = max( 1, (int) $args['page'] );
		$search    = (string) $args['search'];
		$sort      = (string) $args['sort_by'];
		$facets    = is_array( $args['facets'] ?? null ) ? $args['facets'] : [];

		$ids = $this->matching_ids( $post_type, $facets, $search, $sort );

		$total     = count( $ids );
		$offset    = ( $page - 1 ) * $per_page;
		$page_ids  = array_slice( $ids, $offset, $per_page );
		$facet_map = $this->facet_counts( $post_type, $facets, $search );
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
	 * @param array<string,mixed> $facets
	 * @return int[]
	 */
	private function matching_ids( string $post_type, array $facets, string $search, string $sort ): array {
		global $wpdb;

		$table      = Installer::table_name();
		$has_index  = $this->has_index_rows( $post_type );
		$sql        = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''";
		$params     = [ $post_type ];

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$sql    .= ' AND p.post_title LIKE %s';
			$params[] = $like;
		}

		$price_range = $this->parse_price_range( $facets['price_range'] ?? null );
		unset( $facets['price_range'] );

		if ( ! $has_index ) {
			if ( 'title' === $sort ) {
				$sql .= ' ORDER BY p.post_title ASC, p.ID ASC';
			} else {
				$sql .= ' ORDER BY p.post_date DESC, p.ID DESC';
			}
			$prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $prepared ) ) {
				return [];
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$col = $wpdb->get_col( $prepared );
			return array_map( 'intval', is_array( $col ) ? $col : [] );
		}

		if ( $price_range ) {
			$sql     .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = 'price' AND i.numeric_value BETWEEN %f AND %f)";
			$params[] = $post_type;
			$params[] = $price_range[0];
			$params[] = $price_range[1];
		}

		foreach ( $facets as $key => $values ) {
			$key    = Indexer::sanitize_facet_key( (string) $key );
			$values = $this->normalize_values( $values );
			if ( '' === $key || empty( $values ) ) {
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

		if ( 'title' === $sort ) {
			$sql .= ' ORDER BY p.post_title ASC, p.ID ASC';
		} elseif ( 'price_asc' === $sort || 'price_desc' === $sort ) {
			$dir  = 'price_desc' === $sort ? 'DESC' : 'ASC';
			$sql  = "SELECT DISTINCT p.ID, MIN(ip.numeric_value) AS ol_price
				FROM {$wpdb->posts} p
				LEFT JOIN {$table} ip ON ip.post_id = p.ID AND ip.facet_key = 'price'
				WHERE p.post_type = %s AND p.post_status = 'publish' AND p.post_password = ''";
			$params = [ $post_type ];

			if ( '' !== $search ) {
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$sql     .= ' AND p.post_title LIKE %s';
				$params[] = $like;
			}

			if ( $price_range ) {
				$sql     .= " AND p.ID IN (SELECT i.post_id FROM {$table} i WHERE i.post_type = %s AND i.facet_key = 'price' AND i.numeric_value BETWEEN %f AND %f)";
				$params[] = $post_type;
				$params[] = $price_range[0];
				$params[] = $price_range[1];
			}

			foreach ( $facets as $key => $values ) {
				$key    = Indexer::sanitize_facet_key( (string) $key );
				$values = $this->normalize_values( $values );
				if ( '' === $key || empty( $values ) ) {
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

			$sql .= " GROUP BY p.ID ORDER BY ol_price IS NULL ASC, ol_price {$dir}, p.ID ASC";
		} else {
			$sql .= ' ORDER BY p.post_date DESC, p.ID DESC';
		}

		$prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_string( $prepared ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$col = $wpdb->get_col( $prepared );

		return array_map( 'intval', is_array( $col ) ? $col : [] );
	}

	private function has_index_rows( string $post_type ): bool {
		global $wpdb;

		$table    = Installer::table_name();
		$prepared = $wpdb->prepare(
			"SELECT 1 FROM {$table} WHERE post_type = %s LIMIT 1",
			$post_type
		);
		if ( ! is_string( $prepared ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $prepared );
	}

	/**
	 * Counts per facet value with the other filters applied.
	 *
	 * @param array<string,mixed> $facets
	 * @return array<string,array<string,int>>
	 */
	private function facet_counts( string $post_type, array $facets, string $search ): array {
		global $wpdb;

		$table = Installer::table_name();
		$keys  = $this->facet_keys_for_type( $post_type );
		$out   = [];

		foreach ( $keys as $facet_key ) {
			$others = $facets;
			unset( $others[ $facet_key ], $others['price_range'] );

			$ids = $this->matching_ids( $post_type, $others, $search, 'date' );
			if ( empty( $ids ) ) {
				$out[ $facet_key ] = [];
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql          = "SELECT i.facet_value, COUNT(DISTINCT i.post_id) AS c
				FROM {$table} i
				WHERE i.post_type = %s AND i.facet_key = %s AND i.post_id IN ({$placeholders})
				GROUP BY i.facet_value
				ORDER BY c DESC
				LIMIT 80";

			$params   = array_merge( [ $post_type, $facet_key ], $ids );
			$prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $prepared ) ) {
				$out[ $facet_key ] = [];
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $prepared );
			$map  = [];
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$map[ (string) $row->facet_value ] = (int) $row->c;
				}
			}
			$out[ $facet_key ] = $map;
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
		$taxes = get_object_taxonomies( $post_type, 'names' );
		$keys  = [];

		if ( is_array( $taxes ) ) {
			foreach ( $taxes as $tax ) {
				$object = get_taxonomy( $tax );
				if ( $object && ! empty( $object->public ) ) {
					$keys[] = $tax;
				}
			}
		}

		$keys[] = '_stock_status';

		/**
		 * Filter which facet keys appear in counts / UI.
		 *
		 * @param string[] $keys
		 * @param string   $post_type
		 */
		$keys = apply_filters( 'omniloop_facet_keys', $keys, $post_type );

		return array_values( array_unique( array_filter( array_map( [ Indexer::class, 'sanitize_facet_key' ], (array) $keys ) ) ) );
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
