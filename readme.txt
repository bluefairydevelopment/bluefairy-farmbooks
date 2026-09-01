=== BlueFairy FarmBooks ===
Contributors: bluefairydevelopment
Tags: farm accounting, schedule f, income expense, agriculture, bookkeeping
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Farm income and expense tracking aligned to IRS Schedule F — free for small family farms, right inside WordPress.

== Description ==

BlueFairy FarmBooks gives small family farms a dedicated accounting ledger inside WordPress, built around the IRS Schedule F line structure so tax preparation is straightforward.

FarmBooks is designed for farmers who want to stop wrestling with spreadsheets or generic accounting software that has no idea what a "custom hire" expense is. Every category, every account, and every report is built around how the IRS actually asks farms to report their income and expenses.

**Included in FarmBooks:**

* Chart of accounts with 33 default Schedule F accounts (income lines 1–9, expense lines 10–32)
* 10 default farm categories linked to their accounts
* Transaction ledger — date, amount, type (income/expense), category, memo
* WP_List_Table with search, filters, and column sorting
* Reports: income list, expense list, and YTD totals
* Dashboard: YTD summary widgets + 10 most-recent transactions + quick-add form
* Settings: farm name, fiscal year start, tax year, currency symbol
* Extension hooks so it can be extended (see below)

**Extending FarmBooks:**

FarmBooks exposes a set of `do_action` and `apply_filters` hooks so developers can add vendors, custom categories, budgets, payroll, or reports without patching the core plugin. The full hook list is documented in the plugin's `docs/FEATURES.md`.

== Installation ==

**Automatic install:**

1. In WordPress admin go to **Plugins → Add New**.
2. Search for "BlueFairy FarmBooks".
3. Click **Install Now**, then **Activate**.

**Zip upload:**

1. Download the plugin zip.
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Choose the zip, click **Install Now**, then **Activate**.

**FTP / SFTP:**

1. Unzip the plugin.
2. Upload the `bluefairy-farmbooks` folder to `/wp-content/plugins/`.
3. In WordPress admin activate via **Plugins → Installed Plugins**.

**After activation:** open **FarmBooks** in the sidebar. The chart of accounts and default categories are seeded automatically on activation. Set the farm name, fiscal year start, and tax year under **FarmBooks → Settings** before entering transactions.

== Frequently Asked Questions ==

= Does this replace QuickBooks? =

FarmBooks handles farm income and expense tracking aligned to Schedule F. It is not a full double-entry accounting system, but it covers the core record-keeping most small farms need for tax preparation and gives you Schedule F line totals your accountant can file directly.

= Where does the data live? =

Everything is stored in your WordPress database, in tables prefixed `bffb_` (after your site's own table prefix, so usually `wp_bffb_`). Nothing is sent to any external service. If you migrate hosts, back up your database as normal.

= Is my data safe if I deactivate the plugin? =

Yes. Deactivation preserves all data. To remove all FarmBooks data from the database when deleting the plugin, add `define( 'BFFB_REMOVE_DATA', true );` to `wp-config.php` before deleting.

= Can I import historical transactions? =

Not in the version. General CSV import is on the roadmap. If you sell farm products through WooCommerce, the FarmBooks Pro add-on can bulk-import all completed historical orders as income transactions.

= Can I customise the chart of accounts? =

the free plugin ships a fixed Schedule F–aligned chart of accounts (read-only). Custom accounts and categories are available in the Pro add-on.

= Does it support multiple farms? =

The current version tracks a single farm operation. Multi-farm support is planned for v2.0 of the Pro add-on.

= Where can I get help? =

Support requests are handled at [bluefairydevelopment.com](https://bluefairydevelopment.com). Please include your WordPress version, PHP version, and the exact steps that produced the issue.

== Screenshots ==

1. Dashboard — YTD income, expenses, net, and most-recent transactions.
2. Transaction ledger with search, filters, and column sorting.
3. Add / edit transaction form.
4. Reports — expense list, income list, YTD totals.

== Changelog ==

= 1.0.1 =
* Fixed: saving Settings from a tab added by another plugin wiped farm name, EIN, fiscal year start, tax year and currency.
* Fixed: the Settings form now redirects after saving, so a reload no longer re-submits it.
* Fixed: a fresh install seeded an empty farm name, so Schedule F and pay stubs printed a blank one. Existing installs are backfilled.
* Fixed: default accounts and categories could be deleted with a hand-built link despite the button being hidden.
* Fixed: the delete guards now see references held by add-ons, not only this plugin's own transactions.
* Fixed: tables are created and removed on every site of a multisite network.
* Added: extension actions for adding and saving custom fields on the account and category forms, and filters to veto a delete.

= 1.0.0 =
* Initial release.
* Chart of accounts with 33 default Schedule F–aligned accounts.
* 10 default farm categories.
* Transaction ledger with search, filters, and column sorting.
* Reports: income list, expense list, YTD summary.
* Dashboard with YTD widgets and recent-transaction list.
* Settings: farm name, fiscal year start, tax year, currency symbol.
* Extension hooks for the FarmBooks Pro add-on.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
