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
		// A plugin update replaces the files without deactivating, so the
		// activation hook never fires and anything gated behind it reaches new
		// installs only. Outside boot_admin() because is_admin() is false under
		// WP-CLI, and a site administered entirely through `wp plugin update`
		// would otherwise never migrate. Costs one autoloaded option read.
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			( new Installer() )->maybe_upgrade();
		}

		$this->load_textdomain();

		if ( is_admin() ) {
			$this->boot_admin();
		}

		/**
		 * Fires after BlueFairy FarmBooks is fully booted.
		 * Pro and third-party plugins hook here to initialise their own components.
		 *
		 * The full seam list, which this block used to give incompletely - it
		 * named six of the twenty-six and still advertised a filter that cannot do
		 * what it claims. A developer reads this first, so it has to be right.
		 *
		 * Lifecycle
		 *   do_action('bffb_loaded')                     — after full boot
		 *   do_action('bffb_admin_menu_items')           — after the submenus register
		 *
		 * Screens
		 *   do_action('bffb_dashboard_widgets')
		 *   do_action('bffb_transactions_page_actions')
		 *   do_action('bffb_accounts_page_actions')
		 *   do_action('bffb_categories_page_actions')
		 *   apply_filters('bffb_settings_tabs', $tabs)   — adds a tab
		 *   do_action('bffb_save_settings_tab', $tab, $post)
		 *                                                — save YOUR tab's fields here.
		 *   apply_filters('bffb_settings_saved_notice', $notice, $tab)
		 *                                                — and say so afterwards, or the
		 *                                                  screen reports "Nothing was
		 *                                                  saved" over your save.
		 *                                                  Do NOT hook
		 *                                                  admin_post_bffb_save_settings:
		 *                                                  this plugin answers it at
		 *                                                  priority 10 and exits.
		 *   apply_filters('bffb_reports_list', $reports)
		 *   do_action('bffb_render_report', $slug, $filters)
		 *
		 * Row actions
		 *   apply_filters('bffb_transaction_row_actions', $actions, $transaction)
		 *   apply_filters('bffb_account_row_actions', $actions, $account)
		 *   apply_filters('bffb_category_row_actions', $actions, $category)
		 *
		 * Edit forms — to ADD A FIELD use the *_extra_fields ACTION. The
		 * *_form_fields FILTERS pass their return through wp_kses_post(),
		 * which strips input, select, option and textarea, so they can add
		 * markup around a field but never the field itself.
		 *   do_action('bffb_transaction_extra_fields', $transaction)
		 *   do_action('bffb_category_extra_fields', $category)
		 *   do_action('bffb_account_extra_fields', $account)
		 *   apply_filters('bffb_transaction_form_fields', $fields, $transaction)
		 *   apply_filters('bffb_category_form_fields', $fields, $category)
		 *   apply_filters('bffb_account_form_fields', $fields, $account)
		 *
		 * After a write — pair these with the *_extra_fields actions above, or
		 * an added field renders and is discarded on save.
		 *   do_action('bffb_transaction_saved', $id, $post)
		 *   do_action('bffb_transaction_deleted', $id)
		 *   do_action('bffb_category_saved', $id, $post)
		 *   do_action('bffb_account_saved', $id, $post)
		 *
		 * Before a delete — return $in_use PLUS your own references, never a
		 * bare count.
		 *   apply_filters('bffb_category_in_use', $in_use, $category_id)
		 *   apply_filters('bffb_account_in_use', $in_use, $account_id)
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

		// And SettingsPage, for the same reason. Moving its save off the
		// menu-page callback and onto admin-post.php fixed a redirect that
		// could never set a header - and immediately reintroduced the older
		// bug one line up, because the class was still only constructed inside
		// bffb_add_menu(). Nothing was hooked to admin_post_bffb_save_settings
		// on the request that needed it, so every save died with a blank 400.
		$settings_page = new Admin\SettingsPage( $this->settings );

		$menu = new Admin\AdminMenu( $this->settings, $transaction_edit, $dashboard, $category_edit, $account_edit, $settings_page );
		$menu->register();
	}

	public function get_settings(): Settings {
		return $this->settings;
	}
}
