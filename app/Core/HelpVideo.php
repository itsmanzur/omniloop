<?php
/**
 * Help Guide intro video (YouTube, Vimeo, or uploaded file).
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HelpVideo {

	/**
	 * Saved URL from settings, before the display filter.
	 */
	public static function stored_url(): string {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = '';
		if ( is_array( $settings ) && isset( $settings['help']['intro_video'] ) ) {
			$raw = (string) $settings['help']['intro_video'];
		}

		return self::sanitize_url( $raw );
	}

	/**
	 * URL shown in the Help Guide. Empty hides the player.
	 *
	 * Filter: omniloop_help_intro_video
	 */
	public static function url(): string {
		$url = self::stored_url();
		if ( function_exists( 'apply_filters' ) ) {
			$url = (string) apply_filters( 'omniloop_help_intro_video', $url );
		}

		return self::sanitize_url( $url );
	}

	/**
	 * @return array{type:string,src:string}|array{}
	 */
	public static function parse( string $url ): array {
		$url = trim( $url );
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return [];
		}

		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return [];
		}

		$host  = strtolower( (string) $parts['host'] );
		$host  = preg_replace( '/^www\./', '', $host ) ?? $host;
		$path  = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
		$query = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
		}

		if ( in_array( $host, [ 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be' ], true ) ) {
			$id = '';
			if ( 'youtu.be' === $host ) {
				$segments = explode( '/', $path );
				$id       = $segments[0] ?? '';
			} elseif ( isset( $query['v'] ) ) {
				$id = (string) $query['v'];
			} elseif ( preg_match( '#^(?:embed|shorts|live)/([^/]+)#', $path, $match ) ) {
				$id = $match[1];
			}

			$id = self::youtube_id( $id );
			if ( '' === $id ) {
				return [];
			}

			return [
				'type' => 'youtube',
				'src'  => 'https://www.youtube-nocookie.com/embed/' . $id,
			];
		}

		if ( in_array( $host, [ 'vimeo.com', 'player.vimeo.com' ], true ) ) {
			if ( preg_match( '#(?:video/)?(\d+)#', $path, $match ) ) {
				return [
					'type' => 'vimeo',
					'src'  => 'https://player.vimeo.com/video/' . $match[1],
				];
			}

			return [];
		}

		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, [ 'mp4', 'webm', 'ogg' ], true ) ) {
			return [
				'type' => 'file',
				'src'  => $url,
			];
		}

		return [];
	}

	/**
	 * @param mixed $raw
	 */
	public static function sanitize_url( $raw ): string {
		$url = trim( (string) $raw );
		if ( function_exists( 'esc_url_raw' ) ) {
			$url = esc_url_raw( $url );
		}
		if ( '' === $url || ! self::parse( $url ) ) {
			return '';
		}

		return $url;
	}

	public static function markup( string $title = '' ): string {
		$parsed = self::parse( self::url() );
		if ( ! $parsed ) {
			return '';
		}

		$title = '' !== $title ? $title : __( 'OmniLoop intro video', 'omniloop' );
		$src   = $parsed['src'];

		if ( 'file' === $parsed['type'] ) {
			return sprintf(
				'<div class="omniloop-docs-video"><video class="omniloop-docs-video__media" controls preload="metadata" src="%s"></video></div>',
				esc_url( $src )
			);
		}

		return sprintf(
			'<div class="omniloop-docs-video"><iframe class="omniloop-docs-video__media" src="%s" title="%s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
			esc_url( $src ),
			esc_attr( $title )
		);
	}

	private static function youtube_id( string $id ): string {
		$id = trim( $id );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
			return '';
		}

		return $id;
	}
}
