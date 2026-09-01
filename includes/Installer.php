<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation, deactivation, and database installation.
 *
 * IMPORTANT: dbDelta() requires exactly 2-space indentation per column/key line.
 * Do not reformat the SQL strings inside install_tables().
 */
class Installer {

	/**
	 * Run on plugin activation.
	 */
	public function activate(): void {
		$this->install_tables();
		$this->maybe_seed_accounts();
		$this->maybe_seed_categories();
		$this->maybe_seed_settings();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public function deactivate(): void {
		// Nothing to unschedule — no cron events registered by this plugin (Lite).
	}

	/**
	 * Create or upgrade all plugin tables using dbDelta.
	 * Called on activation and also on new multisite site creation.
	 */
	public function install_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		// ------------------------------------------------------------------ //
		// bffb_accounts — Chart of accounts, aligned to IRS Schedule F lines.
		// ------------------------------------------------------------------ //
		$sql_accounts = "CREATE TABLE `{$p}bffb_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` ENUM('income','expense','asset','liability','equity') NOT NULL,
  `schedule_f_line` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY  (id),
  KEY idx_type (type),
  KEY idx_sort (sort_order)
) {$charset_collate};";

		dbDelta( $sql_accounts );

		// ------------------------------------------------------------------ //
		// bffb_categories — Farm income/expense categories.
		// ------------------------------------------------------------------ //
		$sql_categories = "CREATE TABLE `{$p}bffb_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY  (id),
  KEY idx_type (type),
  KEY idx_account (account_id)
) {$charset_collate};";

		dbDelta( $sql_categories );

		// ------------------------------------------------------------------ //
		// bffb_transactions — Master transaction ledger.
		// ------------------------------------------------------------------ //
		$sql_transactions = "CREATE TABLE `{$p}bffb_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` ENUM('income','expense') NOT NULL,
  `date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `account_id` bigint(20) unsigned DEFAULT NULL,
  `memo` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY  (id),
  KEY idx_type_date (type,date),
  KEY idx_category (category_id),
  KEY idx_date (date)
) {$charset_collate};";

		dbDelta( $sql_transactions );

		// ------------------------------------------------------------------ //
		// bffb_settings — Plugin settings key-value store.
		// ------------------------------------------------------------------ //
		$sql_settings = "CREATE TABLE `{$p}bffb_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `option_key` varchar(100) NOT NULL,
  `option_value` longtext DEFAULT NULL,
  `autoload` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_option_key (option_key)
) {$charset_collate};";

		dbDelta( $sql_settings );
	}

	/**
	 * Seed default Chart of Accounts on fresh install.
	 * Only seeds if the accounts table is empty.
	 */
	private function maybe_seed_accounts(): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'bffb_accounts';
		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM `{$table}`"
		);

		if ( (int) $existing > 0 ) {
			return;
		}

		$income_accounts = array(
			array( 'Livestock and other resale sales', 'income', '1' ),
			array( 'Crop sales', 'income', '2' ),
			array( 'Cooperative distributions', 'income', '3a' ),
			array( 'Agricultural program payments', 'income', '4a' ),
			array( 'CCC loans', 'income', '5a' ),
			array( 'Crop insurance and disaster payments', 'income', '6a' ),
			array( 'Custom hire income', 'income', '8' ),
			array( 'Other farm income', 'income', '9' ),
		);

		$expense_accounts = array(
			array( 'Car and truck expenses', 'expense', '10' ),
			array( 'Chemicals', 'expense', '11' ),
			array( 'Conservation expenses', 'expense', '12' ),
			array( 'Custom hire', 'expense', '13' ),
			array( 'Depreciation', 'expense', '14' ),
			array( 'Employee benefit programs', 'expense', '15' ),
			array( 'Feed', 'expense', '16' ),
			array( 'Fertilizers and lime', 'expense', '17' ),
			array( 'Freight and trucking', 'expense', '18' ),
			array( 'Gasoline, fuel, and oil', 'expense', '19' ),
			array( 'Insurance', 'expense', '20' ),
			array( 'Interest — mortgage', 'expense', '21a' ),
			array( 'Interest — other', 'expense', '21b' ),
			array( 'Labor hired', 'expense', '22' ),
			array( 'Pension and profit-sharing', 'expense', '23' ),
			array( 'Rent/lease — vehicles and equipment', 'expense', '24a' ),
			array( 'Rent/lease — other property', 'expense', '24b' ),
			array( 'Repairs and maintenance', 'expense', '25' ),
			array( 'Seeds and plants', 'expense', '26' ),
			array( 'Storage and warehousing', 'expense', '27' ),
			array( 'Supplies', 'expense', '28' ),
			array( 'Taxes', 'expense', '29' ),
			array( 'Utilities', 'expense', '30' ),
			array( 'Veterinary and breeding', 'expense', '31' ),
			array( 'Other expenses', 'expense', '32' ),
		);

		$all_accounts = array_merge( $income_accounts, $expense_accounts );
		$sort         = 1;

		foreach ( $all_accounts as [$name, $type, $line] ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'name'            => $name,
					'type'            => $type,
					'schedule_f_line' => $line,
					'sort_order'      => $sort++,
					'is_default'      => 1,
				),
				array( '%s', '%s', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Seed 10 default categories on fresh install.
	 * Only seeds if the categories table is empty.
	 * Links each category to its matching default account.
	 */
	private function maybe_seed_categories(): void {
		global $wpdb;

		$cat_table = $wpdb->prefix . 'bffb_categories';
		$existing  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM `{$cat_table}`"
		);

		if ( (int) $existing > 0 ) {
			return;
		}

		$acc_table = $wpdb->prefix . 'bffb_accounts';

		/**
		 * Helper: look up an account ID by name.
		 *
		 * @param string $name
		 * @return int|null
		 */
		$get_account_id = function ( string $name ) use ( $wpdb, $acc_table ): ?int {
			$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT `id` FROM `{$acc_table}` WHERE `name` = %s LIMIT 1",
					$name
				)
			);
			return $id !== null ? (int) $id : null;
		};

		$defaults = array(
			array(
				'name'         => 'Crop Sales',
				'type'         => 'income',
				'account_name' => 'Crop sales',
				'sort_order'   => 1,
			),
			array(
				'name'         => 'Livestock Sales',
				'type'         => 'income',
				'account_name' => 'Livestock and other resale sales',
				'sort_order'   => 2,
			),
			array(
				'name'         => 'Government Payments',
				'type'         => 'income',
				'account_name' => 'Agricultural program payments',
				'sort_order'   => 3,
			),
			array(
				'name'         => 'Custom Hire Income',
				'type'         => 'income',
				'account_name' => 'Custom hire income',
				'sort_order'   => 4,
			),
			array(
				'name'         => 'Other Farm Income',
				'type'         => 'income',
				'account_name' => 'Other farm income',
				'sort_order'   => 5,
			),
			array(
				'name'         => 'Feed & Supplies',
				'type'         => 'expense',
				'account_name' => 'Feed',
				'sort_order'   => 6,
			),
			array(
				'name'         => 'Seeds & Fertilizer',
				'type'         => 'expense',
				'account_name' => 'Seeds and plants',
				'sort_order'   => 7,
			),
			array(
				'name'         => 'Fuel & Oil',
				'type'         => 'expense',
				'account_name' => 'Gasoline, fuel, and oil',
				'sort_order'   => 8,
			),
			array(
				'name'         => 'Equipment & Repairs',
				'type'         => 'expense',
				'account_name' => 'Repairs and maintenance',
				'sort_order'   => 9,
			),
			array(
				'name'         => 'Labor Hired',
				'type'         => 'expense',
				'account_name' => 'Labor hired',
				'sort_order'   => 10,
			),
		);

		foreach ( $defaults as $cat ) {
			$account_id = $get_account_id( $cat['account_name'] );

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$cat_table,
				array(
					'name'       => $cat['name'],
					'type'       => $cat['type'],
					'account_id' => $account_id,
					'is_default' => 1,
					'sort_order' => $cat['sort_order'],
				),
				array( '%s', '%s', $account_id !== null ? '%d' : '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Seed default settings into bffb_settings on fresh install.
	 * Only inserts rows that do not already exist.
	 */
	private function maybe_seed_settings(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bffb_settings';

		$defaults = array(
			// Not '': the row exists after activation, and Settings::get()
			// resolves on array_key_exists, so an empty row beats every
			// caller's fallback. Schedule F printed "Farm Name:" followed by
			// nothing and every pay stub had a blank employer heading, on a
			// fresh install, for ever - while both call sites were explicitly
			// asking for the site title.
			'farm_name'         => (string) get_bloginfo( 'name' ),
			'fiscal_year_start' => '1',
			'tax_year'          => (string) (int) wp_date( 'Y' ),
			'currency_symbol'   => '$',
			'db_version'        => '1',
		);

		foreach ( $defaults as $key => $value ) {
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM `{$table}` WHERE `option_key` = %s LIMIT 1",
					$key
				)
			);

			if ( (int) $existing === 0 ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'option_key'   => $key,
						'option_value' => $value,
						'autoload'     => 1,
					),
					array( '%s', '%s', '%d' )
				);
			}
		}
	}
}
