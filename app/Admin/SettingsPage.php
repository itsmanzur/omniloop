<?php
/**
 * Settings + index rebuild UI.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\AcfFacets;
use OmniLoop\Engine\Core\CacheManager;
use OmniLoop\Engine\Core\FilterPresets;
use OmniLoop\Engine\Core\Installer;
use OmniLoop\Engine\Core\Merchandising;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_notices', [ self::class, 'empty_index_notice' ] );
		add_action( 'admin_notices', [ self::class, 'divi_required_notice' ] );
	}

	public static function menu(): void {
		add_menu_page(
			__( 'OmniLoop for Divi', 'omniloop' ),
			__( 'OmniLoop', 'omniloop' ),
			'manage_options',
			'omniloop',
			[ self::class, 'render' ],
			'dashicons-filter',
			58
		);

		add_submenu_page(
			'omniloop',
			__( 'OmniLoop Settings', 'omniloop' ),
			__( 'Settings', 'omniloop' ),
			'manage_options',
			'omniloop',
			[ self::class, 'render' ]
		);
	}

	public static function register(): void {
		register_setting(
			'omniloop',
			'omniloop_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'default'           => [
					'post_types' => Installer::default_post_types(),
					'acf_fields' => [],
					'auto_sync'  => 'on',
					'filter_presets' => [],
					'merchandising'  => [],
				],
			]
		);
	}

	/**
	 * @param mixed $raw
	 * @return array{post_types:string[],acf_fields:string[],auto_sync:string,filter_presets:array<int,array<string,mixed>>}
	 */
	public static function sanitize( $raw ): array {
		$types = [];
		if ( is_array( $raw ) && isset( $raw['post_types'] ) && is_array( $raw['post_types'] ) ) {
			foreach ( $raw['post_types'] as $type ) {
				$type = sanitize_key( (string) $type );
				if ( '' === $type || ! post_type_exists( $type ) ) {
					continue;
				}
				$object = get_post_type_object( $type );
				if ( $object && ! empty( $object->public ) ) {
					$types[] = $type;
				}
			}
		}

		if ( empty( $types ) ) {
			$types = Installer::default_post_types();
		}

		$acf_fields = [];
		if ( is_array( $raw ) && isset( $raw['acf_fields'] ) && is_array( $raw['acf_fields'] ) && AcfFacets::is_active() ) {
			$allowed = [];
			foreach ( AcfFacets::available_fields() as $field ) {
				$allowed[ $field['name'] ] = true;
			}
			foreach ( $raw['acf_fields'] as $name ) {
				$name = sanitize_key( (string) $name );
				if ( '' !== $name && isset( $allowed[ $name ] ) ) {
					$acf_fields[] = $name;
				}
			}
		}

		$clean = [
			'post_types'     => array_values( array_unique( $types ) ),
			'acf_fields'     => array_values( array_unique( $acf_fields ) ),
			'auto_sync'      => ( is_array( $raw ) && isset( $raw['auto_sync'] ) && 'off' === $raw['auto_sync'] ) ? 'off' : 'on',
			'filter_presets' => FilterPresets::sanitize_list( is_array( $raw ) && isset( $raw['filter_presets'] ) ? $raw['filter_presets'] : [] ),
			'merchandising'  => Merchandising::sanitize( is_array( $raw ) && isset( $raw['merchandising'] ) ? $raw['merchandising'] : [] ),
		];
		CacheManager::bump();

		return $clean;
	}

	public static function divi_required_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'OmniLoop for Divi needs Divi 5. Activate the Divi theme (or Extra with Divi Builder 5) to use the Visual Builder module.', 'omniloop' );
		echo '</p></div>';
	}

	public static function empty_index_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, [ 'plugins', 'toplevel_page_omniloop', 'dashboard' ], true ) ) {
			return;
		}

		global $wpdb;
		$table = Installer::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $rows > 0 ) {
			return;
		}

		$url = admin_url( 'admin.php?page=omniloop' );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'OmniLoop index is empty. Rebuild it so filters work at full speed.', 'omniloop' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open OmniLoop settings', 'omniloop' ) . '</a>';
		echo '</p></div>';
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_omniloop' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'omniloop-admin',
			OMNILOOP_URL . 'assets/css/omniloop-admin.css',
			[],
			OMNILOOP_VERSION
		);

		wp_enqueue_script(
			'omniloop-admin',
			OMNILOOP_URL . 'assets/js/omniloop-admin.js',
			[],
			OMNILOOP_VERSION,
			true
		);

		wp_localize_script(
			'omniloop-admin',
			'omniloopAdmin',
			[
				'restUrl' => esc_url_raw( rest_url( 'omniloop/v1/reindex' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'done'       => __( 'Index rebuild complete.', 'omniloop' ),
					'error'      => __( 'Index rebuild failed.', 'omniloop' ),
					'rebuilding' => __( 'Rebuilding…', 'omniloop' ),
				],
			]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table  = Installer::table_name();
		$status = \OmniLoop\Engine\Core\Indexer::status();
		$rows   = (int) $status['indexed_rows'];

		$settings = get_option( 'omniloop_settings', [] );
		$selected = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? $settings['post_types']
			: Installer::default_post_types();
		$acf_selected = AcfFacets::selected_fields();
		$acf_fields   = AcfFacets::available_fields();
		$acf_active   = AcfFacets::is_active();
		$auto_sync_on = ! isset( $settings['auto_sync'] ) || 'off' !== $settings['auto_sync'];
		$presets      = FilterPresets::all();
		$merch        = Merchandising::settings();
		$sort_options = [ 'date', 'title', 'price_asc', 'price_desc', 'popularity', 'rating', 'meta' ];

		$public = get_post_types( [ 'public' => true ], 'objects' );
		$types  = [];
		foreach ( $public as $type ) {
			if ( in_array( $type->name, [ 'attachment' ], true ) ) {
				continue;
			}
			$types[] = $type;
		}

		$selected_count = count(
			array_filter(
				$types,
				static function ( $type ) use ( $selected ): bool {
					return in_array( $type->name, $selected, true );
				}
			)
		);

		$index_ready = $rows > 0;
		$divi_ok     = function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
		$pages_url   = admin_url( 'edit.php?post_type=page' );
		$tick_svg    = '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2.5 6.2l2.4 2.4 4.6-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		?>
		<div class="wrap omniloop-admin">
			<header class="omniloop-admin-hero">
				<div class="omniloop-admin-hero__brand">
					<span class="omniloop-admin-hero__mark" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 18 18" fill="none">
							<path d="M3 4.5h12M5.5 9h7M7.5 13.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						</svg>
					</span>
					<div>
						<p class="omniloop-admin-hero__eyebrow"><?php esc_html_e( 'Divi 5 extension', 'omniloop' ); ?></p>
						<h1><?php esc_html_e( 'OmniLoop for Divi', 'omniloop' ); ?></h1>
						<p class="omniloop-admin-hero__lead">
							<?php esc_html_e( 'Index post types for live facets, then drop the OmniLoop Filter module into any Divi 5 layout.', 'omniloop' ); ?>
						</p>
					</div>
				</div>
				<div class="omniloop-admin-hero__actions">
					<a class="omniloop-admin-btn omniloop-admin-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=omniloop-docs' ) ); ?>">
						<?php esc_html_e( 'Help Guide', 'omniloop' ); ?>
					</a>
					<a class="omniloop-admin-btn omniloop-admin-btn--ghost" href="<?php echo esc_url( $pages_url ); ?>">
						<?php esc_html_e( 'Open Pages', 'omniloop' ); ?>
					</a>
					<button type="button" class="omniloop-admin-btn omniloop-admin-btn--primary" id="omniloop-reindex">
						<?php esc_html_e( 'Rebuild index', 'omniloop' ); ?>
					</button>
				</div>
			</header>

			<section class="omniloop-admin-stats" aria-label="<?php esc_attr_e( 'Index status', 'omniloop' ); ?>">
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Indexed rows', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value" id="omniloop-indexed-count" data-count="<?php echo esc_attr( (string) $rows ); ?>">
						<?php echo esc_html( number_format_i18n( $rows ) ); ?>
					</p>
					<?php if ( $index_ready ) : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--ok"><?php esc_html_e( 'Ready', 'omniloop' ); ?></span>
					<?php else : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--warn"><?php esc_html_e( 'Needs rebuild', 'omniloop' ); ?></span>
					<?php endif; ?>
				</article>
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Indexed posts', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value" id="omniloop-indexed-posts">
						<?php echo esc_html( number_format_i18n( (int) $status['indexed_posts'] ) ); ?>
					</p>
					<p class="omniloop-admin-stat__meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: facet rows in the index table */
								__( '%d facet rows', 'omniloop' ),
								$rows
							)
						);
						?>
					</p>
				</article>
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Last sync', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value" id="omniloop-last-sync" style="font-size:1.05rem;">
						<?php
						if ( (int) $status['last_sync'] > 0 ) {
							echo esc_html(
								sprintf(
									/* translators: %s: human-readable time difference */
									__( '%s ago', 'omniloop' ),
									$status['last_sync_human']
								)
							);
						} else {
							esc_html_e( 'Never', 'omniloop' );
						}
						?>
					</p>
					<?php if ( $auto_sync_on ) : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--ok"><?php esc_html_e( 'Auto-sync on', 'omniloop' ); ?></span>
					<?php else : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--warn"><?php esc_html_e( 'Auto-sync off', 'omniloop' ); ?></span>
					<?php endif; ?>
				</article>
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Post types', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value"><?php echo esc_html( (string) $selected_count ); ?></p>
					<p class="omniloop-admin-stat__meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: total available public post types */
								__( 'of %d available', 'omniloop' ),
								count( $types )
							)
						);
						?>
					</p>
				</article>
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Integrations', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value" style="font-size:1.15rem;letter-spacing:-0.01em;">
						<?php echo $divi_ok ? esc_html__( 'Divi 5', 'omniloop' ) : esc_html__( 'Divi missing', 'omniloop' ); ?>
					</p>
					<?php if ( $acf_active ) : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--ok">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: selected ACF fields */
									_n( '%d ACF field', '%d ACF fields', count( $acf_selected ), 'omniloop' ),
									count( $acf_selected )
								)
							);
							?>
						</span>
					<?php else : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--muted"><?php esc_html_e( 'ACF optional', 'omniloop' ); ?></span>
					<?php endif; ?>
				</article>
			</section>

			<form method="post" action="options.php" class="omniloop-admin-form">
				<?php settings_fields( 'omniloop' ); ?>

				<div class="omniloop-admin-grid">
					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Incremental index', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Keep facets up to date when posts, products, terms, or price meta change. Use Rebuild index for a full refresh.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<ul class="omniloop-admin-checks">
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[auto_sync]" value="off">
										<input
											type="checkbox"
											name="omniloop_settings[auto_sync]"
											value="on"
											<?php checked( $auto_sync_on ); ?>
										>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Auto-sync index on save', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Updates one post at a time when you edit content, stock, price, terms, or ACF fields.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
							</ul>
							<?php if ( ! empty( $status['reindex_pending'] ) ) : ?>
								<p class="omniloop-admin-status omniloop-admin-status--warn">
									<?php esc_html_e( 'A background rebuild is still running. Filters keep working; auto-sync updates individual posts meanwhile.', 'omniloop' ); ?>
								</p>
							<?php endif; ?>
						</div>
					</section>

					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Indexable post types', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Only checked types are indexed and available in the OmniLoop Filter module.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<ul class="omniloop-admin-checks">
								<?php foreach ( $types as $type ) : ?>
									<li class="omniloop-admin-check">
										<label>
											<input
												type="checkbox"
												name="omniloop_settings[post_types][]"
												value="<?php echo esc_attr( $type->name ); ?>"
												<?php checked( in_array( $type->name, $selected, true ) ); ?>
											>
											<span class="omniloop-admin-check__ui">
												<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
												<span class="omniloop-admin-check__text">
													<span class="omniloop-admin-check__label"><?php echo esc_html( $type->labels->name ); ?></span>
													<span class="omniloop-admin-check__slug"><?php echo esc_html( $type->name ); ?></span>
												</span>
											</span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</section>

					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Saved filter presets', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Create reusable filter combinations for modules. Share a preset with ?ol_preset=preset-id on any page.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div id="omniloop-presets-list" class="omniloop-admin-presets">
								<?php
								$preset_rows = $presets;
								if ( empty( $preset_rows ) ) {
									$preset_rows = [
										[
											'id'        => '',
											'label'     => '',
											'post_type' => in_array( 'product', $selected, true ) ? 'product' : ( $selected[0] ?? 'post' ),
											'search'    => '',
											'sort_by'   => 'date',
											'facets'    => [],
										],
									];
								}
								foreach ( $preset_rows as $index => $preset ) :
									$facets_json = wp_json_encode( $preset['facets'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
									?>
									<div class="omniloop-admin-preset" data-index="<?php echo esc_attr( (string) $index ); ?>">
										<div class="omniloop-admin-preset__grid">
											<label>
												<span><?php esc_html_e( 'Label', 'omniloop' ); ?></span>
												<input type="text" name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $preset['label'] ); ?>" placeholder="<?php esc_attr_e( 'Summer Sale', 'omniloop' ); ?>">
											</label>
											<label>
												<span><?php esc_html_e( 'Preset ID', 'omniloop' ); ?></span>
												<input type="text" name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $preset['id'] ); ?>" placeholder="<?php esc_attr_e( 'summer-sale', 'omniloop' ); ?>">
											</label>
											<label>
												<span><?php esc_html_e( 'Post type', 'omniloop' ); ?></span>
												<select name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][post_type]">
													<?php foreach ( $types as $type ) : ?>
														<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $preset['post_type'], $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label>
												<span><?php esc_html_e( 'Sort', 'omniloop' ); ?></span>
												<select name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][sort_by]">
													<?php foreach ( $sort_options as $sort_key ) : ?>
														<option value="<?php echo esc_attr( $sort_key ); ?>" <?php selected( $preset['sort_by'], $sort_key ); ?>><?php echo esc_html( $sort_key ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="omniloop-admin-preset__wide">
												<span><?php esc_html_e( 'Search', 'omniloop' ); ?></span>
												<input type="text" name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][search]" value="<?php echo esc_attr( $preset['search'] ); ?>">
											</label>
											<label class="omniloop-admin-preset__wide">
												<span><?php esc_html_e( 'Facets JSON', 'omniloop' ); ?></span>
												<textarea name="omniloop_settings[filter_presets][<?php echo esc_attr( (string) $index ); ?>][facets]" rows="4" placeholder='{"on_sale":["1"],"product_cat":["shoes"]}'><?php echo esc_textarea( is_string( $facets_json ) ? $facets_json : '{}' ); ?></textarea>
											</label>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="omniloop-admin-status">
								<?php esc_html_e( 'Example facets JSON: {"on_sale":["1"],"product_cat":["shoes"],"price_range":[10,120]}', 'omniloop' ); ?>
							</p>
						</div>
					</section>

					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Merchandising', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Pin products to the top of matching results, push out-of-stock items down, and show promo or low-stock badges.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div class="omniloop-admin-preset__grid">
								<label class="omniloop-admin-preset__wide">
									<span><?php esc_html_e( 'Pinned product IDs', 'omniloop' ); ?></span>
									<input type="text" name="omniloop_settings[merchandising][pin_ids]" value="<?php echo esc_attr( implode( ', ', $merch['pin_ids'] ) ); ?>" placeholder="12, 45, 90">
								</label>
								<label>
									<span><?php esc_html_e( 'Out of stock last', 'omniloop' ); ?></span>
									<select name="omniloop_settings[merchandising][oos_last]">
										<option value="on" <?php selected( $merch['oos_last'] ); ?>><?php esc_html_e( 'Yes', 'omniloop' ); ?></option>
										<option value="off" <?php selected( ! $merch['oos_last'] ); ?>><?php esc_html_e( 'No', 'omniloop' ); ?></option>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Low-stock threshold', 'omniloop' ); ?></span>
									<input type="number" min="0" max="99" name="omniloop_settings[merchandising][urgency_threshold]" value="<?php echo esc_attr( (string) $merch['urgency_threshold'] ); ?>">
								</label>
							</div>
							<p class="omniloop-admin-status"><?php esc_html_e( 'Pinned IDs stay at the top only when they still match the current filters. Set the threshold to 0 to hide “Only X left”.', 'omniloop' ); ?></p>
							<div class="omniloop-admin-presets" style="margin-top:1rem">
								<?php
								$promo_rows = $merch['promotions'];
								if ( empty( $promo_rows ) ) {
									$promo_rows = [
										[
											'id'        => '',
											'badge'     => '',
											'post_type' => in_array( 'product', $selected, true ) ? 'product' : ( $selected[0] ?? 'post' ),
											'facets'    => [],
										],
									];
								}
								foreach ( $promo_rows as $index => $promo ) :
									$promo_facets = wp_json_encode( $promo['facets'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
									?>
									<div class="omniloop-admin-preset">
										<div class="omniloop-admin-preset__grid">
											<label>
												<span><?php esc_html_e( 'Promo badge', 'omniloop' ); ?></span>
												<input type="text" name="omniloop_settings[merchandising][promotions][<?php echo esc_attr( (string) $index ); ?>][badge]" value="<?php echo esc_attr( $promo['badge'] ); ?>" placeholder="<?php esc_attr_e( 'Staff pick', 'omniloop' ); ?>">
											</label>
											<label>
												<span><?php esc_html_e( 'Rule ID', 'omniloop' ); ?></span>
												<input type="text" name="omniloop_settings[merchandising][promotions][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $promo['id'] ); ?>" placeholder="<?php esc_attr_e( 'staff-pick', 'omniloop' ); ?>">
											</label>
											<label>
												<span><?php esc_html_e( 'Post type', 'omniloop' ); ?></span>
												<select name="omniloop_settings[merchandising][promotions][<?php echo esc_attr( (string) $index ); ?>][post_type]">
													<?php foreach ( $types as $type ) : ?>
														<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $promo['post_type'], $type->name ); ?>><?php echo esc_html( $type->labels->singular_name ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="omniloop-admin-preset__wide">
												<span><?php esc_html_e( 'Match facets JSON', 'omniloop' ); ?></span>
												<textarea name="omniloop_settings[merchandising][promotions][<?php echo esc_attr( (string) $index ); ?>][facets]" rows="3" placeholder='{"product_cat":["shoes"]}'><?php echo esc_textarea( is_string( $promo_facets ) ? $promo_facets : '{}' ); ?></textarea>
											</label>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="omniloop-admin-status"><?php esc_html_e( 'Products that have those facet values get the promo badge. Example: {"product_cat":["shoes"]}', 'omniloop' ); ?></p>
						</div>
					</section>

					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'ACF facets', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Optional. Select fields to expose as filters after rebuild.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<?php if ( ! $acf_active ) : ?>
								<p class="omniloop-admin-empty">
									<?php esc_html_e( 'Install and activate Advanced Custom Fields to filter by ACF fields.', 'omniloop' ); ?>
								</p>
							<?php elseif ( ! $acf_fields ) : ?>
								<p class="omniloop-admin-empty">
									<?php esc_html_e( 'No facetable ACF fields found. Add Select, Checkbox, Radio, Button Group, or True/False fields.', 'omniloop' ); ?>
								</p>
							<?php else : ?>
								<ul class="omniloop-admin-checks">
									<?php foreach ( $acf_fields as $field ) : ?>
										<li class="omniloop-admin-check">
											<label>
												<input
													type="checkbox"
													name="omniloop_settings[acf_fields][]"
													value="<?php echo esc_attr( $field['name'] ); ?>"
													<?php checked( in_array( $field['name'], $acf_selected, true ) ); ?>
												>
												<span class="omniloop-admin-check__ui">
													<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
													<span class="omniloop-admin-check__text">
														<span class="omniloop-admin-check__label"><?php echo esc_html( $field['label'] ); ?></span>
														<span class="omniloop-admin-check__slug"><?php echo esc_html( $field['name'] . ' · ' . $field['type'] ); ?></span>
													</span>
												</span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>
				</div>

				<div class="omniloop-admin-tip">
					<span class="omniloop-admin-tip__icon" aria-hidden="true">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
							<path d="M8 1.6a6.4 6.4 0 100 12.8A6.4 6.4 0 008 1.6z" stroke="currentColor" stroke-width="1.5"/>
							<path d="M8 7v3.2M8 4.8h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
						</svg>
					</span>
					<div>
						<strong><?php esc_html_e( 'After saving', 'omniloop' ); ?></strong>
						<p><?php esc_html_e( 'Rebuild the index whenever you change post types or ACF facets, then insert OmniLoop Filter in the Divi 5 Visual Builder.', 'omniloop' ); ?></p>
					</div>
				</div>

				<footer class="omniloop-admin-foot">
					<div class="omniloop-admin-foot__actions">
						<button type="submit" class="omniloop-admin-btn omniloop-admin-btn--primary">
							<?php esc_html_e( 'Save changes', 'omniloop' ); ?>
						</button>
						<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost" id="omniloop-reindex-secondary">
							<?php esc_html_e( 'Rebuild index', 'omniloop' ); ?>
						</button>
					</div>
					<div>
						<div class="omniloop-admin-progress" id="omniloop-reindex-progress" aria-hidden="true">
							<div class="omniloop-admin-progress__bar" id="omniloop-reindex-bar"></div>
						</div>
						<p class="omniloop-admin-status" id="omniloop-reindex-status" aria-live="polite"></p>
					</div>
				</footer>
			</form>
		</div>
		<?php
	}
}
