<?php
/**
 * Minimal WP_Query stand-in for NativeLoop unit tests.
 *
 * @package OmniLoop
 */

if ( class_exists( 'WP_Query' ) ) {
	return;
}

class WP_Query {

	/** @var bool */
	public $is_home = false;

	/** @var bool */
	public $is_post_type_archive = false;

	/** @var bool */
	public $is_singular = false;

	/** @var bool */
	private $is_main = true;

	/** @var array<string,mixed> */
	private $vars = [];

	/**
	 * @param array<string,mixed>|string $query
	 */
	public function __construct( $query = '' ) {
		if ( is_array( $query ) ) {
			$this->vars = $query;
		}
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( $query_var, $default = '' ) {
		if ( array_key_exists( (string) $query_var, $this->vars ) ) {
			return $this->vars[ (string) $query_var ];
		}

		return $default;
	}

	/**
	 * @param mixed $value
	 */
	public function set( $query_var, $value ): void {
		$this->vars[ (string) $query_var ] = $value;
	}

	public function is_main_query(): bool {
		return $this->is_main;
	}

	public function is_singular(): bool {
		return $this->is_singular;
	}

	public function is_home(): bool {
		return $this->is_home;
	}

	/**
	 * @param string|string[] $post_types
	 */
	public function is_post_type_archive( $post_types = '' ): bool {
		if ( empty( $post_types ) || ! $this->is_post_type_archive ) {
			return (bool) $this->is_post_type_archive;
		}

		$post_type = $this->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		return in_array( $post_type, (array) $post_types, true );
	}

	public function set_is_main_query( bool $is_main ): void {
		$this->is_main = $is_main;
	}
}
