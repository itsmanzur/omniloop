<?php
/**
 * Settings JSON export / import for agency deployments.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsIo {

	public const EXPORT_ACTION = 'omniloop_export_settings';
	public const IMPORT_ACTION = 'omniloop_import_settings';

	/**
	 * Keys copied between sites.
	 *
	 * @return string[]
	 */
	public static function portable_keys(): array {
		return [ 'post_types', 'acf_fields', 'auto_sync', 'filter_presets', 'merchandising', 'seo', 'facet_ui', 'search' ];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function export_payload(): array {
		$settings = get_option( 'omniloop_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$data = [];
		foreach ( self::portable_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$data[ $key ] = $settings[ $key ];
			}
		}

		return [
			'plugin'      => 'omniloop',
			'version'     => defined( 'OMNILOOP_VERSION' ) ? OMNILOOP_VERSION : '',
			'exported_at' => gmdate( 'c' ),
			'settings'    => $data,
		];
	}

	/**
	 * @param mixed $raw
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function parse_import( $raw ) {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = null;
		}

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'omniloop_import_json', __( 'The file is not valid JSON.', 'omniloop' ) );
		}

		$settings = isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ? $decoded['settings'] : $decoded;
		if ( isset( $decoded['plugin'] ) && 'omniloop' !== $decoded['plugin'] ) {
			return new \WP_Error( 'omniloop_import_plugin', __( 'This JSON is not an OmniLoop settings export.', 'omniloop' ) );
		}

		$portable = [];
		foreach ( self::portable_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$portable[ $key ] = $settings[ $key ];
			}
		}

		if ( ! $portable ) {
			return new \WP_Error( 'omniloop_import_empty', __( 'No OmniLoop settings found in the file.', 'omniloop' ) );
		}

		return $portable;
	}

	/**
	 * Merge imported keys into current settings, then sanitize.
	 *
	 * @param array<string,mixed> $incoming
	 * @return array<string,mixed>
	 */
	public static function merge_into_current( array $incoming ): array {
		$current = get_option( 'omniloop_settings', [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		return array_merge( $current, $incoming );
	}
}
