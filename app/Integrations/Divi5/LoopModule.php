<?php
/**
 * Divi 5 native module — server render uses LoopRenderer.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Divi5;

use OmniLoop\Engine\Core\LoopRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( '\ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
	$divi_dependency_interface = get_template_directory() . '/includes/builder-5/server/Framework/DependencyManagement/Interfaces/DependencyInterface.php';
	if ( is_readable( $divi_dependency_interface ) ) {
		require_once $divi_dependency_interface;
	}
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

class LoopModule implements DependencyInterface {

	public function load(): void {
		add_action( 'init', [ self::class, 'register_module' ] );
	}

	public static function register_module(): void {
		if ( ! class_exists( ModuleRegistration::class ) ) {
			return;
		}

		ModuleRegistration::register_module(
			OMNILOOP_PATH . 'app/Integrations/Divi5/visual-builder/loop',
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	public static function module_styles( array $args ): void {
		$elements = $args['elements'];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
				],
			]
		);
	}

	public static function module_script_data( array $args ): void {
		$args['elements']->script_data( [ 'attrName' => 'module' ] );
	}

	public static function module_classnames( array $args ): void {
		$args['classnamesInstance']->add( 'omniloop-divi' );
		$args['classnamesInstance']->add(
			ElementClassnames::classnames(
				[ 'attrs' => $args['attrs']['module']['decoration'] ?? [] ]
			)
		);
	}

	public static function render_callback( $attrs, $content, $block, $elements ) {
		$post_type = $attrs['module']['advanced']['postType']['desktop']['value']
			?? $attrs['postType']['innerContent']['desktop']['value']
			?? 'post';
		$per_page  = $attrs['module']['advanced']['perPage']['desktop']['value']
			?? $attrs['perPage']['innerContent']['desktop']['value']
			?? 12;
		$columns      = $attrs['module']['advanced']['columns']['desktop']['value'] ?? 3;
		$image_ratio  = $attrs['module']['advanced']['imageRatio']['desktop']['value'] ?? '4-3';
		$show_filters = $attrs['module']['advanced']['showFilters']['desktop']['value'] ?? 'on';

		$inner = LoopRenderer::render(
			[
				'postType'    => sanitize_key( (string) $post_type ),
				'perPage'     => (int) $per_page,
				'columns'     => (int) $columns,
				'imageRatio'  => sanitize_key( (string) $image_ratio ),
				'showFilters' => $show_filters,
			]
		);

		$module_inner = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [ 'class' => 'et_pb_module_inner' ],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $inner,
			]
		);

		$module_elements = $elements->style_components( [ 'attrName' => 'module' ] );

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'moduleClassName'     => 'ol_loop',
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'children'            => $module_elements . $module_inner,
			]
		);
	}
}
