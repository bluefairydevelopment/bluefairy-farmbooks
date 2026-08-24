<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

/**
 * FarmBooks Reports page — Lite tab shell + YTD Summary, Income List, Expense List.
 *
 * Pro adds tabs via apply_filters('bffb_reports_list') and renders them via
 * do_action('bffb_render_report', $slug, $filters).
 */
class ReportsPage {

	public function __construct() {
		add_action( 'bffb_render_report', array( $this, 'render_report_content' ), 10, 2 );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks-lite' ) );
		}

		$filters = $this->parse_filters();

		/**
		 * Filter the list of available FarmBooks reports.
		 * Pro adds P&L, Cash Flow, Schedule F, Budget vs Actual, and Payroll Summary here.
		 *
		 * @since 1.0.0
		 * @param array<string, string> $reports Slug => label.
		 */
		$reports = apply_filters(
			'bffb_reports_list',
			array(
				'ytd'     => __( 'YTD Summary', 'bluefairy-farmbooks-lite' ),
				'income'  => __( 'Income List', 'bluefairy-farmbooks-lite' ),
				'expense' => __( 'Expense List', 'bluefairy-farmbooks-lite' ),
			)
		);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active = isset( $_GET['bffb_report'] ) ? sanitize_key( $_GET['bffb_report'] ) : 'ytd';
		if ( ! array_key_exists( $active, $reports ) ) {
			$active = (string) array_key_first( $reports );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reports', 'bluefairy-farmbooks-lite' ); ?></h1>

			<nav class="nav-tab-wrapper bffb-report-tabs" style="margin-bottom:0">
				<?php foreach ( $reports as $slug => $label ) : ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'        => 'bffb-reports',
								'bffb_report' => $slug,
								'year'        => $filters['year'],
							),
							admin_url( 'admin.php' )
						)
					);
					?>
								"
						class="nav-tab <?php echo $slug === $active ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="bffb-report-body" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:16px 20px">

				<form method="get" class="bffb-report-filters" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
					<input type="hidden" name="page" value="bffb-reports">
					<input type="hidden" name="bffb_report" value="<?php echo esc_attr( $active ); ?>">

					<label>
						<?php esc_html_e( 'Year', 'bluefairy-farmbooks-lite' ); ?><br>
						<select name="year">
							<?php
							$current_year = (int) wp_date( 'Y' );
							for ( $y = $current_year; $y >= $current_year - 5; $y-- ) :
								?>
								<option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $filters['year'], $y ); ?>>
									<?php echo esc_html( (string) $y ); ?>
								</option>
							<?php endfor; ?>
						</select>
					</label>

					<?php if ( in_array( $active, array( 'income', 'expense' ), true ) ) : ?>
						<label>
							<?php esc_html_e( 'From', 'bluefairy-farmbooks-lite' ); ?><br>
							<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
						</label>

						<label>
							<?php esc_html_e( 'To', 'bluefairy-farmbooks-lite' ); ?><br>
							<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
						</label>

						<label>
							<?php esc_html_e( 'Category', 'bluefairy-farmbooks-lite' ); ?><br>
							<?php $this->render_category_dropdown( $active === 'expense' ? 'expense' : 'income', $filters['category_id'] ); ?>
						</label>
					<?php endif; ?>

					<?php submit_button( __( 'Apply', 'bluefairy-farmbooks-lite' ), 'secondary small', '', false ); ?>
				</form>

				<?php
				/**
				 * Fires to render the content of the active report tab.
				 *
				 * @since 1.0.0
				 * @param string               $slug    Active report slug.
				 * @param array<string, mixed> $filters Parsed filter values.
				 */
				do_action( 'bffb_render_report', $active, $filters );
				?>

			</div>
		</div>
		<?php
	}

	/**
	 * @return array{year:int,date_from:string,date_to:string,category_id:int}
	 */
	private function parse_filters(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
		$year        = isset( $_GET['year'] ) ? max( 2000, min( (int) $_GET['year'], (int) wp_date( 'Y' ) ) ) : (int) wp_date( 'Y' );
		$date_from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$category_id = isset( $_GET['category_id'] ) ? (int) $_GET['category_id'] : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = $year . '-01-01';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = $year . '-12-31';
		}

		return array(
			'year'        => $year,
			'date_from'   => $date_from,
			'date_to'     => $date_to,
			'category_id' => $category_id,
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public function render_report_content( string $slug, array $filters ): void {
		match ( $slug ) {
			'ytd'     => $this->render_ytd( $filters ),
			'income'  => $this->render_transaction_list( 'income', $filters ),
			'expense' => $this->render_transaction_list( 'expense', $filters ),
			default   => null,
		};
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	private function render_ytd( array $filters ): void {
		global $wpdb;

		$t    = $wpdb->prefix . 'bffb_transactions';
		$year = (int) $filters['year'];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$income   = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM `{$t}` WHERE type='income' AND YEAR(date)=%d", $year ) );
		$expenses = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM `{$t}` WHERE type='expense' AND YEAR(date)=%d", $year ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$net = $income - $expenses;
		?>
		<h2 style="margin-top:0">
			<?php
			// translators: %d is the tax year.
			printf( esc_html__( 'Year-to-Date Summary — %d', 'bluefairy-farmbooks-lite' ), (int) $year );
			?>
		</h2>
		<table class="widefat striped" style="max-width:480px">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Total Income', 'bluefairy-farmbooks-lite' ); ?></th>
					<td style="text-align:right"><?php echo esc_html( '$' . number_format( $income, 2 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Total Expenses', 'bluefairy-farmbooks-lite' ); ?></th>
					<td style="text-align:right"><?php echo esc_html( '$' . number_format( $expenses, 2 ) ); ?></td>
				</tr>
				<tr>
					<th scope="row" style="border-top:2px solid #ddd"><?php esc_html_e( 'Net Income / (Loss)', 'bluefairy-farmbooks-lite' ); ?></th>
					<td style="text-align:right;font-weight:bold;border-top:2px solid #ddd;color:<?php echo $net >= 0 ? '#2e7d32' : '#c62828'; ?>">
						<?php echo esc_html( ( $net < 0 ? '(' : '' ) . '$' . number_format( abs( $net ), 2 ) . ( $net < 0 ? ')' : '' ) ); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param 'income'|'expense'   $type
	 * @param array<string, mixed> $filters
	 */
	private function render_transaction_list( string $type, array $filters ): void {
		global $wpdb;

		$t     = $wpdb->prefix . 'bffb_transactions';
		$c     = $wpdb->prefix . 'bffb_categories';
		$label = $type === 'income'
			? __( 'Income List', 'bluefairy-farmbooks-lite' )
			: __( 'Expense List', 'bluefairy-farmbooks-lite' );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged    = max( 1, (int) wp_unslash( $_GET['paged'] ?? '1' ) );
		$per_page = 50;
		$offset   = ( $paged - 1 ) * $per_page;

		$date_from   = (string) $filters['date_from'];
		$date_to     = (string) $filters['date_to'];
		$category_id = (int) $filters['category_id'];

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cat_sql = $category_id > 0 ? $wpdb->prepare( ' AND t.category_id = %d', $category_id ) : '';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` t WHERE t.type=%s AND t.date BETWEEN %s AND %s", $type, $date_from, $date_to ) . $cat_sql
		);

		$total_amount = $total > 0
			? (float) $wpdb->get_var(
				$wpdb->prepare( "SELECT COALESCE(SUM(t.amount),0) FROM `{$t}` t WHERE t.type=%s AND t.date BETWEEN %s AND %s", $type, $date_from, $date_to ) . $cat_sql
			)
			: 0.0;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT t.date, t.amount, t.memo, c.name AS cat_name FROM `{$t}` t LEFT JOIN `{$c}` c ON c.id = t.category_id WHERE t.type=%s AND t.date BETWEEN %s AND %s", $type, $date_from, $date_to )
			. $cat_sql
			. $wpdb->prepare( ' ORDER BY t.date DESC LIMIT %d OFFSET %d', $per_page, $offset )
		);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<h2 style="margin-top:0">' . esc_html( $label ) . '</h2>';

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No transactions found for the selected period.', 'bluefairy-farmbooks-lite' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bluefairy-farmbooks-lite' ); ?></th>
					<th><?php esc_html_e( 'Category', 'bluefairy-farmbooks-lite' ); ?></th>
					<th><?php esc_html_e( 'Memo', 'bluefairy-farmbooks-lite' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Amount', 'bluefairy-farmbooks-lite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->date ); ?></td>
						<td><?php echo esc_html( $row->cat_name ?? '—' ); ?></td>
						<td><?php echo esc_html( $row->memo ?? '' ); ?></td>
						<td style="text-align:right"><?php echo esc_html( '$' . number_format( (float) $row->amount, 2 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="3" style="text-align:left"><?php esc_html_e( 'Total', 'bluefairy-farmbooks-lite' ); ?></th>
					<th style="text-align:right"><?php echo esc_html( '$' . number_format( $total_amount, 2 ) ); ?></th>
				</tr>
			</tfoot>
		</table>

		<?php
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total_pages > 1 ) {
			$base = add_query_arg(
				array(
					'page'        => 'bffb-reports',
					'bffb_report' => $type,
					'year'        => $filters['year'],
					'date_from'   => $filters['date_from'],
					'date_to'     => $filters['date_to'],
					'category_id' => $filters['category_id'],
				),
				admin_url( 'admin.php' )
			);

			echo '<div class="tablenav" style="margin-top:8px"><div class="tablenav-pages">';
			for ( $p = 1; $p <= $total_pages; $p++ ) {
				if ( $p === $paged ) {
					echo '<span class="current" style="padding:4px 8px;border:1px solid #999;margin:2px">' . esc_html( (string) $p ) . '</span> ';
				} else {
					echo '<a href="' . esc_url( add_query_arg( 'paged', $p, $base ) ) . '" style="padding:4px 8px;margin:2px">' . esc_html( (string) $p ) . '</a> ';
				}
			}
			echo '</div></div>';
		}
	}

	/**
	 * @param 'income'|'expense' $type
	 */
	private function render_category_dropdown( string $type, int $selected ): void {
		global $wpdb;

		$c = $wpdb->prefix . 'bffb_categories';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cats = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM `{$c}` WHERE type=%s ORDER BY sort_order, name", $type ) );

		echo '<select name="category_id"><option value="0">' . esc_html__( 'All Categories', 'bluefairy-farmbooks-lite' ) . '</option>';
		foreach ( $cats as $cat ) {
			echo '<option value="' . esc_attr( (string) $cat->id ) . '" ' . selected( $selected, (int) $cat->id, false ) . '>' . esc_html( $cat->name ) . '</option>';
		}
		echo '</select>';
	}
}
