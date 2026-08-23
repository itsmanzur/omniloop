<?php
/**
 * PHPUnit bootstrap — loads plugin classes without WordPress.
 *
 * @package OmniLoop
 */

$vendor = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! is_readable( $vendor ) ) {
	fwrite( STDERR, "Run `composer install` before PHPUnit.\n" );
	exit( 1 );
}

require $vendor;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'OMNILOOP_VERSION' ) ) {
	define( 'OMNILOOP_VERSION', '2.0.1' );
}

if ( ! defined( 'OMNILOOP_DB_VERSION' ) ) {
	define( 'OMNILOOP_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'OMNILOOP_PATH' ) ) {
	define( 'OMNILOOP_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key
	 */
	function sanitize_key( $key ): string {
		$key = strtolower( (string) $key );

		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str
	 */
	function sanitize_text_field( $str ): string {
		$str = wp_strip_all_tags( (string) $str );
		$str = preg_replace( '/[\r\n\t]+/', ' ', $str ) ?? $str;

		return trim( $str );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $string ): string {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string ) ?? $string;

		return trim( strip_tags( $string ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
