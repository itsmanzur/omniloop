<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\HelpVideo;
use PHPUnit\Framework\TestCase;

final class HelpVideoTest extends TestCase {

	public function test_parses_youtube_watch_url(): void {
		$parsed = HelpVideo::parse( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
		$this->assertSame( 'youtube', $parsed['type'] );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $parsed['src'] );
	}

	public function test_parses_youtu_be_and_shorts(): void {
		$short = HelpVideo::parse( 'https://youtu.be/dQw4w9WgXcQ' );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $short['src'] );

		$shorts = HelpVideo::parse( 'https://youtube.com/shorts/dQw4w9WgXcQ' );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $shorts['src'] );
	}

	public function test_parses_vimeo_and_mp4(): void {
		$vimeo = HelpVideo::parse( 'https://vimeo.com/123456789' );
		$this->assertSame( 'vimeo', $vimeo['type'] );
		$this->assertSame( 'https://player.vimeo.com/video/123456789', $vimeo['src'] );

		$file = HelpVideo::parse( 'https://example.com/uploads/intro.mp4' );
		$this->assertSame( 'file', $file['type'] );
		$this->assertSame( 'https://example.com/uploads/intro.mp4', $file['src'] );
	}

	public function test_rejects_unsafe_or_empty_urls(): void {
		$this->assertSame( [], HelpVideo::parse( '' ) );
		$this->assertSame( [], HelpVideo::parse( 'javascript:alert(1)' ) );
		$this->assertSame( [], HelpVideo::parse( 'https://example.com/page' ) );
		$this->assertSame( '', HelpVideo::sanitize_url( 'https://evil.example/watch?v=<script>' ) );
	}
}
