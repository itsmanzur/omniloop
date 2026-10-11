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
		$args['_ol_lang'] = I18n::current_language();
		// Symbols collide ($ for USD/CAD/AUD), so key on the currency code.
		$args['_ol_cur']  = MultiCurrency::code();

		/**
		 * Extra context that changes a query payload (tax location, role pricing…).
		 *
		 * @param array<string,mixed> $parts
		 * @param array<string,mixed> $args
		 */
		$args['_ol_ctx'] = apply_filters( 'omniloop_cache_key_parts', [], $args );

		return md5( (string) wp_json_encode( $args ) );
	}

	private static function prefix(): string {
		$gen = (int) get_option( self::OPTION_GEN, 1 );

		return 'olq_' . $gen . '_';
	}
}
