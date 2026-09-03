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

$wp_query_stub = __DIR__ . '/Support/WpQuery.php';
if ( is_readable( $wp_query_stub ) ) {
	require_once $wp_query_stub;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'OMNILOOP_VERSION' ) ) {
	define( 'OMNILOOP_VERSION', '1.0.0' );
}

if ( ! defined( 'OMNILOOP_DB_VERSION' ) ) {
	define( 'OMNILOOP_DB_VERSION', '1.1.0' );
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

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * @param mixed $title
	 */
	function sanitize_title( $title ): string {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9_\-]+/', '-', $title ) ?? '';

		return trim( $title, '-' );
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

if ( ! function_exists( '__' ) ) {
	/**
	 * @param mixed $text
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );

		return (string) $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;

		/**
		 * @param mixed $code
		 * @param mixed $message
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			unset( $data );
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param mixed $url
	 */
	function esc_url_raw( $url ): string {
		$url = trim( (string) $url );
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return $url;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param mixed $url
	 */
	function esc_url( $url ): string {
		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text
	 */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
