<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks;

defined( 'ABSPATH' ) || exit;

/**
 * Central plugin bootstrap — wires every component together.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Settings $settings;

	private function __construct() {
		$this->settings = new Settings();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot all plugin components.
	 */
	public function boot(): void {
		$this->load_textdomain();

		if ( is_admin() ) {
			$this->boot_admin();
		}

		/**
		 * Fires after BlueFairy FarmBooks Lite is fully booted.
		 * Pro and third-party plugins hook here to initialize their own components.
		 *
		 * Extension hooks registered by Lite for Pro and third-party use:
		 *
		 * do_action('bffb_loaded')                              — fires here, after full boot
		 * do_action('bffb_admin_menu_items')                    — fires after Lite submenus register
		 * do_action('bffb_dashboard_widgets')                   — fires inside DashboardPage::render()
		 * apply_filters('bffb_settings_tabs', $tabs)            — filters settings tab list
		 * apply_filters('bffb_reports_list', $reports)          — filters available reports
		 * apply_filters('bffb_transaction_form_fields', $fields, $transaction_id)
		 *                                                        — filters transaction form fields (Phase 3)
		 */
		do_action( 'bffb_loaded' );
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'bluefairy-farmbooks',
			false,
			dirname( plugin_basename( BFFB_FILE ) ) . '/languages'
		);
	}

	private function boot_admin(): void {
		$transaction_edit = new Admin\TransactionEditPage();

		// DashboardPage is constructed HERE, not inside the admin_menu callback.
		//
		// It registers admin_post_bffb_quick_add_transaction in its constructor,
		// and wp-admin/admin-post.php loads wp-load.php and includes/admin.php
		// only - it never loads menu.php, so admin_menu does not fire there.
		// Built inside bffb_add_menu() the handler was therefore never
		// registered on the one request that needs it, and Quick Add answered
		// every submission with HTTP 400.
		$dashboard = new Admin\DashboardPage( $this->settings );

		// Same reasoning as DashboardPage: these register admin_post_ handlers,
		// so they must be constructed on every admin request, not inside an
		// admin_menu callback that admin-post.php never fires.
		$category_edit = new Admin\CategoryEditPage();
		$account_edit  = new Admin\AccountEditPage();
		$menu = new Admin\AdminMenu( $this->settings, $transaction_edit, $dashboard, $category_edit, $account_edit );
		$menu->register();
	}

	public function get_settings(): Settings {
		return $this->settings;
	}
}
