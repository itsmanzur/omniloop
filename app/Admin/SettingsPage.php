<?php
/**
 * Settings + index rebuild UI.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\AcfFacets;
use OmniLoop\Engine\Core\CacheManager;
use OmniLoop\Engine\Core\FacetAnalytics;
use OmniLoop\Engine\Core\FacetUi;
use OmniLoop\Engine\Core\FilterPresets;
use OmniLoop\Engine\Core\HelpVideo;
use OmniLoop\Engine\Core\I18n;
use OmniLoop\Engine\Core\Indexer;
use OmniLoop\Engine\Core\Installer;
use OmniLoop\Engine\Core\Merchandising;
use OmniLoop\Engine\Core\QueryBuilder;
use OmniLoop\Engine\Core\SeoMeta;
use OmniLoop\Engine\Core\SettingsIo;
use OmniLoop\Engine\Admin\SettingsFields;
use OmniLoop\Engine\Admin\CatalogLookup;

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
		add_action( 'admin_notices', [ self::class, 'import_notice' ] );
		add_action( 'admin_post_' . SettingsIo::EXPORT_ACTION, [ self::class, 'handle_export' ] );
		add_action( 'admin_post_' . SettingsIo::IMPORT_ACTION, [ self::class, 'handle_import' ] );
		add_action( 'update_option_omniloop_settings', [ self::class, 'maybe_reindex_after_save' ], 10, 2 );
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
					'post_types'     => Installer::default_post_types(),
					'acf_fields'     => [],
					'auto_sync'      => 'on',
					'filter_presets' => [],
					'merchandising'  => [],
					'seo'            => SeoMeta::sanitize( [] ),
					'facet_ui'       => FacetUi::sanitize( [] ),
					'search'         => [ 'fuzzy' => 'on' ],
					'analytics'      => [ 'enabled' => 'off' ],
					'help'           => [ 'intro_video' => '' ],
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
			'seo'            => SeoMeta::sanitize( is_array( $raw ) && isset( $raw['seo'] ) ? $raw['seo'] : [] ),
			'facet_ui'       => FacetUi::sanitize( is_array( $raw ) && isset( $raw['facet_ui'] ) ? $raw['facet_ui'] : [] ),
			'search'         => [
				'fuzzy' => ( is_array( $raw ) && isset( $raw['search']['fuzzy'] ) && 'off' === $raw['search']['fuzzy'] ) ? 'off' : 'on',
			],
			'analytics'      => [
				'enabled' => (
					is_array( $raw )
					&& isset( $raw['analytics'] )
					&& is_array( $raw['analytics'] )
					&& isset( $raw['analytics']['enabled'] )
					&& 'on' === $raw['analytics']['enabled']
				) ? 'on' : 'off',
			],
			'help'           => [
				'intro_video' => HelpVideo::sanitize_url( is_array( $raw ) && isset( $raw['help']['intro_video'] ) ? $raw['help']['intro_video'] : '' ),
			],
		];
		CacheManager::bump();

		return $clean;
	}

	/**
	 * Indexed post types or ACF fields changed (settings form or import):
	 * existing rows no longer match, so rebuild the index in the background.
	 *
	 * @param mixed $old_value
	 * @param mixed $value
	 */
	public static function maybe_reindex_after_save( $old_value, $value ): void {
		$old = is_array( $old_value ) ? $old_value : [];
		$new = is_array( $value ) ? $value : [];
		foreach ( [ 'post_types', 'acf_fields' ] as $key ) {
			$before = isset( $old[ $key ] ) && is_array( $old[ $key ] ) ? $old[ $key ] : [];
			$after  = isset( $new[ $key ] ) && is_array( $new[ $key ] ) ? $new[ $key ] : [];
			sort( $before );
			sort( $after );
			if ( $before !== $after ) {
				Indexer::schedule_full_reindex();
				return;
			}
		}
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

		wp_enqueue_media();

		wp_enqueue_style(
			'omniloop-admin',
			OMNILOOP_URL . 'assets/css/omniloop-admin.css',
			[],
			OMNILOOP_VERSION
		);

		wp_enqueue_script(
			'omniloop-admin',
			OMNILOOP_URL . 'assets/js/omniloop-admin.js',
			[ 'jquery' ],
			OMNILOOP_VERSION,
			true
		);

		wp_localize_script(
			'omniloop-admin',
			'omniloopAdmin',
			[
				'restUrl'        => esc_url_raw( rest_url( 'omniloop/v1/reindex' ) ),
				'searchUrl'      => esc_url_raw( rest_url( 'omniloop/v1/admin/search-posts' ) ),
				'facetValuesUrl' => esc_url_raw( rest_url( 'omniloop/v1/admin/facet-values' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'i18n'           => [
					'done'       => __( 'Index rebuild complete.', 'omniloop' ),
					'error'      => __( 'Index rebuild failed.', 'omniloop' ),
					'rebuilding' => __( 'Rebuilding…', 'omniloop' ),
					'videoTitle' => __( 'Select intro video', 'omniloop' ),
					'videoUse'   => __( 'Use this video', 'omniloop' ),
					'noResults'  => __( 'No matches', 'omniloop' ),
					'unpin'      => __( 'Unpin', 'omniloop' ),
					'from'       => __( 'From', 'omniloop' ),
					'to'         => __( 'To', 'omniloop' ),
					'loading'    => __( 'Loading…', 'omniloop' ),
				],
			]
		);
	}

	public static function import_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['omniloop_import'] ) ? sanitize_key( (string) wp_unslash( $_GET['omniloop_import'] ) ) : '';
		if ( '' === $status ) {
			return;
		}
		if ( 'ok' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'OmniLoop settings imported.', 'omniloop' ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not import that settings file.', 'omniloop' ) . '</p></div>';
	}

	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'omniloop' ) );
		}
		check_admin_referer( SettingsIo::EXPORT_ACTION );
		$payload = wp_json_encode( SettingsIo::export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'omniloop-settings-' . gmdate( 'Ymd-His' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo is_string( $payload ) ? $payload : '{}';
		exit;
	}

	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'omniloop' ) );
		}
		check_admin_referer( SettingsIo::IMPORT_ACTION );
		$redirect = admin_url( 'admin.php?page=omniloop' );
		$file     = isset( $_FILES['omniloop_import_file'] ) && is_array( $_FILES['omniloop_import_file'] ) ? $_FILES['omniloop_import_file'] : [];
		$error    = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$tmp      = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_readable( $tmp ) ) {
			wp_safe_redirect( add_query_arg( 'omniloop_import', 'fail', $redirect ) );
			exit;
		}
		$contents = file_get_contents( $tmp );
		$parsed   = SettingsIo::parse_import( is_string( $contents ) ? $contents : '' );
		if ( is_wp_error( $parsed ) ) {
			wp_safe_redirect( add_query_arg( 'omniloop_import', 'fail', $redirect ) );
			exit;
		}
		$merged = SettingsIo::merge_into_current( $parsed );
		update_option( 'omniloop_settings', self::sanitize( $merged ), false );
		wp_safe_redirect( add_query_arg( 'omniloop_import', 'ok', $redirect ) );
		exit;
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
		$seo          = SeoMeta::settings();
		$facet_ui     = FacetUi::settings();
		$fuzzy_on     = QueryBuilder::uses_fuzzy_search();
		$help_video   = HelpVideo::stored_url();
		$facet_choices = \OmniLoop\Engine\Rest\FilterEndpoint::selectable_facets();
		$analytics_clicks = FacetAnalytics::top_clicks( 8 );
		$analytics_empty  = FacetAnalytics::empty_count();
		$analytics_products = FacetAnalytics::top_products( 8 );
		$analytics_enabled  = FacetAnalytics::enabled();

		$public = get_post_types( [ 'public' => true ], 'objects' );
		$types  = [];
		foreach ( $public as $type ) {
			if ( in_array( $type->name, [ 'attachment' ], true ) ) {
				continue;
			}
			$types[] = $type;
		}

		$default_type = in_array( 'product', $selected, true ) ? 'product' : ( $selected[0] ?? 'post' );
		$pin_posts    = CatalogLookup::search_posts( $default_type, '', $merch['pin_ids'] );

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
					<?php if ( ! empty( $status['stale_rows'] ) ) : ?>
						<span class="omniloop-admin-stat__pill omniloop-admin-stat__pill--warn">
							<?php echo esc_html( sprintf( __( '%d stale rows', 'omniloop' ), (int) $status['stale_rows'] ) ); ?>
						</span>
					<?php endif; ?>
				</article>
				<article class="omniloop-admin-stat">
					<p class="omniloop-admin-stat__label"><?php esc_html_e( 'Last sync', 'omniloop' ); ?></p>
					<p class="omniloop-admin-stat__value omniloop-admin-stat__value--sm" id="omniloop-last-sync">
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
					<p class="omniloop-admin-stat__value omniloop-admin-stat__value--sm<?php echo $divi_ok ? '' : ' is-warn'; ?>">
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

			<form method="post" action="options.php" class="omniloop-admin-form" id="omniloop-settings-form">
				<?php settings_fields( 'omniloop' ); ?>

				<nav class="omniloop-admin-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'omniloop' ); ?>">
					<button type="button" class="omniloop-admin-tab is-active" role="tab" id="omniloop-tab-index" data-tab="index" aria-selected="true" aria-controls="omniloop-panel-index"><?php esc_html_e( 'Index', 'omniloop' ); ?></button>
					<button type="button" class="omniloop-admin-tab" role="tab" id="omniloop-tab-seo" data-tab="seo" aria-selected="false" aria-controls="omniloop-panel-seo"><?php esc_html_e( 'SEO', 'omniloop' ); ?></button>
					<button type="button" class="omniloop-admin-tab" role="tab" id="omniloop-tab-facets" data-tab="facets" aria-selected="false" aria-controls="omniloop-panel-facets"><?php esc_html_e( 'Facets', 'omniloop' ); ?></button>
					<button type="button" class="omniloop-admin-tab" role="tab" id="omniloop-tab-analytics" data-tab="analytics" aria-selected="false" aria-controls="omniloop-panel-analytics"><?php esc_html_e( 'Analytics', 'omniloop' ); ?></button>
					<button type="button" class="omniloop-admin-tab" role="tab" id="omniloop-tab-tools" data-tab="tools" aria-selected="false" aria-controls="omniloop-panel-tools"><?php esc_html_e( 'Tools', 'omniloop' ); ?></button>
				</nav>

				<div class="omniloop-admin-grid is-active" data-tab-panel="index" id="omniloop-panel-index" role="tabpanel" aria-labelledby="omniloop-tab-index">
					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Incremental index', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Keep facets up to date when posts, products, terms, or price meta change. Use Rebuild index for a full refresh.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<ul class="omniloop-admin-checks omniloop-admin-checks--solo">
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
								<p><?php esc_html_e( 'Reusable filter sets for modules. Share with ?ol_preset=link-id on any page.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div id="omniloop-presets-list" class="omniloop-admin-presets" data-repeat="preset">
								<?php
								$preset_rows = $presets;
								if ( empty( $preset_rows ) ) {
									$preset_rows = [
										[
											'id'        => '',
											'label'     => '',
											'post_type' => $default_type,
											'search'    => '',
											'sort_by'   => 'date',
											'facets'    => [],
										],
									];
								}
								foreach ( $preset_rows as $index => $preset ) {
									SettingsFields::preset_card( (int) $index, $preset, $types, $facet_choices );
								}
								?>
							</div>
							<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost omniloop-admin-btn--small" data-repeat-add="preset"><?php esc_html_e( 'Add preset', 'omniloop' ); ?></button>
							<p class="omniloop-admin-hint"><?php esc_html_e( 'Leave the link ID blank to generate it from the name. Pick filters below — no JSON required.', 'omniloop' ); ?></p>
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
							<p class="omniloop-admin-subhead"><?php esc_html_e( 'Pinned items', 'omniloop' ); ?></p>
							<?php SettingsFields::pin_picker( $merch['pin_ids'], $pin_posts, $default_type ); ?>
							<div class="omniloop-admin-preset__grid">
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
							<p class="omniloop-admin-hint"><?php esc_html_e( 'Set the threshold to 0 to hide “Only X left”.', 'omniloop' ); ?></p>
							<p class="omniloop-admin-subhead"><?php esc_html_e( 'Promo badges', 'omniloop' ); ?></p>
							<div class="omniloop-admin-presets" data-repeat="promo">
								<?php
								$promo_rows = $merch['promotions'];
								if ( empty( $promo_rows ) ) {
									$promo_rows = [
										[
											'id'        => '',
											'badge'     => '',
											'post_type' => $default_type,
											'facets'    => [],
										],
									];
								}
								foreach ( $promo_rows as $index => $promo ) {
									SettingsFields::promo_card( (int) $index, $promo, $types, $facet_choices );
								}
								?>
							</div>
							<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost omniloop-admin-btn--small" data-repeat-add="promo"><?php esc_html_e( 'Add promo badge', 'omniloop' ); ?></button>
							<p class="omniloop-admin-hint"><?php esc_html_e( 'Items that match the filters below get the badge.', 'omniloop' ); ?></p>
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

				<div class="omniloop-admin-grid" data-tab-panel="seo" id="omniloop-panel-seo" role="tabpanel" aria-labelledby="omniloop-tab-seo" hidden>
					<section class="omniloop-admin-card omniloop-admin-card--wide">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Filtered page SEO', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Templates apply when shoppers land on pretty /ol/ filter URLs. Placeholders: {page_title}, {site_name}, {facet_value}, {facet_labels}, {category}, {search}, {page}.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div class="omniloop-admin-preset__grid">
								<label class="omniloop-admin-preset__wide">
									<span><?php esc_html_e( 'Title template', 'omniloop' ); ?></span>
									<input type="text" name="omniloop_settings[seo][title_template]" value="<?php echo esc_attr( $seo['title_template'] ); ?>">
								</label>
								<label class="omniloop-admin-preset__wide">
									<span><?php esc_html_e( 'Meta description template', 'omniloop' ); ?></span>
									<input type="text" name="omniloop_settings[seo][description_template]" value="<?php echo esc_attr( $seo['description_template'] ); ?>">
								</label>
								<label class="omniloop-admin-preset__wide">
									<span><?php esc_html_e( 'H1 template', 'omniloop' ); ?></span>
									<input type="text" name="omniloop_settings[seo][h1_template]" value="<?php echo esc_attr( $seo['h1_template'] ); ?>">
								</label>
								<label>
									<span><?php esc_html_e( 'Sitemap page', 'omniloop' ); ?></span>
									<select name="omniloop_settings[seo][sitemap_page_id]">
										<option value="0"><?php esc_html_e( 'Front page', 'omniloop' ); ?></option>
										<?php foreach ( get_pages( [ 'number' => 80, 'sort_column' => 'post_title' ] ) as $page ) : ?>
											<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( $seo['sitemap_page_id'], (int) $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label>
									<span><?php esc_html_e( 'Max sitemap URLs', 'omniloop' ); ?></span>
									<input type="number" min="10" max="1000" name="omniloop_settings[seo][sitemap_max]" value="<?php echo esc_attr( (string) $seo['sitemap_max'] ); ?>">
								</label>
							</div>
							<ul class="omniloop-admin-checks omniloop-admin-checks--stack">
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[seo][noindex_filtered]" value="off">
										<input type="checkbox" name="omniloop_settings[seo][noindex_filtered]" value="on" <?php checked( 'on' === $seo['noindex_filtered'] ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Noindex all filtered URLs', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Adds noindex,nofollow whenever a filter, search, or sort is active.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[seo][noindex_paged]" value="off">
										<input type="checkbox" name="omniloop_settings[seo][noindex_paged]" value="on" <?php checked( 'on' === $seo['noindex_paged'] ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Noindex page 2+', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Recommended. Paginated filter URLs are usually duplicate listings.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[seo][sitemap]" value="off">
										<input type="checkbox" name="omniloop_settings[seo][sitemap]" value="on" <?php checked( 'on' === $seo['sitemap'] ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Include filtered URLs in the sitemap', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Adds single-facet combinations from the keys below. Leave keys empty to use the first index facets.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
							</ul>
							<?php if ( $facet_choices ) : ?>
								<p class="omniloop-admin-subhead"><?php esc_html_e( 'Sitemap facet keys', 'omniloop' ); ?></p>
								<ul class="omniloop-admin-checks">
									<?php foreach ( $facet_choices as $facet_key => $facet_label ) : ?>
										<li class="omniloop-admin-check">
											<label>
												<input type="checkbox" name="omniloop_settings[seo][sitemap_facets][]" value="<?php echo esc_attr( $facet_key ); ?>" <?php checked( in_array( $facet_key, $seo['sitemap_facets'], true ) ); ?>>
												<span class="omniloop-admin-check__ui">
													<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
													<span class="omniloop-admin-check__text">
														<span class="omniloop-admin-check__label"><?php echo esc_html( $facet_label ); ?></span>
														<span class="omniloop-admin-check__slug"><?php echo esc_html( $facet_key ); ?></span>
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

				<div class="omniloop-admin-grid" data-tab-panel="facets" id="omniloop-panel-facets" role="tabpanel" aria-labelledby="omniloop-tab-facets" hidden>
					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Empty results', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Hide facet values (or whole groups) when nothing matches the current filters.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<ul class="omniloop-admin-checks omniloop-admin-checks--stack">
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[facet_ui][hide_zero_count]" value="off">
										<input type="checkbox" name="omniloop_settings[facet_ui][hide_zero_count]" value="on" <?php checked( 'on' === $facet_ui['hide_zero_count'] ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Hide zero-count values', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Keeps a checked value visible even if the count drops to zero.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[facet_ui][hide_empty_groups]" value="off">
										<input type="checkbox" name="omniloop_settings[facet_ui][hide_empty_groups]" value="on" <?php checked( 'on' === $facet_ui['hide_empty_groups'] ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Hide empty facet groups', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Removes a whole filter when no values remain.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
								<li class="omniloop-admin-check">
									<label>
										<input type="hidden" name="omniloop_settings[search][fuzzy]" value="off">
										<input type="checkbox" name="omniloop_settings[search][fuzzy]" value="on" <?php checked( $fuzzy_on ); ?>>
										<span class="omniloop-admin-check__ui">
											<span class="omniloop-admin-check__tick"><?php echo $tick_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
											<span class="omniloop-admin-check__text">
												<span class="omniloop-admin-check__label"><?php esc_html_e( 'Typo-tolerant search', 'omniloop' ); ?></span>
												<span class="omniloop-admin-check__slug"><?php esc_html_e( 'Matches similar titles (SOUNDEX) alongside exact words.', 'omniloop' ); ?></span>
											</span>
										</span>
									</label>
								</li>
							</ul>
						</div>
					</section>
					<section class="omniloop-admin-card">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Conditional facets', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Show: target appears only after the source is selected. Hide: target disappears when the source matches.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div class="omniloop-admin-presets" data-repeat="rule">
								<?php
								$rule_rows = $facet_ui['rules'];
								if ( empty( $rule_rows ) ) {
									$rule_rows = [
										[
											'id'            => '',
											'source_facet'  => '',
											'source_values' => [],
											'action'        => 'show',
											'target_facet'  => '',
										],
									];
								}
								foreach ( $rule_rows as $index => $rule ) {
									SettingsFields::rule_card( (int) $index, $rule, $facet_choices );
								}
								?>
							</div>
							<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost omniloop-admin-btn--small" data-repeat-add="rule"><?php esc_html_e( 'Add rule', 'omniloop' ); ?></button>
						</div>
					</section>
				</div>

				<div class="omniloop-admin-grid" data-tab-panel="analytics" id="omniloop-panel-analytics" role="tabpanel" aria-labelledby="omniloop-tab-analytics" hidden>
					<section class="omniloop-admin-card omniloop-admin-card--wide">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Filter analytics (30 days)', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Anonymous facet clicks and empty-result combinations. Also shown on the WordPress dashboard.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<label class="omniloop-admin-check">
								<input type="hidden" name="omniloop_settings[analytics][enabled]" value="off">
								<input type="checkbox" name="omniloop_settings[analytics][enabled]" value="on" <?php checked( $analytics_enabled ); ?>>
								<span class="omniloop-admin-check__ui"><span class="omniloop-admin-check__text"><span class="omniloop-admin-check__label"><?php esc_html_e( 'Enable anonymous conversion analytics', 'omniloop' ); ?></span><span class="omniloop-admin-check__slug"><?php esc_html_e( 'Records filter interactions, product clicks, and add-to-cart intent for 30 days. No IP address is stored.', 'omniloop' ); ?></span></span></span>
							</label>
							<p class="omniloop-admin-hint">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: empty-result events */
										_n( '%d empty-result filter.', '%d empty-result filters.', $analytics_empty, 'omniloop' ),
										$analytics_empty
									)
								);
								?>
							</p>
							<?php if ( $analytics_clicks ) : ?>
								<div class="omniloop-admin-table-wrap">
								<table class="omniloop-admin-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Facet', 'omniloop' ); ?></th>
											<th><?php esc_html_e( 'Value', 'omniloop' ); ?></th>
											<th><?php esc_html_e( 'Clicks', 'omniloop' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $analytics_clicks as $row ) : ?>
											<tr>
												<td><?php echo esc_html( I18n::facet_label( $row['facet_key'] ) ); ?></td>
												<td><?php echo esc_html( I18n::value_label( $row['facet_key'], $row['facet_value'] ) ); ?></td>
												<td><?php echo esc_html( (string) $row['clicks'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								</div>
							<?php else : ?>
								<p class="omniloop-admin-empty"><?php esc_html_e( 'No clicks recorded yet. They appear as shoppers use filters on the front end.', 'omniloop' ); ?></p>
							<?php endif; ?>
							<?php if ( $analytics_products ) : ?>
								<p class="omniloop-admin-subhead"><?php esc_html_e( 'Product engagement', 'omniloop' ); ?></p>
								<div class="omniloop-admin-table-wrap">
								<table class="omniloop-admin-table">
									<thead><tr>
										<th><?php esc_html_e( 'Product', 'omniloop' ); ?></th>
										<th><?php esc_html_e( 'Clicks', 'omniloop' ); ?></th>
										<th><?php esc_html_e( 'Add to carts', 'omniloop' ); ?></th>
										<th><?php esc_html_e( 'Intent rate', 'omniloop' ); ?></th>
									</tr></thead><tbody>
									<?php foreach ( $analytics_products as $row ) : ?>
										<?php $rate = $row['product_clicks'] > 0 ? round( 100 * $row['add_to_carts'] / $row['product_clicks'], 1 ) : 0; ?>
										<tr>
											<td><?php echo esc_html( get_the_title( $row['post_id'] ) ?: '#' . $row['post_id'] ); ?></td>
											<td><?php echo esc_html( (string) $row['product_clicks'] ); ?></td>
											<td><?php echo esc_html( (string) $row['add_to_carts'] ); ?></td>
											<td><?php echo esc_html( (string) $rate . '%' ); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
								</div>
							<?php endif; ?>
						</div>
					</section>
				</div>

				<div class="omniloop-admin-grid" data-tab-panel="tools" id="omniloop-panel-tools" role="tabpanel" aria-labelledby="omniloop-tab-tools" hidden>
					<section class="omniloop-admin-card omniloop-admin-card--wide">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Help Guide intro video', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Paste a YouTube, Vimeo, or MP4 link. It appears at the top of OmniLoop → Help Guide. Leave blank to hide it.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<div class="omniloop-admin-preset__grid">
								<label class="omniloop-admin-preset__wide">
									<span><?php esc_html_e( 'Video URL', 'omniloop' ); ?></span>
									<input
										type="url"
										id="omniloop-intro-video"
										name="omniloop_settings[help][intro_video]"
										value="<?php echo esc_attr( $help_video ); ?>"
										placeholder="https://www.youtube.com/watch?v=…"
									>
								</label>
							</div>
							<div class="omniloop-admin-file-row">
								<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost" id="omniloop-intro-video-pick">
									<?php esc_html_e( 'Choose from Media Library', 'omniloop' ); ?>
								</button>
								<button type="button" class="omniloop-admin-btn omniloop-admin-btn--ghost" id="omniloop-intro-video-clear">
									<?php esc_html_e( 'Clear', 'omniloop' ); ?>
								</button>
								<a class="omniloop-admin-btn omniloop-admin-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=omniloop-docs' ) ); ?>">
									<?php esc_html_e( 'Open Help Guide', 'omniloop' ); ?>
								</a>
							</div>
							<p class="omniloop-admin-hint"><?php esc_html_e( 'YouTube and Vimeo links are embedded privately (no-cookie where possible). Uploaded videos play from your Media Library.', 'omniloop' ); ?></p>
						</div>
					</section>
					<section class="omniloop-admin-card omniloop-admin-card--wide">
						<div class="omniloop-admin-card__head">
							<div>
								<h2><?php esc_html_e( 'Export / import', 'omniloop' ); ?></h2>
								<p><?php esc_html_e( 'Copy presets, merchandising, SEO, and facet rules between sites. Index data is not included — rebuild the index after import.', 'omniloop' ); ?></p>
							</div>
						</div>
						<div class="omniloop-admin-card__body">
							<p>
								<a class="omniloop-admin-btn omniloop-admin-btn--ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . SettingsIo::EXPORT_ACTION ), SettingsIo::EXPORT_ACTION ) ); ?>">
									<?php esc_html_e( 'Download settings JSON', 'omniloop' ); ?>
								</a>
							</p>
						</div>
					</section>
				</div>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="omniloop-admin-import omniloop-admin-card" data-tab-panel="tools" hidden>
				<?php wp_nonce_field( SettingsIo::IMPORT_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( SettingsIo::IMPORT_ACTION ); ?>">
				<div class="omniloop-admin-card__head">
					<div>
						<h2><?php esc_html_e( 'Import settings', 'omniloop' ); ?></h2>
						<p><?php esc_html_e( 'Replace presets, merchandising, SEO, and facet rules from a JSON export. Index data is not included — rebuild after import.', 'omniloop' ); ?></p>
					</div>
				</div>
				<div class="omniloop-admin-card__body">
					<div class="omniloop-admin-file-row">
						<label>
							<span class="screen-reader-text"><?php esc_html_e( 'Settings JSON file', 'omniloop' ); ?></span>
							<input class="omniloop-admin-file" type="file" name="omniloop_import_file" accept="application/json,.json" required>
						</label>
						<button type="submit" class="omniloop-admin-btn omniloop-admin-btn--primary"><?php esc_html_e( 'Import settings', 'omniloop' ); ?></button>
					</div>
				</div>
			</form>

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
					<button type="submit" class="omniloop-admin-btn omniloop-admin-btn--primary" form="omniloop-settings-form">
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
			<?php SettingsFields::templates( $facet_choices, $types, $default_type ); ?>
		</div>
		<?php
	}
}
