<?php
/**
 * Unit-test bootstrap: Composer's autoloader and the few WordPress functions
 * the code under test calls, recorded so a test can read them back.
 *
 * @package BlueFairy\FarmBooks
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

function bffb_test_reset(): void {
	$GLOBALS['bffb_test_hooks']    = [];
	$GLOBALS['bffb_test_menus']    = [];
	$GLOBALS['bffb_test_submenus'] = [];
}
bffb_test_reset();

function __( $text, $domain = 'default' ) {
	return $text;
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['bffb_test_hooks'][ $tag ][] = [ $callback, $priority, $accepted_args ];

	return true;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_action( $tag, $callback, $priority, $accepted_args );
}

function do_action( $tag, ...$args ) {
	foreach ( $GLOBALS['bffb_test_hooks'][ $tag ] ?? [] as [ $callback, , $accepted ] ) {
		// As core: an action fired with no argument passes ''.
		$callback( ...array_slice( [] === $args ? [ '' ] : $args, 0, $accepted ) );
	}
}

function add_menu_page( $page_title, $menu_title, $capability, $slug, $callback = '', $icon = '', $position = null ) {
	$GLOBALS['bffb_test_menus'][ $slug ] = [ 'title' => $page_title, 'capability' => $capability ];

	return 'toplevel_page_' . $slug;
}

function add_submenu_page( $parent, $page_title, $menu_title, $capability, $slug, $callback = '', $position = null ) {
	$GLOBALS['bffb_test_submenus'][ $slug ] = [ 'parent' => $parent, 'title' => $page_title, 'capability' => $capability ];

	return ( '' === $parent ? 'admin_page_' : 'farmbooks_page_' ) . $slug;
}

$GLOBALS['bffb_test_options'] = [];

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['bffb_test_options'] ) ? $GLOBALS['bffb_test_options'][ $name ] : $default;
}

/**
 * A $wpdb that records every write, answers COUNT(*) with 0, and prepares
 * as core does for %s and %d.
 */
final class BFFB_Test_Wpdb {
	public string $prefix = 'wp_';
	public array $queries = [];
	public array $inserts = [];

	public function prepare( string $query, ...$args ): string {
		$args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args;

		return vsprintf( str_replace( [ '%s', '%d' ], [ "'%s'", '%d' ], $query ), $args );
	}

	public function query( string $query ) {
		$this->queries[] = $query;

		return 0;
	}

	public function get_var( string $query ) {
		$this->queries[] = $query;

		return 0;
	}

	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = [ $table, $data ];

		return 1;
	}
}
