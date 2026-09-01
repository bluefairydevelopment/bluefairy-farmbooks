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
	 * Schema version for the base tables.
	 *
	 * Bump this whenever install_tables() or the seeded rows change, and add
	 * the matching one-shot step to run_pending_migrations(). Until 2026-08-31
	 * this value was the literal '1' in two places, written on activation and
	 * read by nothing - so an existing farm never received a schema change, and
	 * Pro's own troubleshooting notes told operators to read it as the base
	 * schema version.
	 */
	private const DB_VERSION    = '3';
	private const VERSION_OPTION  = 'bffb_db_version';
	private const ATTEMPTS_OPTION = 'bffb_install_attempts';
	private const MAX_ATTEMPTS    = 3;


	/**
	 * Run on plugin activation.
	 */
	public function activate(): void {
		// Locked, like maybe_upgrade(): register_activation_hook and
		// wp_initialize_site are two more entry points into the same
		// check-then-insert seeding, and only the queue's call was guarded.
		$locked = bffb_claim_install_lock();

		try {
			$this->install_tables();
			$this->maybe_seed_accounts();
			$this->maybe_seed_categories();
			$this->maybe_seed_settings();

			if ( ! $this->tables_exist() ) {
				// Same reasoning as maybe_upgrade(): dbDelta neither throws nor
				// reports failure, so recording the version here would stamp a
				// site that has no tables as current - and the upgrade gate
				// would then short-circuit for ever, with the only diagnostic
				// unreachable.
				$this->report_install_failure();
				return;
			}

			$this->run_pending_migrations();
			$this->record_version();
			$this->clear_failed_site_marker();
		} finally {
			if ( $locked ) {
				bffb_release_install_lock();
			}
		}
	}

	/**
	 * Say that the tables could not be created, on whichever surface is here.
	 */
	private function report_install_failure(): void {
		add_action( 'admin_notices', array( $this, 'notice_install_failed' ) );

		// A site administered with `wp plugin update` never renders an admin
		// notice, so the one signal there has to be the CLI's own.
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::warning( 'FarmBooks could not create its database tables. Check that the database user has CREATE permission.' );
		}

		error_log( 'FarmBooks: could not create its database tables on blog ' . get_current_blog_id() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * This site installed cleanly, so take it off the network failure list.
	 *
	 * The list is written pessimistically before each queued site and cleared
	 * only by a later queue pass, so a site that self-healed through
	 * maybe_upgrade() stayed named in the network-admin error banner for ever.
	 */
	private function clear_failed_site_marker(): void {
		if ( ! is_multisite() ) {
			return;
		}

		$failed = (array) get_site_option( 'bffb_failed_site_installs', array() );
		$left   = array_values( array_diff( $failed, array( get_current_blog_id() ) ) );

		if ( $left === $failed ) {
			return;
		}

		if ( $left ) {
			update_site_option( 'bffb_failed_site_installs', $left );
		} else {
			delete_site_option( 'bffb_failed_site_installs' );
		}
	}

	/**
	 * Bring an existing install up to the current schema.
	 *
	 * Called on every admin request, gated on a wp_option so the healthy path
	 * costs an autoloaded read and no query. WordPress replaces plugin files
	 * on update without deactivating, so register_activation_hook never fires
	 * and anything reachable only from activate() reaches new installs only -
	 * which for a backfill written for existing installs is no reach at all.
	 */
	public function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		// A repair that cannot succeed must not be retried on every request.
		// Without this, a site whose database user lacks CREATE re-ran four
		// dbDelta calls and thirty-five failing INSERTs on every admin page
		// load and every WP-CLI call, holding the install lock while it did.
		$attempts = (int) get_option( self::ATTEMPTS_OPTION, 0 );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			add_action( 'admin_notices', array( $this, 'notice_install_failed' ) );
			return;
		}

		// The SAME lock the network-install queue and activate() take. They
		// used to hold different ones, so the queue could be running activate()
		// on this very site while this method seeded it - both seeing
		// COUNT(*) = 0, both inserting the whole default chart of accounts, and
		// neither able to delete the duplicates afterwards because every seeded
		// row is is_default = 1.
		$locked = bffb_claim_install_lock();

		try {
			$this->install_tables();
			$this->maybe_seed_accounts();
			$this->maybe_seed_categories();
			$this->maybe_seed_settings();

			if ( ! $this->tables_exist() ) {
				// dbDelta neither throws nor reports failure, so the only way
				// to know whether it worked is to look. Recording the version
				// anyway would short-circuit this method for ever on a site
				// whose tables were never created.
				update_option( self::ATTEMPTS_OPTION, $attempts + 1, false );
				$this->report_install_failure();
				return;
			}

			$this->run_pending_migrations();
			$this->record_version();
			delete_option( self::ATTEMPTS_OPTION );
			$this->clear_failed_site_marker();
		} finally {
			if ( $locked ) {
				bffb_release_install_lock();
			}
		}
	}

	/**
	 * Are all four base tables actually present?
	 */
	private function tables_exist(): bool {
		global $wpdb;

		foreach ( array( 'bffb_transactions', 'bffb_categories', 'bffb_accounts', 'bffb_settings' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = (string) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
			);

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	public function notice_install_failed(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'FarmBooks could not create its database tables. Check that the database user has CREATE permission, then deactivate and reactivate the plugin. Your books cannot be saved until this is resolved.', 'bluefairy-farmbooks' )
		);
	}

	private function record_version(): void {
		// Both: the option is the cheap gate, the settings row is what Pro's
		// troubleshooting notes tell an operator to read.
		update_option( self::VERSION_OPTION, self::DB_VERSION, true );
		$this->settings()->set( 'db_version', self::DB_VERSION );
	}

	/**
	 * One-shot migrations for installs that predate the current schema.
	 */
	private function run_pending_migrations(): void {
		// Deliberately NOT falling back to the bffb_settings db_version row.
		// An earlier release stamped that row with the current version before
		// the migration read it, so on exactly the installs that still need
		// backfilling it reads as already done. An absent option means this
		// mechanism has never run here; every step below is idempotent, so
		// re-running them on a healthy site costs nothing.
		$from = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $from < 2 ) {
			$this->backfill_farm_name();
		}

		if ( $from < 3 ) {
			$this->dedupe_and_lock_seeded_tables();
		}
	}

	/**
	 * Activation seeded farm_name as an EMPTY row, and Settings::get() resolves
	 * on array_key_exists - so the site-title fallback that Schedule F and the
	 * pay stubs both ask for could never fire. Seeding the title fixed new
	 * installs; every existing farm still has the empty row.
	 *
	 * It cannot distinguish "never filled in" from "deliberately cleared" -
	 * nothing records which. It runs exactly once, on the first upgrade to
	 * schema 2, so a farm that clears the field afterwards keeps it clear.
	 */
	/**
	 * Remove duplicate seeded rows, then make duplicates impossible.
	 *
	 * The default chart of accounts and the default categories are seeded with
	 * a COUNT(*) check followed by INSERTs, and nothing at the schema level
	 * stopped two concurrent installs from doing it twice. Every seeded row is
	 * is_default = 1, and the delete handlers refuse those - so a farm that hit
	 * the race had a doubled chart it could not clean up.
	 *
	 * Dedupe keeps the lowest id of each (name, type) and repoints anything
	 * that referenced a discarded twin, so no transaction is orphaned.
	 */
	private function dedupe_and_lock_seeded_tables(): void {
		global $wpdb;

		$accounts   = $wpdb->prefix . 'bffb_accounts';
		$categories = $wpdb->prefix . 'bffb_categories';
		$txns       = $wpdb->prefix . 'bffb_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Categories first: they point at accounts.
		$wpdb->query(
			"UPDATE `{$categories}` c
			   JOIN ( SELECT name, type, MIN(id) AS keep_id FROM `{$accounts}` GROUP BY name, type ) k
			     ON k.name = ( SELECT a.name FROM `{$accounts}` a WHERE a.id = c.account_id )
			    AND k.type = ( SELECT a.type FROM `{$accounts}` a WHERE a.id = c.account_id )
			    SET c.account_id = k.keep_id
			  WHERE c.account_id IS NOT NULL"
		);

		$wpdb->query(
			"DELETE a FROM `{$accounts}` a
			   JOIN ( SELECT name, type, MIN(id) AS keep_id FROM `{$accounts}` GROUP BY name, type ) k
			     ON k.name = a.name AND k.type = a.type
			  WHERE a.id <> k.keep_id"
		);

		$wpdb->query(
			"UPDATE `{$txns}` t
			   JOIN ( SELECT name, type, MIN(id) AS keep_id FROM `{$categories}` GROUP BY name, type ) k
			     ON k.name = ( SELECT c.name FROM `{$categories}` c WHERE c.id = t.category_id )
			    AND k.type = ( SELECT c.type FROM `{$categories}` c WHERE c.id = t.category_id )
			    SET t.category_id = k.keep_id
			  WHERE t.category_id IS NOT NULL"
		);

		$wpdb->query(
			"DELETE c FROM `{$categories}` c
			   JOIN ( SELECT name, type, MIN(id) AS keep_id FROM `{$categories}` GROUP BY name, type ) k
			     ON k.name = c.name AND k.type = c.type
			  WHERE c.id <> k.keep_id"
		);

		// Now the constraint, so no future race can reintroduce them. Added
		// here rather than in the CREATE TABLE statements because dbDelta will
		// not add a unique key to a table that already holds duplicates, and
		// this runs after they are gone.
		foreach ( array( $accounts, $categories ) as $table ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.STATISTICS
					  WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
					DB_NAME,
					$table,
					'uq_name_type'
				)
			);

			if ( 0 === $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_name_type` (`name`, `type`)" );
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function backfill_farm_name(): void {
		$settings = $this->settings();

		if ( '' === (string) $settings->get( 'farm_name', '' ) ) {
			$settings->set( 'farm_name', (string) get_bloginfo( 'name' ) );
		}
	}

	private ?Settings $settings = null;

	private function settings(): Settings {
		// Memoised. A fresh instance per call meant every caller paid for its
		// own full SELECT of the settings table.
		return $this->settings ??= new Settings();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public function deactivate(): void {
		// Leave no dangling scheduled event or half-finished queue behind if the
		// plugin is switched off part way through a network rollout.
		$timestamp = wp_next_scheduled( 'bffb_continue_site_installs' );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bffb_continue_site_installs' );
		}

		delete_site_option( 'bffb_pending_site_installs' );
		delete_site_option( 'bffb_failed_site_installs' );
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
  KEY idx_sort (sort_order),
  UNIQUE KEY uq_name_type (name, type)
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
  KEY idx_account (account_id),
  UNIQUE KEY uq_name_type (name, type)
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
			// db_version is NOT seeded here. maybe_seed_settings() runs before
			// run_pending_migrations(), so seeding the current version stamped
			// an install as migrated before anything had migrated it.
			// record_version() is the only writer.
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
