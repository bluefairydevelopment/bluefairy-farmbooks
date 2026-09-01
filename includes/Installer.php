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
	private const ATTEMPTS_OPTION         = 'bffb_install_attempts';
	private const ATTEMPTS_VERSION_OPTION = 'bffb_install_attempts_for';
	private const MAX_ATTEMPTS    = 3;


	/**
	 * Run on plugin activation.
	 */
	public function activate(): bool {
		// Locked, like maybe_upgrade(): register_activation_hook and
		// wp_initialize_site are two more entry points into the same
		// check-then-insert seeding, and only the queue's call was guarded.
		$locked = bffb_claim_install_lock();

		try {
			$this->install_tables();

			// Seed only while actually holding the lock. Proceeding without it
			// was justified by the unique key catching a race - which is not
			// true on an install where the key could not be added (a host
			// without ALTER, or a farm whose own row collides with a seeded
			// name). Another process holds the lock and is doing this anyway.
			if ( $locked ) {
				$this->maybe_seed_accounts();
				$this->maybe_seed_categories();
				$this->maybe_seed_settings();
			}

			if ( ! $this->tables_exist() ) {
				// Same reasoning as maybe_upgrade(): dbDelta neither throws nor
				// reports failure, so recording the version here would stamp a
				// site that has no tables as current - and the upgrade gate
				// would then short-circuit for ever, with the only diagnostic
				// unreachable.
				$this->report_install_failure();
				return false;
			}

			$this->run_pending_migrations();
			$this->record_version();
			delete_option( self::ATTEMPTS_OPTION );
			$this->clear_failed_site_marker();

			return true;
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
		// A new release carrying new migrations must not be blocked by a counter
		// the previous one exhausted. Moving DB_VERSION resets it.
		if ( (string) get_option( self::ATTEMPTS_VERSION_OPTION, '' ) !== self::DB_VERSION ) {
			delete_option( self::ATTEMPTS_OPTION );
			update_option( self::ATTEMPTS_VERSION_OPTION, self::DB_VERSION, false );
		}

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

			// Seed only while actually holding the lock. Proceeding without it
			// was justified by the unique key catching a race - which is not
			// true on an install where the key could not be added (a host
			// without ALTER, or a farm whose own row collides with a seeded
			// name). Another process holds the lock and is doing this anyway.
			if ( $locked ) {
				$this->maybe_seed_accounts();
				$this->maybe_seed_categories();
				$this->maybe_seed_settings();
			}

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
		$budgets    = $wpdb->prefix . 'bffb_budgets';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// ONLY seeded rows. The first version of this had no is_default
		// predicate, so a farm that had created its own "supplies" beside the
		// seeded "Supplies" - the same string under a case-insensitive
		// collation - lost its own row, with its own Schedule F line and
		// description, and could not recreate it. The duplicates this migration
		// exists for are all seeded, by definition: they came from the same
		// seeder running twice.
		$keep_accounts = "SELECT name, type, MIN(id) AS keep_id FROM `{$accounts}` WHERE is_default = 1 GROUP BY name, type";
		$keep_cats     = "SELECT name, type, MIN(id) AS keep_id FROM `{$categories}` WHERE is_default = 1 GROUP BY name, type";

		// Repoint every reference BEFORE deleting anything.
		//
		// transactions.account_id was missed the first time. Schedule F
		// resolves the account as COALESCE(t.account_id, c.account_id), so the
		// direct column WINS over the correctly repointed category - and a
		// transaction pointing at a deleted twin dropped out of the report
		// entirely, understating the deduction on a filed tax form.
		foreach ( array(
			array( $categories, 'c', 'account_id', $accounts, $keep_accounts ),
			array( $txns, 't', 'account_id', $accounts, $keep_accounts ),
			array( $txns, 't', 'category_id', $categories, $keep_cats ),
		) as list( $table, $alias, $column, $target, $keep_sql ) ) {
			$wpdb->query(
				"UPDATE `{$table}` {$alias}
				   JOIN `{$target}` src ON src.id = {$alias}.{$column}
				   JOIN ( {$keep_sql} ) k ON k.name = src.name AND k.type = src.type
				    SET {$alias}.{$column} = k.keep_id
				  WHERE {$alias}.{$column} IS NOT NULL
				    AND src.is_default = 1"
			);
		}

		// Pro's budgets, if Pro has ever been installed here. uq_category_year
		// means a repoint can collide, so discard the duplicate budget rather
		// than fail the statement - the surviving category keeps its own.
		if ( $this->table_exists( $budgets ) ) {
			$wpdb->query(
				"DELETE b FROM `{$budgets}` b
				   JOIN `{$categories}` src ON src.id = b.category_id
				   JOIN ( {$keep_cats} ) k ON k.name = src.name AND k.type = src.type
				  WHERE src.is_default = 1
				    AND b.category_id <> k.keep_id
				    AND EXISTS ( SELECT 1 FROM ( SELECT * FROM `{$budgets}` ) b2
				                  WHERE b2.category_id = k.keep_id AND b2.tax_year = b.tax_year )"
			);

			$wpdb->query(
				"UPDATE `{$budgets}` b
				   JOIN `{$categories}` src ON src.id = b.category_id
				   JOIN ( {$keep_cats} ) k ON k.name = src.name AND k.type = src.type
				    SET b.category_id = k.keep_id
				  WHERE src.is_default = 1"
			);
		}

		// Pro's WooCommerce mapping holds category ids in options, where no
		// JOIN can reach them. Left behind, every synced order after this
		// migration would be written against an id pointing at nothing.
		$this->repoint_wc_category_options( $categories, $keep_cats );

		$wpdb->query(
			"DELETE a FROM `{$accounts}` a
			   JOIN ( {$keep_accounts} ) k ON k.name = a.name AND k.type = a.type
			  WHERE a.is_default = 1 AND a.id <> k.keep_id"
		);

		$wpdb->query(
			"DELETE c FROM `{$categories}` c
			   JOIN ( {$keep_cats} ) k ON k.name = c.name AND k.type = c.type
			  WHERE c.is_default = 1 AND c.id <> k.keep_id"
		);

		$this->add_seeded_unique_keys( $accounts, $categories );

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Move Pro's stored category ids onto the surviving row.
	 */
	private function repoint_wc_category_options( string $categories, string $keep_sql ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results(
			"SELECT src.id AS old_id, k.keep_id
			   FROM `{$categories}` src
			   JOIN ( {$keep_sql} ) k ON k.name = src.name AND k.type = src.type
			  WHERE src.is_default = 1 AND src.id <> k.keep_id"
		);

		if ( ! $rows ) {
			return;
		}

		$map = array();

		foreach ( $rows as $row ) {
			$map[ (int) $row->old_id ] = (int) $row->keep_id;
		}

		$default = (int) get_option( 'bffbp_wc_default_category_id', 0 );

		if ( isset( $map[ $default ] ) ) {
			update_option( 'bffbp_wc_default_category_id', $map[ $default ], false );
		}

		$wc_map = get_option( 'bffbp_wc_category_map', array() );

		if ( is_array( $wc_map ) ) {
			$changed = false;

			foreach ( $wc_map as $key => $value ) {
				if ( isset( $map[ (int) $value ] ) ) {
					$wc_map[ $key ] = $map[ (int) $value ];
					$changed        = true;
				}
			}

			if ( $changed ) {
				update_option( 'bffbp_wc_category_map', $wc_map, false );
			}
		}
	}

	/**
	 * Add the unique key, but only when it can actually be added.
	 *
	 * The ALTER's result used to be discarded and the schema version stamped
	 * regardless - so on a host that grants CREATE but not ALTER the site was
	 * recorded at this schema with no unique key at all, while the install lock
	 * proceeds on a failed claim precisely BECAUSE the key is supposed to be
	 * there. And a farm whose own row collides with a seeded one under a
	 * case-insensitive collation cannot take the key at all; that is their data,
	 * so the key is skipped rather than their row deleted.
	 */
	private function add_seeded_unique_keys( string $accounts, string $categories ): void {
		global $wpdb;

		foreach ( array( $accounts, $categories ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.STATISTICS
					  WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
					DB_NAME,
					$table,
					'uq_name_type'
				)
			);

			if ( 0 !== $exists ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$collisions = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM ( SELECT name, type FROM `{$table}` GROUP BY name, type HAVING COUNT(*) > 1 ) d"
			);

			if ( $collisions > 0 ) {
				// Rows the farm created that clash with a seeded name. Adding
				// the key would fail; deleting theirs is not ours to do.
				error_log( "FarmBooks: could not add uq_name_type to {$table} - {$collisions} duplicate name/type pair(s) remain, at least one of them user-created." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_name_type` (`name`, `type`)" ) ) {
				error_log( "FarmBooks: could not add uq_name_type to {$table}; the database user may lack ALTER." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}

	private function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (string) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		) === $table;
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
			// Ordered and seeded-only. (name, type) is the uniqueness unit, so
			// name alone no longer identifies a row - and a farm's own account
			// sharing a seeded name must not be picked up here. Lowest id wins,
			// so the answer is stable rather than whatever the storage engine
			// happened to return first.
			$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT `id` FROM `{$acc_table}` WHERE `name` = %s AND `is_default` = 1 ORDER BY `id` ASC LIMIT 1",
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
