<?php
/**
 * In-memory $wpdb stand-in for QueryBuilder SQL tests.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Support;

final class FakeWpdb {

	/** @var string */
	public $posts = 'wp_posts';

	/** @var string */
	public $prefix = 'wp_';

	/** @var list<array<string,mixed>> */
	public $post_rows = [];

	/** @var list<array<string,mixed>> */
	public $index_rows = [];

	/** @var list<array{sql:string,args:array<int,mixed>}> */
	public $log = [];

	/** @var array<string,array{sql:string,args:array<int,mixed>}> */
	private $prepared = [];

	public function esc_like( string $text ): string {
		return $text;
	}

	/**
	 * @param mixed ...$args
	 */
	public function prepare( string $query, ...$args ): string {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$args = array_values( $args );
		$this->log[] = [
			'sql'  => $query,
			'args' => $args,
		];
		$token                   = 'q:' . count( $this->prepared );
		$this->prepared[ $token ] = [
			'sql'  => $query,
			'args' => $args,
		];

		return $token;
	}

	/**
	 * @param mixed $query
	 * @return mixed
	 */
	public function get_var( $query ) {
		$parsed = $this->lookup( $query );
		if ( false !== stripos( $parsed['sql'], 'LIMIT 1' ) && false !== stripos( $parsed['sql'], 'omniloop_index' ) ) {
			return empty( $this->index_rows ) ? null : '1';
		}

		return null;
	}

	/**
	 * @param mixed $query
	 * @return int[]
	 */
	public function get_col( $query ): array {
		$parsed = $this->lookup( $query );
		$ids    = $this->matching_post_ids( $parsed['sql'], $parsed['args'] );
		sort( $ids );

		return $ids;
	}

	/**
	 * @param mixed $query
	 * @return list<object>
	 */
	public function get_results( $query ): array {
		$parsed = $this->lookup( $query );
		$sql    = $parsed['sql'];
		$args   = $parsed['args'];

		if ( false !== stripos( $sql, 'UNION ALL' ) ) {
			$offset = 0;
			$merged = [];
			foreach ( preg_split( '/\)\s*UNION ALL\s*\(/i', $sql ) ?: [] as $branch ) {
				$branch  = trim( $branch, "() \n\t" );
				$count   = preg_match_all( '/%[sdf]/', $branch ) ?: 0;
				$slice   = array_slice( $args, $offset, $count );
				$offset += $count;
				foreach ( $this->count_rows( $branch, $slice ) as $row ) {
					$merged[] = $row;
				}
			}

			return $merged;
		}

		return $this->count_rows( $sql, $args );
	}

	public function reads(): int {
		$n = 0;
		foreach ( $this->log as $entry ) {
			$sql = $entry['sql'];
			if ( false !== stripos( $sql, 'SELECT DISTINCT p.ID' ) || false !== stripos( $sql, 'COUNT(DISTINCT' ) ) {
				++$n;
			}
		}

		return $n;
	}

	/**
	 * @param mixed $query
	 * @return array{sql:string,args:array<int,mixed>}
	 */
	private function lookup( $query ): array {
		if ( is_string( $query ) && isset( $this->prepared[ $query ] ) ) {
			return $this->prepared[ $query ];
		}

		return [
			'sql'  => is_string( $query ) ? $query : '',
			'args' => [],
		];
	}

	/**
	 * @param mixed[] $args
	 * @return int[]
	 */
	private function matching_post_ids( string $sql, array $args ): array {
		$ids = [];
		foreach ( $this->post_rows as $post ) {
			if ( $this->post_matches( $post, $sql, $args ) ) {
				$ids[] = (int) $post['ID'];
			}
		}

		return $ids;
	}

	/**
	 * @param array<string,mixed> $post
	 * @param mixed[]             $args
	 */
	private function post_matches( array $post, string $sql, array $args ): bool {
		$interpolated = $this->interpolate( $sql, $args );
		if ( ! preg_match( "/p\\.post_type = '([^']+)'/", $interpolated, $type_match ) ) {
			return false;
		}
		if ( (string) $post['post_type'] !== $type_match[1] || 'publish' !== $post['post_status'] || '' !== (string) $post['post_password'] ) {
			return false;
		}
		if ( preg_match( "/p\\.post_title LIKE '%([^']+)%'/", $interpolated, $search_match ) ) {
			if ( false === stripos( (string) $post['post_title'], $search_match[1] ) ) {
				return false;
			}
		}

		foreach ( $this->facet_clauses( $interpolated ) as $clause ) {
			if ( ! $this->post_has_facet( (int) $post['ID'], $clause['key'], $clause['values'], $clause['numeric'], ! empty( $clause['overlap'] ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param mixed[] $args
	 * @return list<object>
	 */
	private function count_rows( string $sql, array $args ): array {
		$interpolated = $this->interpolate( $sql, $args );
		$key          = '';
		if ( preg_match( "/i\\.facet_key = '([^']+)'/", $interpolated, $key_match ) ) {
			$key = $key_match[1];
		} elseif ( preg_match( "/i\\.facet_key IN \\(([^)]+)\\)/", $interpolated, $in_match ) ) {
			$keys = array_map(
				static function ( string $item ): string {
					return trim( $item, " '\"" );
				},
				explode( ',', $in_match[1] )
			);

			return $this->count_for_keys( $keys, $this->ids_from_in_list( $interpolated ) );
		}

		$ids = [];
		if ( preg_match( '/i\\.post_id IN \\(([^)]+)\\)/', $interpolated, $id_match ) ) {
			$ids = $this->ids_from_list( $id_match[1] );
		} else {
			$ids = $this->matching_post_ids( $sql, $args );
		}

		return $this->count_for_keys( '' === $key ? [] : [ $key ], $ids );
	}

	/**
	 * @param string[] $keys
	 * @param int[]    $ids
	 * @return list<object>
	 */
	private function count_for_keys( array $keys, array $ids ): array {
		$tally = [];
		$idset = array_fill_keys( $ids, true );
		$seen  = [];
		foreach ( $this->index_rows as $row ) {
			$pid = (int) $row['post_id'];
			$fk  = (string) $row['facet_key'];
			if ( $keys && ! in_array( $fk, $keys, true ) ) {
				continue;
			}
			if ( $idset && ! isset( $idset[ $pid ] ) ) {
				continue;
			}
			$fv      = (string) $row['facet_value'];
			$seen_id = $fk . '|' . $fv . '|' . $pid;
			if ( isset( $seen[ $seen_id ] ) ) {
				continue;
			}
			$seen[ $seen_id ]        = true;
			$tally[ $fk ][ $fv ]     = ( $tally[ $fk ][ $fv ] ?? 0 ) + 1;
		}

		$out = [];
		foreach ( $tally as $fk => $map ) {
			foreach ( $map as $fv => $c ) {
				$out[] = (object) [
					'facet_key'   => $fk,
					'facet_value' => $fv,
					'c'           => $c,
				];
			}
		}

		return $out;
	}

	/**
	 * @return list<array{key:string,values:string[],numeric:array{0:float,1:float}|null}>
	 */
	private function facet_clauses( string $sql ): array {
		$clauses = [];
		if ( preg_match_all( "/i\\.facet_key = 'price' AND i\\.numeric_value BETWEEN ([0-9.]+) AND ([0-9.]+)/", $sql, $price_matches, PREG_SET_ORDER ) ) {
			foreach ( $price_matches as $match ) {
				$clauses[] = [
					'key'     => 'price',
					'values'  => [],
					'numeric' => [ (float) $match[1], (float) $match[2] ],
					'overlap' => false,
				];
			}
		}
		if ( preg_match_all( '/HAVING MIN\(i\.numeric_value\) <= ([0-9.]+) AND MAX\(i\.numeric_value\) >= ([0-9.]+)/', $sql, $have_matches, PREG_SET_ORDER ) ) {
			foreach ( $have_matches as $match ) {
				$clauses[] = [
					'key'     => 'price',
					'values'  => [],
					'numeric' => [ (float) $match[2], (float) $match[1] ],
					'overlap' => true,
				];
			}
		}
		if ( preg_match_all( "/i\\.facet_key = '([^']+)' AND i\\.facet_value IN \\(([^)]+)\\)/", $sql, $in_matches, PREG_SET_ORDER ) ) {
			foreach ( $in_matches as $match ) {
				if ( 'price' === $match[1] ) {
					continue;
				}
				$clauses[] = [
					'key'     => $match[1],
					'values'  => $this->quoted_list( $match[2] ),
					'numeric' => null,
				];
			}
		}
		if ( preg_match_all( "/i\\.facet_key = '([^']+)' AND i\\.facet_value = '([^']+)'/", $sql, $eq_matches, PREG_SET_ORDER ) ) {
			foreach ( $eq_matches as $match ) {
				$clauses[] = [
					'key'     => $match[1],
					'values'  => [ $match[2] ],
					'numeric' => null,
				];
			}
		}

		return $clauses;
	}

	/**
	 * @param string[]                    $values
	 * @param array{0:float,1:float}|null $numeric
	 */
	private function post_has_facet( int $post_id, string $key, array $values, ?array $numeric, bool $overlap = false ): bool {
		if ( null !== $numeric && $overlap ) {
			$min = null;
			$max = null;
			foreach ( $this->index_rows as $row ) {
				if ( (int) $row['post_id'] !== $post_id || (string) $row['facet_key'] !== $key || null === $row['numeric_value'] ) {
					continue;
				}
				$n   = (float) $row['numeric_value'];
				$min = null === $min ? $n : min( $min, $n );
				$max = null === $max ? $n : max( $max, $n );
			}
			if ( null === $min || null === $max ) {
				return false;
			}

			return $min <= $numeric[1] && $max >= $numeric[0];
		}

		foreach ( $this->index_rows as $row ) {
			if ( (int) $row['post_id'] !== $post_id || (string) $row['facet_key'] !== $key ) {
				continue;
			}
			if ( null !== $numeric ) {
				$n = (float) $row['numeric_value'];
				if ( $n >= $numeric[0] && $n <= $numeric[1] ) {
					return true;
				}
				continue;
			}
			if ( in_array( (string) $row['facet_value'], $values, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private function quoted_list( string $list ): array {
		return array_map(
			static function ( string $item ): string {
				return trim( $item, " '\"" );
			},
			explode( ',', $list )
		);
	}

	/**
	 * @return int[]
	 */
	private function ids_from_in_list( string $sql ): array {
		if ( preg_match( '/i\\.post_id IN \\(([^)]+)\\)/', $sql, $match ) ) {
			return $this->ids_from_list( $match[1] );
		}

		return [];
	}

	/**
	 * @return int[]
	 */
	private function ids_from_list( string $list ): array {
		$ids = [];
		foreach ( explode( ',', $list ) as $item ) {
			$ids[] = (int) trim( $item );
		}

		return $ids;
	}

	/**
	 * @param mixed[] $args
	 */
	private function interpolate( string $sql, array $args ): string {
		$i = 0;

		return (string) preg_replace_callback(
			'/%[sdf]/',
			static function ( array $match ) use ( &$i, $args ): string {
				$v = $args[ $i ] ?? '';
				++$i;
				if ( '%s' === $match[0] ) {
					return "'" . str_replace( "'", "''", (string) $v ) . "'";
				}
				if ( '%f' === $match[0] ) {
					return (string) (float) $v;
				}

				return (string) (int) $v;
			},
			$sql
		);
	}
}
