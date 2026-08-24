<?php
/**
 * Attach OmniLoop facet state to native Divi 5 / WP loop queries.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Integrations\Divi5;

use OmniLoop\Engine\Core\I18n;
use OmniLoop\Engine\Core\QueryBuilder;
use OmniLoop\Engine\Core\SeoUrls;
use OmniLoop\Engine\Rest\FilterEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NativeLoop {

	public static function init(): void {
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'pre_get_posts', [ self::class, 'attach_query' ], 20 );
		add_filter( 'query_loop_block_query_vars', [ self::class, 'filter_query_loop' ], 20, 2 );
		add_filter( 'divi_module_loop_query_args', [ self::class, 'filter_divi_loop_args' ], 20, 2 );
		add_filter( 'et_builder_loop_args', [ self::class, 'filter_divi_loop_args' ], 20 );
		add_filter( 'omniloop_matching_ids', [ self::class, 'filter_matching_ids' ], 10, 3 );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'omniloop_attach';

		return $vars;
	}

	/**
	 * Facet keys from the OmniLoop index, for Loop Builder query conditions.
	 *
	 * @return array<string,string> key => label
	 */
	public static function index_keys( string $post_type = '' ): array {
		if ( '' === $post_type ) {
			$post_type = \OmniLoop\Engine\Core\LoopRenderer::default_post_type();
		}
		$post_type = sanitize_key( $post_type );
		$builder   = new QueryBuilder();
		$out       = [];
		foreach ( $builder->facet_keys_for_type( $post_type ) as $key ) {
			$out[ $key ] = I18n::facet_label( $key );
		}

		/**
		 * Expose OmniLoop index facet keys to native Loop Builder conditions.
		 *
		 * @param array<string,string> $out
		 * @param string               $post_type
		 */
		$filtered = apply_filters( 'omniloop_loop_builder_facet_keys', $out, $post_type );

		return is_array( $filtered ) ? $filtered : $out;
	}

	/**
	 * @param array<string,mixed> $facets
	 * @return int[]
	 */
	public static function matching_ids( string $post_type, array $facets = [], string $search = '' ): array {
		$post_type = sanitize_key( $post_type );
		if ( ! FilterEndpoint::is_allowed_post_type( $post_type ) ) {
			$post_type = \OmniLoop\Engine\Core\LoopRenderer::default_post_type();
		}

		$builder = new QueryBuilder();

		return $builder->ids(
			[
				'post_type'     => $post_type,
				'per_page'      => QueryBuilder::MAX_PER_PAGE,
				'page'          => 1,
				'sort_by'       => 'date',
				'search'        => substr( sanitize_text_field( $search ), 0, 120 ),
				'facets'        => $facets,
				'facet_logic'   => 'smart',
				'sort_meta_key' => '',
				'merchandising' => false,
				'extra_pin_ids' => [],
			]
		);
	}

	/**
	 * @param mixed               $ids
	 * @param string              $post_type
	 * @param array<string,mixed> $facets
	 * @return int[]
	 */
	public static function filter_matching_ids( $ids, string $post_type = 'post', array $facets = [] ): array {
		unset( $ids );

		return self::matching_ids( $post_type, $facets );
	}

	public static function attach_query( \WP_Query $query ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$post_type = self::query_post_type( $query );
		if ( '' === $post_type || ! FilterEndpoint::is_allowed_post_type( $post_type ) ) {
			return;
		}

		$forced = (bool) $query->get( 'omniloop_attach' );
		$state  = SeoUrls::frontend_enabled() ? SeoUrls::state_for( $post_type ) : null;
		$filtered = is_array( $state ) && SeoUrls::state_is_filtered( $state );

		$attach = $forced || ( $filtered && self::is_listing_query( $query, $post_type ) );
		/**
		 * Whether this WP_Query should be restricted to OmniLoop matching IDs.
		 *
		 * @param bool     $attach
		 * @param \WP_Query $query
		 * @param string   $post_type
		 */
		$attach = (bool) apply_filters( 'omniloop_attach_native_loop', $attach, $query, $post_type );
		if ( ! $attach || ! is_array( $state ) ) {
			if ( $forced ) {
				$state = [
					'page'   => 1,
					'sort'   => 'date',
					'search' => '',
					'view'   => '',
					'facets' => [],
				];
			} else {
				return;
			}
		}

		$ids = self::matching_ids(
			$post_type,
			is_array( $state['facets'] ) ? $state['facets'] : [],
			(string) ( $state['search'] ?? '' )
		);
		self::apply_ids( $query, $ids );
	}

	/**
	 * @param array<string,mixed> $query
	 * @param mixed               $block
	 * @return array<string,mixed>
	 */
	public static function filter_query_loop( $query, $block = null ): array {
		unset( $block );
		$query = is_array( $query ) ? $query : [];
		$post_type = isset( $query['post_type'] ) ? sanitize_key( (string) $query['post_type'] ) : '';
		if ( '' === $post_type ) {
			$post_type = \OmniLoop\Engine\Core\LoopRenderer::default_post_type();
		}
		if ( ! FilterEndpoint::is_allowed_post_type( $post_type ) || ! SeoUrls::frontend_enabled() ) {
			return $query;
		}
		$state = SeoUrls::state_for( $post_type );
		if ( ! SeoUrls::state_is_filtered( $state ) && empty( $query['omniloop_attach'] ) ) {
			return $query;
		}
		$ids = self::matching_ids( $post_type, is_array( $state['facets'] ) ? $state['facets'] : [], (string) $state['search'] );
		$query['post__in'] = $ids ? $ids : [ 0 ];
		$query['orderby']  = 'post__in';

		return $query;
	}

	/**
	 * @param mixed $args
	 * @param mixed $module
	 * @return mixed
	 */
	public static function filter_divi_loop_args( $args, $module = null ) {
		unset( $module );
		if ( ! is_array( $args ) ) {
			return $args;
		}

		return self::filter_query_loop( $args, null );
	}

	/**
	 * @param int[] $ids
	 */
	private static function apply_ids( \WP_Query $query, array $ids ): void {
		$query->set( 'post__in', $ids ? array_map( 'intval', $ids ) : [ 0 ] );
		$orderby = $query->get( 'orderby' );
		if ( ! $orderby ) {
			$query->set( 'orderby', 'post__in' );
		}
	}

	private static function is_listing_query( \WP_Query $query, string $post_type ): bool {
		if ( $query->is_singular() ) {
			return false;
		}
		if ( $query->get( 'omniloop_attach' ) ) {
			return true;
		}
		$types = $query->get( 'post_type' );
		if ( is_string( $types ) ) {
			$types = [ $types ];
		}
		if ( ! is_array( $types ) || ! $types ) {
			if ( $query->is_home() && 'post' === $post_type ) {
				return true;
			}
			if ( $query->is_post_type_archive( $post_type ) ) {
				return true;
			}

			return false;
		}

		return in_array( $post_type, $types, true );
	}

	private static function query_post_type( \WP_Query $query ): string {
		$types = $query->get( 'post_type' );
		if ( is_array( $types ) && isset( $types[0] ) ) {
			return sanitize_key( (string) $types[0] );
		}
		if ( is_string( $types ) && '' !== $types ) {
			return sanitize_key( $types );
		}
		if ( $query->is_post_type_archive( 'product' ) ) {
			return 'product';
		}

		return $query->is_home() ? 'post' : '';
	}
}
