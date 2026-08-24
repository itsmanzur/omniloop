<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\FacetUi;
use PHPUnit\Framework\TestCase;

final class FacetUiTest extends TestCase {

	public function test_show_rule_hides_target_until_source_matches(): void {
		$rules = [
			[
				'id'            => 'color-size',
				'source_facet'  => 'color',
				'source_values' => [ 'red' ],
				'action'        => 'show',
				'target_facet'  => 'size',
			],
		];
		$keys = [ 'color', 'size', 'brand' ];

		$idle = FacetUi::visibility_map( $keys, [], $rules );
		$this->assertTrue( $idle['color'] );
		$this->assertFalse( $idle['size'] );
		$this->assertTrue( $idle['brand'] );

		$red = FacetUi::visibility_map( $keys, [ 'color' => [ 'red' ] ], $rules );
		$this->assertTrue( $red['size'] );

		$blue = FacetUi::visibility_map( $keys, [ 'color' => [ 'blue' ] ], $rules );
		$this->assertFalse( $blue['size'] );
	}

	public function test_hide_rule_hides_target_when_source_matches(): void {
		$rules = [
			[
				'id'            => 'sale-stock',
				'source_facet'  => 'on_sale',
				'source_values' => [],
				'action'        => 'hide',
				'target_facet'  => '_stock_status',
			],
		];
		$map = FacetUi::visibility_map(
			[ 'on_sale', '_stock_status' ],
			[ 'on_sale' => [ 'yes' ] ],
			$rules
		);
		$this->assertFalse( $map['_stock_status'] );
	}

	public function test_filter_counts_drops_zero_unless_selected(): void {
		$counts = [
			'color' => [ 'red' => 3, 'blue' => 0 ],
			'size'  => [],
		];
		$out = FacetUi::filter_counts( $counts, [ 'color' => [ 'blue' ] ], true, true );
		$this->assertSame( [ 'red' => 3, 'blue' => 0 ], $out['color'] );
		$this->assertArrayNotHasKey( 'size', $out );
	}

	public function test_sanitize_rules_drops_empty_and_self_targets(): void {
		$clean = FacetUi::sanitize(
			[
				'hide_zero_count' => 'off',
				'rules'           => [
					[
						'source_facet'  => 'color',
						'target_facet'  => 'color',
						'action'        => 'show',
					],
					[
						'id'            => 'ok',
						'source_facet'  => 'color',
						'target_facet'  => 'size',
						'action'        => 'HIDE',
						'source_values' => 'red, navy',
					],
				],
			]
		);
		$this->assertSame( 'off', $clean['hide_zero_count'] );
		$this->assertCount( 1, $clean['rules'] );
		$this->assertSame( 'hide', $clean['rules'][0]['action'] );
		$this->assertSame( [ 'red', 'navy' ], $clean['rules'][0]['source_values'] );
	}
}