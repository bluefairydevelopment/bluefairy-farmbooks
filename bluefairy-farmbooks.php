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
	/**
	 * Core fires this ONCE for a network activation, not once per site, so the
	 * tables used to be created only on whichever blog happened to be current.
	 * Every other site in the network then had no bffb_* tables at all, and its
	 * admin's first visit to any FarmBooks screen queried a table that does not
	 * exist. The Installer's own docblock has always claimed this was handled.
	 *
	 * @param bool $network_wide True when activated across a network.
	 */
	static function ( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				( new BlueFairy\FarmBooks\Installer() )->activate();
				restore_current_blog();
			}

			return;
		}

		( new BlueFairy\FarmBooks\Installer() )->activate();
	}
);

// A site created AFTER the network activation gets no activation hook at all,
// so it needs the same treatment on creation.
add_action(
	'wp_initialize_site',
	static function ( $site ): void {
		// wp_initialize_site can fire outside wp-admin, where plugin.php has
		// not been loaded and this function does not exist yet.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		( new BlueFairy\FarmBooks\Installer() )->activate();
		restore_current_blog();
	},
	20
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
