<?php
/**
 * Server markup for the Divi 5 OmniLoop module.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoopRenderer {

	private const FACET_VISIBLE = 8;

	/** @var string */
	private static $card_style = 'classic';

	/** @var bool */
	private static $show_add_to_cart = false;

	/** @var bool */
	private static $show_quick_view = false;

	/** @var string */
	private static $quick_view_label = '';

	/** @var bool */
	private static $show_stock_urgency = true;

	/** @var bool */
	private static $show_promoted_badge = true;

	/** @var string[] */
	private static $card_parts = [];

	public static function default_post_type(): string {
		return FilterEndpoint::is_allowed_post_type( 'product' ) ? 'product' : 'post';
	}

	public static function sanitize_columns( $value ): int {
		$columns = (int) $value;
		return in_array( $columns, [ 2, 3, 4 ], true ) ? $columns : 3;
	}

	public static function sanitize_ratio( $value ): string {
		$ratio = sanitize_key( (string) $value );
		return in_array( $ratio, [ '4-3', '1-1', '16-9', '3-4' ], true ) ? $ratio : '4-3';
	}

	public static function sanitize_filter_style( $value ): string {
		$style = sanitize_key( (string) $value );
		return in_array( $style, [ 'sidebar', 'accordion', 'toolbar', 'pills', 'megabar', 'chipsonly' ], true ) ? $style : 'sidebar';
	}

	public static function sanitize_card_style( $value ): string {
		$style = sanitize_key( (string) $value );
		return in_array( $style, [ 'classic', 'overlay', 'list', 'compact', 'carousel', 'masonry' ], true ) ? $style : 'classic';
	}

	public static function sanitize_grid_gap( $value ): string {
		$gap = sanitize_key( (string) $value );
		return in_array( $gap, [ 'sm', 'md', 'lg', 'xl' ], true ) ? $gap : 'md';
	}

	public static function sanitize_layout_gap( $value ): string {
		$gap = sanitize_key( (string) $value );
		return in_array( $gap, [ 'sm', 'md', 'lg' ], true ) ? $gap : 'md';
	}

	public static function sanitize_badge_position( $value ): string {
		$pos = sanitize_key( (string) $value );
		return in_array( $pos, [ 'top-left', 'top-right', 'bottom-left', 'bottom-right' ], true ) ? $pos : 'top-left';
	}

	public static function sanitize_title_lines( $value ): string {
		$lines = sanitize_key( (string) $value );
		return in_array( $lines, [ '1', '2', '3' ], true ) ? $lines : '2';
	}

	public static function sanitize_image_hover( $value ): string {
		$hover = sanitize_key( (string) $value );
		return in_array( $hover, [ 'off', 'subtle', 'strong' ], true ) ? $hover : 'subtle';
	}

	public static function sanitize_pagination_style( $value ): string {
		$style = sanitize_key( (string) $value );

		return in_array( $style, [ 'pages', 'load_more', 'infinite' ], true ) ? $style : 'pages';
	}

	public static function sanitize_sync_group( $value ): string {
		$group = sanitize_key( (string) $value );

		return '' !== $group ? $group : 'default';
	}

	public static function sanitize_empty_style( $value ): string {
		$style = sanitize_key( (string) $value );
		return in_array( $style, [ 'minimal', 'box', 'search', 'bag' ], true ) ? $style : 'box';
	}

	/**
	 * Visible Quick view button label. Blank falls back to the translated default.
	 *
	 * @param mixed $value
	 */
	public static function sanitize_quick_view_label( $value ): string {
		$label = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			$label = mb_substr( $label, 0, 80 );
		} else {
			$label = substr( $label, 0, 80 );
		}

		return trim( $label );
	}

	public static function quick_view_label( $value = null ): string {
		$label = self::sanitize_quick_view_label( null === $value ? self::$quick_view_label : $value );

		return '' !== $label ? $label : __( 'Quick view', 'omniloop' );
	}

	/**
	 * @param array<string,mixed> $attributes
	 * @return array{title:string,message:string,style:string,resetLabel:string,showReset:bool}
	 */
	public static function empty_copy( array $attributes ): array {
		$title   = sanitize_text_field( (string) ( $attributes['emptyTitle'] ?? '' ) );
		$message = sanitize_textarea_field( (string) ( $attributes['emptyMessage'] ?? '' ) );
		$reset   = sanitize_text_field( (string) ( $attributes['emptyResetLabel'] ?? '' ) );
		$style   = self::sanitize_empty_style( $attributes['emptyStyle'] ?? 'box' );

		if ( '' === $title ) {
			$title = __( 'No results found', 'omniloop' );
		}
		if ( '' === $message && ! array_key_exists( 'emptyMessage', $attributes ) ) {
			$message = __( 'Try clearing filters or searching for something else.', 'omniloop' );
		}
		if ( '' === $reset ) {
			$reset = __( 'Reset filters', 'omniloop' );
		}

		return [
			'title'      => $title,
			'message'    => $message,
			'style'      => $style,
			'resetLabel' => $reset,
			'showReset'  => self::is_flag_on( $attributes['emptyShowReset'] ?? 'on' ),
		];
	}

	/**
	 * @param array{title:string,message:string,style:string,resetLabel:string,showReset:bool} $copy
	 */
	public static function empty_html( array $copy, bool $can_reset = false ): string {
		$style = self::sanitize_empty_style( $copy['style'] ?? 'box' );
		$html  = '<div class="omniloop-empty omniloop-empty--' . esc_attr( $style ) . '" role="status">';
		$html .= self::empty_icon_html( $style );
		$html .= '<p class="omniloop-empty__title">' . esc_html( (string) ( $copy['title'] ?? '' ) ) . '</p>';
		$message = trim( (string) ( $copy['message'] ?? '' ) );
		if ( '' !== $message ) {
			$html .= '<p class="omniloop-empty__message">' . esc_html( $message ) . '</p>';
		}
		if ( ! empty( $copy['showReset'] ) && $can_reset ) {
			$html .= '<button type="button" class="omniloop-empty__reset">' . esc_html( (string) ( $copy['resetLabel'] ?? '' ) ) . '</button>';
		}
		$html .= '</div>';
		return $html;
	}

	private static function empty_icon_html( string $style ): string {
		if ( 'minimal' === $style ) {
			return '';
		}

		$paths = [
			'box'    => '<rect x="4" y="7" width="16" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 11h16M12 7v13M8.5 7l1-3h5l1 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
			'search' => '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
			'bag'    => '<path d="M6 9h12l-1 11H7L6 9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 9V7.5a3 3 0 0 1 6 0V9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
		];
		$path = $paths[ $style ] ?? $paths['box'];

		return '<span class="omniloop-empty__icon" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" focusable="false">' . $path . '</svg></span>';
	}

	/**
	 * Empty list means every facet is visible.
	 *
	 * @param mixed $value Checkbox array, comma-separated string, or empty.
	 * @return string[]
	 */
	public static function parse_visible_facets( $value ): array {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$raw = trim( (string) $value );
			if ( '' === $raw || 'all' === strtolower( $raw ) ) {
				return [];
			}
			$parts = preg_split( '/[\s,|]+/', $raw );
			if ( ! is_array( $parts ) ) {
				return [];
			}
		}

		$out = [];
		foreach ( $parts as $part ) {
			if ( is_array( $part ) ) {
				$part = $part['value'] ?? '';
			}
			$key = sanitize_key( (string) $part );
			if ( '' !== $key ) {
				$out[] = $key;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string[] $visible
	 */
	public static function facet_is_visible( string $key, array $visible ): bool {
		return [] === $visible || in_array( $key, $visible, true );
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	public static function sanitize_card_parts( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : preg_split( '/[\s,]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}

		$allowed = [ 'image', 'title', 'excerpt', 'price', 'rating', 'addToCart', 'quickView' ];
		$out     = [];
		foreach ( $value as $part ) {
			$part = (string) $part;
			$key  = sanitize_key( $part );
			if ( 'addtocart' === $key ) {
				$part = 'addToCart';
			} elseif ( 'quickview' === $key ) {
				$part = 'quickView';
			} else {
				$part = $key;
			}
			if ( in_array( $part, $allowed, true ) && ! in_array( $part, $out, true ) ) {
				$out[] = $part;
			}
		}

		return $out;
	}

	public static function is_flag_on( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$flag = strtolower( trim( (string) $value ) );
		if ( in_array( $flag, [ 'off', '0', 'false', 'no' ], true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $attributes
	 * @return array{autoplay:bool,speed:int,pauseHover:bool,loop:bool,arrows:bool,dots:bool}
	 */
	public static function carousel_options( array $attributes ): array {
		$speed = (int) ( $attributes['carouselSpeed'] ?? 4000 );
		if ( ! in_array( $speed, [ 2000, 3000, 4000, 5000, 6000, 8000 ], true ) ) {
			$speed = 4000;
		}

		$autoplay_raw = strtolower( trim( (string) ( $attributes['carouselAutoplay'] ?? 'off' ) ) );

		return [
			'autoplay'   => in_array( $autoplay_raw, [ 'on', '1', 'true', 'yes' ], true ),
			'speed'      => $speed,
			'pauseHover' => self::is_flag_on( $attributes['carouselPauseHover'] ?? 'on' ),
			'loop'       => self::is_flag_on( $attributes['carouselLoop'] ?? 'on' ),
			'arrows'     => self::is_flag_on( $attributes['carouselArrows'] ?? 'on' ),
			'dots'       => self::is_flag_on( $attributes['carouselDots'] ?? 'on' ),
		];
	}

	/**
	 * @param array<string,mixed> $attributes Block / module attributes.
	 */
	public static function render( array $attributes ): string {
		$post_type = sanitize_key( (string) ( $attributes['postType'] ?? '' ) );
		if ( '' === $post_type ) {
			$post_type = self::default_post_type();
		}
		$per_page  = (int) ( $attributes['perPage'] ?? 12 );

		if ( ! FilterEndpoint::is_allowed_post_type( $post_type ) ) {
			$post_type = 'post';
		}

		$per_page = max( 1, min( QueryBuilder::MAX_PER_PAGE, $per_page > 0 ? $per_page : 12 ) );
		$columns      = self::sanitize_columns( $attributes['columns'] ?? 3 );
		$ratio        = self::sanitize_ratio( $attributes['imageRatio'] ?? '4-3' );
		$filter_style = self::sanitize_filter_style( $attributes['filterStyle'] ?? 'sidebar' );
		$card_style   = self::sanitize_card_style( $attributes['cardStyle'] ?? 'classic' );
		$grid_gap     = self::sanitize_grid_gap( $attributes['gridGap'] ?? 'md' );
		$layout_gap   = self::sanitize_layout_gap( $attributes['layoutGap'] ?? 'md' );
		$badge_pos    = self::sanitize_badge_position( $attributes['badgePosition'] ?? 'top-left' );
		$title_lines  = self::sanitize_title_lines( $attributes['titleLines'] ?? '2' );
		$image_hover  = self::sanitize_image_hover( $attributes['imageHover'] ?? 'subtle' );
		$empty_copy   = self::empty_copy( $attributes );
		$filters_on   = self::is_flag_on( $attributes['showFilters'] ?? 'on' );
		$show_atc     = self::is_flag_on( $attributes['showAddToCart'] ?? 'on' )
			&& 'product' === $post_type
			&& function_exists( 'wc_get_product' );
		$show_qv      = self::is_flag_on( $attributes['showQuickView'] ?? 'on' );
		self::$quick_view_label = self::sanitize_quick_view_label( $attributes['quickViewLabel'] ?? '' );
		$show_pager   = self::is_flag_on( $attributes['showPagination'] ?? 'on' );
		$pager_style  = $show_pager
			? self::sanitize_pagination_style( $attributes['paginationStyle'] ?? 'pages' )
			: 'none';
		$facet_logic  = QueryBuilder::sanitize_facet_logic( (string) ( $attributes['facetLogic'] ?? 'smart' ) );
		$sort_meta    = Indexer::sanitize_facet_key( (string) ( $attributes['sortMetaKey'] ?? '' ) );
		$carousel     = self::carousel_options( $attributes );
		if ( '' !== $sort_meta ) {
			self::remember_sort_meta_key( $sort_meta );
		}
		$merch_on     = self::is_flag_on( $attributes['merchandising'] ?? 'on' );
		$extra_pins   = (string) ( $attributes['extraPinIds'] ?? '' );
		$show_urgency = $merch_on && self::is_flag_on( $attributes['stockUrgency'] ?? 'on' );
		$show_promo   = $merch_on && self::is_flag_on( $attributes['promotedBadge'] ?? 'on' );
		self::$card_style      = $card_style;
		self::$show_add_to_cart = $show_atc;
		self::$show_quick_view  = $show_qv;
		self::$show_stock_urgency  = $show_urgency;
		self::$show_promoted_badge = $show_promo;
		self::$card_parts          = self::sanitize_card_parts( $attributes['cardParts'] ?? [] );
		$visible_facets         = self::parse_visible_facets( $attributes['visibleFacets'] ?? '' );

		$state = SeoUrls::frontend_enabled()
			? SeoUrls::state_for( $post_type )
			: [
				'page'   => 1,
				'sort'   => 'date',
				'search' => '',
				'view'   => '',
				'facets' => [],
			];

		if ( ! SeoUrls::state_is_filtered( $state ) ) {
			$module_preset = sanitize_key( (string) ( $attributes['filterPreset'] ?? '' ) );
			$preset        = FilterPresets::resolve_for_module( $post_type, $module_preset );
			if ( $preset ) {
				$state = FilterPresets::apply_to_state( $state, $preset );
			}
		}

		$parsed = [
			'post_type'     => $post_type,
			'per_page'      => $per_page,
			'page'          => 'none' !== $pager_style ? (int) $state['page'] : 1,
			'sort_by'       => (string) $state['sort'],
			'search'        => (string) $state['search'],
			'facets'        => is_array( $state['facets'] ) ? $state['facets'] : [],
			'facet_logic'   => $facet_logic,
			'sort_meta_key' => $sort_meta,
			'merchandising' => $merch_on,
			'extra_pin_ids' => Merchandising::parse_ids( $extra_pins ),
		];

		$builder = new QueryBuilder();
		$result  = $builder->query( $parsed );

		wp_enqueue_style( 'omniloop-style' );
		wp_enqueue_script( 'omniloop-engine' );
		if ( $show_atc ) {
			wp_enqueue_script( 'wc-add-to-cart' );
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		static $instance = 0;
		++$instance;
		$id = 'omniloop-' . $instance;

		$seo_on = SeoUrls::frontend_enabled();
		$module_preset = sanitize_key( (string) ( $attributes['filterPreset'] ?? '' ) );
		$active_preset = FilterPresets::resolve_for_module( $post_type, $module_preset );
		$config = wp_json_encode(
			[
				'restUrl'       => rest_url( 'omniloop/v1/query' ),
				// Public queries need no nonce; a page-cached one expires and would 403.
				'nonce'         => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'postType'      => $post_type,
				'perPage'       => $per_page,
				'filterStyle'   => $filter_style,
				'cardStyle'     => $card_style,
				'cardParts'     => self::$card_parts,
				'visibleFacets' => $visible_facets,
				'showAddToCart' => $show_atc,
				'showQuickView'  => $show_qv,
				'showPagination' => $show_pager,
				'paginationStyle' => $pager_style,
				'loadMoreLabel'   => __( 'Load more', 'omniloop' ),
				'loadingLabel'    => __( 'Loading…', 'omniloop' ),
				'i18n'            => [
					'result'       => __( 'result', 'omniloop' ),
					'results'      => __( 'results', 'omniloop' ),
					'sale'         => __( 'Sale', 'omniloop' ),
					'quickView'    => self::quick_view_label(),
					'addToCart'    => __( 'Add to cart', 'omniloop' ),
					'viewDetails'  => __( 'View details', 'omniloop' ),
					'close'        => __( 'Close', 'omniloop' ),
					'clearAll'     => __( 'Clear all', 'omniloop' ),
					'requestError' => __( 'Could not load results. Check your connection and try again.', 'omniloop' ),
					'retry'        => __( 'Retry', 'omniloop' ),
					'rated'        => __( 'Rated', 'omniloop' ),
					'outOfFive'    => __( 'out of 5', 'omniloop' ),
					'pagination'   => __( 'Pagination', 'omniloop' ),
					'previousPage' => __( 'Previous page', 'omniloop' ),
					'nextPage'     => __( 'Next page', 'omniloop' ),
					'page'         => __( 'Page', 'omniloop' ),
					'showing'      => __( 'Showing', 'omniloop' ),
					'of'           => __( 'of', 'omniloop' ),
					'remove'       => __( 'Remove', 'omniloop' ),
					'search'       => __( 'Search', 'omniloop' ),
					'price'        => __( 'Price', 'omniloop' ),
				],
				'facetLogic'    => $facet_logic,
				'sortMetaKey'   => $sort_meta,
				'filterSync'    => self::is_flag_on( $attributes['filterSync'] ?? 'on' ),
				'syncGroup'     => self::sanitize_sync_group( $attributes['syncGroup'] ?? 'default' ),
				'instanceId'    => $id,
				'merchandising' => $merch_on,
				'extraPinIds'   => implode( ',', Merchandising::parse_ids( $extra_pins ) ),
				'stockUrgency'  => $show_urgency,
				'promotedBadge' => $show_promo,
				'filterPreset'  => $active_preset ? [
					'id'       => $active_preset['id'],
					'label'    => $active_preset['label'],
					'search'   => $active_preset['search'],
					'sort_by'  => $active_preset['sort_by'],
					'facets'   => $active_preset['facets'],
				] : null,
				'carousel'      => $carousel,
				'empty'         => $empty_copy,
				'seo'           => [
					'enabled' => $seo_on,
					'keys'    => SeoUrls::key_aliases( $post_type ),
					'applied' => $seo_on && SeoUrls::state_is_filtered( $state ),
				],
				'facetUi'       => FacetUi::frontend_config(),
				'eventUrl'      => FacetAnalytics::enabled() ? rest_url( 'omniloop/v1/event' ) : '',
			],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
		);

		$can_reset = $filters_on && (
			'' !== (string) $state['search']
			|| ( is_array( $state['facets'] ) && [] !== $state['facets'] )
		);
		$filters = $filters_on ? self::filters_html( $post_type, $result, $id, $visible_facets, array_merge( $state, [ '_sort_meta_key' => $sort_meta ] ) ) : '';
		$grid    = self::grid_html( $result['post_ids'], $empty_copy, $can_reset );
		$count   = sprintf(
			/* translators: %d: number of matching posts */
			esc_html( _n( '%d result', '%d results', (int) $result['total'], 'omniloop' ) ),
			(int) $result['total']
		);

		$pages = (int) $result['total'] > 0
			? (int) ceil( (int) $result['total'] / $per_page )
			: 0;

		$display_style = $card_style;
		if ( 'list' === $state['view'] ) {
			$display_style = 'list';
		} elseif ( 'grid' === $state['view'] ) {
			$display_style = 'list' === $card_style ? 'classic' : $card_style;
		}

		$classes = [
			'omniloop-app',
			'omniloop-app--cols-' . $columns,
			'omniloop-app--ratio-' . $ratio,
			'omniloop-app--filter-' . $filter_style,
			'omniloop-app--card-' . $display_style,
			'omniloop-app--gap-' . $grid_gap,
			'omniloop-app--layout-gap-' . $layout_gap,
			'omniloop-app--badge-' . $badge_pos,
			'omniloop-app--title-lines-' . $title_lines,
			'omniloop-app--zoom-' . $image_hover,
		];
		if ( ! $filters_on ) {
			$classes[] = 'omniloop-app--no-filters';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $id ); ?>">
			<script type="application/json" class="omniloop-config"><?php echo $config ? $config : '{}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG encoded ?></script>
			<div class="omniloop-layout">
				<?php echo $filters; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled with escaping below ?>
				<div class="omniloop-results">
					<div class="omniloop-active">
						<div class="omniloop-toolbar">
							<p class="omniloop-count"><?php echo esc_html( $count ); ?></p>
							<?php echo self::view_toggle_html( $card_style, (string) $state['view'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="omniloop-chips" hidden></div>
					</div>
					<?php if ( 'carousel' === $display_style ) : ?>
					<?php
					$carousel_attrs = sprintf(
						'data-cols="%1$s" data-autoplay="%2$s" data-speed="%3$s" data-pause-hover="%4$s" data-loop="%5$s" data-arrows="%6$s" data-dots="%7$s"',
						esc_attr( (string) $columns ),
						$carousel['autoplay'] ? '1' : '0',
						esc_attr( (string) $carousel['speed'] ),
						$carousel['pauseHover'] ? '1' : '0',
						$carousel['loop'] ? '1' : '0',
						$carousel['arrows'] ? '1' : '0',
						$carousel['dots'] ? '1' : '0'
					);
					?>
					<div class="omniloop-carousel" <?php echo $carousel_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above ?>>
						<button type="button" class="omniloop-carousel__btn omniloop-carousel__btn--prev" aria-label="<?php echo esc_attr__( 'Previous products', 'omniloop' ); ?>"<?php echo $carousel['arrows'] ? '' : ' hidden'; ?>>
							<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M11.5 3.5L6 9l5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="omniloop-carousel__viewport">
							<div class="omniloop-grid">
								<?php echo $grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
						<button type="button" class="omniloop-carousel__btn omniloop-carousel__btn--next" aria-label="<?php echo esc_attr__( 'Next products', 'omniloop' ); ?>"<?php echo $carousel['arrows'] ? '' : ' hidden'; ?>>
							<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M6.5 3.5L12 9l-5.5 5.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<div class="omniloop-carousel__dots"<?php echo $carousel['dots'] ? '' : ' hidden'; ?>></div>
					</div>
					<?php else : ?>
					<div class="omniloop-grid">
						<?php echo $grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php endif; ?>
					<?php if ( 'pages' === $pager_style ) : ?>
					<nav class="omniloop-pagination" data-pages="<?php echo esc_attr( (string) $pages ); ?>" data-page="<?php echo esc_attr( (string) max( 1, (int) $state['page'] ) ); ?>" hidden></nav>
					<?php elseif ( 'load_more' === $pager_style ) : ?>
					<div class="omniloop-loadmore" data-pages="<?php echo esc_attr( (string) $pages ); ?>" data-page="<?php echo esc_attr( (string) max( 1, (int) $state['page'] ) ); ?>" hidden>
						<button type="button" class="omniloop-loadmore__btn"><?php echo esc_html__( 'Load more', 'omniloop' ); ?></button>
						<p class="omniloop-loadmore__status" hidden></p>
					</div>
					<?php elseif ( 'infinite' === $pager_style ) : ?>
					<div class="omniloop-infinite-sentinel" data-pages="<?php echo esc_attr( (string) $pages ); ?>" data-page="<?php echo esc_attr( (string) max( 1, (int) $state['page'] ) ); ?>" aria-hidden="true"></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * @param int[]                                                                               $post_ids
	 * @param array{title:string,message:string,style:string,resetLabel:string,showReset:bool}|null $empty_copy
	 */
	public static function grid_html( array $post_ids, ?array $empty_copy = null, bool $can_reset = false ): string {
		if ( empty( $post_ids ) ) {
			$copy = is_array( $empty_copy ) ? $empty_copy : self::empty_copy( [] );
			return self::empty_html( $copy, $can_reset );
		}

		self::prime_caches( $post_ids );

		$html = '';
		foreach ( $post_ids as $post_id ) {
			$payload = self::post_payload( (int) $post_id );
			if ( $payload ) {
				$html .= $payload['rendered_card'];
			}
		}

		return $html;
	}

	/**
	 * Load posts, meta, terms and thumbnails for a page of results in a few
	 * queries instead of several per card.
	 *
	 * @param int[] $post_ids
	 */
	public static function prime_caches( array $post_ids ): void {
		$post_ids = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
		if ( ! $post_ids || ! function_exists( '_prime_post_caches' ) ) {
			return;
		}

		_prime_post_caches( $post_ids, true, true );

		$thumb_ids = [];
		foreach ( $post_ids as $post_id ) {
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id > 0 ) {
				$thumb_ids[] = $thumb_id;
			}
		}
		if ( $thumb_ids ) {
			_prime_post_caches( $thumb_ids, false, true );
		}
	}

	/**
	 * @param bool $include_card When false, skip HTML card (REST payload).
	 * @return array{id:int,title:string,permalink:string,thumbnail:string,price:string,priceRegular:string,onSale:bool,salePercent:int,excerpt:string,rendered_card?:string}|null
	 */
	public static function post_payload( int $post_id, bool $include_card = true ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}

		$title     = get_the_title( $post );
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		$thumb = self::thumbnail_url( $post );

		$price         = '';
		$price_regular = '';
		$on_sale       = false;
		$sale_percent  = 0;
		$add_to_cart   = null;
		$product       = null;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$formatted     = self::product_price_label( $product );
				$price         = $formatted['price'];
				$price_regular = $formatted['regular'];
				$sale_meta     = self::sale_meta( $product );
				$on_sale       = $sale_meta['on_sale'];
				$sale_percent  = $sale_meta['percent'];
				if ( 'product' === $post->post_type ) {
					$add_to_cart = self::add_to_cart_meta( $product );
				}
			}
		}

		$raw = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		// Divi 4 content is shortcode markup; don't leak [et_pb_*] into card excerpts.
		$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $raw ) ), 18 );

		$merch   = Merchandising::decorate( $post_id, (string) $post->post_type, $product );
		$payload = [
			'id'            => $post_id,
			'title'         => wp_specialchars_decode( $title, ENT_QUOTES ),
			'permalink'     => $permalink,
			'thumbnail'     => $thumb,
			'price'         => $price,
			'priceRegular'  => $price_regular,
			'onSale'        => $on_sale,
			'salePercent'   => $sale_percent,
			'excerpt'       => $excerpt,
			'addToCart'     => $add_to_cart,
			'promotedBadge' => $merch['promotedBadge'],
			'stockUrgency'  => $merch['stockUrgency'],
			'rating'        => self::product_rating( $product ),
			'reviewCount'   => self::product_review_count( $product ),
		];
		if ( $include_card ) {
			$payload['rendered_card'] = self::card_html( $payload, self::$card_style );
		}

		return $payload;
	}

	public static function thumbnail_url( \WP_Post $post ): string {
		$sizes = [ 'medium_large', 'medium', 'woocommerce_thumbnail', 'large', 'full' ];

		foreach ( $sizes as $size ) {
			$url = get_the_post_thumbnail_url( $post, $size );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$image_id = (int) $product->get_image_id();
				if ( $image_id > 0 ) {
					foreach ( [ 'woocommerce_thumbnail', 'medium_large', 'medium', 'full' ] as $size ) {
						$url = wp_get_attachment_image_url( $image_id, $size );
						if ( is_string( $url ) && '' !== $url ) {
							return $url;
						}
					}
				}
			}
		}

		$attached = get_attached_media( 'image', $post->ID );
		if ( is_array( $attached ) && $attached ) {
			$first = reset( $attached );
			if ( $first instanceof \WP_Post ) {
				$url = wp_get_attachment_image_url( $first->ID, 'medium_large' );
				if ( is_string( $url ) && '' !== $url ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Plain-text WooCommerce price for cards / REST / Visual Builder.
	 * Avoids get_price_html() screen-reader copy and undecoded entities.
	 *
	 * @param object $product WooCommerce product.
	 * @return array{price:string,regular:string}
	 */
	public static function product_price_label( $product ): array {
		$empty = [
			'price'   => '',
			'regular' => '',
		];

		if ( ! is_object( $product ) || ! function_exists( 'wc_price' ) ) {
			return $empty;
		}

		$format = static function ( $amount ): string {
			$html = wc_price( (float) $amount );
			$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = preg_replace( '/\s+/u', ' ', $text );

			return is_string( $text ) ? trim( $text ) : '';
		};

		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( '' === $min && '' === $max ) {
				return $empty;
			}
			if ( (float) $min === (float) $max ) {
				return [
					'price'   => $format( $min ),
					'regular' => '',
				];
			}

			return [
				'price'   => $format( $min ) . ' – ' . $format( $max ),
				'regular' => '',
			];
		}

		$current = function_exists( 'wc_get_price_to_display' )
			? wc_get_price_to_display( $product )
			: $product->get_price();

		if ( null === $current || '' === $current ) {
			return $empty;
		}

		$regular = '';
		if ( method_exists( $product, 'is_on_sale' ) && $product->is_on_sale() && $product->get_regular_price() !== '' ) {
			$regular_amount = function_exists( 'wc_get_price_to_display' )
				? wc_get_price_to_display( $product, [ 'price' => (float) $product->get_regular_price() ] )
				: $product->get_regular_price();
			$regular_label  = $format( $regular_amount );
			$sale_label     = $format( $current );
			if ( $regular_label !== $sale_label ) {
				$regular = $regular_label;
			}
		}

		return [
			'price'   => $format( $current ),
			'regular' => $regular,
		];
	}

	/**
	 * @param object $product WooCommerce product.
	 * @return array{on_sale:bool,percent:int}
	 */
	public static function sale_meta( $product ): array {
		$empty = [
			'on_sale' => false,
			'percent' => 0,
		];

		if ( ! is_object( $product ) || ! method_exists( $product, 'is_on_sale' ) || ! $product->is_on_sale() ) {
			return $empty;
		}

		$percent = 0;
		if ( method_exists( $product, 'is_type' ) && ! $product->is_type( 'variable' ) && function_exists( 'wc_get_price_to_display' ) ) {
			$regular = (float) wc_get_price_to_display( $product, [ 'price' => (float) $product->get_regular_price() ] );
			$sale    = (float) wc_get_price_to_display( $product );
			if ( $regular > 0 && $sale < $regular ) {
				$percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
				if ( $percent < 1 || $percent > 99 ) {
					$percent = 0;
				}
			}
		}

		return [
			'on_sale' => true,
			'percent' => $percent,
		];
	}

	/**
	 * @param object $product WooCommerce product.
	 * @return array{id:int,url:string,label:string,ajax:bool,type:string,sku:string}|null
	 */
	public static function add_to_cart_meta( $product ): ?array {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}

		$id   = (int) $product->get_id();
		$type = method_exists( $product, 'get_type' ) ? (string) $product->get_type() : 'simple';

		$label = method_exists( $product, 'add_to_cart_text' )
			? (string) $product->add_to_cart_text()
			: __( 'Add to cart', 'omniloop' );
		$label = wp_strip_all_tags( $label );
		if ( '' === $label ) {
			$label = __( 'Add to cart', 'omniloop' );
		}

		$ajax = method_exists( $product, 'supports' )
			&& $product->supports( 'ajax_add_to_cart' )
			&& method_exists( $product, 'is_purchasable' )
			&& $product->is_purchasable()
			&& method_exists( $product, 'is_in_stock' )
			&& $product->is_in_stock();

		// WC builds a simple-product URL from the current request. During a REST
		// response that would incorrectly point shoppers back to /wp-json/.
		$url = $ajax ? (string) add_query_arg( 'add-to-cart', $id, home_url( '/' ) ) : '';
		if ( '' === $url && method_exists( $product, 'add_to_cart_url' ) ) {
			$url = (string) $product->add_to_cart_url();
		}
		if ( ( '' === $url || false !== strpos( $url, '/wp-json/' ) ) && method_exists( $product, 'get_permalink' ) ) {
			$url = (string) $product->get_permalink();
		}
		if ( '' === $url ) {
			return null;
		}

		$sku = method_exists( $product, 'get_sku' ) ? (string) $product->get_sku() : '';

		return [
			'id'    => $id,
			'url'   => $url,
			'label' => $label,
			'ajax'  => $ajax,
			'type'  => sanitize_key( $type ),
			'sku'   => $sku,
		];
	}

	/**
	 * @param array{id:int,url:string,label:string,ajax:bool,type:string,sku:string} $meta
	 */
	private static function add_to_cart_button_html( array $meta ): string {
		$id    = (int) ( $meta['id'] ?? 0 );
		$url   = (string) ( $meta['url'] ?? '' );
		$label = (string) ( $meta['label'] ?? '' );
		$type  = sanitize_key( (string) ( $meta['type'] ?? 'simple' ) );
		$sku   = (string) ( $meta['sku'] ?? '' );
		if ( $id < 1 || '' === $url || '' === $label ) {
			return '';
		}

		$classes = [
			'omniloop-card__atc',
			'button',
			'product_type_' . $type,
			'add_to_cart_button',
		];
		if ( ! empty( $meta['ajax'] ) ) {
			$classes[] = 'ajax_add_to_cart';
		}

		$aria = sprintf(
			/* translators: %s: product action label */
			__( 'Add to cart: %s', 'omniloop' ),
			$label
		);

		return sprintf(
			'<a href="%s" class="%s" data-quantity="1" data-product_id="%d" data-product_sku="%s" aria-label="%s" rel="nofollow">%s</a>',
			esc_url( $url ),
			esc_attr( implode( ' ', $classes ) ),
			$id,
			esc_attr( $sku ),
			esc_attr( $aria ),
			esc_html( $label )
		);
	}

	/**
	 * @param array{id:int,title:string,permalink:string,thumbnail:string,price:string,priceRegular?:string,excerpt?:string} $item
	 */
	private static function quick_view_button_html( array $item ): string {
		$id = (int) ( $item['id'] ?? 0 );
		if ( $id < 1 ) {
			return '';
		}

		$label = self::quick_view_label();

		return sprintf(
			'<button type="button" class="omniloop-card__qv" data-omniloop-qv aria-haspopup="dialog" aria-label="%s">%s</button>',
			esc_attr( $label ),
			esc_html( $label )
		);
	}

	/**
	 * @param array{id:int,title:string,permalink:string,thumbnail:string,price:string,priceRegular?:string,onSale?:bool,salePercent?:int,excerpt:string,addToCart?:array|null} $item
	 */
	private static function card_actions_html( array $item ): string {
		$parts = [];
		if ( self::$show_quick_view ) {
			$qv = self::quick_view_button_html( $item );
			if ( '' !== $qv ) {
				$parts[] = $qv;
			}
		}
		if ( self::$show_add_to_cart && ! empty( $item['addToCart'] ) && is_array( $item['addToCart'] ) ) {
			$atc = self::add_to_cart_button_html( $item['addToCart'] );
			if ( '' !== $atc ) {
				$parts[] = $atc;
			}
		}
		if ( ! $parts ) {
			return '';
		}

		return '<div class="omniloop-card__actions">' . implode( '', $parts ) . '</div>';
	}

	/**
	 * @param array{id:int,title:string,permalink:string,thumbnail:string,price:string,priceRegular?:string,onSale?:bool,salePercent?:int,excerpt:string,addToCart?:array|null} $item
	 */
	public static function card_html( array $item, string $card_style = 'classic' ): string {
		if ( self::$card_parts ) {
			return self::card_html_from_parts( $item );
		}

		$badge  = self::badges_html( $item );
		$letter = self::fallback_letter( $item['title'] );
		if ( $item['thumbnail'] ) {
			$img = sprintf(
				'<div class="omniloop-card__media">%s<img src="%s" alt="%s" loading="lazy" width="600" height="450"></div>',
				$badge,
				esc_url( $item['thumbnail'] ),
				esc_attr( $item['title'] )
			);
		} else {
			$img = sprintf(
				'<div class="omniloop-card__media omniloop-card__media--empty">%s<span class="omniloop-card__fallback">%s</span></div>',
				$badge,
				esc_html( $letter )
			);
		}

		$price = '';
		if ( $item['price'] ) {
			$regular = ! empty( $item['priceRegular'] )
				? '<s class="omniloop-card__price-regular">' . esc_html( (string) $item['priceRegular'] ) . '</s> '
				: '';
			$price   = '<p class="omniloop-card__price">' . $regular . esc_html( $item['price'] ) . '</p>';
		}

		$excerpt = '';
		if ( ! empty( $item['excerpt'] ) ) {
			$excerpt = '<p class="omniloop-card__excerpt">' . esc_html( (string) $item['excerpt'] ) . '</p>';
		}

		$actions = self::card_actions_html( $item );

		return sprintf(
			'<article class="omniloop-card" data-id="%d"><a class="omniloop-card__link" href="%s">%s<div class="omniloop-card__body"><h3 class="omniloop-card__title">%s</h3>%s%s</div></a>%s</article>',
			(int) $item['id'],
			esc_url( $item['permalink'] ),
			$img,
			esc_html( $item['title'] ),
			$excerpt,
			$price,
			$actions
		);
	}

	private static function card_html_from_parts( array $item ): string {
		$media   = '';
		$body    = '';
		$actions = [];

		foreach ( self::$card_parts as $part ) {
			if ( 'image' === $part ) {
				$media = self::card_media_html( $item );
				continue;
			}
			if ( 'addToCart' === $part || 'quickView' === $part ) {
				$actions[] = $part;
				continue;
			}
			$body .= self::card_part_html( $part, $item );
		}

		if ( '' === $media && in_array( 'image', self::$card_parts, true ) ) {
			$media = self::card_media_html( $item );
		}

		$body_html = '' !== $body ? '<div class="omniloop-card__body">' . $body . '</div>' : '';
		$act_html  = self::card_actions_from_parts( $item, $actions );

		return sprintf(
			'<article class="omniloop-card omniloop-card--custom" data-id="%d"><a class="omniloop-card__link" href="%s">%s%s</a>%s</article>',
			(int) $item['id'],
			esc_url( $item['permalink'] ),
			$media,
			$body_html,
			$act_html
		);
	}

	private static function card_media_html( array $item ): string {
		$badge  = self::badges_html( $item );
		$letter = self::fallback_letter( (string) ( $item['title'] ?? '' ) );
		if ( ! empty( $item['thumbnail'] ) ) {
			return sprintf(
				'<div class="omniloop-card__media">%s<img src="%s" alt="%s" loading="lazy" width="600" height="450"></div>',
				$badge,
				esc_url( (string) $item['thumbnail'] ),
				esc_attr( (string) $item['title'] )
			);
		}

		return sprintf(
			'<div class="omniloop-card__media omniloop-card__media--empty">%s<span class="omniloop-card__fallback">%s</span></div>',
			$badge,
			esc_html( $letter )
		);
	}

	private static function card_part_html( string $part, array $item ): string {
		if ( 'title' === $part ) {
			return '<h3 class="omniloop-card__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</h3>';
		}
		if ( 'excerpt' === $part && ! empty( $item['excerpt'] ) ) {
			return '<p class="omniloop-card__excerpt">' . esc_html( (string) $item['excerpt'] ) . '</p>';
		}
		if ( 'price' === $part && ! empty( $item['price'] ) ) {
			$regular = ! empty( $item['priceRegular'] )
				? '<s class="omniloop-card__price-regular">' . esc_html( (string) $item['priceRegular'] ) . '</s> '
				: '';
			return '<p class="omniloop-card__price">' . $regular . esc_html( (string) $item['price'] ) . '</p>';
		}
		if ( 'rating' === $part ) {
			return self::rating_html( $item );
		}

		return '';
	}

	/**
	 * @param string[] $actions
	 */
	private static function card_actions_from_parts( array $item, array $actions ): string {
		if ( ! $actions ) {
			return '';
		}

		$parts = [];
		if ( in_array( 'quickView', $actions, true ) ) {
			$qv = self::quick_view_button_html( $item );
			if ( '' !== $qv ) {
				$parts[] = $qv;
			}
		}
		if ( in_array( 'addToCart', $actions, true ) && ! empty( $item['addToCart'] ) && is_array( $item['addToCart'] ) ) {
			$atc = self::add_to_cart_button_html( $item['addToCart'] );
			if ( '' !== $atc ) {
				$parts[] = $atc;
			}
		}
		if ( ! $parts ) {
			return '';
		}

		return '<div class="omniloop-card__actions">' . implode( '', $parts ) . '</div>';
	}

	private static function rating_html( array $item ): string {
		$rating = (float) ( $item['rating'] ?? 0 );
		if ( $rating <= 0 ) {
			return '';
		}

		$stars = (int) max( 1, min( 5, (int) round( $rating ) ) );
		$label = sprintf(
			/* translators: %s: average rating */
			__( 'Rated %s out of 5', 'omniloop' ),
			number_format_i18n( $rating, 1 )
		);

		return '<p class="omniloop-card__rating" aria-label="' . esc_attr( $label ) . '"><span aria-hidden="true">' . esc_html( str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars ) ) . '</span></p>';
	}

	/**
	 * @param object|null $product WooCommerce product.
	 */
	private static function product_rating( $product ): float {
		if ( is_object( $product ) && method_exists( $product, 'get_average_rating' ) ) {
			return (float) $product->get_average_rating();
		}

		return 0.0;
	}

	/**
	 * @param object|null $product WooCommerce product.
	 */
	private static function product_review_count( $product ): int {
		if ( is_object( $product ) && method_exists( $product, 'get_review_count' ) ) {
			return (int) $product->get_review_count();
		}

		return 0;
	}

	private static function badges_html( array $item ): string {
		$parts = [];
		if ( self::$show_promoted_badge && ! empty( $item['promotedBadge'] ) ) {
			$parts[] = '<span class="omniloop-card__badge omniloop-card__badge--promo">' . esc_html( (string) $item['promotedBadge'] ) . '</span>';
		}
		if ( ! empty( $item['onSale'] ) ) {
			$percent = (int) ( $item['salePercent'] ?? 0 );
			$label   = $percent > 0
				? sprintf(
					/* translators: %d: discount percent */
					__( '-%d%%', 'omniloop' ),
					$percent
				)
				: __( 'Sale', 'omniloop' );
			$parts[] = '<span class="omniloop-card__badge">' . esc_html( $label ) . '</span>';
		}
		if ( self::$show_stock_urgency && ! empty( $item['stockUrgency'] ) ) {
			$parts[] = '<span class="omniloop-card__badge omniloop-card__badge--urgency">' . esc_html( (string) $item['stockUrgency'] ) . '</span>';
		}
		if ( ! $parts ) {
			return '';
		}

		return '<span class="omniloop-card__badges">' . implode( '', $parts ) . '</span>';
	}

	private static function fallback_letter( string $title ): string {
		$title = trim( wp_strip_all_tags( $title ) );
		if ( '' === $title ) {
			return '•';
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_strtoupper( mb_substr( $title, 0, 1 ) );
		}

		return strtoupper( substr( $title, 0, 1 ) );
	}

	private static function view_toggle_html( string $card_style, string $view = '' ): string {
		$list_on = 'list' === $view || ( '' === $view && 'list' === $card_style );
		$html    = '<div class="omniloop-view" role="group" aria-label="' . esc_attr__( 'Layout', 'omniloop' ) . '">';
		$html   .= sprintf(
			'<button type="button" class="omniloop-view__btn%s" data-view="grid" aria-pressed="%s" aria-label="%s">',
			$list_on ? '' : ' is-active',
			$list_on ? 'false' : 'true',
			esc_attr__( 'Grid view', 'omniloop' )
		);
		$html        .= '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="1" y="9" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="9" width="6" height="6" rx="1" fill="currentColor"/></svg>';
		$html        .= '</button>';
		$html        .= sprintf(
			'<button type="button" class="omniloop-view__btn%s" data-view="list" aria-pressed="%s" aria-label="%s">',
			$list_on ? ' is-active' : '',
			$list_on ? 'true' : 'false',
			esc_attr__( 'List view', 'omniloop' )
		);
		$html        .= '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1" y="2" width="14" height="2.4" rx="0.6" fill="currentColor"/><rect x="1" y="6.8" width="14" height="2.4" rx="0.6" fill="currentColor"/><rect x="1" y="11.6" width="14" height="2.4" rx="0.6" fill="currentColor"/></svg>';
		$html        .= '</button></div>';

		return $html;
	}

	/**
	 * @param array<string,mixed> $result
	 * @param string[]            $visible
	 * @param array<string,mixed> $state
	 */
	private static function filters_html( string $post_type, array $result, string $app_id, array $visible = [], array $state = [] ): string {
		$builder = new QueryBuilder();
		$keys    = $builder->facet_keys_for_type( $post_type );
		if ( $visible ) {
			$keys = array_values(
				array_filter(
					$keys,
					static function ( string $key ) use ( $visible ): bool {
						return self::facet_is_visible( $key, $visible );
					}
				)
			);
		}
		$counts   = $result['facets'] ?? [];
		$panel_id = $app_id . '-filters';
		$selected = isset( $state['facets'] ) && is_array( $state['facets'] ) ? $state['facets'] : [];
		$search   = (string) ( $state['search'] ?? '' );
		$sort     = (string) ( $state['sort'] ?? 'date' );
		$price_from = null;
		$price_to   = null;
		if ( isset( $selected['price_range'] ) && is_array( $selected['price_range'] ) && isset( $selected['price_range'][0], $selected['price_range'][1] ) ) {
			$price_from = (float) $selected['price_range'][0];
			$price_to   = (float) $selected['price_range'][1];
		}

		$html  = '<div class="omniloop-filters-wrap">';
		$html .= '<button type="button" class="omniloop-filters__open" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '" data-label="' . esc_attr__( 'Filters', 'omniloop' ) . '">';
		$html .= esc_html__( 'Filters', 'omniloop' ) . '</button>';
		$html .= '<div class="omniloop-filters-backdrop" hidden></div>';
		$html .= '<div class="omniloop-filters" id="' . esc_attr( $panel_id ) . '">';
		$html .= '<div class="omniloop-filters__bar">';
		$html .= '<p class="omniloop-filters__heading">' . esc_html__( 'Filters', 'omniloop' ) . '</p>';
		$html .= '<button type="button" class="omniloop-filters__close" aria-label="' . esc_attr__( 'Close filters', 'omniloop' ) . '">&times;</button>';
		$html .= '</div>';
		$html .= '<label class="omniloop-search"><span class="screen-reader-text">' . esc_html__( 'Search', 'omniloop' ) . '</span>';
		$html .= '<input type="search" name="ol_search" class="omniloop-search__input" placeholder="' . esc_attr__( 'Search…', 'omniloop' ) . '" autocomplete="off" value="' . esc_attr( $search ) . '"></label>';

		$min = (float) ( $result['price_min'] ?? 0 );
		$max = (float) ( $result['price_max'] ?? 0 );
		$display_bounds = MultiCurrency::bounds_for_display( $min, $max );
		$min = $display_bounds['min'];
		$max = $display_bounds['max'];
		if ( 'product' === $post_type && $max > 0 && self::facet_is_visible( 'price', $visible ) ) {
			$currency  = MultiCurrency::symbol();
			$bound_min = (int) floor( $min );
			$bound_max = (int) ceil( $max );
			$from_val  = null !== $price_from ? (int) max( $bound_min, min( $bound_max, $price_from ) ) : $bound_min;
			$to_val    = null !== $price_to ? (int) max( $bound_min, min( $bound_max, $price_to ) ) : $bound_max;
			if ( $from_val > $to_val ) {
				$tmp      = $from_val;
				$from_val = $to_val;
				$to_val   = $tmp;
			}
			$html .= '<div class="omniloop-facet" role="group" aria-label="' . esc_attr__( 'Price', 'omniloop' ) . '">';
			$html .= '<div class="omniloop-facet__title">' . esc_html__( 'Price', 'omniloop' ) . '</div>';
			$html .= '<div class="omniloop-price" data-currency="' . esc_attr( $currency ) . '" data-bound-min="' . esc_attr( (string) $bound_min ) . '" data-bound-max="' . esc_attr( (string) $bound_max ) . '">';
			$html .= '<div class="omniloop-price__inputs">';
			$html .= '<label class="omniloop-price__field"><span>' . esc_html__( 'From', 'omniloop' ) . '</span>';
			$html .= '<input type="number" class="omniloop-price__min" inputmode="numeric" min="' . esc_attr( (string) $bound_min ) . '" max="' . esc_attr( (string) $bound_max ) . '" step="1" value="' . esc_attr( (string) $from_val ) . '" aria-label="' . esc_attr__( 'Minimum price', 'omniloop' ) . '"></label>';
			$html .= '<span class="omniloop-price__sep" aria-hidden="true">–</span>';
			$html .= '<label class="omniloop-price__field"><span>' . esc_html__( 'To', 'omniloop' ) . '</span>';
			$html .= '<input type="number" class="omniloop-price__max" inputmode="numeric" min="' . esc_attr( (string) $bound_min ) . '" max="' . esc_attr( (string) $bound_max ) . '" step="1" value="' . esc_attr( (string) $to_val ) . '" aria-label="' . esc_attr__( 'Maximum price', 'omniloop' ) . '"></label>';
			$html .= '</div>';
			$lo_pct = $bound_max > $bound_min ? ( ( $from_val - $bound_min ) / ( $bound_max - $bound_min ) ) * 100 : 0;
			$hi_pct = $bound_max > $bound_min ? ( ( $to_val - $bound_min ) / ( $bound_max - $bound_min ) ) * 100 : 100;
			$html  .= '<div class="omniloop-price__slider" style="--ol-range-lo:' . esc_attr( (string) round( $lo_pct, 2 ) ) . '%;--ol-range-hi:' . esc_attr( (string) round( $hi_pct, 2 ) ) . '%;">';
			$html  .= '<div class="omniloop-price__track" aria-hidden="true"><div class="omniloop-price__fill"></div></div>';
			$html  .= '<input type="range" class="omniloop-price__thumb omniloop-price__thumb--min" min="' . esc_attr( (string) $bound_min ) . '" max="' . esc_attr( (string) $bound_max ) . '" step="1" value="' . esc_attr( (string) $from_val ) . '" aria-label="' . esc_attr__( 'Minimum price', 'omniloop' ) . '">';
			$html  .= '<input type="range" class="omniloop-price__thumb omniloop-price__thumb--max" min="' . esc_attr( (string) $bound_min ) . '" max="' . esc_attr( (string) $bound_max ) . '" step="1" value="' . esc_attr( (string) $to_val ) . '" aria-label="' . esc_attr__( 'Maximum price', 'omniloop' ) . '">';
			$html  .= '</div></div></div>';
		}

		$ui           = FacetUi::settings();
		$hide_zero    = 'on' === $ui['hide_zero_count'];
		$hide_empty   = 'on' === $ui['hide_empty_groups'];
		$facet_rules  = $ui['rules'];
		$visibility   = FacetUi::visibility_map( $keys, $selected, $facet_rules );

		foreach ( $keys as $key ) {
			if ( empty( $visibility[ $key ] ) ) {
				continue;
			}
			$map = isset( $counts[ $key ] ) && is_array( $counts[ $key ] ) ? $counts[ $key ] : [];
			if ( empty( $map ) ) {
				continue;
			}

			$picked = isset( $selected[ $key ] ) && is_array( $selected[ $key ] ) ? $selected[ $key ] : [];
			if ( $hide_zero ) {
				$map = array_filter(
					$map,
					static function ( $count, $value ) use ( $picked ): bool {
						return (int) $count > 0 || in_array( (string) $value, $picked, true );
					},
					ARRAY_FILTER_USE_BOTH
				);
			}
			if ( $hide_empty && ! $map ) {
				continue;
			}

			if ( 'rating' === $key ) {
				krsort( $map, SORT_NUMERIC );
			} else {
				arsort( $map, SORT_NUMERIC );
			}

			$mode        = WooFacets::resolve_swatch_mode( $key, array_map( 'strval', array_keys( $map ) ) );
			$is_swatch   = '' !== $mode && 'rating' !== $mode;
			$facet_class = 'omniloop-facet';
			if ( 'color' === $mode ) {
				$facet_class .= ' omniloop-facet--swatch-color';
			} elseif ( 'image' === $mode ) {
				$facet_class .= ' omniloop-facet--swatch-image';
			} elseif ( 'size' === $mode ) {
				$facet_class .= ' omniloop-facet--swatch-size';
			} elseif ( 'rating' === $mode ) {
				$facet_class .= ' omniloop-facet--rating';
			}

			$label  = I18n::facet_label( $key );
			$html  .= '<div class="' . esc_attr( $facet_class ) . '" role="group" aria-label="' . esc_attr( $label ) . '" data-facet-key="' . esc_attr( $key ) . '">';
			$html  .= '<div class="omniloop-facet__title">' . esc_html( $label ) . '</div>';
			if ( ! $is_swatch && 'rating' !== $mode && count( $map ) > self::FACET_VISIBLE ) {
				$html .= '<label class="omniloop-facet__find"><span class="screen-reader-text">' . esc_html__( 'Search facet', 'omniloop' ) . '</span>';
				$html .= '<input type="search" class="omniloop-facet__find-input" placeholder="' . esc_attr__( 'Search…', 'omniloop' ) . '" autocomplete="off"></label>';
			}
			$html  .= '<div class="omniloop-facet__list">';
			$index  = 0;
			$extra  = $is_swatch || 'rating' === $mode ? 0 : count( $map ) - self::FACET_VISIBLE;
			foreach ( $map as $value => $count ) {
				$display  = I18n::value_label( $key, (string) $value );
				$checked  = in_array( (string) $value, $picked, true );
				$class    = 'omniloop-check';
				if ( 'color' === $mode ) {
					$class .= ' omniloop-check--color';
				} elseif ( 'image' === $mode ) {
					$class .= ' omniloop-check--image';
				} elseif ( 'size' === $mode ) {
					$class .= ' omniloop-check--size';
				} elseif ( 'rating' === $mode ) {
					$class .= ' omniloop-check--rating';
				}
				if ( ! $is_swatch && 'rating' !== $mode && $index >= self::FACET_VISIBLE && ! $checked ) {
					$class .= ' omniloop-check--extra';
				}
				$html .= self::facet_option_html( $key, (string) $value, $display, (int) $count, $class, $mode, $checked );
				++$index;
			}
			$html .= '</div>';
			if ( $extra > 0 ) {
				$html .= '<button type="button" class="omniloop-facet__more" aria-expanded="false" data-more="' . esc_attr__( 'Show more', 'omniloop' ) . '" data-less="' . esc_attr__( 'Show less', 'omniloop' ) . '">';
				$html .= esc_html(
					sprintf(
						/* translators: %d: number of hidden facet values */
						__( 'Show more (%d)', 'omniloop' ),
						$extra
					)
				);
				$html .= '</button>';
			}
			$html .= '</div>';
		}

		$html .= '<label class="omniloop-sort"><span>' . esc_html__( 'Sort', 'omniloop' ) . '</span>';
		$html .= '<select class="omniloop-sort__select">';
		$html .= '<option value="date"' . selected( $sort, 'date', false ) . '>' . esc_html__( 'Newest', 'omniloop' ) . '</option>';
		$html .= '<option value="title"' . selected( $sort, 'title', false ) . '>' . esc_html__( 'Title', 'omniloop' ) . '</option>';
		if ( 'product' === $post_type ) {
			$html .= '<option value="price_asc"' . selected( $sort, 'price_asc', false ) . '>' . esc_html__( 'Price: low to high', 'omniloop' ) . '</option>';
			$html .= '<option value="price_desc"' . selected( $sort, 'price_desc', false ) . '>' . esc_html__( 'Price: high to low', 'omniloop' ) . '</option>';
			$html .= '<option value="popularity"' . selected( $sort, 'popularity', false ) . '>' . esc_html__( 'Popularity', 'omniloop' ) . '</option>';
			$html .= '<option value="rating"' . selected( $sort, 'rating', false ) . '>' . esc_html__( 'Average rating', 'omniloop' ) . '</option>';
		}
		$sort_meta = Indexer::sanitize_facet_key( (string) ( $state['_sort_meta_key'] ?? '' ) );
		if ( '' !== $sort_meta ) {
			$html .= '<option value="meta"' . selected( $sort, 'meta', false ) . '>' . esc_html__( 'Custom meta', 'omniloop' ) . '</option>';
		}
		$html .= '</select></label>';
		$html .= '<button type="button" class="omniloop-reset">' . esc_html__( 'Reset filters', 'omniloop' ) . '</button>';
		$html .= '</div></div>';

		return $html;
	}

	private static function remember_sort_meta_key( string $key ): void {
		$key = Indexer::sanitize_facet_key( $key );
		if ( '' === $key ) {
			return;
		}
		$saved = get_option( 'omniloop_sort_meta_keys', [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		if ( in_array( $key, $saved, true ) ) {
			return;
		}
		$saved[] = $key;
		update_option( 'omniloop_sort_meta_keys', array_slice( array_values( array_unique( $saved ) ), 0, 24 ), false );

		// Existing posts have no rows for the new key yet; rebuild in the background.
		Indexer::schedule_full_reindex();
	}

	private static function stars_html( int $stars ): string {
		$html = '<span class="omniloop-stars" aria-hidden="true">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$class = $i <= $stars ? 'omniloop-stars__on' : 'omniloop-stars__off';
			$html .= '<span class="' . $class . '">★</span>';
		}
		$html .= '</span>';

		return $html;
	}

	private static function facet_option_html( string $key, string $value, string $display, int $count, string $class, string $mode, bool $checked = false ): string {
		$title = sprintf(
			/* translators: 1: facet value, 2: matching count */
			__( '%1$s (%2$d)', 'omniloop' ),
			$display,
			$count
		);
		$tick = $checked ? ' checked="checked"' : '';

		if ( 'color' === $mode || 'image' === $mode ) {
			$image = WooFacets::swatch_image( $key, $value );
			if ( '' !== $image ) {
				return sprintf(
					'<label class="%s" title="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> <i class="omniloop-swatch omniloop-swatch--image" aria-hidden="true"><img src="%s" alt="" width="40" height="40" loading="lazy" decoding="async"></i> <span class="screen-reader-text">%s</span> <em>%d</em></label>',
					esc_attr( $class ),
					esc_attr( $title ),
					esc_attr( $key ),
					esc_attr( $value ),
					$tick,
					esc_url( $image ),
					esc_html( $display ),
					$count
				);
			}
		}

		if ( 'color' === $mode ) {
			$hex    = WooFacets::color_hex( $key, $value );
			$style  = '' !== $hex ? ' style="--ol-swatch:' . esc_attr( $hex ) . '"' : '';
			$letter = '' !== $hex ? '' : esc_html( strtoupper( substr( $display, 0, 1 ) ) );

			return sprintf(
				'<label class="%s"%s title="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> <i class="omniloop-swatch" aria-hidden="true">%s</i> <span class="screen-reader-text">%s</span> <em>%d</em></label>',
				esc_attr( $class ),
				$style,
				esc_attr( $title ),
				esc_attr( $key ),
				esc_attr( $value ),
				$tick,
				$letter,
				esc_html( $display ),
				$count
			);
		}

		if ( 'image' === $mode ) {
			$letter = esc_html( strtoupper( substr( $display, 0, 1 ) ) );

			return sprintf(
				'<label class="%s" title="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> <i class="omniloop-swatch omniloop-swatch--image omniloop-swatch--empty" aria-hidden="true">%s</i> <span class="screen-reader-text">%s</span> <em>%d</em></label>',
				esc_attr( $class ),
				esc_attr( $title ),
				esc_attr( $key ),
				esc_attr( $value ),
				$tick,
				$letter,
				esc_html( $display ),
				$count
			);
		}

		if ( 'size' === $mode ) {
			return sprintf(
				'<label class="%s" title="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> <span class="omniloop-check__text">%s</span> <em>%d</em></label>',
				esc_attr( $class ),
				esc_attr( $title ),
				esc_attr( $key ),
				esc_attr( $value ),
				$tick,
				esc_html( $display ),
				$count
			);
		}

		if ( 'rating' === $mode ) {
			$stars = max( 1, min( 5, (int) $value ) );
			return sprintf(
				'<label class="%s" title="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> %s <span>%s</span> <em>%d</em></label>',
				esc_attr( $class ),
				esc_attr( $title ),
				esc_attr( $key ),
				esc_attr( $value ),
				$tick,
				self::stars_html( $stars ),
				esc_html( $display ),
				$count
			);
		}

		return sprintf(
			'<label class="%s"><input type="checkbox" data-facet="%s" data-value="%s"%s> <span class="omniloop-check__text">%s</span> <em>%d</em></label>',
			esc_attr( $class ),
			esc_attr( $key ),
			esc_attr( $value ),
			$tick,
			esc_html( $display ),
			$count
		);
	}
}
