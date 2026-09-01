# BlueFairy FarmBooks — Feature Reference

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
| `bffb_loaded` | Action | Fires after the plugin is fully booted. |
| `bffb_admin_menu_items` | Action | Fires after the submenus are registered. |
| `bffb_dashboard_widgets` | Action | Fires inside the dashboard page. |
| `bffb_transactions_page_actions` | Action | Fires above the transactions list. |
| `bffb_accounts_page_actions` | Action | Fires above the accounts list. |
| `bffb_categories_page_actions` | Action | Fires above the categories list. |
| `bffb_render_report` | Action | Renders a report added via `bffb_reports_list`. |
| `bffb_transaction_extra_fields` | Action | Add a field to the transaction form. |
| `bffb_category_extra_fields` | Action | Add a field to the category form. |
| `bffb_account_extra_fields` | Action | Add a field to the account form. |
| `bffb_transaction_saved` | Action | After a transaction is written. Save your field here. |
| `bffb_transaction_deleted` | Action | After a transaction is deleted. |
| `bffb_category_saved` | Action | After a category is written. Save your field here. |
| `bffb_account_saved` | Action | After an account is written. Save your field here. |
| `bffb_settings_tabs` | Filter | Adds a settings tab. |
| `bffb_save_settings_tab` | Action | Save YOUR tab's fields here. |
| `bffb_reports_list` | Filter | Filters the available reports list. |
| `bffb_transaction_row_actions` | Filter | Row actions on the transactions list. |
| `bffb_account_row_actions` | Filter | Row actions on the accounts list. |
| `bffb_category_row_actions` | Filter | Row actions on the categories list. |
| `bffb_transaction_form_fields` | Filter | Markup only — see the note below. |
| `bffb_category_form_fields` | Filter | Markup only — see the note below. |
| `bffb_account_form_fields` | Filter | Markup only — see the note below. |
| `bffb_category_in_use` | Filter | Declare a reference that should block a category delete. |
| `bffb_account_in_use` | Filter | Declare a reference that should block an account delete. |

**To save a settings tab you added, hook `bffb_save_settings_tab` — not
`admin_post_bffb_save_settings`.** This plugin answers that action at priority
10 and exits, so a handler registered at the default priority is appended after
it and never runs.

**To add a field to an edit form, use the `*_extra_fields` ACTION, not the
`*_form_fields` filter.** The filters pass their return value through
`wp_kses_post()`, whose allowed tags include no `input`, `select`, `option` or
`textarea` — so a filter can add markup around a field but never the field
itself. Pair the action with the matching `*_saved` action, or your field will
render, be typed into, and be discarded on save.

The `*_in_use` filters must return `$in_use` **plus** your own count, never a
bare number: returning a bare count would mask the references the plugin found
itself and let a referenced row be deleted.

### Data Protection
- All four tables are preserved on deactivation and deletion by default.
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
