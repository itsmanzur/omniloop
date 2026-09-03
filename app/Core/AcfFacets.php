<?php
/**
 * Optional ACF facets — choice fields selected in OmniLoop settings.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AcfFacets {

	private const PREFIX = 'acf_';

	private const FACET_TYPES = [
		'select',
		'checkbox',
		'radio',
		'button_group',
		'true_false',
	];

	/** @var array<string,array<string,mixed>>|null */
	private static $field_cache = null;

	public static function is_active(): bool {
		return function_exists( 'acf_get_field_groups' ) || function_exists( 'get_field_object' );
	}

	public static function init(): void {
		if ( ! self::is_active() ) {
			return;
		}
		add_action( 'acf/save_post', [ self::class, 'on_acf_save' ], 30 );
	}

	/**
	 * @param int|string $post_id
	 */
	public static function on_acf_save( $post_id ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		Indexer::maybe_index_post_id( $post_id );
	}

	public static function is_indexed_meta_key( string $meta_key ): bool {
		if ( '' === $meta_key ) {
			return false;
		}

		foreach ( self::selected_fields() as $field_name ) {
			if ( $meta_key === $field_name || $meta_key === '_' . $field_name ) {
				return true;
			}
		}

		return false;
	}

	public static function is_acf_key( string $key ): bool {
		return 0 === strpos( $key, self::PREFIX );
	}

	public static function field_name_from_key( string $key ): string {
		if ( ! self::is_acf_key( $key ) ) {
			return '';
		}

		return substr( $key, strlen( self::PREFIX ) );
	}

	public static function facet_key_for_field( string $field_name ): string {
		$name = Indexer::sanitize_facet_key( $field_name );
		if ( '' === $name ) {
			return '';
		}

		return Indexer::sanitize_facet_key( self::PREFIX . $name );
	}

	/**
	 * Selected ACF field names from settings.
	 *
	 * @return string[]
	 */
	public static function selected_fields(): array {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = isset( $settings['acf_fields'] ) && is_array( $settings['acf_fields'] )
			? $settings['acf_fields']
			: [];

		$out = [];
		foreach ( $raw as $name ) {
			$name = Indexer::sanitize_facet_key( (string) $name );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Discover facetable ACF fields site-wide.
	 *
	 * @return list<array{name:string,label:string,type:string}>
	 */
	public static function available_fields(): array {
		if ( ! self::is_active() ) {
			return [];
		}

		$fields = self::all_fields();
		$out    = [];
		foreach ( $fields as $field ) {
			$name = isset( $field['name'] ) ? Indexer::sanitize_facet_key( (string) $field['name'] ) : '';
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( '' === $name || ! in_array( $type, self::FACET_TYPES, true ) ) {
				continue;
			}
			$label = isset( $field['label'] ) ? (string) $field['label'] : $name;
			$out[ $name ] = [
				'name'  => $name,
				'label' => '' !== $label ? $label : $name,
				'type'  => $type,
			];
		}

		$list = array_values( $out );
		usort(
			$list,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $list;
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function facet_keys( string $post_type, array $keys ): array {
		unset( $post_type );
		if ( ! self::is_active() ) {
			return $keys;
		}

		foreach ( self::selected_fields() as $name ) {
			$facet = self::facet_key_for_field( $name );
			if ( '' !== $facet ) {
				$keys[] = $facet;
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * @return list<array{0:string,1:string,2:float|null}>
	 */
	public static function extra_index_values( \WP_Post $post ): array {
		if ( ! self::is_active() ) {
			return [];
		}

		$selected = self::selected_fields();
		if ( ! $selected ) {
			return [];
		}

		$out = [];
		foreach ( $selected as $name ) {
			$facet = self::facet_key_for_field( $name );
			if ( '' === $facet ) {
				continue;
			}

			foreach ( self::values_for_post( $post->ID, $name ) as $value ) {
				$numeric = is_numeric( $value ) ? (float) $value : null;
				$out[]   = [ $facet, $value, $numeric ];
			}
		}

		return $out;
	}

	public static function label( string $key ): string {
		$name = self::field_name_from_key( $key );
		if ( '' === $name ) {
			return $key;
		}

		$field = self::field_by_name( $name );
		if ( $field && ! empty( $field['label'] ) ) {
			return (string) $field['label'];
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $name ) );
	}

	public static function value_label( string $key, string $value ): string {
		$name = self::field_name_from_key( $key );
		if ( '' === $name ) {
			return $value;
		}

		$field = self::field_by_name( $name );
		if ( ! $field ) {
			return $value;
		}

		$type = isset( $field['type'] ) ? (string) $field['type'] : '';
		if ( 'true_false' === $type ) {
			if ( '1' === $value || 'true' === strtolower( $value ) || 'yes' === strtolower( $value ) ) {
				return ! empty( $field['ui_on_text'] )
					? (string) $field['ui_on_text']
					: __( 'Yes', 'omniloop' );
			}
			return ! empty( $field['ui_off_text'] )
				? (string) $field['ui_off_text']
				: __( 'No', 'omniloop' );
		}

		if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
			if ( isset( $field['choices'][ $value ] ) ) {
				return (string) $field['choices'][ $value ];
			}
			foreach ( $field['choices'] as $choice_value => $choice_label ) {
				if ( (string) $choice_value === $value ) {
					return (string) $choice_label;
				}
			}
		}

		return $value;
	}

	/**
	 * @return string[]
	 */
	private static function values_for_post( int $post_id, string $field_name ): array {
		$raw = null;
		if ( function_exists( 'get_field' ) ) {
			$raw = get_field( $field_name, $post_id, false );
		}
		if ( null === $raw || false === $raw || '' === $raw ) {
			$raw = get_post_meta( $post_id, $field_name, true );
		}

		$field = self::field_by_name( $field_name );
		$type  = $field && isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( 'true_false' === $type ) {
			$truthy = (bool) $raw || '1' === (string) $raw || 'true' === strtolower( (string) $raw );
			return $truthy ? [ '1' ] : [ '0' ];
		}

		if ( is_array( $raw ) ) {
			$out = [];
			foreach ( $raw as $item ) {
				if ( is_array( $item ) ) {
					if ( isset( $item['value'] ) ) {
						$item = $item['value'];
					} elseif ( isset( $item['slug'] ) ) {
						$item = $item['slug'];
					} else {
						continue;
					}
				}
				$clean = self::sanitize_value( $item );
				if ( '' !== $clean ) {
					$out[] = $clean;
				}
			}

			return array_values( array_unique( $out ) );
		}

		$clean = self::sanitize_value( $raw );
		return '' !== $clean ? [ $clean ] : [];
	}

	/**
	 * @param mixed $value
	 */
	private static function sanitize_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$clean = sanitize_text_field( (string) $value );
		if ( '' === $clean ) {
			return '';
		}

		return Indexer::truncate_text( $clean, 191 );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function field_by_name( string $name ): ?array {
		$fields = self::all_fields();
		return isset( $fields[ $name ] ) ? $fields[ $name ] : null;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function all_fields(): array {
		if ( null !== self::$field_cache ) {
			return self::$field_cache;
		}

		self::$field_cache = [];
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return self::$field_cache;
		}

		$groups = acf_get_field_groups();
		if ( ! is_array( $groups ) ) {
			return self::$field_cache;
		}

		foreach ( $groups as $group ) {
			$group_key = isset( $group['key'] ) ? $group['key'] : ( $group['ID'] ?? null );
			if ( ! $group_key || ! function_exists( 'acf_get_fields' ) ) {
				continue;
			}
			$fields = acf_get_fields( $group_key );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			self::collect_fields( $fields );
		}

		return self::$field_cache;
	}

	/**
	 * @param array<int,mixed> $fields
	 */
	private static function collect_fields( array $fields ): void {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$name = isset( $field['name'] ) ? Indexer::sanitize_facet_key( (string) $field['name'] ) : '';
			if ( '' !== $name ) {
				self::$field_cache[ $name ] = $field;
			}
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				self::collect_fields( $field['sub_fields'] );
			}
			if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( is_array( $layout ) && ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
						self::collect_fields( $layout['sub_fields'] );
					}
				}
			}
		}
	}
}
