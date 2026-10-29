<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks;

defined( 'ABSPATH' ) || exit;

/**
 * Manages plugin settings stored in the bffb_settings table.
 *
 * Reads from and writes to {$wpdb->prefix}bffb_settings (not wp_options),
 * so settings survive plugin option pruning and are queryable.
 */
class Settings {

	/** @var array<string, mixed>|null In-memory cache of all settings. */
	private static ?array $cache = null;

	/**
	 * Which blog the cache belongs to.
	 *
	 * Plugin::$settings is a singleton and survives switch_to_blog(), and the
	 * network install and uninstall loops both switch blogs. Without this, the
	 * first read after a switch would return the previous site's values from
	 * cache, silently.
	 */
	private static ?int $cache_blog_id = null;

	/**
	 * Get a setting value by key with optional fallback default.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->load_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		$defaults = self::defaults();
		return $defaults[ $key ] ?? $default;
	}

	/**
	 * Persist a single setting value by key.
	 *
	 * Uses INSERT … ON DUPLICATE KEY UPDATE to handle both insert and update.
	 */
	public function set( string $key, mixed $value ): void {
		self::$cache = null;

		global $wpdb;

		$table        = $wpdb->prefix . 'bffb_settings';
		$string_value = is_array( $value ) || is_object( $value )
			? wp_json_encode( $value )
			: (string) $value;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT INTO `{$table}` (`option_key`, `option_value`, `autoload`)
                 VALUES (%s, %s, 1)
                 ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`)",
				$key,
				$string_value
			)
		);

		// Invalidate cache.
	}

	/**
	 * Return the default settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'farm_name'         => '',
			// Read by Pro's Schedule F header. It had no default and no writer,
			// so that report always printed "EIN: —" with no way to set one.
			'farm_ein'          => '',
			'fiscal_year_start' => '1',
			'tax_year'          => (string) (int) wp_date( 'Y' ),
			'currency_symbol'   => '$',
			// Deliberately 1, not Installer::DB_VERSION: a missing row must read
			// as "never migrated" so the migrations run. Raising this to match
			// the installer would silently disable every one of them.
			'db_version'        => '1',
		);
	}

	/**
	 * Load all settings from the DB (cached per request).
	 *
	 * @return array<string, mixed>
	 */
	/**
	 * Load every setting row.
	 *
	 * The cap was 500. option_key is a free-form varchar and the table is
	 * documented as extensible, so anything past row 500 would have become
	 * permanently unreadable through get() — silently, because
	 * array_key_exists() on the truncated cache just falls through to the coded
	 * default. Raised, and it now says so in the log if it is ever reached.
	 *
	 * @return array<string, mixed>
	 */
	private function load_all(): array {
		$blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;

		if ( self::$cache !== null && self::$cache_blog_id === $blog_id ) {
			return self::$cache;
		}

		self::$cache_blog_id = $blog_id;

		global $wpdb;

		$table = $wpdb->prefix . 'bffb_settings';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT `option_key`, `option_value` FROM `{$table}` LIMIT 5000",
			ARRAY_A
		);

		self::$cache = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				self::$cache[ (string) $row['option_key'] ] = $row['option_value'];
			}
		}

		return self::$cache;
	}

	/**
	 * First day of the current fiscal year, as Y-m-d.
	 *
	 * Lives here because the fiscal_year_start setting lives here. Free's YTD
	 * stat widgets and Pro's YTD charts render on the same screen, inches
	 * apart, and each used to compute this window from its own private copy of
	 * the same nine lines - so a fix to one silently left the other covering a
	 * different period, with neither number labelled with its window.
	 */
	public function fiscal_year_start_date(): string {
		$fiscal_month  = max( 1, min( 12, (int) $this->get( 'fiscal_year_start', 1 ) ) );
		$current_year  = (int) wp_date( 'Y' );
		$current_month = (int) wp_date( 'n' );

		$year = ( $current_month >= $fiscal_month ) ? $current_year : $current_year - 1;

		return sprintf( '%04d-%02d-01', $year, $fiscal_month );
	}
}
