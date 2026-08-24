<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

use BlueFairy\FarmBooks\Settings;

/**
 * Registers the FarmBooks top-level admin menu and sub-pages.
 *
 * Pro plugins hook into do_action('bffb_admin_menu_items') to add
 * their own sub-pages (Vendors, Payroll, Budgets, WooCommerce).
 */
class AdminMenu {

	private Settings $settings;
	private TransactionEditPage $transaction_edit;

	public function __construct( Settings $settings, TransactionEditPage $transaction_edit ) {
		$this->settings         = $settings;
		$this->transaction_edit = $transaction_edit;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'bffb_add_menu' ) );
	}

	/**
	 * Register the top-level FarmBooks menu and Lite sub-pages.
	 */
	public function bffb_add_menu(): void {
		$dashboard = new DashboardPage( $this->settings );

		// Top-level menu entry.
		add_menu_page(
			__( 'FarmBooks', 'bluefairy-farmbooks-lite' ),
			__( 'FarmBooks', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-dashboard',
			array( $dashboard, 'render' ),
			'dashicons-money-alt',
			30
		);

		// Dashboard — points to the top-level slug.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks Dashboard', 'bluefairy-farmbooks-lite' ),
			__( 'Dashboard', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-dashboard',
			array( $dashboard, 'render' )
		);

		// Transactions.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Transactions', 'bluefairy-farmbooks-lite' ),
			__( 'Transactions', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-transactions',
			array( new TransactionsPage(), 'render' )
		);

		// Hidden add/edit page — empty parent keeps it off the sidebar.
		add_submenu_page(
			'',
			__( 'FarmBooks — Transaction', 'bluefairy-farmbooks-lite' ),
			__( 'Transaction', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-transaction-edit',
			array( $this->transaction_edit, 'render' )
		);

		// Accounts (Chart of Accounts).
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Accounts', 'bluefairy-farmbooks-lite' ),
			__( 'Accounts', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-accounts',
			array( new AccountsListPage(), 'render' )
		);

		// Categories.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Categories', 'bluefairy-farmbooks-lite' ),
			__( 'Categories', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-categories',
			array( new CategoriesListPage(), 'render' )
		);

		// Reports.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Reports', 'bluefairy-farmbooks-lite' ),
			__( 'Reports', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-reports',
			array( new ReportsPage(), 'render' )
		);

		// Settings.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Settings', 'bluefairy-farmbooks-lite' ),
			__( 'Settings', 'bluefairy-farmbooks-lite' ),
			'manage_options',
			'bffb-settings',
			array( new SettingsPage( $this->settings ), 'render' )
		);

		/**
		 * Fires after Lite sub-pages are registered.
		 * Pro hooks here to add Vendors, Payroll, Budgets, and WooCommerce pages.
		 *
		 * @since 1.0.0
		 */
		do_action( 'bffb_admin_menu_items' );
	}
}
