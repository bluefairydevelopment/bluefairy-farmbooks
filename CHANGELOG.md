# Changelog — BlueFairy FarmBooks Lite

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows Major.Minor.Patch: major = architecture shift, minor = new feature, patch = bug/security fix.

## [1.0.0] — 2026-08-24

### Added
- Initial release.
- Chart of accounts with 33 IRS Schedule F default accounts (income lines 1–9, expense lines 10–32).
- 10 default farm categories linked to their Schedule F accounts.
- Transaction ledger table (`bffb_transactions`) with income/expense type, date, amount, category, account, memo, and created-by fields.
- Plugin settings table (`bffb_settings`) with farm name, fiscal year start, tax year, and currency symbol.
- Settings admin page with full nonce verification and per-field sanitization.
- FarmBooks top-level admin menu (Dashboard, Transactions, Reports, Settings) at position 30 with `dashicons-money-alt`.
- Extension hooks for Pro add-on: `bffb_loaded`, `bffb_admin_menu_items`, `bffb_settings_tabs`, `bffb_reports_list`, `bffb_dashboard_widgets`, `bffb_transaction_form_fields`.
- Uninstall routine gated by `BFFB_REMOVE_DATA` constant to protect farm data by default.
- PSR-4 autoloader mapping `BlueFairy\FarmBooks\` to `includes/`.
