<?php
/**
 * Settings + index rebuild UI.
 *
 * @package OmniLoop
 */

namespace OmniLoop\Engine\Admin;

use OmniLoop\Engine\Core\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_init', [ self::class, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_notices', [ self::class, 'empty_index_notice' ] );
	}

	public static function menu(): void {
		add_options_page(
			__( 'OmniLoop Engine', 'omniloop' ),
			__( 'OmniLoop', 'omniloop' ),
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
				],
			]
		);
	}

	/**
	 * @param mixed $raw
	 * @return array{post_types:string[]}
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

		return [ 'post_types' => array_values( array_unique( $types ) ) ];
	}

	public static function empty_index_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, [ 'plugins', 'settings_page_omniloop', 'dashboard' ], true ) ) {
			return;
		}

		global $wpdb;
		$table = Installer::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $rows > 0 ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=omniloop' );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'OmniLoop index is empty. Rebuild it so filters work at full speed.', 'omniloop' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open OmniLoop settings', 'omniloop' ) . '</a>';
		echo '</p></div>';
	}

	public static function assets( string $hook ): void {
		if ( 'settings_page_omniloop' !== $hook ) {
			return;
		}

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
					'done'  => __( 'Index rebuild complete.', 'omniloop' ),
					'error' => __( 'Index rebuild failed.', 'omniloop' ),
				],
			]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = Installer::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$settings = get_option( 'omniloop_settings', [] );
		$selected = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? $settings['post_types']
			: Installer::default_post_types();

		$public = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OmniLoop Engine', 'omniloop' ); ?></h1>
			<p><?php esc_html_e( 'Custom facet index and REST loop. Shortcode: [omniloop post_type="post" per_page="12"]', 'omniloop' ); ?></p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: indexed row count */
						__( 'Indexed rows: %d', 'omniloop' ),
						$rows
					)
				);
				?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'omniloop' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Indexable post types', 'omniloop' ); ?></th>
						<td>
							<?php foreach ( $public as $type ) : ?>
								<?php if ( in_array( $type->name, [ 'attachment' ], true ) ) { continue; } ?>
								<label>
									<input type="checkbox" name="omniloop_settings[post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $selected, true ) ); ?>>
									<?php echo esc_html( $type->labels->name ); ?>
									<code><?php echo esc_html( $type->name ); ?></code>
								</label><br>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<p>
				<button type="button" class="button button-primary" id="omniloop-reindex"><?php esc_html_e( 'Rebuild index', 'omniloop' ); ?></button>
				<span id="omniloop-reindex-status" aria-live="polite"></span>
			</p>
		</div>
		<?php
	}
}
