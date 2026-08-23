<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\SeoUrls;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SeoUrlsTest extends TestCase {

	public function test_query_vars_registers_seo_var(): void {
		$vars = SeoUrls::query_vars( [ 'pagename' ] );
		$this->assertContains( SeoUrls::QUERY_VAR, $vars );
		$this->assertContains( 'pagename', $vars );
	}

	public function test_state_is_filtered_detects_active_filters(): void {
		$empty = [
			'page'   => 1,
			'sort'   => 'date',
			'search' => '',
			'view'   => '',
			'facets' => [],
		];
		$this->assertFalse( SeoUrls::state_is_filtered( $empty ) );

		$empty['search'] = 'boots';
		$this->assertTrue( SeoUrls::state_is_filtered( $empty ) );

		$empty['search']  = '';
		$empty['facets'] = [ 'product_cat' => [ 'shoes' ] ];
		$this->assertTrue( SeoUrls::state_is_filtered( $empty ) );
	}

	public function test_parse_price_token_handles_range_and_swap(): void {
		$this->assertSame( [ 10.0, 50.0 ], $this->call( 'parse_price_token', [ '10-50' ] ) );
		$this->assertSame( [ 10.0, 50.0 ], $this->call( 'parse_price_token', [ '50-10' ] ) );
		$this->assertSame( [ 0.0, 99.0 ], $this->call( 'parse_price_token', [ '99' ] ) );
		$this->assertNull( $this->call( 'parse_price_token', [ '' ] ) );
	}

	public function test_split_values_splits_plus_and_comma(): void {
		$this->assertSame(
			[ 'red', 'blue', 'green' ],
			$this->call( 'split_values', [ 'red+blue,green' ] )
		);
	}

	public function test_merge_segments_maps_aliases_and_reserved_pairs(): void {
		$state = [
			'page'   => 1,
			'sort'   => 'date',
			'search' => '',
			'view'   => '',
			'facets' => [],
		];
		$aliases = [ 'color' => 'pa_color' ];
		$out     = $this->call(
			'merge_segments',
			[ $state, 'color/red+navy/p/2/sort/price_asc', $aliases ]
		);

		$this->assertSame( 2, $out['page'] );
		$this->assertSame( 'price_asc', $out['sort'] );
		$this->assertSame( [ 'red', 'navy' ], $out['facets']['pa_color'] );
	}

	/**
	 * @param mixed[] $args
	 * @return mixed
	 */
	private function call( string $method, array $args ) {
		$ref = new ReflectionMethod( SeoUrls::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}
}
