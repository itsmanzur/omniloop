<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use OmniLoop\Engine\Core\QueryBuilder;
use OmniLoop\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class QueryBuilderTest extends TestCase {

	/** @var mixed */
	private $wpdb_backup;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;
		$this->stub_facet_keys();
	}

	protected function tearDown(): void {
		if ( null === $this->wpdb_backup ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->wpdb_backup;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sanitize_facet_logic_keeps_allowed_values(): void {
		$this->assertSame( 'smart', QueryBuilder::sanitize_facet_logic( 'smart' ) );
		$this->assertSame( 'or', QueryBuilder::sanitize_facet_logic( 'OR' ) );
		$this->assertSame( 'and', QueryBuilder::sanitize_facet_logic( 'and' ) );
	}

	public function test_sanitize_facet_logic_falls_back_to_smart(): void {
		$this->assertSame( 'smart', QueryBuilder::sanitize_facet_logic( 'xor' ) );
		$this->assertSame( 'smart', QueryBuilder::sanitize_facet_logic( '' ) );
		$this->assertSame( 'smart', QueryBuilder::sanitize_facet_logic( 'and;drop' ) );
	}

	public function test_sorts_and_logics_are_whitelists(): void {
		$this->assertContains( 'date', QueryBuilder::SORTS );
		$this->assertContains( 'price_asc', QueryBuilder::SORTS );
		$this->assertSame( [ 'smart', 'or', 'and' ], QueryBuilder::FACET_LOGICS );
		$this->assertSame( 48, QueryBuilder::MAX_PER_PAGE );
		$this->assertSame( 24, QueryBuilder::MAX_FACET_KEYS );
		$this->assertSame( 20, QueryBuilder::MAX_VALUES_PER_FACET );
	}

	public function test_facet_counts_match_leave_one_facet_out_semantics(): void {
		$wpdb = $this->catalog_wpdb();
		$GLOBALS['wpdb'] = $wpdb;

		$builder = new QueryBuilder();
		$facets  = [
			'color' => [ 'red' ],
			'size'  => [ 'm' ],
		];
		$ids_all = [ 1 ];

		$actual = $this->call(
			$builder,
			'facet_counts',
			[ 'product', $facets, '', $ids_all, 'smart' ]
		);

		$this->assertSame( [ 'acme' => 1 ], $actual['brand'] );
		$this->assertEqualsCanonicalizing( [ 'red' => 1, 'blue' => 1 ], $actual['color'] );
		$this->assertEqualsCanonicalizing( [ 'm' => 1, 'l' => 1 ], $actual['size'] );
	}

	public function test_active_facet_counts_use_one_union_query_not_n_id_lookups(): void {
		$wpdb = $this->catalog_wpdb();
		$GLOBALS['wpdb'] = $wpdb;

		$builder = new QueryBuilder();
		$facets  = [
			'color' => [ 'red' ],
			'size'  => [ 'm' ],
		];

		$this->call(
			$builder,
			'facet_counts',
			[ 'product', $facets, '', [ 1 ], 'smart' ]
		);

		$id_lookups = 0;
		$unions     = 0;
		foreach ( $wpdb->log as $entry ) {
			$sql = $entry['sql'];
			if ( false !== stripos( $sql, 'SELECT DISTINCT p.ID' ) ) {
				++$id_lookups;
			}
			if ( false !== stripos( $sql, 'UNION ALL' ) ) {
				++$unions;
			}
		}

		$active = 2;
		$this->assertSame( 0, $id_lookups, 'active facet counts must not re-run matching_ids() per facet' );
		$this->assertSame( 1, $unions, 'active facet counts should be one UNION ALL statement' );
		$this->assertLessThan( 2 * $active, $wpdb->reads() );
	}

	/**
	 * @param mixed[] $args
	 * @return mixed
	 */
	private function call( QueryBuilder $builder, string $method, array $args ) {
		$ref = new ReflectionMethod( QueryBuilder::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $builder, $args );
	}

	private function catalog_wpdb(): FakeWpdb {
		$wpdb             = new FakeWpdb();
		$wpdb->post_rows  = [
			$this->post( 1, 'Red M' ),
			$this->post( 2, 'Blue M' ),
			$this->post( 3, 'Red L' ),
			$this->post( 4, 'Green L' ),
		];
		$wpdb->index_rows = [
			$this->idx( 1, 'color', 'red' ),
			$this->idx( 1, 'size', 'm' ),
			$this->idx( 1, 'brand', 'acme' ),
			$this->idx( 2, 'color', 'blue' ),
			$this->idx( 2, 'size', 'm' ),
			$this->idx( 2, 'brand', 'acme' ),
			$this->idx( 3, 'color', 'red' ),
			$this->idx( 3, 'size', 'l' ),
			$this->idx( 3, 'brand', 'foo' ),
			$this->idx( 4, 'color', 'green' ),
			$this->idx( 4, 'size', 'l' ),
			$this->idx( 4, 'brand', 'foo' ),
		];

		return $wpdb;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function post( int $id, string $title ): array {
		return [
			'ID'            => $id,
			'post_type'     => 'product',
			'post_status'   => 'publish',
			'post_password' => '',
			'post_title'    => $title,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function idx( int $id, string $key, string $value ): array {
		return [
			'post_id'       => $id,
			'post_type'     => 'product',
			'facet_key'     => $key,
			'facet_value'   => $value,
			'numeric_value' => null,
		];
	}

	private function stub_facet_keys(): void {
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				if ( 'omniloop_facet_keys' === $hook ) {
					return [ 'color', 'size', 'brand' ];
				}

				return $value;
			}
		);
	}
}
