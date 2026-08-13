<?php
/**
 * Generation-based transient cache for query payloads.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CacheManager {

	private const OPTION_GEN = 'omniloop_cache_gen';
	private const TTL        = 300;

	public static function get( string $key ) {
		$cached = get_transient( self::prefix() . $key );

		return false === $cached ? null : $cached;
	}

	public static function set( string $key, $value, int $ttl = self::TTL ): void {
		set_transient( self::prefix() . $key, $value, $ttl );
	}

	public static function bump(): void {
		$gen = (int) get_option( self::OPTION_GEN, 1 );
		update_option( self::OPTION_GEN, $gen + 1, false );
	}

	public static function query_key( array $args ): string {
		return md5( wp_json_encode( $args ) );
	}

	private static function prefix(): string {
		$gen = (int) get_option( self::OPTION_GEN, 1 );

		return 'olq_' . $gen . '_';
	}
}
