<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks;

/**
 * Manages plugin settings stored in the bffb_settings table.
 *
 * Reads from and writes to {$wpdb->prefix}bffb_settings (not wp_options),
 * so settings survive plugin option pruning and are queryable.
 */
class Settings {

	/** @var array<string, mixed>|null In-memory cache of all settings. */
	private ?array $cache = null;

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
		$this->cache = null;
	}

	/**
	 * Return the default settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'farm_name'         => '',
			'fiscal_year_start' => '1',
			'tax_year'          => (string) (int) wp_date( 'Y' ),
			'currency_symbol'   => '$',
			'db_version'        => '1',
		);
	}

	/**
	 * Load all settings from the DB (cached per request).
	 *
	 * @return array<string, mixed>
	 */
	private function load_all(): array {
		if ( $this->cache !== null ) {
			return $this->cache;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'bffb_settings';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT `option_key`, `option_value` FROM `{$table}` LIMIT 500",
			ARRAY_A
		);

		$this->cache = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$this->cache[ (string) $row['option_key'] ] = $row['option_value'];
			}
		}

		return $this->cache;
	}
}
