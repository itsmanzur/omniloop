<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\FacetUi;
use OmniLoop\Engine\Core\SeoMeta;
use OmniLoop\Engine\Core\SeoUrls;
use PHPUnit\Framework\TestCase;

final class SeoMetaTest extends TestCase {

	public function test_expand_replaces_placeholders_and_trims_pipes(): void {
		$out = SeoMeta::expand(
			'{page_title} | {facet_labels} | {search}',
			[
				'page_title'   => 'Shop',
				'facet_labels' => 'Red, Shoes',
				'search'       => '',
			]
		);
		$this->assertSame( 'Shop | Red, Shoes', $out );
	}

	public function test_robots_noindex_filtered_when_enabled(): void {
		$state = [
			'page'   => 1,
			'sort'   => 'date',
			'search' => '',
			'view'   => '',
			'facets' => [ 'product_cat' => [ 'shoes' ] ],
		];
		$this->assertTrue(
			SeoMeta::robots_for_state(
				$state,
				[ 'noindex_filtered' => 'on', 'noindex_paged' => 'off' ]
			)
		);
		$this->assertFalse(
			SeoMeta::robots_for_state(
				[
					'page'   => 1,
					'sort'   => 'date',
					'search' => '',
					'view'   => '',
					'facets' => [],
				],
				[ 'noindex_filtered' => 'on', 'noindex_paged' => 'on' ]
			)
		);
	}

	public function test_robots_noindex_paged_by_default(): void {
		$state = [
			'page'   => 2,
			'sort'   => 'date',
			'search' => '',
			'view'   => '',
			'facets' => [],
		];
		$this->assertTrue( SeoUrls::state_is_filtered( $state ) );
		$this->assertTrue(
			SeoMeta::robots_for_state( $state, [ 'noindex_filtered' => 'off', 'noindex_paged' => 'on' ] )
		);
		$this->assertFalse(
			SeoMeta::robots_for_state( $state, [ 'noindex_filtered' => 'off', 'noindex_paged' => 'off' ] )
		);
	}

	public function test_sanitize_clamps_sitemap_max(): void {
		$clean = SeoMeta::sanitize(
			[
				'title_template' => '<b>Hi {category}</b>',
				'sitemap_max'    => 9999,
				'sitemap'        => 'on',
			]
		);
		$this->assertSame( 1000, $clean['sitemap_max'] );
		$this->assertSame( 'on', $clean['sitemap'] );
		$this->assertStringNotContainsString( '<b>', $clean['title_template'] );
	}
}