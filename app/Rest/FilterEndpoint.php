<?php
/**
 * Public REST contract: POST /wp-json/omniloop/v1/query
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Rest;

use OmniLoop\Engine\Core\CacheManager;
use OmniLoop\Engine\Core\FacetAnalytics;
use OmniLoop\Engine\Core\FacetUi;
use OmniLoop\Engine\Core\FilterPresets;
use OmniLoop\Engine\Core\I18n;
use OmniLoop\Engine\Core\Indexer;
use OmniLoop\Engine\Core\Installer;
use OmniLoop\Engine\Core\LoopRenderer;
use OmniLoop\Engine\Core\Merchandising;
use OmniLoop\Engine\Core\MultiCurrency;
use OmniLoop\Engine\Core\QueryBuilder;
use OmniLoop\Engine\Core\WooFacets;
use OmniLoop\Engine\Admin\CatalogLookup;
use OmniLoop\Engine\Integrations\Divi5\NativeLoop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FilterEndpoint {

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			'omniloop/v1',
			'/query',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => [ self::class, 'allow_public_query' ],
				'args'                => self::args_schema(),
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/reindex',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'reindex' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/index-status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'index_status' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/event',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'event' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/facet-keys',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'facet_keys' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/admin/search-posts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'search_posts' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'omniloop/v1',
			'/admin/facet-values',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'facet_values' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	public static function search_posts( \WP_REST_Request $request ) {
		$ids = Merchandising::parse_ids( $request->get_param( 'ids' ) );

		return rest_ensure_response(
			[
				'posts' => CatalogLookup::search_posts(
					sanitize_key( (string) $request->get_param( 'post_type' ) ),
					(string) $request->get_param( 'q' ),
					$ids
				),
			]
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	public static function facet_values( \WP_REST_Request $request ) {
		return rest_ensure_response(
			[
				'values' => CatalogLookup::facet_values(
					sanitize_key( (string) $request->get_param( 'post_type' ) ),
					(string) $request->get_param( 'key' )
				),
			]
		);
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ) {
		$parsed = self::parse_request( $request );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$cache_key = CacheManager::query_key( $parsed );
		$cached    = CacheManager::get( $cache_key );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$builder = new QueryBuilder();
		$result  = $builder->query( $parsed );
		$payload = self::build_payload( $parsed, $result );

		CacheManager::set( $cache_key, $payload );

		return rest_ensure_response( $payload );
	}

	/**
	 * Bound anonymous query work and transient cardinality. Authenticated editors
	 * are not throttled while working in the Visual Builder.
	 *
	 * @return true|\WP_Error
	 */
	public static function allow_public_query() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		$ip  = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'anonymous' ) );
		$key = 'ol_qr_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 180 ) {
			return new \WP_Error(
				'omniloop_rate_limited',
				__( 'Too many filter requests. Please wait a moment and try again.', 'omniloop' ),
				[ 'status' => 429 ]
			);
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function reindex( \WP_REST_Request $request ) {
		$reset = rest_sanitize_boolean( $request->get_param( 'reset' ) );

		if ( $reset ) {
			delete_option( 'omniloop_reindex_cursor' );
			delete_option( 'omniloop_reindex_processed' );
		}

		$after_id = $reset ? 0 : (int) get_option( 'omniloop_reindex_cursor', 0 );

		return rest_ensure_response(
			array_merge(
				Indexer::process_batch( $after_id ),
				[ 'status' => Indexer::status() ]
			)
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	public static function index_status( \WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response( Indexer::status() );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public static function event( \WP_REST_Request $request ) {
		$ok = FacetAnalytics::record(
			[
				'post_type'    => $request->get_param( 'post_type' ),
				'event_type'   => $request->get_param( 'event_type' ),
				'facet_key'    => $request->get_param( 'facet_key' ),
				'facet_value'  => $request->get_param( 'facet_value' ),
				'post_id'      => $request->get_param( 'post_id' ),
				'result_count' => $request->get_param( 'result_count' ),
			]
		);

		return rest_ensure_response( [ 'ok' => $ok ] );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function facet_keys( \WP_REST_Request $request ) {
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		if ( '' === $post_type ) {
			$post_type = LoopRenderer::default_post_type();
		}
		if ( ! self::is_allowed_post_type( $post_type ) ) {
			return new \WP_Error(
				'omniloop_forbidden_type',
				__( 'This post type cannot be queried.', 'omniloop' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response(
			[
				'post_type' => $post_type,
				'keys'      => NativeLoop::index_keys( $post_type ),
			]
		);
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function parse_request( \WP_REST_Request $request ) {
		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		if ( '' === $post_type ) {
			$post_type = 'post';
		}

		if ( ! self::is_allowed_post_type( $post_type ) ) {
			return new \WP_Error(
				'omniloop_forbidden_type',
				__( 'This post type cannot be queried.', 'omniloop' ),
				[ 'status' => 400 ]
			);
		}

		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 ) {
			$per_page = 12;
		}
		$per_page = min( QueryBuilder::MAX_PER_PAGE, $per_page );

		$page = min( 1000, max( 1, (int) $request->get_param( 'page' ) ) );

		$sort = sanitize_key( (string) $request->get_param( 'sort_by' ) );
		if ( ! in_array( $sort, QueryBuilder::SORTS, true ) ) {
			$sort = 'date';
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$search = Indexer::truncate_text( $search, 120 );

		$facet_logic = QueryBuilder::sanitize_facet_logic( (string) $request->get_param( 'facet_logic' ) );
		$sort_meta   = Indexer::sanitize_facet_key( (string) $request->get_param( 'sort_meta_key' ) );

		$facets = $request->get_param( 'facets' );
		if ( is_string( $facets ) ) {
			$decoded = json_decode( $facets, true );
			$facets  = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $facets ) ) {
			$facets = [];
		}

		$merch_raw = $request->get_param( 'merchandising' );
		if ( null === $merch_raw || '' === $merch_raw ) {
			$merch_raw = true;
		}

		return [
			'post_type'     => $post_type,
			'per_page'      => $per_page,
			'page'          => $page,
			'sort_by'       => $sort,
			'search'        => $search,
			'facets'        => self::sanitize_facets( $facets ),
			'facet_logic'   => $facet_logic,
			'sort_meta_key' => $sort_meta,
			'merchandising' => Merchandising::is_enabled( $merch_raw ),
			'extra_pin_ids' => array_slice( Merchandising::parse_ids( $request->get_param( 'extra_pin_ids' ) ), 0, 50 ),
		];
	}

	/**
	 * @param array<string,mixed> $facets
	 * @return array<string,mixed>
	 */
	private static function sanitize_facets( array $facets ): array {
		$clean = [];
		$i     = 0;

		foreach ( $facets as $key => $values ) {
			if ( $i++ >= QueryBuilder::MAX_FACET_KEYS ) {
				break;
			}

			$key = (string) $key;
			if ( 'price_range' === $key ) {
				if ( is_array( $values ) && isset( $values[0], $values[1] ) ) {
					$min = (float) $values[0];
					$max = (float) $values[1];
					$clean['price_range'] = [ min( $min, $max ), max( $min, $max ) ];
				}
				continue;
			}

			$key = Indexer::sanitize_facet_key( $key );
			if ( '' === $key ) {
				continue;
			}

			if ( ! is_array( $values ) ) {
				$values = [ $values ];
			}

			$out = [];
			foreach ( array_slice( $values, 0, 20 ) as $value ) {
				if ( is_array( $value ) ) {
					continue;
				}
				$item = sanitize_text_field( (string) $value );
				if ( '' !== $item ) {
					$out[] = Indexer::truncate_text( $item, 191 );
				}
			}

			if ( $out ) {
				$clean[ $key ] = array_values( array_unique( $out ) );
			}
		}

		return $clean;
	}

	public static function is_allowed_post_type( string $post_type ): bool {
		if ( ! post_type_exists( $post_type ) ) {
			return false;
		}

		$object = get_post_type_object( $post_type );
		if ( ! $object || empty( $object->public ) || ! empty( $object->exclude_from_search ) ) {
			return false;
		}

		if ( in_array( $post_type, [ 'revision', 'nav_menu_item', 'attachment', 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation' ], true ) ) {
			return false;
		}

		$allowed = Installer::allowed_post_types();

		return in_array( $post_type, $allowed, true );
	}

	/**
	 * Public post types allowed by OmniLoop settings.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function selectable_post_types(): array {
		$out = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $type ) {
			if ( self::is_allowed_post_type( $type->name ) ) {
				$out[ $type->name ] = $type->labels->singular_name;
			}
		}

		if ( post_type_exists( 'product' ) ) {
			$object         = get_post_type_object( 'product' );
			$out['product'] = $object && isset( $object->labels->singular_name )
				? (string) $object->labels->singular_name
				: __( 'Product', 'omniloop' );
		}

		if ( isset( $out['product'] ) ) {
			$product = [ 'product' => $out['product'] ];
			unset( $out['product'] );
			$out = $product + $out;
		}

		return $out;
	}

	/**
	 * Saved filter presets for the Visual Builder dropdown.
	 *
	 * @return array<string,string> id => label
	 */
	public static function selectable_presets(): array {
		return FilterPresets::selectable();
	}

	/**
	 * Facet keys editors can show or hide, keyed by slug.
	 *
	 * @return array<string,string> key => label
	 */
	public static function selectable_facets(): array {
		$builder = new QueryBuilder();
		$out     = [];

		if ( post_type_exists( 'product' ) ) {
			$out['price'] = __( 'Price', 'omniloop' );
		}

		$types = array_keys( self::selectable_post_types() );
		if ( ! $types ) {
			$types = [ 'post' ];
		}

		foreach ( $types as $type ) {
			foreach ( $builder->facet_keys_for_type( $type ) as $key ) {
				if ( ! isset( $out[ $key ] ) ) {
					$out[ $key ] = I18n::facet_label( $key );
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $parsed
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	private static function build_payload( array $parsed, array $result ): array {
		LoopRenderer::prime_caches( $result['post_ids'] );

		$posts = [];
		foreach ( $result['post_ids'] as $post_id ) {
			$built = LoopRenderer::post_payload( (int) $post_id, false );
			if ( $built ) {
				$posts[] = $built;
			}
		}

		$per_page    = (int) $parsed['per_page'];
		$total       = (int) $result['total'];
		$total_pages = $per_page > 0 ? (int) max( 1, (int) ceil( $total / $per_page ) ) : 1;
		if ( 0 === $total ) {
			$total_pages = 0;
		}

		$facet_labels = [];
		$facet_ui     = [];
		$counts       = is_array( $result['facets'] ) ? $result['facets'] : [];
		$visibility   = FacetUi::visibility_map( array_map( 'strval', array_keys( $counts ) ), $parsed['facets'] );
		foreach ( $counts as $key => $map ) {
			$key = (string) $key;
			if ( isset( $visibility[ $key ] ) && ! $visibility[ $key ] ) {
				continue;
			}
			$facet_labels[ $key ] = I18n::facet_label( $key );
			$ui                   = WooFacets::facet_ui( $key, is_array( $map ) ? array_keys( $map ) : [] );
			if ( $ui ) {
				$facet_ui[ $key ] = $ui;
			}
		}

		$display = MultiCurrency::bounds_for_display( (float) $result['price_min'], (float) $result['price_max'] );

		return [
			'status'        => 'success',
			'total_posts'   => $total,
			'total_pages'   => $total_pages,
			'page'          => (int) $parsed['page'],
			'posts'         => $posts,
			'facets_counts' => $counts,
			'facet_labels'  => $facet_labels,
			'facet_ui'      => $facet_ui,
			'price_min'     => $display['min'],
			'price_max'     => $display['max'],
			'currency'      => MultiCurrency::symbol(),
		];
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function args_schema(): array {
		return [
			'post_type' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => 'post',
			],
			'per_page'  => [
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => QueryBuilder::MAX_PER_PAGE,
				'default' => 12,
			],
			'page'      => [
				'type'    => 'integer',
				'minimum' => 1,
				'default' => 1,
			],
			'facets'    => [
				'type'    => 'object',
				'default' => [],
			],
			'sort_by'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => 'date',
			],
			'search'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'facet_logic' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => 'smart',
			],
			'sort_meta_key' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => '',
			],
			'merchandising' => [
				'type'    => 'boolean',
				'default' => true,
			],
			'extra_pin_ids' => [
				'type'    => 'string',
				'default' => '',
			],
		];
	}
}
