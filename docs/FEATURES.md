# BlueFairy FarmBooks Lite — Feature Reference

## Phase 1 (Current)

### Chart of Accounts
- 33 default Schedule F accounts seeded on activation (income lines 1–9, expense lines 10–32).
- Types: income, expense, asset, liability, equity.
- Each account stores the IRS Schedule F line number for report mapping.
- Accounts can be marked as default (`is_default = 1`); defaults are never overwritten on re-activation.

### Farm Categories
- 10 default categories (5 income, 5 expense) seeded on activation.
- Each category links to a parent account for Schedule F line grouping.
- Custom categories can be added in later phases.

### Plugin Settings
- Farm Name — displayed on reports.
- Fiscal Year Start — month 1–12 (default: January).
- Tax Year — four-digit year used to scope transaction filters and reports.
- Currency Symbol — up to 3 characters (default: $).
- Settings stored in the `bffb_settings` table, not `wp_options`.

### Admin Interface
- Top-level **FarmBooks** menu with dashicon `dashicons-money-alt` at position 30.
- Sub-pages: Dashboard, Transactions, Reports, Settings.
- Pro sub-pages (Vendors, Payroll, Budgets, WooCommerce) are added via the `bffb_admin_menu_items` action.

### Extension Hooks
| Hook | Type | Description |
|------|------|-------------|
| `bffb_loaded` | Action | Fires after Lite is fully booted. |
| `bffb_admin_menu_items` | Action | Fires after Lite submenus are registered. |
| `bffb_dashboard_widgets` | Action | Fires inside the dashboard page. |
| `bffb_settings_tabs` | Filter | Filters the settings tab list. |
| `bffb_reports_list` | Filter | Filters the available reports list. |
| `bffb_transaction_form_fields` | Filter | Filters transaction form fields (Phase 3). |

### Data Protection
- All four Lite tables are preserved on deactivation and deletion by default.
- To drop data on uninstall: `define( 'BFFB_REMOVE_DATA', true );` in `wp-config.php`.

## Upcoming Phases

| Phase | Feature |
|-------|---------|
| 2 | Dashboard widgets: income/expense YTD totals, recent transactions, Schedule F balance |
| 3 | Transaction CRUD: add, edit, delete income and expense entries; CSV export |
| 4 | Reports: Schedule F summary, income/expense by category, annual P&L, PDF export |
| 5 | Chart of Accounts and Categories management UI |
| 6 | Pro — Vendor management with encrypted EIN, 1099 report |
| 7 | Pro — Employee payroll with W-2 withholding, payroll run workflow |
| 8 | Pro — Budget vs. actual by category |
| 9 | Pro — WooCommerce income sync and receipt attachment uploads |
