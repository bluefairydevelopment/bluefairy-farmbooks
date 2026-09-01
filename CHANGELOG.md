# Changelog — BlueFairy FarmBooks

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows Major.Minor.Patch: major = architecture shift, minor = new feature, patch = bug/security fix.

## [1.0.1] — 2026-08-31

### Fixed
- Settings could be saved from a tab contributed by another plugin, which
  overwrote farm name, EIN, fiscal year start, tax year and currency with the
  defaults of a form that never carried those fields.
- The Settings form now posts to `admin-post.php` and redirects, so a reload no
  longer re-submits it.
- Activation seeded `farm_name` as an empty row, which beat every caller's
  fallback: Schedule F printed a blank farm name and pay stubs had a blank
  employer heading on a fresh install. New installs seed the site title, and
  existing installs are backfilled by a migration.
- Deleting a default account or category was blocked in the list table and not
  in the handler, so a hand-built link removed one anyway.
- The account and category delete guards could only see this plugin's own
  transactions table, so a category referenced only by a Pro budget or a
  WooCommerce mapping deleted cleanly.
- Tables are now created on every site of a multisite network, on activation
  and on new-site creation, in resumable batches; uninstall removes them from
  every site too.
- The plugin now records a schema version it actually advances, so future
  changes reach existing installs.

### Added
- `bffb_category_extra_fields` and `bffb_account_extra_fields` actions. The
  matching `*_form_fields` filters pass their return through `wp_kses_post()`,
  which strips `input`, `select`, `option` and `textarea` — so they could never
  add the field they were documented for.
- `bffb_category_saved` and `bffb_account_saved` actions, so a field added by
  the actions above has somewhere to be saved.
- `bffb_category_in_use` and `bffb_account_in_use` filters, so an add-on can
  declare references this plugin cannot see.
- `Settings::fiscal_year_start_date()`, the single owner of the year-to-date
  window that free and Pro both render on the same screen.

### Added
- `bffb_settings_saved_notice` filter, so a tab added through
  `bffb_settings_tabs` can report its own save. Without it the screen said
  "Nothing was saved" over a save that had just succeeded.

### Fixed
- Saving an account, category or transaction reported success whether or not
  the write happened, and the `*_saved` action fired with an ID of 0.
- Deleting one reported success the same way, including when the row was
  already gone.

### Security
- The pay stub purge could be made to delete anything. Its containment check
  resolved the directory twice and the walk trusted the second answer, so
  swapping `uploads/farmbooks/paystubs` for a symlink in between escaped it
  entirely. It resolves once now, and refuses a root that is a link or that
  resolves outside the uploads folder. (Pro.)
- Every way that purge could fail recorded it as complete, so an ordinary
  permissions problem reported the exposure as remediated with the files still
  in place. Failures are now named on screen and in the log, and retried.
  (Pro.)

### Fixed
- The install lock was not a lock. `add_option()` is an existence check
  followed by `INSERT ... ON DUPLICATE KEY UPDATE`, so two callers racing to
  create the row both succeeded and both believed they held it - and the
  stale-recovery path had the second process delete the lock the first had just
  taken. It uses MySQL's `GET_LOCK` now, which is held by the connection and
  released by the server when a request dies.
- Duplicate seeded rows are now impossible: the accounts and categories tables
  carry a unique key on (name, type), and a migration removes duplicates an
  earlier race created - which mattered because every seeded row is
  `is_default = 1` and could not be deleted through the product.
- Activation recorded the schema version whether or not the tables were
  created, so a site whose database user lacks CREATE was stamped as current
  and never retried, with the only diagnostic unreachable.
- The upgrade path retried a failing install on every admin request and every
  WP-CLI call, holding the install lock while it did.

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
