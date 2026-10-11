<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\LoopRenderer;
use PHPUnit\Framework\TestCase;

final class LoopRendererCopyTest extends TestCase {

	public function test_blank_quick_view_label_falls_back_to_default(): void {
		$this->assertSame( 'Quick view', LoopRenderer::quick_view_label( '' ) );
		$this->assertSame( 'Quick view', LoopRenderer::quick_view_label( '   ' ) );
	}

	public function test_custom_quick_view_label_is_kept(): void {
		$this->assertSame( 'Peek', LoopRenderer::quick_view_label( 'Peek' ) );
		$this->assertSame( 'Quick look', LoopRenderer::sanitize_quick_view_label( " Quick look \n" ) );
	}

	public function test_quick_view_label_strips_tags_and_truncates(): void {
		$this->assertSame( 'Peek', LoopRenderer::sanitize_quick_view_label( '<em>Peek</em>' ) );
		$long = str_repeat( 'a', 100 );
		$this->assertSame( 80, strlen( LoopRenderer::sanitize_quick_view_label( $long ) ) );
	}
}
