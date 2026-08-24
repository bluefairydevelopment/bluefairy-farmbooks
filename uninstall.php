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

// Drop Lite tables in dependency order (transactions references categories + accounts).
$bffb_tables = array(
	$wpdb->prefix . 'bffb_transactions',
	$wpdb->prefix . 'bffb_categories',
	$wpdb->prefix . 'bffb_accounts',
	$wpdb->prefix . 'bffb_settings',
);

foreach ( $bffb_tables as $bffb_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$bffb_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
