<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\FilterPresets;
use OmniLoop\Engine\Core\QueryBuilder;
use OmniLoop\Engine\Core\WooFacets;
use PHPUnit\Framework\TestCase;

final class AdminPolishTest extends TestCase {

	public function test_preset_id_comes_from_label_when_blank(): void {
		$list = FilterPresets::sanitize_list(
			[
				[
					'label'     => 'Summer Sale',
					'id'        => '',
					'post_type' => 'product',
					'sort_by'   => 'price_asc',
					'facets'    => [
						'on_sale'     => [ 'yes' ],
						'product_cat' => 'shoes',
					],
				],
			]
		);

		$this->assertCount( 1, $list );
		$this->assertSame( 'summer-sale', $list[0]['id'] );
		$this->assertSame( 'Summer Sale', $list[0]['label'] );
		$this->assertSame( [ 'yes' ], $list[0]['facets']['on_sale'] );
		$this->assertSame( [ 'shoes' ], $list[0]['facets']['product_cat'] );
	}

	public function test_preset_facets_accept_price_range_array(): void {
		$facets = FilterPresets::sanitize_facets(
			[
				'price_range' => [ 40, 12 ],
				'pa_color'    => [ 'navy', 'navy', '' ],
			]
		);
		$this->assertSame( [ 12.0, 40.0 ], $facets['price_range'] );
		$this->assertSame( [ 'navy' ], $facets['pa_color'] );
	}

	public function test_duplicate_preset_ids_get_a_suffix(): void {
		$list = FilterPresets::sanitize_list(
			[
				[
					'label'     => 'Sale',
					'post_type' => 'product',
				],
				[
					'label'     => 'Sale',
					'post_type' => 'product',
				],
			]
		);
		$this->assertSame( 'sale', $list[0]['id'] );
		$this->assertSame( 'sale-2', $list[1]['id'] );
	}

	public function test_sort_labels_cover_every_sort_key(): void {
		$labels = QueryBuilder::sort_labels();
		foreach ( QueryBuilder::SORTS as $sort ) {
			$this->assertArrayHasKey( $sort, $labels );
			$this->assertNotSame( $sort, $labels[ $sort ] );
		}
	}

	public function test_variation_attribute_keys_strip_attribute_prefix(): void {
		$this->assertSame( 'pa_color', WooFacets::normalize_variation_attribute_key( 'attribute_pa_color' ) );
		$this->assertSame( 'pa_size', WooFacets::normalize_variation_attribute_key( 'PA_Size' ) );
	}

	public function test_variation_attribute_index_skips_any_and_blank(): void {
		$rows = WooFacets::variation_attribute_index_values(
			[
				'attribute_pa_color' => 'Red',
				'attribute_pa_size'  => 'any',
				'custom'             => '',
			]
		);
		$this->assertSame(
			[
				[ 'pa_color', 'red', null ],
			],
			$rows
		);
	}
}
