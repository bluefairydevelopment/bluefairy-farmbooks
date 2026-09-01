<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

defined( 'ABSPATH' ) || exit;

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

	private DashboardPage $dashboard;
	private CategoryEditPage $category_edit;
	private AccountEditPage $account_edit;
	private SettingsPage $settings_page;

	public function __construct(
		Settings $settings,
		TransactionEditPage $transaction_edit,
		DashboardPage $dashboard,
		CategoryEditPage $category_edit,
		AccountEditPage $account_edit,
		SettingsPage $settings_page
	) {
		$this->settings         = $settings;
		$this->transaction_edit = $transaction_edit;
		$this->dashboard        = $dashboard;
		$this->category_edit    = $category_edit;
		$this->account_edit     = $account_edit;
		$this->settings_page    = $settings_page;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'bffb_add_menu' ) );

		// The base advertises these four seams on its own list screens and,
		// until 2026-08-31, answered none of them — the Add buttons and Edit
		// links were contributed by Pro, so a free install could never create
		// or edit an account or a category.
		add_filter( 'bffb_category_row_actions', array( $this, 'category_row_actions' ), 10, 2 );
		add_filter( 'bffb_account_row_actions', array( $this, 'account_row_actions' ), 10, 2 );
		add_action( 'bffb_categories_page_actions', array( $this, 'categories_page_add_button' ) );
		add_action( 'bffb_accounts_page_actions', array( $this, 'accounts_page_add_button' ) );
	}

	/**
	 * Register the top-level FarmBooks menu and Lite sub-pages.
	 */
	public function bffb_add_menu(): void {
		$dashboard = $this->dashboard;

		// Top-level menu entry.
		add_menu_page(
			__( 'FarmBooks', 'bluefairy-farmbooks' ),
			__( 'FarmBooks', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-dashboard',
			array( $dashboard, 'render' ),
			'dashicons-money-alt',
			30
		);

		// Dashboard — points to the top-level slug.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks Dashboard', 'bluefairy-farmbooks' ),
			__( 'Dashboard', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-dashboard',
			array( $dashboard, 'render' )
		);

		// Transactions.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Transactions', 'bluefairy-farmbooks' ),
			__( 'Transactions', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-transactions',
			array( new TransactionsPage( $this->settings ), 'render' )
		);

		// Hidden add/edit page — empty parent keeps it off the sidebar.
		add_submenu_page(
			'',
			__( 'FarmBooks — Transaction', 'bluefairy-farmbooks' ),
			__( 'Transaction', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-transaction-edit',
			array( $this->transaction_edit, 'render' )
		);

		// Accounts (Chart of Accounts).
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Accounts', 'bluefairy-farmbooks' ),
			__( 'Accounts', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-accounts',
			array( new AccountsListPage(), 'render' )
		);

		// Categories.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Categories', 'bluefairy-farmbooks' ),
			__( 'Categories', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-categories',
			array( new CategoriesListPage(), 'render' )
		);

		// Reports.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Reports', 'bluefairy-farmbooks' ),
			__( 'Reports', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-reports',
			array( new ReportsPage(), 'render' )
		);

		// Settings.
		add_submenu_page(
			'bffb-dashboard',
			__( 'FarmBooks — Settings', 'bluefairy-farmbooks' ),
			__( 'Settings', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-settings',
			array( $this->settings_page, 'render' )
		);

		// The two hidden edit screens for the base's own entities. Registered
		// with an empty parent so they stay off the sidebar.
		add_submenu_page(
			'',
			__( 'FarmBooks — Edit Category', 'bluefairy-farmbooks' ),
			__( 'Edit Category', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-category-edit',
			array( $this->category_edit, 'render' )
		);

		add_submenu_page(
			'',
			__( 'FarmBooks — Edit Account', 'bluefairy-farmbooks' ),
			__( 'Edit Account', 'bluefairy-farmbooks' ),
			'manage_options',
			'bffb-account-edit',
			array( $this->account_edit, 'render' )
		);

		/**
		 * Fires after Lite sub-pages are registered.
		 * Pro hooks here to add Vendors, Payroll, Budgets, and WooCommerce pages.
		 *
		 * @since 1.0.0
		 */
		do_action( 'bffb_admin_menu_items' );
	}

	public function category_row_actions( array $actions, object $item ): array {
		$actions['edit'] = '<a href="'
			. esc_url( admin_url( 'admin.php?page=bffb-category-edit&id=' . (int) $item->id ) )
			. '">' . esc_html__( 'Edit', 'bluefairy-farmbooks' ) . '</a>';

		if ( empty( $item->is_default ) ) {
			$delete_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=bffb_delete_category&id=' . (int) $item->id ),
				'bffb_delete_category_' . (int) $item->id
			);

			$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" class="submitdelete">'
				. esc_html__( 'Delete', 'bluefairy-farmbooks' ) . '</a>';
		}

		return $actions;
	}

	public function account_row_actions( array $actions, object $item ): array {
		$actions['edit'] = '<a href="'
			. esc_url( admin_url( 'admin.php?page=bffb-account-edit&id=' . (int) $item->id ) )
			. '">' . esc_html__( 'Edit', 'bluefairy-farmbooks' ) . '</a>';

		if ( empty( $item->is_default ) ) {
			$delete_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=bffb_delete_account&id=' . (int) $item->id ),
				'bffb_delete_account_' . (int) $item->id
			);

			$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" class="submitdelete">'
				. esc_html__( 'Delete', 'bluefairy-farmbooks' ) . '</a>';
		}

		return $actions;
	}

	public function categories_page_add_button(): void {
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=bffb-category-edit' ) ),
			esc_html__( 'Add Category', 'bluefairy-farmbooks' )
		);
	}

	public function accounts_page_add_button(): void {
		printf(
			'<a href="%s" class="page-title-action">%s</a>',
			esc_url( admin_url( 'admin.php?page=bffb-account-edit' ) ),
			esc_html__( 'Add Account', 'bluefairy-farmbooks' )
		);
	}
}
