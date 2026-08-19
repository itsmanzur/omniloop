<?php
/**
 * Divi 5 adapter — registers the loop module and Visual Builder bundle.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Divi5;

use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bootstrap {

	public static function init(): void {
		add_action(
			'divi_module_library_modules_dependency_tree',
			static function ( $dependency_tree ): void {
				if ( is_object( $dependency_tree ) && method_exists( $dependency_tree, 'add_dependency' ) ) {
					$dependency_tree->add_dependency( new LoopModule() );
					$dependency_tree->add_dependency( new LoopItemModule() );
				}
			}
		);

		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', [ self::class, 'enqueue_visual_builder_assets' ] );
	}

	public static function enqueue_visual_builder_assets(): void {
		if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
			return;
		}
		if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
			return;
		}

		$bundle = OMNILOOP_PATH . 'app/Integrations/Divi5/visual-builder/build/loop.js';
		if ( ! is_readable( $bundle ) ) {
			return;
		}

		wp_enqueue_style(
			'omniloop-style',
			OMNILOOP_URL . 'assets/css/omniloop.css',
			[],
			OMNILOOP_VERSION
		);

		$bridge = wp_json_encode(
			[
				'restUrl'      => rest_url( 'omniloop/v1/query' ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'postTypes'    => FilterEndpoint::selectable_post_types(),
				'facetOptions' => FilterEndpoint::selectable_facets(),
				'filterPresets' => FilterEndpoint::selectable_presets(),
			],
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);

		wp_register_script( 'omniloop-vb-bridge', '', [], OMNILOOP_VERSION, false );
		wp_enqueue_script( 'omniloop-vb-bridge' );
		wp_add_inline_script( 'omniloop-vb-bridge', 'window.omniloopVb = ' . ( $bridge ? $bridge : '{}' ) . ';', 'before' );

		if ( ! class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
			return;
		}

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			[
				'name'    => 'omniloop-divi-vb-loop',
				'version' => OMNILOOP_VERSION,
				'script'  => [
					'src'                => OMNILOOP_URL . 'app/Integrations/Divi5/visual-builder/build/loop.js',
					'deps'               => [ 'omniloop-vb-bridge', 'jquery', 'divi-module-library', 'wp-hooks', 'divi-rest' ],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
					'data_app_window'    => [
						'restUrl'   => rest_url( 'omniloop/v1/query' ),
						'nonce'     => wp_create_nonce( 'wp_rest' ),
						'postTypes'    => FilterEndpoint::selectable_post_types(),
						'facetOptions' => FilterEndpoint::selectable_facets(),
					],
				],
			]
		);
	}
}
