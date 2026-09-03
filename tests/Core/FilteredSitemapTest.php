<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use OmniLoop\Engine\Core\FilteredSitemap;
use PHPUnit\Framework\TestCase;

final class FilteredSitemapTest extends TestCase {

	/** @var mixed */
	private $wpdb_backup;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;
		FilteredSitemap::reset_cache();
	}

	protected function tearDown(): void {
		FilteredSitemap::reset_cache();
		if ( null === $this->wpdb_backup ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->wpdb_backup;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_max_pages_spans_two_sitemaps_when_over_fifty_urls(): void {
		$this->stub_front( 60 );

		$this->assertSame( 2, FilteredSitemap::max_pages() );

		$page_1 = FilteredSitemap::url_list( 1 );
		$page_2 = FilteredSitemap::url_list( 2 );

		$this->assertCount( 50, $page_1 );
		$this->assertCount( 10, $page_2 );

		$locs = array_map(
			static function ( array $row ): string {
				return (string) $row['loc'];
			},
			array_merge( $page_1, $page_2 )
		);
		$this->assertCount( 60, $locs );
		$this->assertCount( 60, array_unique( $locs ) );
		$this->assertSame( [], array_values( array_intersect( $this->locs( $page_1 ), $this->locs( $page_2 ) ) ) );
		$this->assertSame( $this->locs( $page_2 ), array_slice( $locs, 50 ) );
	}

	public function test_max_pages_is_one_when_under_fifty_urls(): void {
		$this->stub_front( 30 );

		$this->assertSame( 1, FilteredSitemap::max_pages() );
		$this->assertCount( 30, FilteredSitemap::url_list( 1 ) );
		$this->assertSame( [], FilteredSitemap::url_list( 2 ) );
	}

	/**
	 * @param list<array{loc:string}> $rows
	 * @return string[]
	 */
	private function locs( array $rows ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$out[] = (string) $row['loc'];
		}

		return $out;
	}

	private function stub_front( int $value_count ): void {
		$values = [];
		for ( $i = 0; $i < $value_count; $i++ ) {
			$values[] = 'v' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT );
		}

		$wpdb         = new SitemapColWpdb();
		$wpdb->values = $values;
		$GLOBALS['wpdb'] = $wpdb;

		$settings = [
			'post_types' => [ 'product' ],
			'seo'        => [
				'sitemap'         => 'on',
				'sitemap_page_id' => 9,
				'sitemap_max'     => 200,
				'sitemap_facets'  => [ 'color' ],
			],
		];

		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $settings ) {
				if ( 'omniloop_settings' === $name ) {
					return $settings;
				}

				return $default;
			}
		);
		Functions\when( 'get_permalink' )->justReturn( 'https://shop.test/shop/' );
		Functions\when( 'trailingslashit' )->alias(
			static function ( $url ) {
				$url = (string) $url;

				return '' === $url ? '/' : rtrim( $url, '/' ) . '/';
			}
		);
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'name'               => 'product',
				'public'             => 1,
				'exclude_from_search' => 0,
			]
		);
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				if ( 'omniloop_facet_keys' === $hook ) {
					return [ 'color' ];
				}

				return $value;
			}
		);
	}
}

/**
 * $wpdb stand-in that returns DISTINCT facet values from get_col().
 */
final class SitemapColWpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var string */
	public $posts = 'wp_posts';

	/** @var string[] */
	public $values = [];

	/**
	 * @param mixed ...$args
	 */
	public function prepare( string $query, ...$args ): string {
		unset( $query, $args );

		return 'sitemap-values';
	}

	/**
	 * @param mixed $query
	 * @return string[]
	 */
	public function get_col( $query ): array {
		unset( $query );

		return $this->values;
	}
}
