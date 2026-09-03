<?php
/**
 * @package OmniLoop
 */

namespace OmniLoop\Tests\Integrations;

use Brain\Monkey;
use Brain\Monkey\Functions;
use OmniLoop\Engine\Integrations\Divi5\NativeLoop;
use OmniLoop\Tests\Support\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class NativeLoopTest extends TestCase {

	/** @var mixed */
	private $wpdb_backup;

	/** @var array<string,mixed> */
	private $get_backup;

	/** @var mixed */
	private $uri_backup;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;
		$this->get_backup  = $_GET;
		$this->uri_backup  = $_SERVER['REQUEST_URI'] ?? null;

		$wpdb            = new FakeWpdb();
		$wpdb->post_rows = [
			[
				'ID'            => 101,
				'post_type'     => 'product',
				'post_status'   => 'publish',
				'post_password' => '',
				'post_title'    => 'Beanie',
			],
		];
		$GLOBALS['wpdb'] = $wpdb;

		$_GET                      = [ 'ol_q' => 'beanie' ];
		$_SERVER['REQUEST_URI']    = '/shop/?ol_q=beanie';

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'name'                => 'product',
				'public'              => 1,
				'exclude_from_search' => 0,
			]
		);
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) {
				if ( 'omniloop_settings' === $name ) {
					return [ 'post_types' => [ 'product' ] ];
				}

				return $default;
			}
		);
		Functions\when( 'get_object_taxonomies' )->justReturn( [] );
		Functions\when( 'get_query_var' )->justReturn( '' );
		Functions\when( 'home_url' )->justReturn( 'https://shop.test/' );
		Functions\when( 'wp_unslash' )->alias(
			static function ( $value ) {
				return $value;
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				return parse_url( $url, $component );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				if ( 'omniloop_facet_keys' === $hook ) {
					return [ 'color' ];
				}

				return $value;
			}
		);
	}

	protected function tearDown(): void {
		$_GET = $this->get_backup;
		if ( null === $this->uri_backup ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->uri_backup;
		}
		if ( null === $this->wpdb_backup ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->wpdb_backup;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_query_vars_registers_attach_flag(): void {
		$vars = NativeLoop::query_vars( [ 'post_type' ] );
		$this->assertContains( 'omniloop_attach', $vars );
		$this->assertContains( 'post_type', $vars );
	}

	public function test_attach_query_skips_secondary_archive_style_query(): void {
		$query = $this->product_listing_query( false );

		NativeLoop::attach_query( $query );

		$this->assertSame( '', $query->get( 'post__in' ) );
	}

	public function test_attach_query_still_restricts_main_listing_query(): void {
		$query = $this->product_listing_query( true );

		NativeLoop::attach_query( $query );

		$ids = $query->get( 'post__in' );
		$this->assertIsArray( $ids );
		$this->assertNotSame( [], $ids );
	}

	public function test_forced_attach_applies_on_secondary_query(): void {
		$_GET                   = [];
		$_SERVER['REQUEST_URI'] = '/shop/';

		$query = $this->product_listing_query( false );
		$query->set( 'omniloop_attach', 1 );

		NativeLoop::attach_query( $query );

		$ids = $query->get( 'post__in' );
		$this->assertIsArray( $ids );
		$this->assertNotSame( [], $ids );
	}

	private function product_listing_query( bool $is_main ): \WP_Query {
		$query                         = new \WP_Query( [ 'post_type' => 'product' ] );
		$query->is_post_type_archive   = true;
		$query->is_home                = false;
		$query->is_singular            = false;
		$query->set_is_main_query( $is_main );

		return $query;
	}
}
