<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\Indexer;
use PHPUnit\Framework\TestCase;

final class IndexerTest extends TestCase {

	public function test_sanitize_facet_key_lowercases_and_strips_junk(): void {
		$this->assertSame( 'pa_color', Indexer::sanitize_facet_key( 'PA_Color' ) );
		$this->assertSame( 'product_cat', Indexer::sanitize_facet_key( 'product_cat!!' ) );
		$this->assertSame( 'on-sale', Indexer::sanitize_facet_key( 'on-sale' ) );
	}

	public function test_sanitize_facet_key_truncates_to_fifty_chars(): void {
		$key = str_repeat( 'a', 80 );
		$this->assertSame( 50, strlen( Indexer::sanitize_facet_key( $key ) ) );
	}

	public function test_sanitize_facet_key_allows_empty_after_strip(): void {
		$this->assertSame( '', Indexer::sanitize_facet_key( '!!!' ) );
	}
}
