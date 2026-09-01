<?php
declare(strict_types=1);

/**
 * Plugin Name:       BlueFairy FarmBooks
 * Plugin URI:        https://bluefairydevelopment.com/plugins/bluefairy-farmbooks/
 * Description:       Farm income and expense tracking aligned to IRS Schedule F. Chart of accounts, transaction ledger, reports, and dashboard — built for small family farms.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Blue Fairy Development
 * Author URI:        https://bluefairydevelopment.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluefairy-farmbooks
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BFFB_VERSION', '1.0.0' );
define( 'BFFB_FILE', __FILE__ );
define( 'BFFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'BFFB_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
spl_autoload_register(
	static function ( string $class ): void {
		if ( str_starts_with( $class, 'BlueFairy\\FarmBooks\\' ) ) {
			$relative = str_replace( 'BlueFairy\\FarmBooks\\', '', $class );
			$path     = BFFB_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}
);

/**
 * Global helper — allows Pro and third-party code to detect Lite without class_exists().
 */
function bffb_plugin(): BlueFairy\FarmBooks\Plugin {
	return BlueFairy\FarmBooks\Plugin::instance();
}

// Activation / deactivation hooks.
register_activation_hook(
	__FILE__,
	static function (): void {
		( new BlueFairy\FarmBooks\Installer() )->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		( new BlueFairy\FarmBooks\Installer() )->deactivate();
	}
);

// Boot the plugin after plugins are loaded so other plugins are available.
add_action(
	'plugins_loaded',
	static function (): void {
		BlueFairy\FarmBooks\Plugin::instance()->boot();
	}
);
