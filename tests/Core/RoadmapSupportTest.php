<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use OmniLoop\Engine\Core\FacetAnalytics;
use OmniLoop\Engine\Core\I18n;
use OmniLoop\Engine\Core\MultiCurrency;
use OmniLoop\Engine\Core\SettingsIo;
use PHPUnit\Framework\TestCase;

final class RoadmapSupportTest extends TestCase {

	public function test_analytics_sanitize_accepts_click_and_rejects_junk(): void {
		$ok = FacetAnalytics::sanitize_event(
			[
				'event_type'   => 'click',
				'post_type'    => 'product',
				'facet_key'    => 'pa_color',
				'facet_value'  => 'red',
				'result_count' => 12,
			]
		);
		$this->assertIsArray( $ok );
		$this->assertSame( 'click', $ok['event_type'] );
		$this->assertSame( 'pa_color', $ok['facet_key'] );

		$this->assertNull(
			FacetAnalytics::sanitize_event(
				[
					'event_type' => 'hack',
					'post_type'  => 'product',
				]
			)
		);
	}

	public function test_settings_io_parse_roundtrip_and_reject_foreign_plugin(): void {
		$payload = [
			'plugin'   => 'omniloop',
			'settings' => [
				'post_types' => [ 'product' ],
				'seo'        => [ 'sitemap' => 'on' ],
			],
		];
		$parsed = SettingsIo::parse_import( wp_json_encode( $payload ) );
		$this->assertIsArray( $parsed );
		$this->assertArrayHasKey( 'post_types', $parsed );
		$this->assertArrayHasKey( 'seo', $parsed );

		$error = SettingsIo::parse_import( '{"plugin":"other","settings":{"post_types":["post"]}}' );
		$this->assertTrue( is_wp_error( $error ) );
	}

	public function test_multi_currency_identity_without_converters(): void {
		$this->assertSame( 19.99, MultiCurrency::from_base( 19.99 ) );
		$this->assertSame( [ 10.0, 40.0 ], MultiCurrency::range_to_base( [ 10.0, 40.0 ] ) );
		$bounds = MultiCurrency::bounds_for_display( 5.0, 20.0 );
		$this->assertSame( 5.0, $bounds['min'] );
		$this->assertSame( 20.0, $bounds['max'] );
	}

	public function test_i18n_language_empty_without_wpml(): void {
		$this->assertSame( '', I18n::current_language() );
	}

	public function test_portable_keys_cover_agency_settings(): void {
		$keys = SettingsIo::portable_keys();
		$this->assertContains( 'filter_presets', $keys );
		$this->assertContains( 'merchandising', $keys );
		$this->assertContains( 'seo', $keys );
		$this->assertContains( 'facet_ui', $keys );
	}
}