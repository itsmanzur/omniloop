<?php
/**
 * Plugin singleton — wires REST, indexer, Gutenberg, Divi 5, admin, shortcode.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

use OmniLoop\Engine\Admin\SettingsPage;
use OmniLoop\Engine\Integrations\Divi5\Bootstrap as DiviBootstrap;
use OmniLoop\Engine\Integrations\Gutenberg\LoopBlock;
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

		Indexer::init();
		FilterEndpoint::init();
		LoopBlock::init();
		DiviBootstrap::init();
		SettingsPage::init();

		add_shortcode( 'omniloop', [ LoopRenderer::class, 'shortcode' ] );
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
