<?php
/**
 * Schedule F (2025) Part I: custom hire income is line 7, other income line 8,
 * gross income line 9. The chart of accounts seeded them on 8 and 9 until
 * 1.0.2; the upgrade to schema 4 moves them.
 *
 * @package BlueFairy\FarmBooks
 */

declare(strict_types=1);

namespace BlueFairy\FarmBooks\Tests\Unit;

use BlueFairy\FarmBooks\Installer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ScheduleFLinesTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpdb']               = new \BFFB_Test_Wpdb();
		$GLOBALS['bffb_test_options'] = [];
	}

	private static function call( string $method, ...$args ): void {
		$installer = ( new ReflectionClass( Installer::class ) )->newInstanceWithoutConstructor();
		( new ReflectionMethod( Installer::class, $method ) )->invoke( $installer, ...$args );
	}

	public function test_a_fresh_chart_puts_custom_hire_on_7_and_other_income_on_8(): void {
		self::call( 'maybe_seed_accounts' );

		$lines = [];
		foreach ( $GLOBALS['wpdb']->inserts as [ , $row ] ) {
			$lines[ $row['name'] ] = $row['schedule_f_line'];
		}
		$this->assertCount( 33, $lines, 'control: the whole chart was seeded' );
		$this->assertSame( '7', $lines['Custom hire income'] );
		$this->assertSame( '8', $lines['Other farm income'] );
		$this->assertNotContains( '9', $lines, 'line 9 is gross income, not an account' );
	}

	public function test_the_upgrade_moves_custom_hire_first_then_other_income_and_own_accounts_too(): void {
		self::call( 'move_part_i_income_lines', true );

		$q = $GLOBALS['wpdb']->queries;
		$this->assertCount( 2, $q );
		$this->assertStringContainsString( "SET schedule_f_line = '7' WHERE type = 'income' AND schedule_f_line = '8'", $q[0] );
		$this->assertStringContainsString( "name = 'Custom hire income'", $q[0] );
		$this->assertStringContainsString( "SET schedule_f_line = '8' WHERE type = 'income' AND schedule_f_line = '9'", $q[1] );
		$this->assertStringContainsString( "name = 'Other farm income'", $q[1] );
		foreach ( $q as $sql ) {
			$this->assertStringContainsString( 'OR is_default = 0', $sql, 'a farm\'s own accounts were chosen against the old labels' );
		}
	}

	public function test_a_fresh_install_moves_only_the_seeded_rows_by_name(): void {
		self::call( 'move_part_i_income_lines', false );

		foreach ( $GLOBALS['wpdb']->queries as $sql ) {
			$this->assertStringNotContainsString( 'is_default = 0', $sql );
			$this->assertStringContainsString( 'is_default = 1 AND name =', $sql );
		}
	}

	public function test_run_pending_migrations_moves_the_lines_below_schema_4_only(): void {
		$GLOBALS['bffb_test_options']['bffb_db_version'] = '4';
		self::call( 'run_pending_migrations' );
		$this->assertSame( [], preg_grep( '/schedule_f_line = \'7\'/', $GLOBALS['wpdb']->queries ), 'a current site: nothing moved' );

		$GLOBALS['wpdb']                                = new \BFFB_Test_Wpdb();
		$GLOBALS['bffb_test_options']['bffb_db_version'] = '3';
		self::call( 'run_pending_migrations' );
		$moved = preg_grep( "/SET schedule_f_line = '7'/", $GLOBALS['wpdb']->queries );
		$this->assertNotEmpty( $moved, 'an upgrading site (schema 3) moves the lines' );
		$this->assertStringContainsString( 'OR is_default = 0', (string) reset( $moved ), 'as an existing install' );
	}
}
