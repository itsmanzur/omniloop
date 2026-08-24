<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Integrations;

use OmniLoop\Engine\Integrations\Divi5\NativeLoop;
use PHPUnit\Framework\TestCase;

final class NativeLoopTest extends TestCase {

	public function test_query_vars_registers_attach_flag(): void {
		$vars = NativeLoop::query_vars( [ 'post_type' ] );
		$this->assertContains( 'omniloop_attach', $vars );
		$this->assertContains( 'post_type', $vars );
	}
}