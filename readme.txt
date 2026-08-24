=== BlueFairy FarmBooks Lite ===
Contributors: bluefairydevelopment
Tags: farm accounting, schedule f, income, expense, agriculture
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Farm income and expense tracking aligned to IRS Schedule F — free for small family farms.

== Description ==

BlueFairy FarmBooks Lite gives small family farms a dedicated accounting ledger inside WordPress, built around the IRS Schedule F line structure so tax prep is straightforward.

**Features:**

* Chart of accounts with 33 default Schedule F accounts (income lines 1–9, expense lines 10–32)
* 10 default farm categories linked to their accounts
* Transaction ledger — date, amount, type (income/expense), category, memo
* WP_List_Table with search, filters, and column sorting
* Reports: income list, expense list, and YTD totals
* Dashboard: YTD summary widgets + 10 most-recent transactions + quick-add form
* Settings: farm name, fiscal year start, tax year, currency symbol
* Extension hooks for the BlueFairy FarmBooks Pro add-on

**Pro add-on** (sold separately at bluefairydevelopment.com) adds vendor management, 1099-NEC prep, full payroll with W-2 exports, budgets, advanced reports, receipt uploads, and WooCommerce sync.

== Installation ==

1. Upload the `bluefairy-farmbooks-lite` folder to `/wp-content/plugins/`.
2. Activate via **Plugins > Installed Plugins**.
3. Navigate to **FarmBooks** in the WordPress admin sidebar.

== Frequently Asked Questions ==

= Does this replace QuickBooks? =

FarmBooks Lite handles farm income and expense tracking aligned to Schedule F. It is not a full double-entry accounting system, but it covers the core record-keeping most small farms need for tax preparation.

= Is my data safe if I deactivate the plugin? =

Yes. Deactivation does not delete your data. To remove all FarmBooks data from the database, add `define( 'BFFB_REMOVE_DATA', true );` to `wp-config.php` before deleting the plugin.

= Can I import historical transactions? =

Not in the Lite version. The Pro add-on includes a WooCommerce bulk import; general CSV import is on the roadmap.

== Changelog ==

= 1.0.0 =
* Initial release.
