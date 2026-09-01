<?php
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/*
 * Only drop data if the site administrator has explicitly opted in via
 * defining the BFFB_REMOVE_DATA constant in wp-config.php.
 *
 *   define( 'BFFB_REMOVE_DATA', true );
 *
 * Without this constant, uninstalling the plugin preserves all farm data.
 */
if ( ! defined( 'BFFB_REMOVE_DATA' ) || ! BFFB_REMOVE_DATA ) {
	return;
}

/**
 * Remove this plugin's data from the CURRENT site.
 *
 * @return void
 */
function bffb_uninstall_this_site(): void {
	global $wpdb;

	// Drop the tables in dependency order (transactions references categories + accounts).
	$bffb_tables = array(
		$wpdb->prefix . 'bffb_transactions',
		$wpdb->prefix . 'bffb_categories',
		$wpdb->prefix . 'bffb_accounts',
		$wpdb->prefix . 'bffb_settings',
	);

	foreach ( $bffb_tables as $bffb_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$bffb_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	delete_option( 'bffb_db_version' );
	delete_option( 'bffb_install_attempts' );


	// Anything else this plugin left in wp_options, including from a version that
	// wrote one this list does not know about. Matched on the bffb_ prefix
	// exactly: bffbp_ belongs to Pro and must survive a free-only uninstall.
	$bffb_leftovers = $wpdb->get_col(
	    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'bffb\\_%'" // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);

	foreach ( (array) $bffb_leftovers as $bffb_option ) {
	    delete_option( $bffb_option );
	}
}

/*
 * Core calls uninstall_plugin() once, against whichever blog happens to be
 * current. Activation creates these tables on every site in a network, so
 * removing them from one and leaving the rest is not a partial job - it is
 * data the administrator explicitly asked to be gone, silently surviving.
 */
// Network options, written with update_site_option(). Once, outside the
// per-site loop: wp_sitemeta is not per blog, so deleting them inside it ran
// the same delete once per site.
delete_site_option( 'bffb_pending_site_installs' );
delete_site_option( 'bffb_site_install_lock' );
delete_site_option( 'bffb_failed_site_installs' );
delete_option( 'bffb_pending_site_installs' );
delete_option( 'bffb_site_install_lock' );

if ( is_multisite() ) {
	/*
	 * Under a deadline, and recording what is left.
	 *
	 * The activation loop was given a resumable, time-budgeted queue because a
	 * bare foreach dies mid-loop on a large network. This loop does strictly
	 * more work per site - table drops plus a recursive filesystem walk - and
	 * uninstall cannot schedule a continuation, because the files that would
	 * run it are about to be deleted. So: lift the limit where the host allows
	 * it, and if the deadline still arrives, leave the remaining site IDs in a
	 * network option and say so in the error log. Silently stopping partway
	 * would leave data an administrator explicitly asked to be gone.
	 */
	// Try to lift the limit, but do not rely on it. function_exists() is TRUE
	// for a function listed in disable_functions - common on shared hosting -
	// so the call can be a silent no-op with the real max_execution_time still
	// in force. Budget against the limit we might be stuck with, not the one
	// we hoped to remove: 20 seconds is inside a 30-second default.
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$bffb_limit    = (int) ini_get( 'max_execution_time' );
	$bffb_budget   = ( $bffb_limit > 0 ) ? max( 10.0, $bffb_limit * 0.6 ) : 120.0;
	$bffb_deadline = microtime( true ) + $bffb_budget;
	$bffb_pending  = array_map( 'intval', get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) );

	while ( $bffb_pending ) {
		if ( microtime( true ) > $bffb_deadline ) {
			update_site_option( 'bffb_uninstall_remaining_sites', $bffb_pending );
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf(
					'FarmBooks uninstall ran out of time with %d site(s) left. Their IDs are in the bffb_uninstall_remaining_sites network option. This file is deleted with the plugin, so nothing will resume automatically: reinstall and delete again, or drop the tables on those sites by hand.',
					count( $bffb_pending )
				)
			);
			break;
		}

		$bffb_site_id = (int) array_shift( $bffb_pending );

		switch_to_blog( $bffb_site_id );

		try {
			bffb_uninstall_this_site();
		} finally {
			restore_current_blog();
		}
	}

	if ( ! $bffb_pending ) {
		delete_site_option( 'bffb_uninstall_remaining_sites' );
	}
} else {
	bffb_uninstall_this_site();
}
