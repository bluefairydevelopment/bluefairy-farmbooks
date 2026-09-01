<?php
declare(strict_types=1);

/**
 * Plugin Name:       BlueFairy FarmBooks
 * Plugin URI:        https://bluefairydevelopment.com/plugins/bluefairy-farmbooks/
 * Description:       Farm income and expense tracking aligned to IRS Schedule F. Chart of accounts, transaction ledger, reports, and dashboard — built for small family farms.
 * Version:           1.0.1
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

define( 'BFFB_VERSION', '1.0.1' );
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

/**
 * Install into as many queued sites as fit in a time budget, then reschedule.
 *
 * Resumable by design: the queue is a network option, and each site is removed
 * from it only after its install returns. A request that dies half way leaves
 * the remainder queued rather than silently skipped.
 */
function bffb_process_pending_site_installs(): void {
	$pending = get_site_option( 'bffb_pending_site_installs', array() );

	if ( ! is_array( $pending ) || ! $pending ) {
		delete_site_option( 'bffb_pending_site_installs' );
		return;
	}

	// Well inside a 30s max_execution_time, with room for the request's own
	// overhead. Whatever is left over goes to the next cron run.
	$deadline = microtime( true ) + 15.0;

	while ( $pending && microtime( true ) < $deadline ) {
		$site_id = (int) array_shift( $pending );

		switch_to_blog( $site_id );

		try {
			( new BlueFairy\FarmBooks\Installer() )->activate();
		} finally {
			// In a finally: a throw here used to leave the request switched
			// into another site's tables for whatever ran next.
			restore_current_blog();
		}

		update_site_option( 'bffb_pending_site_installs', $pending );
	}

	if ( $pending ) {
		update_site_option( 'bffb_pending_site_installs', $pending );

		if ( ! wp_next_scheduled( 'bffb_continue_site_installs' ) ) {
			wp_schedule_single_event( time() + 30, 'bffb_continue_site_installs' );
		}

		return;
	}

	delete_site_option( 'bffb_pending_site_installs' );
}

add_action( 'bffb_continue_site_installs', 'bffb_process_pending_site_installs' );

// A network admin who never triggers cron would otherwise wait for ever.
add_action(
	'admin_notices',
	static function (): void {
		$pending = get_site_option( 'bffb_pending_site_installs', array() );

		if ( ! is_array( $pending ) || ! $pending || ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: number of sites still waiting */
					__( 'FarmBooks is still creating its tables on %s site(s) in this network. This continues automatically in the background.', 'bluefairy-farmbooks' ),
					number_format_i18n( count( $pending ) )
				)
			)
		);
	}
);

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
			// Queue the whole network and work through it under a time budget.
			// A bare foreach over get_sites() is one synchronous request doing
			// ten dbDelta calls and thirty seed INSERTs per site: on a
			// 500-site network that is tens of thousands of queries behind a
			// 30-second max_execution_time. It dies mid-loop, every site past
			// that point has no tables, and the admin sees "Plugin activated"
			// - which is the exact failure this hook was added to prevent.
			update_site_option(
				'bffb_pending_site_installs',
				array_map( 'intval', get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) )
			);

			bffb_process_pending_site_installs();

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
	// Explicitly ahead of Pro's handler, which ALTERs the table this one
	// creates. Both sat at 20 and the order was whatever
	// active_sitewide_plugins happened to hold.
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
