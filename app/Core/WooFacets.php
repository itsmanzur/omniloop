<?php
/**
 * WooCommerce facet keys, labels, and extra index rows.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WooFacets {

	private const SKIP_TAXONOMIES = [
		'product_type',
		'product_visibility',
		'product_shipping_class',
	];

	public static function is_skip_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, self::SKIP_TAXONOMIES, true );
	}

	public static function is_attribute_taxonomy( string $taxonomy ): bool {
		return 0 === strpos( $taxonomy, 'pa_' );
	}

	/**
	 * Extra facet rows for a product: on_sale and featured.
	 *
	 * @return list<array{0:string,1:string,2:float|null}>
	 */
	public static function extra_index_values( \WP_Post $post ): array {
		if ( 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return [];
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return [];
		}

		$out = [];
		if ( method_exists( $product, 'is_on_sale' ) && $product->is_on_sale() ) {
			$out[] = [ 'on_sale', 'yes', 1.0 ];
		}
		if ( method_exists( $product, 'is_featured' ) && $product->is_featured() ) {
			$out[] = [ 'featured', 'yes', 1.0 ];
		}

		$rating = 0.0;
		if ( method_exists( $product, 'get_average_rating' ) ) {
			$rating = (float) $product->get_average_rating();
		}
		$stars = self::rounded_stars( $rating );
		if ( $stars > 0 ) {
			$out[] = [ 'rating', (string) $stars, $rating ];
		}

		$sales = 0.0;
		if ( method_exists( $product, 'get_total_sales' ) ) {
			$sales = (float) $product->get_total_sales();
		}
		$out[] = [ 'popularity', (string) (int) $sales, $sales ];

		return $out;
	}

	/**
	 * WooCommerce visibility terms use rounded average rating (half up).
	 */
	public static function rounded_stars( float $rating ): int {
		if ( $rating <= 0 ) {
			return 0;
		}

		$stars = (int) round( $rating, 0, PHP_ROUND_HALF_UP );
		if ( $stars > 5 ) {
			return 5;
		}
		if ( $stars < 1 ) {
			return 1;
		}

		return $stars;
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	public static function facet_keys( string $post_type, array $keys ): array {
		if ( 'product' !== $post_type ) {
			return array_values(
				array_filter(
					$keys,
					static function ( string $key ): bool {
						return '_stock_status' !== $key && 'on_sale' !== $key && 'featured' !== $key && 'rating' !== $key;
					}
				)
			);
		}

		$keys[] = '_stock_status';
		$keys[] = 'on_sale';
		$keys[] = 'featured';
		$keys[] = 'rating';

		$keys = array_values( array_unique( $keys ) );
		usort(
			$keys,
			static function ( string $a, string $b ): int {
				$rank = static function ( string $key ): int {
					if ( 'product_cat' === $key ) {
						return 10;
					}
					if ( 'product_brand' === $key ) {
						return 20;
					}
					if ( 0 === strpos( $key, 'pa_' ) ) {
						return 40;
					}
					if ( 'product_tag' === $key ) {
						return 70;
					}
					if ( 'rating' === $key ) {
						return 75;
					}
					if ( '_stock_status' === $key ) {
						return 80;
					}
					if ( 'on_sale' === $key ) {
						return 90;
					}
					if ( 'featured' === $key ) {
						return 95;
					}

					return 50;
				};

				$cmp = $rank( $a ) <=> $rank( $b );
				return 0 === $cmp ? strcmp( $a, $b ) : $cmp;
			}
		);

		return $keys;
	}

	public static function label( string $key ): string {
		if ( '_stock_status' === $key ) {
			return __( 'Stock', 'omniloop' );
		}
		if ( 'on_sale' === $key ) {
			return __( 'On sale', 'omniloop' );
		}
		if ( 'featured' === $key ) {
			return __( 'Featured', 'omniloop' );
		}
		if ( 'rating' === $key ) {
			return __( 'Rating', 'omniloop' );
		}
		if ( self::is_attribute_taxonomy( $key ) && function_exists( 'wc_attribute_label' ) ) {
			return (string) wc_attribute_label( $key );
		}

		$tax = get_taxonomy( $key );
		if ( $tax ) {
			return (string) $tax->labels->name;
		}

		if ( AcfFacets::is_acf_key( $key ) ) {
			return AcfFacets::label( $key );
		}

		return $key;
	}

	public static function value_label( string $key, string $value ): string {
		if ( taxonomy_exists( $key ) ) {
			$term = get_term_by( 'slug', $value, $key );
			if ( $term instanceof \WP_Term ) {
				return $term->name;
			}
		}

		if ( AcfFacets::is_acf_key( $key ) ) {
			return AcfFacets::value_label( $key, $value );
		}

		if ( 'instock' === $value ) {
			return __( 'In stock', 'omniloop' );
		}
		if ( 'outofstock' === $value ) {
			return __( 'Out of stock', 'omniloop' );
		}
		if ( 'onbackorder' === $value ) {
			return __( 'On backorder', 'omniloop' );
		}
		if ( 'yes' === $value && ( 'on_sale' === $key || 'featured' === $key ) ) {
			return 'on_sale' === $key ? __( 'On sale', 'omniloop' ) : __( 'Featured', 'omniloop' );
		}
		if ( 'rating' === $key ) {
			$stars = (int) $value;
			if ( 1 === $stars ) {
				return __( '1 star', 'omniloop' );
			}
			if ( $stars >= 2 && $stars <= 5 ) {
				return sprintf(
					/* translators: %d: star rating */
					__( '%d stars', 'omniloop' ),
					$stars
				);
			}
		}

		return $value;
	}

	public static function currency_symbol(): string {
		if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
			return '';
		}

		$symbol = html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( $symbol );
	}

	/**
	 * Auto UI for a facet: color dots, image tiles, size pills, or empty for checkboxes.
	 */
	public static function swatch_mode( string $key ): string {
		$mode = '';
		if ( 'rating' === $key ) {
			$mode = 'rating';
		} elseif ( self::is_attribute_taxonomy( $key ) ) {
			$type = self::attribute_type( $key );
			$hay  = strtolower( substr( $key, 3 ) . ' ' . self::label( $key ) );

			if ( (bool) preg_match( '/\b(colou?rs?|farbe|couleur|kleur)\b/', $hay ) ) {
				$mode = 'color';
			} elseif ( (bool) preg_match( '/\b(sizes?|taille|groesse|grose|gr[oö]sse)\b/u', $hay ) ) {
				$mode = 'size';
			} elseif ( (bool) preg_match( '/\b(images?|photos?|patterns?|prints?|brands?|logos?)\b/', $hay ) ) {
				$mode = 'image';
			} elseif ( 'color' === $type || 'wc-visual' === $type ) {
				$mode = 'color';
			} elseif ( 'image' === $type || 'image_swatch' === $type ) {
				$mode = 'image';
			}
		}

		$filtered = apply_filters( 'omniloop_swatch_mode', $mode, $key );

		return in_array( $filtered, [ 'color', 'size', 'rating', 'image' ], true ) ? $filtered : '';
	}

	/**
	 * Resolve UI mode, upgrading plain attributes that have term images.
	 *
	 * @param string[] $values
	 */
	public static function resolve_swatch_mode( string $key, array $values = [] ): string {
		$mode = self::swatch_mode( $key );
		if ( '' !== $mode ) {
			return $mode;
		}
		foreach ( $values as $slug ) {
			if ( '' !== self::swatch_image( $key, (string) $slug ) ) {
				return 'image';
			}
		}

		return '';
	}

	/**
	 * @return array{mode:string,colors?:array<string,string>,images?:array<string,string>}|null
	 */
	public static function facet_ui( string $key, array $values ): ?array {
		$mode   = self::resolve_swatch_mode( $key, $values );
		$images = [];
		foreach ( $values as $slug ) {
			$url = self::swatch_image( $key, (string) $slug );
			if ( '' !== $url ) {
				$images[ (string) $slug ] = $url;
			}
		}

		if ( '' === $mode ) {
			return null;
		}

		$ui = [ 'mode' => $mode ];
		if ( 'color' === $mode ) {
			$colors = [];
			foreach ( $values as $slug ) {
				$hex = self::color_hex( $key, (string) $slug );
				if ( '' !== $hex ) {
					$colors[ (string) $slug ] = $hex;
				}
			}
			$ui['colors'] = $colors;
		}
		if ( ( 'color' === $mode || 'image' === $mode ) && $images ) {
			$ui['images'] = $images;
		}

		return $ui;
	}

	/**
	 * Term image URL for attribute swatches (Variation Swatches plugins + thumbnail).
	 */
	public static function swatch_image( string $taxonomy, string $slug ): string {
		$url  = '';
		$term = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $slug, $taxonomy ) : false;
		if ( $term instanceof \WP_Term ) {
			$meta_keys = [
				'product_attribute_image',
				'image_id',
				'thumbnail_id',
				'cfvsw_image',
				'cfvsw_image_id',
				'swatch_image',
				'swatch_image_id',
				'rtwpvs_image_id',
				'woo_variation_swatches_image',
				'product_' . $taxonomy,
			];
			foreach ( $meta_keys as $meta_key ) {
				$url = self::url_from_meta( get_term_meta( $term->term_id, $meta_key, true ) );
				if ( '' !== $url ) {
					break;
				}
			}
			if ( '' === $url && function_exists( 'get_term_meta' ) ) {
				$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $thumb > 0 ) {
					$src = wp_get_attachment_image_url( $thumb, 'thumbnail' );
					if ( is_string( $src ) && '' !== $src ) {
						$url = $src;
					}
				}
			}
		}

		$filtered = apply_filters( 'omniloop_swatch_image', $url, $taxonomy, $slug, $term instanceof \WP_Term ? $term : null );

		return self::sanitize_image_url( $filtered );
	}

	/**
	 * @param mixed $raw
	 */
	private static function url_from_meta( $raw ): string {
		if ( is_array( $raw ) ) {
			if ( isset( $raw['id'] ) ) {
				$raw = $raw['id'];
			} elseif ( isset( $raw['url'] ) ) {
				$raw = $raw['url'];
			} elseif ( isset( $raw['ID'] ) ) {
				$raw = $raw['ID'];
			} else {
				$raw = reset( $raw );
			}
		}
		if ( is_numeric( $raw ) ) {
			$id = (int) $raw;
			if ( $id <= 0 ) {
				return '';
			}
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );

			return is_string( $src ) ? self::sanitize_image_url( $src ) : '';
		}
		if ( is_string( $raw ) ) {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return '';
			}
			if ( ctype_digit( $raw ) ) {
				return self::url_from_meta( (int) $raw );
			}

			return self::sanitize_image_url( $raw );
		}

		return '';
	}

	/**
	 * @param mixed $raw
	 */
	private static function sanitize_image_url( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$url = esc_url_raw( trim( $raw ) );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) && 0 !== strpos( $url, '/' ) ) {
			return '';
		}

		return $url;
	}

	public static function color_hex( string $taxonomy, string $slug ): string {
		$hex  = '';
		$term = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $slug, $taxonomy ) : false;
		if ( $term instanceof \WP_Term ) {
			foreach ( [ 'product_attribute_color', 'color', 'cfvsw_color', 'swatch_color', 'product_' . $taxonomy ] as $meta_key ) {
				$hex = self::sanitize_hex( get_term_meta( $term->term_id, $meta_key, true ) );
				if ( '' !== $hex ) {
					break;
				}
			}
			if ( '' === $hex ) {
				$hex = self::hex_from_name( $term->name );
			}
		}
		if ( '' === $hex ) {
			$hex = self::hex_from_name( $slug );
		}

		$filtered = apply_filters( 'omniloop_swatch_color', $hex, $taxonomy, $slug, $term instanceof \WP_Term ? $term : null );

		return self::sanitize_hex( $filtered );
	}

	private static function attribute_type( string $taxonomy ): string {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return '';
		}

		$name = substr( $taxonomy, 3 );
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			if ( isset( $tax->attribute_name ) && (string) $tax->attribute_name === $name ) {
				return isset( $tax->attribute_type ) ? (string) $tax->attribute_type : '';
			}
		}

		return '';
	}

	/**
	 * @param mixed $raw
	 */
	private static function sanitize_hex( $raw ): string {
		if ( is_array( $raw ) ) {
			$raw = $raw['color'] ?? $raw['hex'] ?? '';
		}
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( '{' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = (string) ( $decoded['color'] ?? $decoded['hex'] ?? '' );
			}
		}
		if ( function_exists( 'sanitize_hex_color' ) ) {
			$hex = sanitize_hex_color( $raw );
			if ( is_string( $hex ) && '' !== $hex ) {
				return strtolower( $hex );
			}
		}
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw, $m ) ) {
			$digits = strtolower( $m[1] );
			if ( 3 === strlen( $digits ) ) {
				$digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
			}

			return '#' . $digits;
		}

		return '';
	}

	private static function hex_from_name( string $name ): string {
		$norm = strtolower( trim( $name ) );
		$norm = str_replace( [ '_', ' ' ], '-', $norm );
		$norm = preg_replace( '/[^a-z0-9-]/', '', $norm );
		if ( ! is_string( $norm ) || '' === $norm ) {
			return '';
		}

		$map = [
			'black'        => '#111827',
			'white'        => '#ffffff',
			'off-white'    => '#f8fafc',
			'offwhite'     => '#f8fafc',
			'ivory'        => '#fffff0',
			'cream'        => '#f5f0e6',
			'beige'        => '#d6c4a8',
			'gray'         => '#6b7280',
			'grey'         => '#6b7280',
			'silver'       => '#c0c0c0',
			'charcoal'     => '#374151',
			'slate'        => '#64748b',
			'red'          => '#dc2626',
			'maroon'       => '#7f1d1d',
			'burgundy'     => '#7f1d1d',
			'wine'         => '#881337',
			'pink'         => '#ec4899',
			'hot-pink'     => '#db2777',
			'rose'         => '#f43f5e',
			'salmon'       => '#fb7185',
			'coral'        => '#fb7185',
			'orange'       => '#ea580c',
			'peach'        => '#fdba74',
			'gold'         => '#d97706',
			'mustard'      => '#ca8a04',
			'yellow'       => '#eab308',
			'lime'         => '#84cc16',
			'green'        => '#16a34a',
			'olive'        => '#4d7c0f',
			'mint'         => '#6ee7b7',
			'teal'         => '#0d9488',
			'turquoise'    => '#14b8a6',
			'cyan'         => '#06b6d4',
			'aqua'         => '#22d3ee',
			'blue'         => '#2563eb',
			'navy'         => '#1e3a8a',
			'navy-blue'    => '#1e3a8a',
			'sky'          => '#38bdf8',
			'sky-blue'     => '#38bdf8',
			'royal-blue'   => '#1d4ed8',
			'indigo'       => '#4f46e5',
			'purple'       => '#7c3aed',
			'violet'       => '#8b5cf6',
			'lilac'        => '#c4b5fd',
			'lavender'     => '#a78bfa',
			'magenta'      => '#d946ef',
			'brown'        => '#92400e',
			'tan'          => '#d6a36a',
			'khaki'        => '#c4b59a',
			'chocolate'    => '#78350f',
			'bronze'       => '#b45309',
			'copper'       => '#c2410c',
			'rust'         => '#c2410c',
		];

		$candidates = [ $norm, str_replace( '-', '', $norm ) ];
		$parts      = explode( '-', $norm );
		if ( count( $parts ) > 1 ) {
			$candidates[] = end( $parts );
			$candidates[] = $parts[0];
		}

		foreach ( $candidates as $key ) {
			if ( isset( $map[ $key ] ) ) {
				return $map[ $key ];
			}
		}

		return '';
	}
}
