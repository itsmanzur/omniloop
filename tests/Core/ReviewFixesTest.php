<?php
/**
 * Regression tests for cache keys, debounced indexing and settings-driven reindex.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Core;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use OmniLoop\Engine\Admin\SettingsPage;
use OmniLoop\Engine\Core\CacheManager;
use OmniLoop\Engine\Core\Indexer;
use PHPUnit\Framework\TestCase;

final class ReviewFixesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'get_option' )->justReturn( false );
	}

	protected function tearDown(): void {
		$this->addToAssertionCount( \Mockery::getContainer()->mockery_getExpectationCount() );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_cache_key_differs_for_currencies_sharing_a_symbol(): void {
		Functions\when( 'get_woocommerce_currency_symbol' )->justReturn( '$' );

		Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		$usd = CacheManager::query_key( [ 'post_type' => 'product' ] );

		Functions\when( 'get_woocommerce_currency' )->justReturn( 'CAD' );
		$cad = CacheManager::query_key( [ 'post_type' => 'product' ] );

		$this->assertNotSame( $usd, $cad );
	}

	public function test_enqueue_registers_one_shutdown_flush_and_dedupes(): void {
		Actions\expectAdded( 'shutdown' )->once();

		Indexer::enqueue( 10 );
		Indexer::enqueue( 10 );
		Indexer::enqueue( 11 );
		Indexer::enqueue( 0 );

		$queue = ( new \ReflectionProperty( Indexer::class, 'queue' ) );
		$queue->setAccessible( true );
		$this->assertSame( [ 10, 11 ], array_keys( $queue->getValue() ) );

		$queue->setValue( null, [] );
	}

	public function test_settings_save_reindexes_when_post_types_change(): void {
		Functions\expect( 'wp_schedule_single_event' )->once();
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );

		SettingsPage::maybe_reindex_after_save(
			[ 'post_types' => [ 'post' ] ],
			[ 'post_types' => [ 'post', 'product' ] ]
		);
	}

	public function test_settings_save_skips_reindex_when_only_order_changes(): void {
		Functions\expect( 'wp_schedule_single_event' )->never();

		SettingsPage::maybe_reindex_after_save(
			[ 'post_types' => [ 'product', 'post' ], 'acf_fields' => [] ],
			[ 'post_types' => [ 'post', 'product' ], 'seo' => [ 'noindex_filtered' => 'on' ] ]
		);
	}
}
