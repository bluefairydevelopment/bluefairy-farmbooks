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

	// The one option this plugin writes. It is a NETWORK option, so
	// delete_option() would not find it on multisite.
	delete_site_option( 'bffb_pending_site_installs' );
	delete_option( 'bffb_pending_site_installs' );

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
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $bffb_site_id ) {
		switch_to_blog( (int) $bffb_site_id );

		try {
			bffb_uninstall_this_site();
		} finally {
			restore_current_blog();
		}
	}
} else {
	bffb_uninstall_this_site();
}
