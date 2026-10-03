<?php
/**
 * The screens reached by link and not listed in the menu (Transaction, Edit
 * Category, Edit Account): WordPress finds no title for a page with no parent,
 * and admin-header.php then called strip_tags( null ), a PHP 8.1+ deprecation
 * logged on every visit (seen on the rig's web log, 2026-10-03). Each sets its
 * own title on its load-{hook} action, before the header.
 *
 * @package BlueFairy\FarmBooks
 */

declare(strict_types=1);

namespace BlueFairy\FarmBooks\Tests\Unit;

use BlueFairy\FarmBooks\Admin\AdminMenu;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminMenuTest extends TestCase {

	private const HIDDEN = [
		'bffb-transaction-edit' => 'FarmBooks — Transaction',
		'bffb-category-edit'    => 'FarmBooks — Edit Category',
		'bffb-account-edit'     => 'FarmBooks — Edit Account',
	];

	protected function setUp(): void {
		bffb_test_reset();
		unset( $GLOBALS['title'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['title'] );
	}

	/**
	 * The menu without its collaborators' constructors (they read settings
	 * and the database): only their render callbacks are registered here.
	 */
	private static function menu(): AdminMenu {
		$menu = ( new ReflectionClass( AdminMenu::class ) )->newInstanceWithoutConstructor();
		foreach ( ( new ReflectionClass( AdminMenu::class ) )->getProperties() as $property ) {
			$type = $property->getType();
			if ( $type instanceof \ReflectionNamedType && ! $type->isBuiltin() ) {
				$property->setValue( $menu, ( new ReflectionClass( $type->getName() ) )->newInstanceWithoutConstructor() );
			}
		}

		return $menu;
	}

	public function test_each_hidden_screen_has_no_parent_and_sets_its_title_before_the_header(): void {
		self::menu()->bffb_add_menu();

		foreach ( self::HIDDEN as $slug => $page_title ) {
			$this->assertSame( '', $GLOBALS['bffb_test_submenus'][ $slug ]['parent'] ?? null, "{$slug}: registered, not listed" );
			$this->assertSame( 'manage_options', $GLOBALS['bffb_test_submenus'][ $slug ]['capability'] );

			$load = 'load-admin_page_' . $slug;
			$this->assertCount( 1, $GLOBALS['bffb_test_hooks'][ $load ] ?? [], "{$slug}: one {$load} action" );

			unset( $GLOBALS['title'] );
			do_action( $load );
			$this->assertSame( $page_title, $GLOBALS['title'] ?? null, "{$slug}: the title core's header reads" );
		}
	}

	public function test_a_listed_screen_gets_no_title_hook(): void {
		self::menu()->bffb_add_menu();

		$this->assertSame( 'bffb-dashboard', $GLOBALS['bffb_test_submenus']['bffb-transactions']['parent'], 'control: listed' );
		$this->assertArrayNotHasKey( 'load-farmbooks_page_bffb-transactions', $GLOBALS['bffb_test_hooks'], 'core finds its title itself' );
	}
}
