<?php
/**
 * Divi 5 native module — server render uses LoopRenderer.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Divi5;

use OmniLoop\Engine\Core\LoopRenderer;
use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
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

class LoopModule implements DependencyInterface {

	public function load() {
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
					$elements->style(
						[
							'attrName' => 'card',
						]
					),
					$elements->style(
						[
							'attrName' => 'cardTitle',
						]
					),
					$elements->style(
						[
							'attrName' => 'cardPrice',
						]
					),
					$elements->style(
						[
							'attrName' => 'filters',
						]
					),
					$elements->style(
						[
							'attrName' => 'cardBadge',
						]
					),
					$elements->style(
						[
							'attrName' => 'cardPriceRegular',
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
		$attrs = is_array( $attrs ) ? $attrs : [];
		$post_type = $attrs['module']['advanced']['postType']['desktop']['value']
			?? $attrs['postType']['innerContent']['desktop']['value']
			?? 'post';
		$per_page  = $attrs['module']['advanced']['perPage']['desktop']['value']
			?? $attrs['perPage']['innerContent']['desktop']['value']
			?? 12;
		$columns      = $attrs['module']['advanced']['cardColumns']['desktop']['value']
			?? $attrs['module']['advanced']['columns']['desktop']['value']
			?? 3;
		$image_ratio  = $attrs['module']['advanced']['imageRatio']['desktop']['value'] ?? '4-3';
		$filter_style = $attrs['module']['advanced']['filterStyle']['desktop']['value'] ?? 'sidebar';
		$card_style   = $attrs['module']['advanced']['cardStyle']['desktop']['value'] ?? 'classic';
		$grid_gap     = $attrs['module']['advanced']['gridGap']['desktop']['value'] ?? 'md';
		$layout_gap   = $attrs['module']['advanced']['layoutGap']['desktop']['value'] ?? 'md';
		$badge_pos    = $attrs['module']['advanced']['badgePosition']['desktop']['value'] ?? 'top-left';
		$title_lines  = $attrs['module']['advanced']['titleLines']['desktop']['value'] ?? '2';
		$image_hover  = $attrs['module']['advanced']['imageHover']['desktop']['value'] ?? 'subtle';
		$empty_title  = $attrs['module']['advanced']['emptyTitle']['desktop']['value'] ?? '';
		$empty_message = $attrs['module']['advanced']['emptyMessage']['desktop']['value'] ?? null;
		$empty_style  = $attrs['module']['advanced']['emptyStyle']['desktop']['value'] ?? 'box';
		$empty_reset  = $attrs['module']['advanced']['emptyResetLabel']['desktop']['value'] ?? '';
		$empty_show_reset = $attrs['module']['advanced']['emptyShowReset']['desktop']['value'] ?? 'on';
		$show_filters = $attrs['module']['advanced']['showFilters']['desktop']['value'] ?? 'on';
		$show_atc     = $attrs['module']['advanced']['showAddToCart']['desktop']['value'] ?? 'on';
		$show_qv      = $attrs['module']['advanced']['showQuickView']['desktop']['value'] ?? 'on';
		$show_pager   = $attrs['module']['advanced']['showPagination']['desktop']['value'] ?? 'on';
		$pager_style  = $attrs['module']['advanced']['paginationStyle']['desktop']['value'] ?? 'pages';
		$filter_sync  = $attrs['module']['advanced']['filterSync']['desktop']['value'] ?? 'on';
		$sync_group   = $attrs['module']['advanced']['syncGroup']['desktop']['value'] ?? 'default';
		$filter_preset = $attrs['module']['advanced']['filterPreset']['desktop']['value'] ?? '';
		$merchandising = $attrs['module']['advanced']['merchandising']['desktop']['value'] ?? 'on';
		$extra_pin_ids = $attrs['module']['advanced']['extraPinIds']['desktop']['value'] ?? '';
		$stock_urgency = $attrs['module']['advanced']['stockUrgency']['desktop']['value'] ?? 'on';
		$promoted_badge = $attrs['module']['advanced']['promotedBadge']['desktop']['value'] ?? 'on';
		$facet_logic  = $attrs['module']['advanced']['facetLogic']['desktop']['value'] ?? 'smart';
		$sort_meta    = $attrs['module']['advanced']['sortMetaKey']['desktop']['value'] ?? '';
		$carousel_autoplay = $attrs['module']['advanced']['carouselAutoplay']['desktop']['value'] ?? 'off';
		$carousel_speed    = $attrs['module']['advanced']['carouselSpeed']['desktop']['value'] ?? '4000';
		$carousel_pause    = $attrs['module']['advanced']['carouselPauseHover']['desktop']['value'] ?? 'on';
		$carousel_loop     = $attrs['module']['advanced']['carouselLoop']['desktop']['value'] ?? 'on';
		$carousel_arrows   = $attrs['module']['advanced']['carouselArrows']['desktop']['value'] ?? 'on';
		$carousel_dots     = $attrs['module']['advanced']['carouselDots']['desktop']['value'] ?? 'on';
		$visible_facets = $attrs['module']['advanced']['visibleFacets']['desktop']['value'] ?? '';
		if ( '' === $show_filters || null === $show_filters ) {
			$show_filters = 'on';
		}
		if ( '' === $show_atc || null === $show_atc ) {
			$show_atc = 'on';
		}
		if ( '' === $show_qv || null === $show_qv ) {
			$show_qv = 'on';
		}
		if ( '' === $show_pager || null === $show_pager ) {
			$show_pager = 'on';
		}

		$empty_attrs = [
			'emptyTitle'      => is_string( $empty_title ) ? $empty_title : '',
			'emptyStyle'      => sanitize_key( (string) $empty_style ),
			'emptyResetLabel' => is_string( $empty_reset ) ? $empty_reset : '',
			'emptyShowReset'  => $empty_show_reset,
		];
		if ( null !== $empty_message ) {
			$empty_attrs['emptyMessage'] = is_string( $empty_message ) ? $empty_message : '';
		}

		$card_parts = LoopItemModule::parts_from_block( $block );

		$inner = LoopRenderer::render(
			array_merge(
				[
					'postType'      => sanitize_key( (string) $post_type ),
					'cardParts'     => $card_parts,
					'perPage'       => (int) $per_page,
					'columns'       => (int) $columns,
					'imageRatio'    => sanitize_key( (string) $image_ratio ),
					'filterStyle'   => sanitize_key( (string) $filter_style ),
					'cardStyle'     => sanitize_key( (string) $card_style ),
					'gridGap'       => sanitize_key( (string) $grid_gap ),
					'layoutGap'     => sanitize_key( (string) $layout_gap ),
					'badgePosition' => sanitize_key( (string) $badge_pos ),
					'titleLines'    => sanitize_key( (string) $title_lines ),
					'imageHover'    => sanitize_key( (string) $image_hover ),
					'showFilters'   => $show_filters,
					'showAddToCart' => $show_atc,
					'showQuickView'  => $show_qv,
					'showPagination' => $show_pager,
					'paginationStyle' => LoopRenderer::sanitize_pagination_style( $pager_style ),
					'filterSync'      => $filter_sync,
					'syncGroup'       => LoopRenderer::sanitize_sync_group( $sync_group ),
					'filterPreset'    => sanitize_key( (string) $filter_preset ),
					'merchandising'   => $merchandising,
					'extraPinIds'     => is_string( $extra_pin_ids ) ? $extra_pin_ids : '',
					'stockUrgency'    => $stock_urgency,
					'promotedBadge'   => $promoted_badge,
					'facetLogic'          => sanitize_key( (string) $facet_logic ),
					'sortMetaKey'         => is_string( $sort_meta ) ? $sort_meta : '',
					'carouselAutoplay'    => sanitize_key( (string) $carousel_autoplay ),
					'carouselSpeed'       => sanitize_key( (string) $carousel_speed ),
					'carouselPauseHover'  => sanitize_key( (string) $carousel_pause ),
					'carouselLoop'        => sanitize_key( (string) $carousel_loop ),
					'carouselArrows'      => sanitize_key( (string) $carousel_arrows ),
					'carouselDots'        => sanitize_key( (string) $carousel_dots ),
					'visibleFacets'       => $visible_facets,
				],
				$empty_attrs
			)
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
