<?php
/**
 * Pin products, push out-of-stock last, and decorate cards with promo / urgency badges.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Merchandising {

	/** @var array<string,array<int,string>> */
	private static $promo_maps = [];

	/**
	 * @return array{pin_ids:int[],oos_last:bool,urgency_threshold:int,promotions:list<array{id:string,badge:string,post_type:string,facets:array<string,mixed>}>}
	 */
	public static function settings(): array {
		$settings = get_option( 'omniloop_settings', [] );
		$raw      = is_array( $settings ) && isset( $settings['merchandising'] ) ? $settings['merchandising'] : [];

		return self::sanitize( $raw );
	}

	public static function is_enabled( $flag ): bool {
		if ( is_bool( $flag ) ) {
			return $flag;
		}

		$flag = strtolower( trim( (string) $flag ) );

		return ! in_array( $flag, [ 'off', '0', 'false', 'no' ], true );
	}

	/**
	 * @param mixed $raw
	 * @return int[]
	 */
	public static function parse_ids( $raw ): array {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$parts = preg_split( '/[\s,]+/', (string) $raw );
			if ( ! is_array( $parts ) ) {
				return [];
			}
		}

		$out = [];
		foreach ( $parts as $part ) {
			$id = (int) $part;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Pinned matching IDs first, then the rest (optionally with out-of-stock last).
	 *
	 * @param int[] $ids
	 * @param mixed $extra_pins
	 * @return int[]
	 */
	public static function reorder( string $post_type, array $ids, $extra_pins = [] ): array {
		if ( [] === $ids ) {
			return [];
		}

		$ids    = array_values( array_map( 'intval', $ids ) );
		$lookup = array_fill_keys( $ids, true );
		$conf   = self::settings();
		$pins   = array_values( array_unique( array_merge( $conf['pin_ids'], self::parse_ids( $extra_pins ) ) ) );

		$pinned = [];
		foreach ( $pins as $id ) {
			if ( isset( $lookup[ $id ] ) ) {
				$pinned[] = $id;
				unset( $lookup[ $id ] );
			}
		}

		$rest = [];
		foreach ( $ids as $id ) {
			if ( isset( $lookup[ $id ] ) ) {
				$rest[] = $id;
			}
		}

		if ( $conf['oos_last'] ) {
			$oos     = self::oos_set( $post_type, $rest );
			$in_stock = [];
			$out      = [];
			foreach ( $rest as $id ) {
				if ( isset( $oos[ $id ] ) ) {
					$out[] = $id;
				} else {
					$in_stock[] = $id;
				}
			}
			$rest = array_merge( $in_stock, $out );
		}

		return array_merge( $pinned, $rest );
	}

	/**
	 * @return array{promotedBadge:string,stockUrgency:string}
	 */
	public static function decorate( int $post_id, string $post_type, $product = null ): array {
		$map = self::promo_map( $post_type );

		return [
			'promotedBadge' => $map[ $post_id ] ?? '',
			'stockUrgency'  => self::urgency_label( $product ),
		];
	}

	/**
	 * @param mixed $raw
	 * @return array{pin_ids:int[],oos_last:bool,urgency_threshold:int,promotions:list<array{id:string,badge:string,post_type:string,facets:array<string,mixed>}>}
	 */
	public static function sanitize( $raw ): array {
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		$threshold = isset( $raw['urgency_threshold'] ) ? (int) $raw['urgency_threshold'] : 5;
		if ( $threshold < 0 ) {
			$threshold = 0;
		}
		if ( $threshold > 99 ) {
			$threshold = 99;
		}

		$oos_last = true;
		if ( isset( $raw['oos_last'] ) ) {
			$oos_last = ! in_array( strtolower( (string) $raw['oos_last'] ), [ 'off', '0', 'false', 'no' ], true );
		}

		return [
			'pin_ids'            => self::parse_ids( $raw['pin_ids'] ?? [] ),
			'oos_last'           => $oos_last,
			'urgency_threshold'  => $threshold,
			'promotions'         => self::sanitize_promotions( $raw['promotions'] ?? [] ),
		];
	}

	/**
	 * @param mixed $raw
	 * @return list<array{id:string,badge:string,post_type:string,facets:array<string,mixed>}>
	 */
	private static function sanitize_promotions( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out  = [];
		$seen = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$badge = sanitize_text_field( (string) ( $row['badge'] ?? '' ) );
			if ( '' === $badge ) {
				continue;
			}

			$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = sanitize_key( $badge );
			}
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$post_type = sanitize_key( (string) ( $row['post_type'] ?? 'product' ) );
			if ( '' === $post_type || ( function_exists( 'post_type_exists' ) && ! post_type_exists( $post_type ) ) ) {
				$post_type = 'product';
			}

			$out[]        = [
				'id'        => $id,
				'badge'     => $badge,
				'post_type' => $post_type,
				'facets'    => self::sanitize_facets( $row['facets'] ?? [] ),
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
			if ( '' === $key || 'price_range' === $key ) {
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

	/**
	 * @return array<int,string>
	 */
	private static function promo_map( string $post_type ): array {
		if ( isset( self::$promo_maps[ $post_type ] ) ) {
			return self::$promo_maps[ $post_type ];
		}

		$map = [];
		foreach ( self::settings()['promotions'] as $rule ) {
			if ( $rule['post_type'] !== $post_type || empty( $rule['facets'] ) ) {
				continue;
			}
			foreach ( self::ids_for_facets( $post_type, $rule['facets'] ) as $id ) {
				if ( ! isset( $map[ $id ] ) ) {
					$map[ $id ] = $rule['badge'];
				}
			}
		}

		self::$promo_maps[ $post_type ] = $map;

		return $map;
	}

	/**
	 * @param array<string,mixed> $facets
	 * @return int[]
	 */
	private static function ids_for_facets( string $post_type, array $facets ): array {
		global $wpdb;

		$table = Installer::table_name();
		$ids   = null;

		foreach ( $facets as $key => $values ) {
			$key    = Indexer::sanitize_facet_key( (string) $key );
			$values = is_array( $values ) ? $values : [ $values ];
			$clean  = [];
			foreach ( $values as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value ) {
					$clean[] = $value;
				}
			}
			if ( '' === $key || ! $clean ) {
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $clean ), '%s' ) );
			$sql          = "SELECT DISTINCT post_id FROM {$table} WHERE post_type = %s AND facet_key = %s AND facet_value IN ({$placeholders})";
			$params       = array_merge( [ $post_type, $key ], $clean );
			$prepared     = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $prepared ) ) {
				return [];
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$col = $wpdb->get_col( $prepared );
			$set = array_map( 'intval', is_array( $col ) ? $col : [] );
			$ids = null === $ids ? $set : array_values( array_intersect( $ids, $set ) );
			if ( ! $ids ) {
				return [];
			}
		}

		return $ids ?? [];
	}

	/**
	 * @param int[] $ids
	 * @return array<int,true>
	 */
	private static function oos_set( string $post_type, array $ids ): array {
		if ( 'product' !== $post_type || ! $ids ) {
			return [];
		}

		global $wpdb;

		$table  = Installer::table_name();
		$out    = [];
		$chunks = array_chunk( array_map( 'intval', $ids ), 400 );

		foreach ( $chunks as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$sql          = "SELECT DISTINCT post_id FROM {$table} WHERE post_type = %s AND facet_key = %s AND facet_value = %s AND post_id IN ({$placeholders})";
			$params       = array_merge( [ $post_type, '_stock_status', 'outofstock' ], $chunk );
			$prepared     = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_string( $prepared ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$col = $wpdb->get_col( $prepared );
			if ( ! is_array( $col ) ) {
				continue;
			}
			foreach ( $col as $id ) {
				$out[ (int) $id ] = true;
			}
		}

		return $out;
	}

	/**
	 * @param object|null $product WooCommerce product.
	 */
	private static function urgency_label( $product ): string {
		$threshold = self::settings()['urgency_threshold'];
		if ( $threshold < 1 || ! is_object( $product ) ) {
			return '';
		}
		if ( ! method_exists( $product, 'managing_stock' ) || ! $product->managing_stock() ) {
			return '';
		}
		if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
			return '';
		}

		$qty = method_exists( $product, 'get_stock_quantity' ) ? (int) $product->get_stock_quantity() : 0;
		if ( $qty < 1 || $qty > $threshold ) {
			return '';
		}

		if ( 1 === $qty ) {
			return __( 'Only 1 left', 'omniloop' );
		}

		return sprintf(
			/* translators: %d: remaining stock quantity */
			__( 'Only %d left', 'omniloop' ),
			$qty
		);
	}
}
