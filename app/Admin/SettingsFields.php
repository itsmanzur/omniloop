<?php
/**
 * Settings form fragments: presets, merchandising pickers, facet rules.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\QueryBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsFields {

	/**
	 * @param array<string,string> $facet_choices
	 * @param list<\WP_Post_Type>  $types
	 * @param array<string,mixed>  $preset
	 */
	public static function preset_card( int $index, array $preset, array $types, array $facet_choices ): void {
		$sort_labels = QueryBuilder::sort_labels();
		$name        = 'omniloop_settings[filter_presets][' . $index . ']';
		$tpl         = 'omniloop_settings[filter_presets][__i__]';
		?>
		<div class="omniloop-admin-preset" data-repeat-item="preset">
			<div class="omniloop-admin-preset__toolbar">
				<span class="omniloop-admin-preset__badge"><?php esc_html_e( 'Preset', 'omniloop' ); ?></span>
				<button type="button" class="omniloop-admin-icon-btn" data-repeat-remove aria-label="<?php esc_attr_e( 'Remove preset', 'omniloop' ); ?>">&times;</button>
			</div>
			<div class="omniloop-admin-preset__grid">
				<label>
					<span><?php esc_html_e( 'Name', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[label]' ); ?>" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( (string) ( $preset['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Summer Sale', 'omniloop' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Link ID (optional)', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[id]' ); ?>" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( (string) ( $preset['id'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'summer-sale', 'omniloop' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Post type', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[post_type]' ); ?>" name="<?php echo esc_attr( $name . '[post_type]' ); ?>" class="omniloop-preset-type">
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( (string) ( $preset['post_type'] ?? '' ), $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Sort', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[sort_by]' ); ?>" name="<?php echo esc_attr( $name . '[sort_by]' ); ?>">
						<?php foreach ( $sort_labels as $sort_key => $sort_label ) : ?>
							<option value="<?php echo esc_attr( $sort_key ); ?>" <?php selected( (string) ( $preset['sort_by'] ?? 'date' ), $sort_key ); ?>><?php echo esc_html( $sort_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="omniloop-admin-preset__wide">
					<span><?php esc_html_e( 'Search text (optional)', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[search]' ); ?>" name="<?php echo esc_attr( $name . '[search]' ); ?>" value="<?php echo esc_attr( (string) ( $preset['search'] ?? '' ) ); ?>">
				</label>
			</div>
			<?php
			self::facet_builder(
				$name . '[facets]',
				$tpl . '[facets]',
				is_array( $preset['facets'] ?? null ) ? $preset['facets'] : [],
				$facet_choices,
				(string) ( $preset['post_type'] ?? 'product' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * @param array<string,string> $facet_choices
	 * @param list<\WP_Post_Type>  $types
	 * @param array<string,mixed>  $promo
	 */
	public static function promo_card( int $index, array $promo, array $types, array $facet_choices ): void {
		$name = 'omniloop_settings[merchandising][promotions][' . $index . ']';
		$tpl  = 'omniloop_settings[merchandising][promotions][__i__]';
		?>
		<div class="omniloop-admin-preset" data-repeat-item="promo">
			<div class="omniloop-admin-preset__toolbar">
				<span class="omniloop-admin-preset__badge"><?php esc_html_e( 'Promo', 'omniloop' ); ?></span>
				<button type="button" class="omniloop-admin-icon-btn" data-repeat-remove aria-label="<?php esc_attr_e( 'Remove promo', 'omniloop' ); ?>">&times;</button>
			</div>
			<div class="omniloop-admin-preset__grid">
				<label>
					<span><?php esc_html_e( 'Badge text', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[badge]' ); ?>" name="<?php echo esc_attr( $name . '[badge]' ); ?>" value="<?php echo esc_attr( (string) ( $promo['badge'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Staff pick', 'omniloop' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Rule ID (optional)', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[id]' ); ?>" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( (string) ( $promo['id'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'staff-pick', 'omniloop' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Post type', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[post_type]' ); ?>" name="<?php echo esc_attr( $name . '[post_type]' ); ?>" class="omniloop-preset-type">
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( (string) ( $promo['post_type'] ?? '' ), $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<?php
			self::facet_builder(
				$name . '[facets]',
				$tpl . '[facets]',
				is_array( $promo['facets'] ?? null ) ? $promo['facets'] : [],
				$facet_choices,
				(string) ( $promo['post_type'] ?? 'product' )
			);
			?>
		</div>
		<?php
	}

	/**
	 * @param array<string,string> $facet_choices
	 * @param array<string,mixed>  $rule
	 */
	public static function rule_card( int $index, array $rule, array $facet_choices ): void {
		$name        = 'omniloop_settings[facet_ui][rules][' . $index . ']';
		$tpl         = 'omniloop_settings[facet_ui][rules][__i__]';
		$rule_values = implode( ', ', is_array( $rule['source_values'] ?? null ) ? $rule['source_values'] : [] );
		?>
		<div class="omniloop-admin-preset" data-repeat-item="rule">
			<div class="omniloop-admin-preset__toolbar">
				<span class="omniloop-admin-preset__badge"><?php esc_html_e( 'Rule', 'omniloop' ); ?></span>
				<button type="button" class="omniloop-admin-icon-btn" data-repeat-remove aria-label="<?php esc_attr_e( 'Remove rule', 'omniloop' ); ?>">&times;</button>
			</div>
			<div class="omniloop-admin-preset__grid">
				<label>
					<span><?php esc_html_e( 'When facet', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[source_facet]' ); ?>" name="<?php echo esc_attr( $name . '[source_facet]' ); ?>">
						<option value=""><?php esc_html_e( '—', 'omniloop' ); ?></option>
						<?php foreach ( $facet_choices as $facet_key => $facet_label ) : ?>
							<option value="<?php echo esc_attr( $facet_key ); ?>" <?php selected( (string) ( $rule['source_facet'] ?? '' ), $facet_key ); ?>><?php echo esc_html( $facet_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Has values (optional)', 'omniloop' ); ?></span>
					<input type="text" data-name="<?php echo esc_attr( $tpl . '[source_values]' ); ?>" name="<?php echo esc_attr( $name . '[source_values]' ); ?>" value="<?php echo esc_attr( $rule_values ); ?>" placeholder="<?php esc_attr_e( 'red, navy — empty = any', 'omniloop' ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Then', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[action]' ); ?>" name="<?php echo esc_attr( $name . '[action]' ); ?>">
						<option value="show" <?php selected( (string) ( $rule['action'] ?? 'show' ), 'show' ); ?>><?php esc_html_e( 'Show', 'omniloop' ); ?></option>
						<option value="hide" <?php selected( (string) ( $rule['action'] ?? '' ), 'hide' ); ?>><?php esc_html_e( 'Hide', 'omniloop' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Target facet', 'omniloop' ); ?></span>
					<select data-name="<?php echo esc_attr( $tpl . '[target_facet]' ); ?>" name="<?php echo esc_attr( $name . '[target_facet]' ); ?>">
						<option value=""><?php esc_html_e( '—', 'omniloop' ); ?></option>
						<?php foreach ( $facet_choices as $facet_key => $facet_label ) : ?>
							<option value="<?php echo esc_attr( $facet_key ); ?>" <?php selected( (string) ( $rule['target_facet'] ?? '' ), $facet_key ); ?>><?php echo esc_html( $facet_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<input type="hidden" data-name="<?php echo esc_attr( $tpl . '[id]' ); ?>" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * @param int[]                        $pin_ids
	 * @param list<array{id:int,title:string,type:string}> $pins
	 */
	public static function pin_picker( array $pin_ids, array $pins, string $post_type ): void {
		$by_id = [];
		foreach ( $pins as $pin ) {
			$by_id[ (int) $pin['id'] ] = $pin;
		}
		?>
		<div class="omniloop-pin-picker" data-post-type="<?php echo esc_attr( $post_type ); ?>">
			<div class="omniloop-pin-picker__chips" data-pin-chips>
				<?php foreach ( $pin_ids as $id ) : ?>
					<?php
					$title = isset( $by_id[ $id ] ) ? (string) $by_id[ $id ]['title'] : ( '#' . $id );
					?>
					<span class="omniloop-pin-chip" data-id="<?php echo esc_attr( (string) $id ); ?>">
						<input type="hidden" name="omniloop_settings[merchandising][pin_ids][]" value="<?php echo esc_attr( (string) $id ); ?>">
						<span><?php echo esc_html( $title ); ?></span>
						<button type="button" class="omniloop-pin-chip__remove" aria-label="<?php esc_attr_e( 'Unpin', 'omniloop' ); ?>">&times;</button>
					</span>
				<?php endforeach; ?>
			</div>
			<div class="omniloop-pin-picker__search-wrap">
				<input type="search" class="omniloop-pin-picker__search" placeholder="<?php esc_attr_e( 'Search by title to pin…', 'omniloop' ); ?>" autocomplete="off">
				<div class="omniloop-pin-picker__results" hidden></div>
			</div>
			<p class="omniloop-admin-hint"><?php esc_html_e( 'Pinned items stay at the top only while they still match the current filters.', 'omniloop' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed>  $facets
	 * @param array<string,string> $facet_choices
	 */
	public static function facet_builder( string $name, string $tpl, array $facets, array $facet_choices, string $post_type ): void {
		$choices = self::builder_choices( $facet_choices );
		$rows    = [];
		foreach ( $facets as $key => $values ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			$rows[] = [
				'key'    => $key,
				'values' => $values,
			];
		}
		if ( ! $rows ) {
			$rows[] = [
				'key'    => '',
				'values' => [],
			];
		}
		?>
		<div class="omniloop-facet-builder" data-facet-builder data-name="<?php echo esc_attr( $name ); ?>" data-name-tpl="<?php echo esc_attr( $tpl ); ?>" data-post-type="<?php echo esc_attr( $post_type ); ?>">
			<p class="omniloop-admin-subhead"><?php esc_html_e( 'Filters in this set', 'omniloop' ); ?></p>
			<div data-facet-rows>
				<?php foreach ( $rows as $row ) : ?>
					<?php self::facet_row( $name, $choices, (string) $row['key'], $row['values'] ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost omniloop-admin-btn--small" data-facet-add><?php esc_html_e( 'Add filter', 'omniloop' ); ?></button>
		</div>
		<?php
	}

	/**
	 * @param array<string,string> $choices
	 * @param mixed                $values
	 */
	public static function facet_row( string $name, array $choices, string $selected_key, $values ): void {
		$is_price = 'price_range' === $selected_key;
		$min      = '';
		$max      = '';
		$list     = [];
		if ( $is_price && is_array( $values ) && isset( $values[0], $values[1] ) ) {
			$min = (string) $values[0];
			$max = (string) $values[1];
		} elseif ( is_array( $values ) ) {
			foreach ( $values as $value ) {
				if ( is_array( $value ) ) {
					continue;
				}
				$list[] = (string) $value;
			}
		}
		?>
		<div class="omniloop-facet-row" data-facet-row>
			<div class="omniloop-facet-row__head">
				<select class="omniloop-facet-row__key" aria-label="<?php esc_attr_e( 'Filter', 'omniloop' ); ?>">
					<option value=""><?php esc_html_e( 'Choose a filter…', 'omniloop' ); ?></option>
					<?php foreach ( $choices as $facet_key => $facet_label ) : ?>
						<option value="<?php echo esc_attr( $facet_key ); ?>" <?php selected( $selected_key, $facet_key ); ?>><?php echo esc_html( $facet_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="omniloop-admin-icon-btn" data-facet-remove aria-label="<?php esc_attr_e( 'Remove filter', 'omniloop' ); ?>">&times;</button>
			</div>
			<div class="omniloop-facet-row__values" data-facet-values>
				<?php if ( $is_price ) : ?>
					<div class="omniloop-facet-price">
						<label><?php esc_html_e( 'From', 'omniloop' ); ?> <input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name . '[price_range][0]' ); ?>" value="<?php echo esc_attr( $min ); ?>"></label>
						<label><?php esc_html_e( 'To', 'omniloop' ); ?> <input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name . '[price_range][1]' ); ?>" value="<?php echo esc_attr( $max ); ?>"></label>
					</div>
				<?php elseif ( $selected_key && $list ) : ?>
					<div class="omniloop-facet-checks">
						<?php foreach ( $list as $value ) : ?>
							<label class="omniloop-facet-check">
								<input type="checkbox" name="<?php echo esc_attr( $name . '[' . $selected_key . '][]' ); ?>" value="<?php echo esc_attr( $value ); ?>" checked>
								<span><?php echo esc_html( $value ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<string,string> $facet_choices
	 * @return array<string,string>
	 */
	public static function builder_choices( array $facet_choices ): array {
		$out = [
			'price_range' => __( 'Price range', 'omniloop' ),
		];
		foreach ( $facet_choices as $key => $label ) {
			if ( 'price' === $key ) {
				continue;
			}
			$out[ $key ] = $label;
		}

		return $out;
	}

	/**
	 * @param array<string,string> $facet_choices
	 * @param list<\WP_Post_Type>  $types
	 */
	public static function templates( array $facet_choices, array $types, string $default_type ): void {
		$empty_preset = [
			'id'        => '',
			'label'     => '',
			'post_type' => $default_type,
			'search'    => '',
			'sort_by'   => 'date',
			'facets'    => [],
		];
		$empty_promo  = [
			'id'        => '',
			'badge'     => '',
			'post_type' => $default_type,
			'facets'    => [],
		];
		$empty_rule   = [
			'id'            => '',
			'source_facet'  => '',
			'source_values' => [],
			'action'        => 'show',
			'target_facet'  => '',
		];
		?>
		<template id="omniloop-template-preset">
			<?php self::preset_card( 0, $empty_preset, $types, $facet_choices ); ?>
		</template>
		<template id="omniloop-template-promo">
			<?php self::promo_card( 0, $empty_promo, $types, $facet_choices ); ?>
		</template>
		<template id="omniloop-template-rule">
			<?php self::rule_card( 0, $empty_rule, $facet_choices ); ?>
		</template>
		<template id="omniloop-template-facet-row">
			<?php self::facet_row( 'omniloop_settings[filter_presets][0][facets]', self::builder_choices( $facet_choices ), '', [] ); ?>
		</template>
		<?php
	}
}
