<?php
/**
 * Optional sitemap entries for filtered OmniLoop URLs.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FilteredSitemap {

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 30 );
	}

	public static function register(): void {
		if ( ! SeoMeta::sitemap_enabled() ) {
			return;
		}
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( '\WP_Sitemaps_Provider' ) ) {
			return;
		}

		wp_register_sitemap_provider(
			'omniloop',
			new class() extends \WP_Sitemaps_Provider {
				public function __construct() {
					$this->name        = 'omniloop';
					$this->object_type = 'omniloop';
				}

				/**
				 * @param int    $page_num
				 * @param string $object_subtype
				 * @return array<int,array<string,mixed>>
				 */
				public function get_url_list( $page_num, $object_subtype = '' ): array {
					unset( $object_subtype );

					return FilteredSitemap::url_list( (int) $page_num );
				}

				/**
				 * @param string $object_subtype
				 */
				public function get_max_num_pages( $object_subtype = '' ): int {
					unset( $object_subtype );

					return FilteredSitemap::max_pages();
				}
			}
		);
	}

	/**
	 * Single-facet pretty URLs for the configured shop page.
	 *
	 * @return list<array{loc:string,lastmod?:string}>
	 */
	public static function url_list( int $page_num = 1 ): array {
		$settings = SeoMeta::settings();
		$page_id  = (int) $settings['sitemap_page_id'];
		if ( $page_id < 1 ) {
			$page_id = (int) get_option( 'page_on_front' );
		}
		if ( $page_id < 1 ) {
			return [];
		}

		$permalink = get_permalink( $page_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return [];
		}

		$post_type = LoopRenderer::default_post_type();
		$keys      = $settings['sitemap_facets'];
		if ( ! $keys ) {
			$builder = new QueryBuilder();
			$keys    = array_slice( $builder->facet_keys_for_type( $post_type ), 0, 8 );
		}

		$max   = max( 10, (int) $settings['sitemap_max'] );
		$urls  = [];
		$seen  = [];
		foreach ( $keys as $key ) {
			foreach ( self::indexed_values( $post_type, (string) $key, 80 ) as $value ) {
				$state = [
					'page'   => 1,
					'sort'   => 'date',
					'search' => '',
					'view'   => '',
					'facets' => [ $key => [ $value ] ],
				];
				$url = SeoUrls::url_for( $state, $post_type, trailingslashit( $permalink ) );
				if ( '' === $url || isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$urls[]       = [ 'loc' => $url ];
				if ( count( $urls ) >= $max ) {
					break 2;
				}
			}
		}

		$offset = ( max( 1, $page_num ) - 1 ) * 50;

		return array_slice( $urls, $offset, 50 );
	}

	public static function max_pages(): int {
		return 1;
	}

	/**
	 * @return string[]
	 */
	public static function indexed_values( string $post_type, string $facet_key, int $limit = 80 ): array {
		global $wpdb;

		$table = Installer::table_name();
		$limit = max( 1, min( 200, $limit ) );
		$sql   = $wpdb->prepare(
			"SELECT DISTINCT facet_value FROM {$table} WHERE post_type = %s AND facet_key = %s AND facet_value <> '' ORDER BY facet_value ASC LIMIT %d",
			$post_type,
			$facet_key,
			$limit
		);
		if ( ! is_string( $sql ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_col( $sql );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];
		foreach ( $rows as $value ) {
			$value = sanitize_text_field( (string) $value );
			if ( '' !== $value ) {
				$out[] = $value;
			}
		}

		return $out;
	}
}
