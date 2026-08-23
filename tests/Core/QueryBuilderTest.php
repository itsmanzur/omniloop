<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase {

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
	}
}
