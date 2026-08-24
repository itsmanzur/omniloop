<?php
/**
 * WPML / Polylang language for labels and index queries.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class I18n {

	public static function current_language(): string {
		if ( defined( 'ICL_LANGUAGE_CODE' ) && is_string( ICL_LANGUAGE_CODE ) && '' !== ICL_LANGUAGE_CODE ) {
			return sanitize_key( ICL_LANGUAGE_CODE );
		}
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			if ( is_string( $lang ) && '' !== $lang ) {
				return sanitize_key( $lang );
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			$lang = apply_filters( 'wpml_current_language', null );
			if ( is_string( $lang ) && '' !== $lang ) {
				return sanitize_key( $lang );
			}
		}

		return '';
	}

	public static function facet_label( string $key ): string {
		$label = WooFacets::label( $key );

		return (string) apply_filters( 'omniloop_facet_label', $label, $key, self::current_language() );
	}

	public static function value_label( string $key, string $value ): string {
		$label = WooFacets::value_label( $key, $value );
		if ( taxonomy_exists( $key ) && function_exists( 'apply_filters' ) ) {
			$term = get_term_by( 'slug', $value, $key );
			if ( $term instanceof \WP_Term ) {
				$translated_id = (int) apply_filters( 'wpml_object_id', $term->term_id, $key, true );
				if ( $translated_id > 0 && $translated_id !== (int) $term->term_id ) {
					$translated = get_term( $translated_id, $key );
					if ( $translated instanceof \WP_Term && '' !== $translated->name ) {
						$label = $translated->name;
					}
				}
			}
		}

		return (string) apply_filters( 'omniloop_facet_value_label', $label, $key, $value, self::current_language() );
	}

	/**
	 * Restrict posts to the current WPML / Polylang language.
	 *
	 * @param mixed[] $params
	 */
	public static function append_post_language( string &$sql, array &$params, string $post_type ): void {
		$lang = self::current_language();
		if ( '' === $lang ) {
			return;
		}

		global $wpdb;

		if ( defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'ICL_LANGUAGE_CODE' ) ) {
			$table        = $wpdb->prefix . 'icl_translations';
			$element_type = 'post_' . sanitize_key( $post_type );
			$sql         .= " AND p.ID IN (SELECT element_id FROM {$table} WHERE element_type = %s AND language_code = %s)";
			$params[]     = $element_type;
			$params[]     = $lang;

			return;
		}

		if ( function_exists( 'pll_current_language' ) && taxonomy_exists( 'language' ) && isset( $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms ) ) {
			$sql     .= " AND p.ID IN (
				SELECT tr.object_id FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tt.taxonomy = 'language' AND t.slug = %s
			)";
			$params[] = $lang;
		}
	}
}
