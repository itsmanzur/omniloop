<?php
/**
 * Shared markup for Gutenberg, Divi 5, and the [omniloop] shortcode.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LoopRenderer {

	/**
	 * @param array<string,mixed> $atts
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'post_type'    => self::default_post_type(),
				'per_page'     => 12,
				'columns'      => 3,
				'image_ratio'  => '4-3',
				'show_filters' => 'on',
			],
			is_array( $atts ) ? $atts : [],
			'omniloop'
		);

		return self::render(
			[
				'postType'    => sanitize_key( (string) $atts['post_type'] ),
				'perPage'     => (int) $atts['per_page'],
				'columns'     => (int) $atts['columns'],
				'imageRatio'  => sanitize_key( (string) $atts['image_ratio'] ),
				'showFilters' => $atts['show_filters'],
			]
		);
	}

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

	public static function is_flag_on( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$flag = strtolower( (string) $value );
		if ( in_array( $flag, [ 'off', '0', 'false', 'no', '' ], true ) ) {
			return false;
		}
		return true;
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
		$columns  = self::sanitize_columns( $attributes['columns'] ?? 3 );
		$ratio    = self::sanitize_ratio( $attributes['imageRatio'] ?? '4-3' );
		$filters_on = self::is_flag_on( $attributes['showFilters'] ?? 'on' );

		$parsed = [
			'post_type' => $post_type,
			'per_page'  => $per_page,
			'page'      => 1,
			'sort_by'   => 'date',
			'search'    => '',
			'facets'    => [],
		];

		$builder = new QueryBuilder();
		$result  = $builder->query( $parsed );

		wp_enqueue_style( 'omniloop-style' );
		wp_enqueue_script( 'omniloop-engine' );

		static $instance = 0;
		++$instance;
		$id = 'omniloop-' . $instance;

		$config = wp_json_encode(
			[
				'restUrl'  => rest_url( 'omniloop/v1/query' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'postType' => $post_type,
				'perPage'  => $per_page,
			],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
		);

		$filters = $filters_on ? self::filters_html( $post_type, $result ) : '';
		$grid    = self::grid_html( $result['post_ids'] );
		$count   = sprintf(
			/* translators: %d: number of matching posts */
			esc_html( _n( '%d result', '%d results', (int) $result['total'], 'omniloop' ) ),
			(int) $result['total']
		);

		$pages = (int) $result['total'] > 0
			? (int) ceil( (int) $result['total'] / $per_page )
			: 0;

		$classes = [
			'omniloop-app',
			'omniloop-app--cols-' . $columns,
			'omniloop-app--ratio-' . $ratio,
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
					<p class="omniloop-count"><?php echo esc_html( $count ); ?></p>
					<div class="omniloop-grid">
						<?php echo $grid; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<nav class="omniloop-pagination" data-pages="<?php echo esc_attr( (string) $pages ); ?>" hidden></nav>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * @param int[] $post_ids
	 */
	public static function grid_html( array $post_ids ): string {
		if ( empty( $post_ids ) ) {
			return '<p class="omniloop-empty">' . esc_html__( 'No results.', 'omniloop' ) . '</p>';
		}

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
	 * @return array{id:int,title:string,permalink:string,thumbnail:string,price:string,excerpt:string,rendered_card:string}|null
	 */
	public static function post_payload( int $post_id ): ?array {
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

		$price = '';
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$price = wp_strip_all_tags( (string) $product->get_price_html() );
			}
		}

		$raw     = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
		$excerpt = wp_trim_words( wp_strip_all_tags( $raw ), 18 );

		$card = self::card_html(
			[
				'id'        => $post_id,
				'title'     => $title,
				'permalink' => $permalink,
				'thumbnail' => $thumb,
				'price'     => $price,
				'excerpt'   => $excerpt,
			]
		);

		return [
			'id'            => $post_id,
			'title'         => wp_specialchars_decode( $title, ENT_QUOTES ),
			'permalink'     => $permalink,
			'thumbnail'     => $thumb,
			'price'         => $price,
			'excerpt'       => $excerpt,
			'rendered_card' => $card,
		];
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
	 * @param array{id:int,title:string,permalink:string,thumbnail:string,price:string,excerpt:string} $item
	 */
	public static function card_html( array $item ): string {
		$letter = self::fallback_letter( $item['title'] );
		if ( $item['thumbnail'] ) {
			$img = sprintf(
				'<div class="omniloop-card__media"><img src="%s" alt="%s" loading="lazy" width="600" height="450"></div>',
				esc_url( $item['thumbnail'] ),
				esc_attr( $item['title'] )
			);
		} else {
			$img = sprintf(
				'<div class="omniloop-card__media omniloop-card__media--empty"><span class="omniloop-card__fallback">%s</span></div>',
				esc_html( $letter )
			);
		}

		$price = '';
		if ( $item['price'] ) {
			$price = '<p class="omniloop-card__price">' . wp_kses_post( $item['price'] ) . '</p>';
		}

		return sprintf(
			'<article class="omniloop-card" data-id="%d"><a class="omniloop-card__link" href="%s">%s<h3 class="omniloop-card__title">%s</h3>%s</a></article>',
			(int) $item['id'],
			esc_url( $item['permalink'] ),
			$img,
			esc_html( $item['title'] ),
			$price
		);
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

	/**
	 * @param array<string,mixed> $result
	 */
	private static function filters_html( string $post_type, array $result ): string {
		$builder = new QueryBuilder();
		$keys    = $builder->facet_keys_for_type( $post_type );
		$counts  = $result['facets'] ?? [];

		$html  = '<form class="omniloop-filters" action="#" method="get">';
		$html .= '<p class="omniloop-filters__heading">' . esc_html__( 'Filters', 'omniloop' ) . '</p>';
		$html .= '<label class="omniloop-search"><span class="screen-reader-text">' . esc_html__( 'Search', 'omniloop' ) . '</span>';
		$html .= '<input type="search" name="ol_search" class="omniloop-search__input" placeholder="' . esc_attr__( 'Search…', 'omniloop' ) . '" autocomplete="off"></label>';

		$min = (float) ( $result['price_min'] ?? 0 );
		$max = (float) ( $result['price_max'] ?? 0 );
		if ( $max > 0 ) {
			$html .= '<fieldset class="omniloop-facet"><legend>' . esc_html__( 'Max price', 'omniloop' ) . '</legend>';
			$html .= '<div class="omniloop-price"><span>' . esc_html( (string) (int) $min ) . '</span>';
			$html .= '<input type="range" class="omniloop-price__range" min="' . esc_attr( (string) (int) $min ) . '" max="' . esc_attr( (string) (int) $max ) . '" value="' . esc_attr( (string) (int) $max ) . '">';
			$html .= '<span class="omniloop-price__value">' . esc_html( (string) (int) $max ) . '</span></div></fieldset>';
		}

		foreach ( $keys as $key ) {
			if ( '_stock_status' === $key ) {
				$label = __( 'Stock', 'omniloop' );
			} else {
				$tax   = get_taxonomy( $key );
				$label = $tax ? $tax->labels->name : $key;
			}

			$map = isset( $counts[ $key ] ) && is_array( $counts[ $key ] ) ? $counts[ $key ] : [];
			if ( empty( $map ) ) {
				continue;
			}

			$html .= '<fieldset class="omniloop-facet"><legend>' . esc_html( $label ) . '</legend>';
			foreach ( $map as $value => $count ) {
				$display = $value;
				if ( taxonomy_exists( $key ) ) {
					$term = get_term_by( 'slug', (string) $value, $key );
					if ( $term instanceof \WP_Term ) {
						$display = $term->name;
					}
				} elseif ( 'instock' === $value ) {
					$display = __( 'In stock', 'omniloop' );
				} elseif ( 'outofstock' === $value ) {
					$display = __( 'Out of stock', 'omniloop' );
				}

				$html .= sprintf(
					'<label class="omniloop-check"><input type="checkbox" data-facet="%s" data-value="%s"> <span>%s</span> <em>%d</em></label>',
					esc_attr( $key ),
					esc_attr( (string) $value ),
					esc_html( $display ),
					(int) $count
				);
			}
			$html .= '</fieldset>';
		}

		$html .= '<label class="omniloop-sort"><span>' . esc_html__( 'Sort', 'omniloop' ) . '</span>';
		$html .= '<select class="omniloop-sort__select">';
		$html .= '<option value="date">' . esc_html__( 'Newest', 'omniloop' ) . '</option>';
		$html .= '<option value="title">' . esc_html__( 'Title', 'omniloop' ) . '</option>';
		$html .= '<option value="price_asc">' . esc_html__( 'Price: low to high', 'omniloop' ) . '</option>';
		$html .= '<option value="price_desc">' . esc_html__( 'Price: high to low', 'omniloop' ) . '</option>';
		$html .= '</select></label>';
		$html .= '<button type="button" class="omniloop-reset">' . esc_html__( 'Reset filters', 'omniloop' ) . '</button>';
		$html .= '</form>';

		return $html;
	}
}
