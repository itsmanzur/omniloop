<?php
/**
 * Saved filter presets for OmniLoop modules and shareable URLs.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FilterPresets {

	/**
	 * @return list<array{id:string,label:string,post_type:string,search:string,sort_by:string,facets:array<string,mixed>}>
	 */
	public static function all(): array {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = is_array( $settings ) && isset( $settings['filter_presets'] ) && is_array( $settings['filter_presets'] )
			? $settings['filter_presets']
			: [];

		return self::sanitize_list( $raw );
	}

	/**
	 * @return array{id:string,label:string,post_type:string,search:string,sort_by:string,facets:array<string,mixed>}|null
	 */
	public static function get( string $id ): ?array {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return null;
		}

		foreach ( self::all() as $preset ) {
			if ( $preset['id'] === $id ) {
				return $preset;
			}
		}

		return null;
	}

	/**
	 * @return array<string,string> id => label
	 */
	public static function selectable(): array {
		$out = [
			'' => __( 'None', 'omniloop' ),
		];

		foreach ( self::all() as $preset ) {
			$out[ $preset['id'] ] = $preset['label'];
		}

		return $out;
	}

	/**
	 * Preset slug from shareable URL (?ol_preset= or ?preset=).
	 */
	public static function id_from_request(): string {
		if ( is_admin() ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['ol_preset'] ) ? (string) wp_unslash( $_GET['ol_preset'] ) : '';
		if ( '' === $raw ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = isset( $_GET['preset'] ) ? (string) wp_unslash( $_GET['preset'] ) : '';
		}

		return sanitize_key( $raw );
	}

	/**
	 * Resolve preset for a module + current request.
	 *
	 * @return array{id:string,label:string,post_type:string,search:string,sort_by:string,facets:array<string,mixed>}|null
	 */
	public static function resolve_for_module( string $post_type, string $module_preset_id = '' ): ?array {
		$post_type = sanitize_key( $post_type );
		$url_id    = self::id_from_request();
		$module_id = sanitize_key( $module_preset_id );

		$candidate = '' !== $url_id ? $url_id : $module_id;
		if ( '' === $candidate ) {
			return null;
		}

		$preset = self::get( $candidate );
		if ( ! $preset || $preset['post_type'] !== $post_type ) {
			return null;
		}

		return $preset;
	}

	/**
	 * @param array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>} $state
	 * @param array{id:string,label:string,post_type:string,search:string,sort_by:string,facets:array<string,mixed>} $preset
	 * @return array{page:int,sort:string,search:string,view:string,facets:array<string,mixed>}
	 */
	public static function apply_to_state( array $state, array $preset ): array {
		if ( '' !== $preset['search'] ) {
			$state['search'] = $preset['search'];
		}
		if ( '' !== $preset['sort_by'] && in_array( $preset['sort_by'], QueryBuilder::SORTS, true ) ) {
			$state['sort'] = $preset['sort_by'];
		}
		if ( ! empty( $preset['facets'] ) ) {
			$state['facets'] = array_merge( $state['facets'], $preset['facets'] );
		}
		$state['page'] = 1;

		return $state;
	}

	/**
	 * @param mixed $raw
	 * @return list<array{id:string,label:string,post_type:string,search:string,sort_by:string,facets:array<string,mixed>}>
	 */
	public static function sanitize_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out  = [];
		$seen = [];

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $row['label'] ?? $id ) );
			if ( '' === $label ) {
				$label = $id;
			}

			$post_type = sanitize_key( (string) ( $row['post_type'] ?? 'post' ) );
			if ( ! post_type_exists( $post_type ) ) {
				$post_type = 'post';
			}

			$sort = sanitize_key( (string) ( $row['sort_by'] ?? 'date' ) );
			if ( ! in_array( $sort, QueryBuilder::SORTS, true ) ) {
				$sort = 'date';
			}

			$search = substr( sanitize_text_field( (string) ( $row['search'] ?? '' ) ), 0, 120 );
			$facets = self::sanitize_facets( $row['facets'] ?? [] );

			$out[]        = [
				'id'        => $id,
				'label'     => $label,
				'post_type' => $post_type,
				'search'    => $search,
				'sort_by'   => $sort,
				'facets'    => $facets,
			];
			$seen[ $id ] = true;
		}

		return $out;
	}

	/**
	 * @param mixed $raw
	 * @return array<string,mixed>
	 */
	private static function sanitize_facets( $raw ): array {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $key => $values ) {
			$key = Indexer::sanitize_facet_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( 'price_range' === $key ) {
				if ( is_array( $values ) && count( $values ) >= 2 ) {
					$min = (float) $values[0];
					$max = (float) $values[1];
					if ( $max < $min ) {
						$tmp = $min;
						$min = $max;
						$max = $tmp;
					}
					$out[ $key ] = [ $min, $max ];
				}
				continue;
			}

			if ( ! is_array( $values ) ) {
				$values = [ $values ];
			}

			$clean = [];
			foreach ( $values as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value ) {
					$clean[] = $value;
				}
			}
			if ( $clean ) {
				$out[ $key ] = array_values( array_unique( $clean ) );
			}
		}

		return $out;
	}
}
