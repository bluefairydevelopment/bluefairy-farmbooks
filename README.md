# BlueFairy FarmBooks Lite

Farm income and expense tracking aligned to IRS Schedule F. Built for small family farms running WordPress.

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later
- MySQL 5.7+ / MariaDB 10.3+

## Features (Phase 1)

- Chart of accounts with 33 default Schedule F accounts (income lines 1–9, expense lines 10–32)
- 10 default farm categories linked to their accounts
- Settings: farm name, fiscal year start, tax year, currency symbol
- Admin menu: Dashboard, Transactions, Reports, Settings
- Extension hooks for Pro add-on (`bffb_loaded`, `bffb_admin_menu_items`, `bffb_settings_tabs`, `bffb_reports_list`, `bffb_dashboard_widgets`)

## Installation

1. Upload the `bluefairy-farmbooks-lite` folder to `/wp-content/plugins/`.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. Navigate to **FarmBooks** in the WordPress admin sidebar.

## Removing Data on Uninstall

By default, deactivating or deleting the plugin preserves all your farm data in the database.

To drop all FarmBooks tables when you delete the plugin, add this line to your `wp-config.php` **before** deleting:

```php
define( 'BFFB_REMOVE_DATA', true );
```

Remove or set to `false` after deletion if you reinstall.

## Upgrading to FarmBooks Pro

FarmBooks Pro adds:

- Vendor management with encrypted EIN storage
- 1099 reporting
- Payroll (W-2 employees) with federal/state withholding calculations
- Budget vs. actual reporting by category
- Receipt/attachment uploads
- WooCommerce income sync
- Licence key for automatic updates

Both plugins must be active simultaneously. Pro boots after Lite (priority 20).

## Database Tables

Lite creates four tables (all prefixed with your WordPress table prefix):

| Table | Purpose |
|-------|---------|
| `{prefix}bffb_accounts` | Chart of accounts (Schedule F lines) |
| `{prefix}bffb_categories` | Farm income/expense categories |
| `{prefix}bffb_transactions` | Transaction ledger |
| `{prefix}bffb_settings` | Plugin settings |

## License

GPL-2.0-or-later — https://www.gnu.org/licenses/gpl-2.0.html
