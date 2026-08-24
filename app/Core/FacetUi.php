<?php
/**
 * Empty-result hiding and conditional facet visibility rules.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FacetUi {

	/**
	 * @param mixed $raw
	 * @return array{hide_zero_count:string,hide_empty_groups:string,rules:list<array{id:string,source_facet:string,source_values:string[],action:string,target_facet:string}>}
	 */
	public static function sanitize( $raw ): array {
		$raw = is_array( $raw ) ? $raw : [];

		return [
			'hide_zero_count'   => self::flag( $raw['hide_zero_count'] ?? 'on', 'on' ),
			'hide_empty_groups' => self::flag( $raw['hide_empty_groups'] ?? 'on', 'on' ),
			'rules'             => self::sanitize_rules( $raw['rules'] ?? [] ),
		];
	}

	/**
	 * @return array{hide_zero_count:string,hide_empty_groups:string,rules:list<array{id:string,source_facet:string,source_values:string[],action:string,target_facet:string}>}
	 */
	public static function settings(): array {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = is_array( $settings ) && isset( $settings['facet_ui'] ) ? $settings['facet_ui'] : [];

		return self::sanitize( $raw );
	}

	public static function hide_zero_count(): bool {
		return 'on' === self::settings()['hide_zero_count'];
	}

	public static function hide_empty_groups(): bool {
		return 'on' === self::settings()['hide_empty_groups'];
	}

	/**
	 * @return list<array{id:string,source_facet:string,source_values:string[],action:string,target_facet:string}>
	 */
	public static function rules(): array {
		return self::settings()['rules'];
	}

	/**
	 * @param string[]            $facet_keys
	 * @param array<string,mixed> $selected
	 * @return array<string,bool>
	 */
	public static function visibility_map( array $facet_keys, array $selected, array $rules = [] ): array {
		$out = [];
		foreach ( $facet_keys as $key ) {
			$out[ (string) $key ] = true;
		}

		if ( ! $rules ) {
			$rules = self::rules();
		}

		$show_targets = [];
		foreach ( $rules as $rule ) {
			if ( 'show' === $rule['action'] ) {
				$show_targets[ $rule['target_facet'] ] = true;
			}
		}

		foreach ( array_keys( $show_targets ) as $target ) {
			if ( isset( $out[ $target ] ) ) {
				$out[ $target ] = false;
			}
		}

		foreach ( $rules as $rule ) {
			$matched = self::source_matches( $selected, $rule['source_facet'], $rule['source_values'] );
			$target  = $rule['target_facet'];
			if ( ! isset( $out[ $target ] ) ) {
				continue;
			}
			if ( 'show' === $rule['action'] && $matched ) {
				$out[ $target ] = true;
			}
			if ( 'hide' === $rule['action'] && $matched ) {
				$out[ $target ] = false;
			}
		}

		return $out;
	}

	public static function is_visible( string $facet_key, array $selected, array $rules = [] ): bool {
		$map = self::visibility_map( [ $facet_key ], $selected, $rules );

		return ! empty( $map[ $facet_key ] );
	}

	/**
	 * Drop zero-count values unless they are currently selected.
	 *
	 * @param array<string,array<string,int>> $counts
	 * @param array<string,mixed>             $selected
	 * @return array<string,array<string,int>>
	 */
	public static function filter_counts( array $counts, array $selected, bool $hide_zero = true, bool $hide_empty = true ): array {
		if ( ! $hide_zero && ! $hide_empty ) {
			return $counts;
		}

		$out = [];
		foreach ( $counts as $key => $map ) {
			if ( ! is_array( $map ) ) {
				continue;
			}
			$picked = isset( $selected[ $key ] ) && is_array( $selected[ $key ] ) ? $selected[ $key ] : [];
			$kept   = [];
			foreach ( $map as $value => $count ) {
				$count = (int) $count;
				if ( $hide_zero && $count < 1 && ! in_array( (string) $value, $picked, true ) ) {
					continue;
				}
				$kept[ (string) $value ] = $count;
			}
			if ( $hide_empty && ! $kept && ! $picked ) {
				continue;
			}
			$out[ (string) $key ] = $kept;
		}

		return $out;
	}

	/**
	 * Payload fragment for the frontend engine.
	 *
	 * @return array{hideZeroCount:bool,hideEmptyGroups:bool,rules:list<array<string,mixed>>}
	 */
	public static function frontend_config(): array {
		$settings = self::settings();

		return [
			'hideZeroCount'   => 'on' === $settings['hide_zero_count'],
			'hideEmptyGroups' => 'on' === $settings['hide_empty_groups'],
			'rules'           => $settings['rules'],
		];
	}

	/**
	 * @param array<string,mixed> $selected
	 * @param string[]            $values
	 */
	public static function source_matches( array $selected, string $source_facet, array $values ): bool {
		$picked = isset( $selected[ $source_facet ] ) && is_array( $selected[ $source_facet ] )
			? array_map( 'strval', $selected[ $source_facet ] )
			: [];
		if ( ! $picked ) {
			return false;
		}
		if ( ! $values ) {
			return true;
		}

		return (bool) array_intersect( $values, $picked );
	}

	/**
	 * @param mixed $raw
	 * @return list<array{id:string,source_facet:string,source_values:string[],action:string,target_facet:string}>
	 */
	private static function sanitize_rules( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out  = [];
		$seen = [];
		foreach ( array_slice( $raw, 0, 40 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source = Indexer::sanitize_facet_key( (string) ( $row['source_facet'] ?? '' ) );
			$target = Indexer::sanitize_facet_key( (string) ( $row['target_facet'] ?? '' ) );
			if ( '' === $source || '' === $target || $source === $target ) {
				continue;
			}
			$action = sanitize_key( (string) ( $row['action'] ?? 'show' ) );
			if ( ! in_array( $action, [ 'show', 'hide' ], true ) ) {
				$action = 'show';
			}
			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = $source . '-' . $action . '-' . $target;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$values = [];
			$raw_values = $row['source_values'] ?? [];
			if ( is_string( $raw_values ) ) {
				$raw_values = preg_split( '/[\s,]+/', $raw_values ) ?: [];
			}
			if ( is_array( $raw_values ) ) {
				foreach ( array_slice( $raw_values, 0, 20 ) as $value ) {
					if ( is_array( $value ) ) {
						continue;
					}
					$item = sanitize_text_field( (string) $value );
					if ( '' !== $item ) {
						$values[] = substr( $item, 0, 191 );
					}
				}
			}
			$seen[ $id ] = true;
			$out[]       = [
				'id'            => $id,
				'source_facet'  => $source,
				'source_values' => array_values( array_unique( $values ) ),
				'action'        => $action,
				'target_facet'  => $target,
			];
		}

		return $out;
	}

	private static function flag( $raw, string $default ): string {
		$raw = is_string( $raw ) ? strtolower( $raw ) : $default;

		return 'on' === $raw ? 'on' : 'off';
	}
}
