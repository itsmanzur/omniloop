<?php
/**
 * Divi 5 child module — one element in a custom OmniLoop card template.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Divi5;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'ET\\Builder\\Framework\\DependencyManagement\\Interfaces\\DependencyInterface', false ) ) {
	$divi_dependency_interface = get_template_directory() . '/includes/builder-5/server/Framework/DependencyManagement/Interfaces/DependencyInterface.php';
	if ( is_readable( $divi_dependency_interface ) ) {
		require_once $divi_dependency_interface;
	}
}

class LoopItemModule implements DependencyInterface {

	public const TYPES = [ 'image', 'title', 'excerpt', 'price', 'rating', 'addToCart', 'quickView' ];

	public function load() {
		add_action( 'init', [ self::class, 'register_module' ] );
	}

	public static function register_module(): void {
		if ( ! class_exists( ModuleRegistration::class ) ) {
			return;
		}

		ModuleRegistration::register_module(
			OMNILOOP_PATH . 'app/Integrations/Divi5/visual-builder/item',
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	/**
	 * Template-only: the parent loop clones this element per result.
	 *
	 * @param array<string,mixed> $attrs   Block attributes.
	 * @param string              $content Inner content.
	 * @param mixed               $block   Parsed block.
	 * @param mixed               $elements Module elements.
	 */
	public static function render_callback( $attrs, $content, $block, $elements ) {
		unset( $attrs, $content, $block, $elements );

		return '';
	}

	/**
	 * @param mixed $block WP_Block or parsed block array.
	 * @return string[]
	 */
	public static function parts_from_block( $block ): array {
		$inner = [];
		if ( is_object( $block ) && isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] ) ) {
			$inner = $block->parsed_block['innerBlocks'];
		} elseif ( is_array( $block ) && isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$inner = $block['innerBlocks'];
		}

		$parts = [];
		foreach ( $inner as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$name = (string) ( $child['blockName'] ?? $child['name'] ?? '' );
			if ( 'omniloop/loop-item' !== $name ) {
				continue;
			}
			$type = $child['attrs']['module']['advanced']['elementType']['desktop']['value'] ?? 'title';
			$type = sanitize_key( (string) $type );
			if ( 'addtocart' === $type ) {
				$type = 'addToCart';
			}
			if ( 'quickview' === $type ) {
				$type = 'quickView';
			}
			if ( in_array( $type, self::TYPES, true ) ) {
				$parts[] = $type;
			}
		}

		return $parts;
	}
}
