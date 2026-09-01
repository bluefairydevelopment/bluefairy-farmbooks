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
 * Global helper — allows Pro and third-party code to detect the free plugin without class_exists().
 */
function bffb_plugin(): BlueFairy\FarmBooks\Plugin {
	return BlueFairy\FarmBooks\Plugin::instance();
}

/**
 * Take the network-wide install lock, atomically.
 *
 * add_option() on the MAIN site, not add_site_option(): the latter is a
 * check-then-act on multisite, because wp_sitemeta carries an ordinary KEY on
 * meta_key rather than a UNIQUE one - so two callers can both read "absent" and
 * both insert. wp_options DOES have a unique key on option_name, which makes
 * this INSERT the atomic primitive available here.
 *
 * @return bool True when this process now holds the lock.
 */
function bffb_claim_install_lock(): bool {
	$main = is_multisite() ? get_main_site_id() : 0;

	if ( $main ) {
		switch_to_blog( $main );
	}

	try {
		if ( add_option( 'bffb_site_install_lock', time(), '', false ) ) {
			return true;
		}

		$age = time() - (int) get_option( 'bffb_site_install_lock', 0 );

		if ( $age < 300 ) {
			return false;
		}

		// A lock left by a request that died. Retake it the same atomic way, so
		// two processes recovering the same stale lock cannot both continue.
		delete_option( 'bffb_site_install_lock' );

		return (bool) add_option( 'bffb_site_install_lock', time(), '', false );
	} finally {
		if ( $main ) {
			restore_current_blog();
		}
	}
}

function bffb_release_install_lock(): void {
	$main = is_multisite() ? get_main_site_id() : 0;

	if ( $main ) {
		switch_to_blog( $main );
	}

	try {
		delete_option( 'bffb_site_install_lock' );
	} finally {
		if ( $main ) {
			restore_current_blog();
		}
	}
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

	// Schedule the continuation FIRST, before anything below can return.
	//
	// It used to sit under the lock check, and wp-cron unschedules a single
	// event before invoking its hook - so a run that found the lock held (a
	// crashed predecessor, or simply a slow one still inside its budget)
	// consumed the event and returned without rescheduling. The queue then
	// stalled for ever behind a notice saying it was still progressing.
	if ( ! wp_next_scheduled( 'bffb_continue_site_installs' ) ) {
		wp_schedule_single_event( time() + 60, 'bffb_continue_site_installs' );
	}

	// One run at a time. Two overlapping firings can both read the same pending
	// array before either writes, and Installer::activate() seeds the default
	// chart of accounts with a check-then-insert and no unique key - so a race
	// duplicates every seeded row on that site.
	if ( ! bffb_claim_install_lock() ) {
		return;
	}

	// Well inside a 30s max_execution_time, with room for the request's own
	// overhead. Whatever is left over goes to that cron run.
	$deadline = microtime( true ) + 15.0;

	while ( $pending && microtime( true ) < $deadline ) {
		$site_id = (int) array_shift( $pending );

		// Advance the stored queue BEFORE attempting the site, not after. A
		// fatal inside activate() - an E_ERROR in dbDelta on a crashed table,
		// an out-of-memory on a large site - kills the request with that site
		// still at the head, and every later run shifts the same site and dies
		// the same way. One casualty blocked the whole network for ever.
		update_site_option( 'bffb_pending_site_installs', $pending );

		// Assume it will fail, and clear that assumption on success. Advancing
		// the queue before the attempt is right for liveness, but with nothing
		// recording the casualty the counter simply reached zero and the notice
		// disappeared - which reads as success.
		$failed   = (array) get_site_option( 'bffb_failed_site_installs', array() );
		$failed[] = $site_id;
		update_site_option( 'bffb_failed_site_installs', array_values( array_unique( $failed ) ) );

		switch_to_blog( $site_id );

		try {
			( new BlueFairy\FarmBooks\Installer() )->activate();
		} finally {
			// In a finally: a throw here used to leave the request switched
			// into another site's tables for whatever ran next.
			restore_current_blog();
		}

		// It returned, so it did not die: drop the pessimistic marker.
		$failed = array_values( array_diff( (array) get_site_option( 'bffb_failed_site_installs', array() ), array( $site_id ) ) );

		if ( $failed ) {
			update_site_option( 'bffb_failed_site_installs', $failed );
		} else {
			delete_site_option( 'bffb_failed_site_installs' );
		}
	}

	bffb_release_install_lock();

	if ( $pending ) {
		update_site_option( 'bffb_pending_site_installs', $pending );
		return; // The continuation is already scheduled, above.
	}

	delete_site_option( 'bffb_pending_site_installs' );

	$scheduled = wp_next_scheduled( 'bffb_continue_site_installs' );

	if ( $scheduled ) {
		wp_unschedule_event( $scheduled, 'bffb_continue_site_installs' );
	}
}

add_action( 'bffb_continue_site_installs', 'bffb_process_pending_site_installs' );

// A network admin who never triggers cron would otherwise wait for ever.
add_action(
	// all_admin_notices, not admin_notices: the latter fires only when NOT on
	// a network-admin screen, and the screen a super admin lands on after
	// Network Activate is precisely a network-admin screen. The notice could
	// never appear where it was needed.
	'all_admin_notices',
	static function (): void {
		// A single-site install can never have a network-install queue, so it
		// should not pay for the lookup. Site options have no alloptions-style
		// preload, so this is a real query on every admin request otherwise.
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$pending = get_site_option( 'bffb_pending_site_installs', array() );
		$failed  = (array) get_site_option( 'bffb_failed_site_installs', array() );

		if ( $failed ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: comma-separated site IDs */
						__( 'FarmBooks could not create its tables on site(s) %s. Visit each one and reactivate the plugin there.', 'bluefairy-farmbooks' ),
						implode( ', ', array_map( 'intval', $failed ) )
					)
				)
			);
		}

		if ( ! is_array( $pending ) || ! $pending ) {
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
				array_map( 'intval', get_sites(
					array(
						'fields'   => 'ids',
						'number'   => 0,
						// Archived, spam and deleted sites are not sites to install into.
						'archived' => 0,
						'spam'     => 0,
						'deleted'  => 0,
					)
				) )
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
