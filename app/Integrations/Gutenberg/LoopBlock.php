<?php
/**
 * Dynamic Gutenberg loop block — PHP render is the source of truth.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Gutenberg;

use OmniLoop\Engine\Core\LoopRenderer;
use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoopBlock {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ] );
		add_filter( 'block_categories_all', [ self::class, 'category' ] );
	}

	/**
	 * @param array<int,array<string,mixed>> $categories
	 * @return array<int,array<string,mixed>>
	 */
	public static function category( array $categories ): array {
		return array_merge(
			[
				[
					'slug'  => 'omniloop',
					'title' => __( 'OmniLoop', 'omniloop' ),
					'icon'  => null,
				],
			],
			$categories
		);
	}

	public static function register(): void {
		wp_register_script(
			'omniloop-loop-editor',
			OMNILOOP_URL . 'assets/js/omniloop-block-editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
			OMNILOOP_VERSION,
			true
		);

		wp_register_style( 'omniloop-style', OMNILOOP_URL . 'assets/css/omniloop.css', [], OMNILOOP_VERSION );
		wp_register_script( 'omniloop-engine', OMNILOOP_URL . 'assets/js/omniloop-engine.js', [], OMNILOOP_VERSION, true );

		$types = FilterEndpoint::selectable_post_types();
		$options = [];
		foreach ( $types as $slug => $label ) {
			$options[] = [
				'value' => $slug,
				'label' => $label,
			];
		}

		wp_localize_script(
			'omniloop-loop-editor',
			'omniloopBlock',
			[
				'postTypes'       => $options,
				'defaultPostType' => LoopRenderer::default_post_type(),
			]
		);

		register_block_type(
			OMNILOOP_PATH . 'assets/blocks/loop',
			[
				'render_callback' => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public static function render( array $attributes ): string {
		return LoopRenderer::render( $attributes );
	}
}
