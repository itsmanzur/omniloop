<?php
/**
 * Plugin Name: OmniLoop Engine
 * Plugin URI: https://example.com/omniloop
 * Description: High-speed faceted loop for Divi 5 and Gutenberg. Custom index table, REST query API, shared frontend engine.
 * Version: 1.0.0
 * Author: OmniLoop
 * Text Domain: omniloop
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * @package OmniLoop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMNILOOP_VERSION', '1.0.0' );
define( 'OMNILOOP_DB_VERSION', '1.0.0' );
define( 'OMNILOOP_FILE', __FILE__ );
define( 'OMNILOOP_PATH', plugin_dir_path( __FILE__ ) );
define( 'OMNILOOP_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'OmniLoop\\Engine\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = OMNILOOP_PATH . 'app/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( OMNILOOP_FILE, static function (): void {
	\OmniLoop\Engine\Core\Installer::activate();
} );

register_deactivation_hook( OMNILOOP_FILE, static function (): void {
	\OmniLoop\Engine\Core\Installer::deactivate();
} );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'omniloop', false, dirname( plugin_basename( OMNILOOP_FILE ) ) . '/languages' );
		\OmniLoop\Engine\Core\App::instance()->boot();
	}
);
