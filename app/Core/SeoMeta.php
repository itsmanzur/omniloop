<?php
/**
 * Filtered-page title, description, H1, and robots.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoMeta {

	public static function init(): void {
		add_filter( 'document_title_parts', [ self::class, 'title_parts' ], 20 );
		add_filter( 'wpseo_title', [ self::class, 'filter_title_string' ], 20 );
		add_filter( 'rank_math/frontend/title', [ self::class, 'filter_title_string' ], 20 );
		add_filter( 'wpseo_metadesc', [ self::class, 'filter_description' ], 20 );
		add_filter( 'rank_math/frontend/description', [ self::class, 'filter_description' ], 20 );
		add_filter( 'wp_robots', [ self::class, 'robots' ], 20 );
		add_filter( 'wpseo_robots', [ self::class, 'filter_robots_string' ], 20 );
		add_filter( 'rank_math/frontend/robots', [ self::class, 'filter_rank_math_robots' ], 20 );
		add_action( 'wp_head', [ self::class, 'meta_description_tag' ], 4 );
		add_filter( 'the_title', [ self::class, 'filter_h1' ], 20, 2 );
	}

	/**
	 * @param mixed $raw
	 * @return array{
	 *   title_template:string,
	 *   description_template:string,
	 *   h1_template:string,
	 *   noindex_filtered:string,
	 *   noindex_paged:string,
	 *   sitemap:string,
	 *   sitemap_page_id:int,
	 *   sitemap_facets:string[],
	 *   sitemap_max:int
	 * }
	 */
	public static function sanitize( $raw ): array {
		$raw = is_array( $raw ) ? $raw : [];
		$max = isset( $raw['sitemap_max'] ) ? (int) $raw['sitemap_max'] : 200;
		$max = max( 10, min( 1000, $max ) );

		$facets = [];
		if ( isset( $raw['sitemap_facets'] ) && is_array( $raw['sitemap_facets'] ) ) {
			foreach ( $raw['sitemap_facets'] as $key ) {
				$key = Indexer::sanitize_facet_key( (string) $key );
				if ( '' !== $key ) {
					$facets[] = $key;
				}
			}
		}

		return [
			'title_template'       => self::sanitize_template( $raw['title_template'] ?? '{page_title} | {facet_labels}' ),
			'description_template' => self::sanitize_template( $raw['description_template'] ?? 'Shop {facet_labels} at {site_name}.' ),
			'h1_template'          => self::sanitize_template( $raw['h1_template'] ?? '{facet_labels}' ),
			'noindex_filtered'     => self::flag( $raw['noindex_filtered'] ?? 'off', 'off' ),
			'noindex_paged'        => self::flag( $raw['noindex_paged'] ?? 'on', 'on' ),
			'sitemap'              => self::flag( $raw['sitemap'] ?? 'off', 'off' ),
			'sitemap_page_id'      => max( 0, (int) ( $raw['sitemap_page_id'] ?? 0 ) ),
			'sitemap_facets'       => array_values( array_unique( $facets ) ),
			'sitemap_max'          => $max,
		];
	}

	/**
	 * @return array{
	 *   title_template:string,
	 *   description_template:string,
	 *   h1_template:string,
	 *   noindex_filtered:string,
	 *   noindex_paged:string,
	 *   sitemap:string,
	 *   sitemap_page_id:int,
	 *   sitemap_facets:string[],
	 *   sitemap_max:int
	 * }
	 */
	public static function settings(): array {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = is_array( $settings ) && isset( $settings['seo'] ) ? $settings['seo'] : [];

		return self::sanitize( $raw );
	}

	public static function sitemap_enabled(): bool {
		return 'on' === self::settings()['sitemap'];
	}

	/**
	 * @param array<string,string> $parts
	 * @return array<string,string>
	 */
	public static function title_parts( $parts ): array {
		$parts = is_array( $parts ) ? $parts : [];
		$title = self::rendered_title( isset( $parts['title'] ) ? (string) $parts['title'] : '' );
		if ( '' !== $title ) {
			$parts['title'] = $title;
		}

		return $parts;
	}

	/**
	 * @param mixed $title
	 */
	public static function filter_title_string( $title ) {
		$rendered = self::rendered_title( is_string( $title ) ? $title : '' );

		return '' !== $rendered ? $rendered : $title;
	}

	/**
	 * @param mixed $description
	 */
	public static function filter_description( $description ) {
		$rendered = self::rendered_description();

		return '' !== $rendered ? $rendered : $description;
	}

	public static function meta_description_tag(): void {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$description = self::rendered_description();
		if ( '' === $description ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $description ) . "\" />\n";
	}

	/**
	 * @param array<string,mixed> $robots
	 * @return array<string,mixed>
	 */
	public static function robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : [];
		if ( ! self::should_noindex() ) {
			return $robots;
		}
		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * @param mixed $robots
	 */
	public static function filter_robots_string( $robots ) {
		if ( ! self::should_noindex() ) {
			return $robots;
		}

		return 'noindex, nofollow';
	}

	/**
	 * @param mixed $robots
	 * @return mixed
	 */
	public static function filter_rank_math_robots( $robots ) {
		if ( ! self::should_noindex() ) {
			return $robots;
		}
		if ( is_array( $robots ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';

			return $robots;
		}

		return 'noindex, nofollow';
	}

	/**
	 * @param mixed $title
	 * @param mixed $post_id
	 */
	public static function filter_h1( $title, $post_id = 0 ) {
		if ( is_admin() || ! SeoUrls::frontend_enabled() ) {
			return $title;
		}
		if ( ! in_the_loop() || ! is_singular() ) {
			return $title;
		}
		$queried = get_queried_object_id();
		if ( $queried < 1 || (int) $post_id !== $queried ) {
			return $title;
		}

		$rendered = self::rendered_h1( is_string( $title ) ? $title : '' );

		return '' !== $rendered ? $rendered : $title;
	}

	public static function should_noindex(): bool {
		$ctx = self::context();
		if ( ! $ctx ) {
			return false;
		}

		return self::robots_for_state( $ctx['state'], self::settings() );
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @param array<string,mixed> $settings
	 */
	public static function robots_for_state( array $state, array $settings ): bool {
		if ( 'on' === ( $settings['noindex_filtered'] ?? 'off' ) && SeoUrls::state_is_filtered( $state ) ) {
			return true;
		}

		return 'on' === ( $settings['noindex_paged'] ?? 'on' ) && (int) ( $state['page'] ?? 1 ) > 1;
	}

	/**
	 * @param array<string,string> $vars
	 */
	public static function expand( string $template, array $vars ): string {
		$template = trim( $template );
		if ( '' === $template ) {
			return '';
		}

		$replace = [];
		foreach ( $vars as $key => $value ) {
			$replace[ '{' . $key . '}' ] = (string) $value;
		}

		$out = strtr( $template, $replace );
		$out = (string) preg_replace( '/\s+\|\s+\|/', ' |', $out );
		$out = (string) preg_replace( '/\s{2,}/', ' ', $out );
		$out = (string) preg_replace( '/\s+\|\s*$/', '', $out );
		$out = (string) preg_replace( '/^\s*\|\s+/', '', $out );

		return trim( $out );
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @return array<string,string>
	 */
	public static function placeholders( array $state, string $post_type, string $page_title ): array {
		$labels = [];
		$first  = '';
		$cat    = '';
		$facets = isset( $state['facets'] ) && is_array( $state['facets'] ) ? $state['facets'] : [];

		foreach ( $facets as $key => $values ) {
			if ( 'price_range' === $key || ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				$label = I18n::value_label( (string) $key, (string) $value );
				if ( '' === $label ) {
					continue;
				}
				$labels[] = $label;
				if ( '' === $first ) {
					$first = $label;
				}
				if ( '' === $cat && in_array( (string) $key, [ 'product_cat', 'category', 'product_brand' ], true ) ) {
					$cat = $label;
				}
			}
		}

		$labels = array_values( array_unique( $labels ) );

		return [
			'page_title'   => $page_title,
			'site_name'    => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
			'facet_value'  => $first,
			'facet_labels' => implode( ', ', $labels ),
			'category'     => $cat,
			'search'       => (string) ( $state['search'] ?? '' ),
			'page'         => (string) max( 1, (int) ( $state['page'] ?? 1 ) ),
			'post_type'    => $post_type,
		];
	}

	private static function rendered_title( string $fallback ): string {
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}
		$settings = self::settings();
		$template = (string) $settings['title_template'];
		if ( '' === $template ) {
			return '';
		}

		return self::expand( $template, self::placeholders( $ctx['state'], $ctx['post_type'], $fallback ) );
	}

	private static function rendered_description(): string {
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}
		$settings = self::settings();
		$template = (string) $settings['description_template'];
		if ( '' === $template ) {
			return '';
		}
		$page_title = '';
		$object     = get_queried_object();
		if ( $object instanceof \WP_Post ) {
			$page_title = get_the_title( $object );
		}

		return self::expand( $template, self::placeholders( $ctx['state'], $ctx['post_type'], is_string( $page_title ) ? $page_title : '' ) );
	}

	private static function rendered_h1( string $fallback ): string {
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}
		$settings = self::settings();
		$template = (string) $settings['h1_template'];
		if ( '' === $template ) {
			return '';
		}

		return self::expand( $template, self::placeholders( $ctx['state'], $ctx['post_type'], $fallback ) );
	}

	/**
	 * @return array{post_type:string,state:array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}}|null
	 */
	private static function context(): ?array {
		if ( ! SeoUrls::frontend_enabled() ) {
			return null;
		}
		$post_type = LoopRenderer::default_post_type();
		$state     = SeoUrls::state_for( $post_type );
		if ( ! SeoUrls::state_is_filtered( $state ) ) {
			return null;
		}

		return [
			'post_type' => $post_type,
			'state'     => $state,
		];
	}

	private static function sanitize_template( $raw ): string {
		$text = sanitize_text_field( (string) $raw );

		return substr( $text, 0, 200 );
	}

	private static function flag( $raw, string $default ): string {
		$raw = is_string( $raw ) ? strtolower( $raw ) : $default;

		return 'on' === $raw ? 'on' : 'off';
	}
}
