<?php
/**
 * Admin post search and facet-value lists for settings pickers.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\I18n;
use OmniLoop\Engine\Core\Indexer;
use OmniLoop\Engine\Core\Installer;
use OmniLoop\Engine\Core\WooFacets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CatalogLookup {

	/**
	 * @param int[] $include_ids
	 * @return list<array{id:int,title:string,type:string}>
	 */
	public static function search_posts( string $post_type, string $search, array $include_ids = [] ): array {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		$out  = [];
		$seen = [];

		$include_ids = array_values( array_unique( array_filter( array_map( 'intval', $include_ids ) ) ) );
		if ( $include_ids ) {
			$posts = get_posts(
				[
					'post_type'              => $post_type,
					'post__in'               => $include_ids,
					'posts_per_page'         => count( $include_ids ),
					'orderby'                => 'post__in',
					'post_status'            => [ 'publish', 'private', 'draft', 'pending' ],
					'suppress_filters'       => false,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);
			foreach ( $posts as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				$seen[ $post->ID ] = true;
				$out[]             = self::card( $post );
			}
			foreach ( $include_ids as $id ) {
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$title = get_the_title( $id );
				$out[] = [
					'id'    => $id,
					'title' => '' !== $title ? $title : ( '#' . $id ),
					'type'  => $post_type,
				];
				$seen[ $id ] = true;
			}
		}

		$search = substr( sanitize_text_field( $search ), 0, 80 );
		if ( '' !== $search ) {
			$posts = get_posts(
				[
					'post_type'              => $post_type,
					's'                      => $search,
					'posts_per_page'         => 20,
					'post_status'            => 'publish',
					'orderby'                => 'relevance',
					'suppress_filters'       => false,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);
			foreach ( $posts as $post ) {
				if ( ! $post instanceof \WP_Post || isset( $seen[ $post->ID ] ) ) {
					continue;
				}
				$seen[ $post->ID ] = true;
				$out[]             = self::card( $post );
			}
		}

		return $out;
	}

	/**
	 * @return list<array{value:string,label:string}>
	 */
	public static function facet_values( string $post_type, string $key ): array {
		$post_type = sanitize_key( $post_type );
		$key       = Indexer::sanitize_facet_key( $key );
		if ( '' === $key ) {
			return [];
		}

		$fixed = self::fixed_values( $key );
		if ( null !== $fixed ) {
			return $fixed;
		}

		$from_index = self::values_from_index( $post_type, $key );
		if ( $from_index ) {
			return $from_index;
		}

		if ( taxonomy_exists( $key ) ) {
			$terms = get_terms(
				[
					'taxonomy'   => $key,
					'hide_empty' => false,
					'number'     => 200,
				]
			);
			if ( is_array( $terms ) ) {
				$out = [];
				foreach ( $terms as $term ) {
					if ( ! $term instanceof \WP_Term ) {
						continue;
					}
					$out[] = [
						'value' => $term->slug,
						'label' => $term->name,
					];
				}

				return $out;
			}
		}

		return [];
	}

	/**
	 * @return list<array{value:string,label:string}>|null
	 */
	private static function fixed_values( string $key ): ?array {
		if ( 'on_sale' === $key ) {
			return [
				[
					'value' => 'yes',
					'label' => __( 'On sale', 'omniloop' ),
				],
			];
		}
		if ( 'featured' === $key ) {
			return [
				[
					'value' => 'yes',
					'label' => __( 'Featured', 'omniloop' ),
				],
			];
		}
		if ( '_stock_status' === $key ) {
			return [
				[
					'value' => 'instock',
					'label' => __( 'In stock', 'omniloop' ),
				],
				[
					'value' => 'outofstock',
					'label' => __( 'Out of stock', 'omniloop' ),
				],
				[
					'value' => 'onbackorder',
					'label' => __( 'On backorder', 'omniloop' ),
				],
			];
		}
		if ( 'rating' === $key ) {
			$out = [];
			for ( $stars = 5; $stars >= 1; $stars-- ) {
				$out[] = [
					'value' => (string) $stars,
					'label' => WooFacets::value_label( 'rating', (string) $stars ),
				];
			}

			return $out;
		}

		return null;
	}

	/**
	 * @return list<array{value:string,label:string}>
	 */
	private static function values_from_index( string $post_type, string $key ): array {
		global $wpdb;

		$table = Installer::table_name();
		$sql   = $wpdb->prepare(
			"SELECT facet_value, COUNT(*) AS c FROM {$table} WHERE post_type = %s AND facet_key = %s GROUP BY facet_value ORDER BY c DESC LIMIT 200",
			$post_type,
			$key
		);
		if ( ! is_string( $sql ) ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$out = [];
		foreach ( $rows as $row ) {
			$value = isset( $row->facet_value ) ? (string) $row->facet_value : '';
			if ( '' === $value ) {
				continue;
			}
			$out[] = [
				'value' => $value,
				'label' => I18n::value_label( $key, $value ),
			];
		}

		return $out;
	}

	/**
	 * @return array{id:int,title:string,type:string}
	 */
	private static function card( \WP_Post $post ): array {
		$title = get_the_title( $post );
		if ( '' === $title ) {
			$title = '#' . (int) $post->ID;
		}

		return [
			'id'    => (int) $post->ID,
			'title' => $title,
			'type'  => (string) $post->post_type,
		];
	}
}
