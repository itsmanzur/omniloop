<?php
/**
 * Convert price facet amounts between store currency and the shopper currency.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MultiCurrency {

	/**
	 * Shopper-facing amount from the base (indexed) price.
	 */
	public static function from_base( float $amount ): float {
		if ( function_exists( 'wmc_get_price' ) ) {
			return (float) wmc_get_price( $amount );
		}
		if ( function_exists( 'apply_filters' ) ) {
			$converted = apply_filters( 'wcml_raw_price_amount', $amount );
			if ( is_numeric( $converted ) && (float) $converted !== $amount ) {
				return (float) $converted;
			}
			$converted = apply_filters( 'omniloop_price_from_base', $amount );

			return (float) $converted;
		}

		return $amount;
	}

	/**
	 * Indexed / query amount from a shopper-facing price.
	 */
	public static function to_base( float $amount ): float {
		if ( function_exists( 'wmc_revert_price' ) ) {
			return (float) wmc_revert_price( $amount );
		}
		$from = self::from_base( 1.0 );
		if ( $from > 0 && abs( $from - 1.0 ) > 0.00001 ) {
			return $amount / $from;
		}
		if ( function_exists( 'apply_filters' ) ) {
			return (float) apply_filters( 'omniloop_price_to_base', $amount );
		}

		return $amount;
	}

	public static function symbol(): string {
		if ( function_exists( 'wmc_get_list_currencies' ) && function_exists( 'get_woocommerce_currency' ) ) {
			$code = (string) get_woocommerce_currency();
			$list = wmc_get_list_currencies();
			if ( is_array( $list ) && isset( $list[ $code ]['symbol'] ) ) {
				$symbol = html_entity_decode( (string) $list[ $code ]['symbol'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( '' !== trim( $symbol ) ) {
					return trim( $symbol );
				}
			}
		}

		return WooFacets::currency_symbol();
	}

	/**
	 * @return array{min:float,max:float}
	 */
	public static function bounds_for_display( float $min, float $max ): array {
		$min = self::from_base( $min );
		$max = self::from_base( $max );
		if ( $min > $max ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}

		return [
			'min' => $min,
			'max' => $max,
		];
	}

	/**
	 * @param array{0:float,1:float} $range
	 * @return array{0:float,1:float}
	 */
	public static function range_to_base( array $range ): array {
		$min = self::to_base( (float) $range[0] );
		$max = self::to_base( (float) $range[1] );
		if ( $min > $max ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}

		return [ $min, $max ];
	}
}
