<?php
/**
 * Pretty filter URLs: /page/ol/color/red/category/sunglass/
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoUrls {

	public const QUERY_VAR       = 'omniloop_seo';
	public const REWRITE_VERSION = '1';
	public const MARKER          = 'ol';

	private const RESERVED = [ 'p', 'q', 'sort', 'view', 'price' ];

	private const PREFERRED = [
		'product_cat'    => 'category',
		'product_tag'    => 'tag',
		'product_brand'  => 'brand',
		'_stock_status'  => 'stock',
		'on_sale'        => 'sale',
		'featured'       => 'featured',
		'rating'         => 'rating',
	];

	public static function init(): void {
		add_filter( 'query_vars', [ self::class, 'query_vars' ] );
		add_action( 'init', [ self::class, 'register_rules' ], 6 );
		add_action( 'init', [ self::class, 'maybe_flush' ], 20 );
		add_filter( 'request', [ self::class, 'hydrate_request' ] );
		add_filter( 'redirect_canonical', [ self::class, 'disable_canonical' ], 10, 2 );
		add_filter( 'pre_handle_404', [ self::class, 'prevent_404' ], 10, 2 );
		add_action( 'template_redirect', [ self::class, 'redirect_legacy' ], 1 );
		add_filter( 'get_canonical_url', [ self::class, 'filter_canonical' ], 20, 2 );
		add_filter( 'wpseo_canonical', [ self::class, 'filter_canonical_url' ] );
		add_filter( 'rank_math/frontend/canonical', [ self::class, 'filter_canonical_url' ] );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public static function register_rules(): void {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '(.+)' );
		add_rewrite_rule(
			'(.+)/' . self::MARKER . '/(.+)/?$',
			'index.php?pagename=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . self::MARKER . '/(.+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function maybe_flush(): void {
		if ( (string) get_option( 'omniloop_rewrite_version', '' ) === self::REWRITE_VERSION ) {
			return;
		}
		self::register_rules();
		flush_rewrite_rules( false );
		update_option( 'omniloop_rewrite_version', self::REWRITE_VERSION, false );
	}

	/**
	 * @param array<string,mixed> $query_vars
	 * @return array<string,mixed>
	 */
	public static function hydrate_request( array $query_vars ): array {
		if ( is_admin() ) {
			return $query_vars;
		}
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		if ( false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, '/wp-admin/' ) ) {
			return $query_vars;
		}

		$parts = self::split_request_uri();
		if ( ! $parts ) {
			return $query_vars;
		}

		$query_vars[ self::QUERY_VAR ] = $parts['seo'];
		unset( $query_vars['error'], $query_vars['attachment'] );

		$post_id = $parts['post_id'];
		if ( $post_id < 1 && '' === $parts['base'] && 'page' === get_option( 'show_on_front' ) ) {
			$post_id = (int) get_option( 'page_on_front' );
		}

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				unset( $query_vars['name'], $query_vars['pagename'], $query_vars['category_name'], $query_vars['product'] );
				if ( 'page' === $post->post_type ) {
					$query_vars['page_id'] = $post_id;
				} else {
					$query_vars['p']         = $post_id;
					$query_vars['post_type'] = $post->post_type;
				}
			}
		} elseif ( '' !== $parts['base'] ) {
			$query_vars['pagename'] = $parts['base'];
			unset( $query_vars['name'] );
		}

		return $query_vars;
	}

	/**
	 * @param string|false $redirect
	 * @return string|false
	 */
	public static function disable_canonical( $redirect, $requested ) {
		unset( $requested );
		if ( self::split_request_uri() ) {
			return false;
		}

		return $redirect;
	}

	/**
	 * @param bool     $handled
	 * @param \WP_Query $query
	 */
	public static function prevent_404( $handled, $query ): bool {
		if ( $handled ) {
			return true;
		}
		if ( ! $query instanceof \WP_Query || ! self::split_request_uri() ) {
			return false;
		}
		if ( $query->get_queried_object_id() ) {
			$query->is_404 = false;
			status_header( 200 );

			return true;
		}

		return false;
	}

	public static function redirect_legacy(): void {
		if ( ! self::frontend_enabled() || headers_sent() ) {
			return;
		}
		if ( ! self::has_legacy_query() ) {
			return;
		}

		$post_type = LoopRenderer::default_post_type();
		$state     = self::state_for( $post_type );
		$target    = self::url_for( $state, $post_type );
		$current   = self::current_url();
		if ( '' === $target || $target === $current ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * @param string $canonical
	 * @param \WP_Post|null $post
	 */
	public static function filter_canonical( $canonical, $post = null ): string {
		unset( $post );
		$pretty = self::filter_canonical_url( is_string( $canonical ) ? $canonical : '' );

		return is_string( $pretty ) && '' !== $pretty ? $pretty : (string) $canonical;
	}

	/**
	 * @param string $canonical
	 */
	public static function filter_canonical_url( $canonical ): string {
		if ( ! self::frontend_enabled() || ! self::split_request_uri() ) {
			return is_string( $canonical ) ? $canonical : '';
		}
		$post_type = LoopRenderer::default_post_type();
		$pretty    = self::url_for( self::state_for( $post_type ), $post_type );

		return '' !== $pretty ? $pretty : ( is_string( $canonical ) ? $canonical : '' );
	}

	public static function frontend_enabled(): bool {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( ! empty( $_GET['et_fb'] ) || ! empty( $_GET['et_pb_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
			return false;
		}

		return true;
	}

	/**
	 * @return array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}
	 */
	public static function state_for( string $post_type ): array {
		$state = [
			'page'   => 1,
			'sort'   => 'date',
			'search' => '',
			'view'   => '',
			'facets' => [],
		];

		$aliases = self::alias_map( $post_type );
		$parts   = self::split_request_uri();
		$seo     = '';
		if ( $parts ) {
			$seo = $parts['seo'];
		} else {
			$seo = (string) get_query_var( self::QUERY_VAR, '' );
		}
		if ( '' !== $seo ) {
			$state = self::merge_segments( $state, $seo, $aliases );
		}

		$state = self::merge_query( $state, $aliases );

		if ( ! in_array( $state['sort'], QueryBuilder::SORTS, true ) ) {
			$state['sort'] = 'date';
		}
		if ( ! in_array( $state['view'], [ '', 'grid', 'list' ], true ) ) {
			$state['view'] = '';
		}
		$state['page']   = max( 1, (int) $state['page'] );
		$state['search'] = substr( sanitize_text_field( $state['search'] ), 0, 120 );

		return $state;
	}

	public static function state_is_filtered( array $state ): bool {
		if ( (int) ( $state['page'] ?? 1 ) > 1 ) {
			return true;
		}
		if ( '' !== (string) ( $state['search'] ?? '' ) ) {
			return true;
		}
		if ( isset( $state['sort'] ) && 'date' !== $state['sort'] && '' !== $state['sort'] ) {
			return true;
		}
		if ( '' !== (string) ( $state['view'] ?? '' ) ) {
			return true;
		}

		return ! empty( $state['facets'] );
	}

	/**
	 * Facet key => URL alias.
	 *
	 * @return array<string,string>
	 */
	public static function key_aliases( string $post_type ): array {
		$map  = self::alias_map( $post_type );
		$keys = [];
		foreach ( $map as $alias => $key ) {
			if ( ! isset( $keys[ $key ] ) ) {
				$keys[ $key ] = $alias;
			}
		}

		return $keys;
	}

	/**
	 * @param array{page?:int,sort?:string,search?:string,view?:string,facets?:array<string,mixed>} $state
	 */
	public static function url_for( array $state, string $post_type, string $base = '' ): string {
		if ( '' === $base ) {
			$base = self::page_base_url();
		} else {
			$base = trailingslashit( $base );
		}
		if ( '' === $base ) {
			return '';
		}

		$path     = self::path_from_state( $state, $post_type );
		$query    = [];
		$search   = trim( (string) ( $state['search'] ?? '' ) );
		$sort     = (string) ( $state['sort'] ?? 'date' );
		$view     = (string) ( $state['view'] ?? '' );
		if ( '' !== $search ) {
			$query['q'] = $search;
		}
		if ( '' !== $sort && 'date' !== $sort ) {
			$query['sort'] = $sort;
		}
		if ( 'grid' === $view || 'list' === $view ) {
			$query['view'] = $view;
		}

		$url = $base . $path;
		if ( $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		return $url;
	}

	public static function page_base_url(): string {
		$parts = self::split_request_uri();
		if ( $parts ) {
			$base = trim( $parts['base'], '/' );

			return '' === $base
				? trailingslashit( home_url( '/' ) )
				: trailingslashit( home_url( user_trailingslashit( $base ) ) );
		}

		$permalink = get_permalink();
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return trailingslashit( $permalink );
		}

		$uri  = self::request_path();
		$idx  = strpos( $uri, '/' . self::MARKER . '/' );
		$path = false !== $idx ? substr( $uri, 0, $idx ) : $uri;

		return trailingslashit( home_url( $path ) );
	}

	private static function current_url(): string {
		$https = ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'];
		$host  = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
		$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
		if ( '' === $host ) {
			return home_url( $uri );
		}

		return ( $https ? 'https://' : 'http://' ) . $host . $uri;
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 */
	private static function path_from_state( array $state, string $post_type ): string {
		$aliases = self::key_aliases( $post_type );
		$facets  = is_array( $state['facets'] ?? null ) ? $state['facets'] : [];
		$pairs   = [];

		if ( isset( $facets['price_range'] ) && is_array( $facets['price_range'] ) && isset( $facets['price_range'][0], $facets['price_range'][1] ) ) {
			$min = (int) round( (float) $facets['price_range'][0] );
			$max = (int) round( (float) $facets['price_range'][1] );
			if ( $min > $max ) {
				$tmp = $min;
				$min = $max;
				$max = $tmp;
			}
			if ( $max > 0 || $min > 0 ) {
				$pairs['price'] = $min . '-' . $max;
			}
		}

		foreach ( $facets as $key => $values ) {
			if ( 'price_range' === $key ) {
				continue;
			}
			$alias = $aliases[ $key ] ?? '';
			if ( '' === $alias || ! is_array( $values ) || ! $values ) {
				continue;
			}
			$clean = [];
			foreach ( $values as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value ) {
					$clean[] = $value;
				}
			}
			$clean = array_values( array_unique( $clean ) );
			sort( $clean, SORT_STRING );
			if ( $clean ) {
				$pairs[ $alias ] = implode( '+', array_map( 'rawurlencode', $clean ) );
			}
		}

		ksort( $pairs, SORT_STRING );

		$page = max( 1, (int) ( $state['page'] ?? 1 ) );
		if ( $page > 1 ) {
			$pairs['p'] = (string) $page;
		}

		if ( ! $pairs ) {
			return '';
		}

		$segments = [];
		foreach ( $pairs as $alias => $value ) {
			$segments[] = rawurlencode( (string) $alias );
			$segments[] = $value;
		}

		return self::MARKER . '/' . implode( '/', $segments ) . '/';
	}

	/**
	 * Alias => facet key.
	 *
	 * @return array<string,string>
	 */
	private static function alias_map( string $post_type ): array {
		$builder = new QueryBuilder();
		$keys    = $builder->facet_keys_for_type( $post_type );
		$used    = [];
		$map     = [];

		foreach ( $keys as $key ) {
			$alias = self::preferred_alias( $key );
			if ( isset( $used[ $alias ] ) ) {
				$alias = str_replace( '_', '-', $key );
			}
			if ( in_array( $alias, self::RESERVED, true ) && 'price' !== $alias ) {
				$alias = str_replace( '_', '-', $key );
			}
			$used[ $alias ] = true;
			$map[ $alias ]  = $key;
		}

		return $map;
	}

	private static function preferred_alias( string $key ): string {
		if ( isset( self::PREFERRED[ $key ] ) ) {
			return self::PREFERRED[ $key ];
		}
		if ( 0 === strpos( $key, 'pa_' ) ) {
			return substr( $key, 3 );
		}
		if ( AcfFacets::is_acf_key( $key ) ) {
			$name = AcfFacets::field_name_from_key( $key );

			return '' !== $name ? str_replace( '_', '-', $name ) : str_replace( '_', '-', $key );
		}

		return str_replace( '_', '-', $key );
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @param array<string,string> $aliases
	 * @return array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}
	 */
	private static function merge_segments( array $state, string $seo, array $aliases ): array {
		$tokens = explode( '/', trim( $seo, '/' ) );
		$count  = count( $tokens );
		for ( $i = 0; $i + 1 < $count; $i += 2 ) {
			$alias = sanitize_key( rawurldecode( (string) $tokens[ $i ] ) );
			$raw   = rawurldecode( (string) $tokens[ $i + 1 ] );
			if ( '' === $alias || '' === $raw ) {
				continue;
			}
			$state = self::apply_pair( $state, $alias, $raw, $aliases );
		}

		return $state;
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @param array<string,string> $aliases
	 * @return array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}
	 */
	private static function merge_query( array $state, array $aliases ): array {
		$get = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( isset( $get['q'] ) ) {
			$state['search'] = (string) wp_unslash( $get['q'] );
		} elseif ( isset( $get['ol_q'] ) ) {
			$state['search'] = (string) wp_unslash( $get['ol_q'] );
		}
		if ( isset( $get['sort'] ) ) {
			$state['sort'] = sanitize_key( (string) wp_unslash( $get['sort'] ) );
		} elseif ( isset( $get['ol_sort'] ) ) {
			$state['sort'] = sanitize_key( (string) wp_unslash( $get['ol_sort'] ) );
		}
		if ( isset( $get['view'] ) ) {
			$state['view'] = sanitize_key( (string) wp_unslash( $get['view'] ) );
		} elseif ( isset( $get['ol_view'] ) ) {
			$state['view'] = sanitize_key( (string) wp_unslash( $get['ol_view'] ) );
		}
		if ( isset( $get['ol_page'] ) ) {
			$state['page'] = max( 1, (int) $get['ol_page'] );
		}
		if ( isset( $get['ol_price'] ) || isset( $get['price'] ) ) {
			$token = isset( $get['ol_price'] ) ? (string) wp_unslash( $get['ol_price'] ) : (string) wp_unslash( $get['price'] );
			$range = self::parse_price_token( $token );
			if ( $range ) {
				$state['facets']['price_range'] = $range;
			}
		}
		if ( isset( $get['ol_price_min'] ) || isset( $get['ol_price_max'] ) ) {
			$min = isset( $get['ol_price_min'] ) ? (float) $get['ol_price_min'] : 0.0;
			$max = isset( $get['ol_price_max'] ) ? (float) $get['ol_price_max'] : $min;
			if ( $max < $min ) {
				$tmp = $min;
				$min = $max;
				$max = $tmp;
			}
			$state['facets']['price_range'] = [ $min, $max ];
		}

		foreach ( $get as $param => $value ) {
			$param = (string) $param;
			if ( 0 !== strpos( $param, 'ol_f_' ) ) {
				continue;
			}
			$key = Indexer::sanitize_facet_key( substr( $param, 5 ) );
			if ( '' === $key ) {
				continue;
			}
			$state['facets'][ $key ] = self::split_values( (string) wp_unslash( (string) $value ) );
		}

		foreach ( $aliases as $alias => $key ) {
			if ( ! isset( $get[ $alias ] ) ) {
				continue;
			}
			$state['facets'][ $key ] = self::split_values( (string) wp_unslash( (string) $get[ $alias ] ) );
		}

		return $state;
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @param array<string,string> $aliases
	 * @return array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}
	 */
	private static function apply_pair( array $state, string $alias, string $raw, array $aliases ): array {
		if ( 'p' === $alias ) {
			$state['page'] = max( 1, (int) $raw );
			return $state;
		}
		if ( 'q' === $alias ) {
			$state['search'] = $raw;
			return $state;
		}
		if ( 'sort' === $alias ) {
			$state['sort'] = sanitize_key( $raw );
			return $state;
		}
		if ( 'view' === $alias ) {
			$state['view'] = sanitize_key( $raw );
			return $state;
		}
		if ( 'price' === $alias ) {
			$range = self::parse_price_token( $raw );
			if ( $range ) {
				$state['facets']['price_range'] = $range;
			}
			return $state;
		}
		if ( ! isset( $aliases[ $alias ] ) ) {
			return $state;
		}
		$key                     = $aliases[ $alias ];
		$state['facets'][ $key ] = self::split_values( $raw );

		return $state;
	}

	/**
	 * @return array{0:float,1:float}|null
	 */
	private static function parse_price_token( string $raw ): ?array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $raw, $match ) ) {
			$min = (float) $match[1];
			$max = (float) $match[2];
			if ( $min > $max ) {
				$tmp = $min;
				$min = $max;
				$max = $tmp;
			}

			return [ $min, $max ];
		}

		$max = (float) $raw;
		if ( $max > 0 ) {
			return [ 0.0, $max ];
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	private static function split_values( string $raw ): array {
		$parts = preg_split( '/[+,]/', $raw );
		if ( ! is_array( $parts ) ) {
			return [];
		}
		$out = [];
		foreach ( $parts as $part ) {
			$item = sanitize_text_field( $part );
			if ( '' !== $item ) {
				$out[] = substr( $item, 0, 191 );
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * @return array{base:string,seo:string,post_id:int}|null
	 */
	private static function split_request_uri(): ?array {
		$path = self::request_path();
		if ( ! preg_match( '#^(.*?)/' . self::MARKER . '/(.+)$#', $path, $match ) ) {
			return null;
		}

		$seo = trim( (string) $match[2], '/' );
		if ( '' === $seo ) {
			return null;
		}

		$base = trim( (string) $match[1], '/' );

		return [
			'base'    => $base,
			'seo'     => $seo,
			'post_id' => self::post_id_from_path( $base ),
		];
	}

	private static function request_path(): string {
		$uri  = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}

		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home ) && '/' !== $home && 0 === strpos( $path, untrailingslashit( $home ) ) ) {
			$stripped = substr( $path, strlen( untrailingslashit( $home ) ) );
			$path     = is_string( $stripped ) && '' !== $stripped ? $stripped : '/';
			if ( '/' !== $path[0] ) {
				$path = '/' . $path;
			}
		}

		return $path;
	}

	private static function post_id_from_path( string $base ): int {
		$base = trim( $base, '/' );
		if ( '' === $base ) {
			return 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		}

		$url = home_url( user_trailingslashit( $base ) );
		$id  = url_to_postid( $url );
		if ( $id > 0 ) {
			return $id;
		}

		$page = get_page_by_path( $base, OBJECT, [ 'page', 'post' ] );
		if ( $page instanceof \WP_Post ) {
			return (int) $page->ID;
		}

		return 0;
	}

	private static function has_legacy_query(): bool {
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = (string) $key;
			if ( 0 === strpos( $key, 'ol_f_' ) || in_array( $key, [ 'ol_q', 'ol_sort', 'ol_page', 'ol_price', 'ol_price_min', 'ol_price_max', 'ol_view' ], true ) ) {
				return true;
			}
		}

		return false;
	}
}
