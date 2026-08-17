<?php
/**
 * Plugin singleton — wires REST, indexer, Divi 5, and admin.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

use OmniLoop\Engine\Admin\DocsPage;
use OmniLoop\Engine\Admin\SettingsPage;
use OmniLoop\Engine\Integrations\Divi5\Bootstrap as DiviBootstrap;
use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class App {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		Installer::maybe_upgrade();

		if ( (string) get_option( 'omniloop_asset_version', '' ) !== OMNILOOP_VERSION ) {
			CacheManager::bump();
			update_option( 'omniloop_asset_version', OMNILOOP_VERSION, false );
		}

		Indexer::init();
		SeoUrls::init();
		FilterEndpoint::init();
		DiviBootstrap::init();
		SettingsPage::init();
		DocsPage::init();

		add_action( 'init', [ Installer::class, 'maybe_enable_product' ], 20 );
		add_action( 'init', [ $this, 'register_assets' ], 5 );
		add_action( 'omniloop_reindex_batch', [ Indexer::class, 'process_batch' ] );
	}

	public function register_assets(): void {
		wp_register_style(
			'omniloop-style',
			OMNILOOP_URL . 'assets/css/omniloop.css',
			[],
			OMNILOOP_VERSION
		);

		wp_register_script(
			'omniloop-engine',
			OMNILOOP_URL . 'assets/js/omniloop-engine.js',
			[],
			OMNILOOP_VERSION,
			true
		);
	}
}
