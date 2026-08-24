<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

use BlueFairy\FarmBooks\Settings;

class DashboardPage {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		add_action( 'admin_post_bffb_quick_add_transaction', array( $this, 'handle_quick_add' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks-lite' ) );
		}

		$currency                    = (string) $this->settings->get( 'currency_symbol', '$' );
		[$ytd_income, $ytd_expenses] = $this->get_ytd_totals();
		$net                         = $ytd_income - $ytd_expenses;
		$recent                      = $this->get_recent_transactions();
		$cats                        = $this->get_categories_by_type();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$added = isset( $_GET['bffb_quick_added'] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FarmBooks Dashboard', 'bluefairy-farmbooks-lite' ); ?></h1>

			<?php if ( $added ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Transaction added.', 'bluefairy-farmbooks-lite' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="bffb-widgets" style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">
				<?php
				$this->render_stat_widget( __( 'YTD Income', 'bluefairy-farmbooks-lite' ), $currency . number_format( $ytd_income, 2 ), '#46b450' );
				$this->render_stat_widget( __( 'YTD Expenses', 'bluefairy-farmbooks-lite' ), $currency . number_format( $ytd_expenses, 2 ), '#dc3232' );
				$this->render_stat_widget( __( 'Net', 'bluefairy-farmbooks-lite' ), $currency . number_format( $net, 2 ), $net >= 0.0 ? '#46b450' : '#dc3232' );
				?>
			</div>

			<?php
			/**
			 * Fires inside the FarmBooks dashboard, after YTD stat widgets.
			 * Pro hooks here to add Chart.js charts, budget bars, and payroll widget.
			 *
			 * @since 1.0.0
			 */
			do_action( 'bffb_dashboard_widgets' );
			?>

			<div style="display:flex;align-items:center;justify-content:space-between;margin-top:24px;margin-bottom:4px">
				<h2 style="margin:0"><?php esc_html_e( 'Recent Transactions', 'bluefairy-farmbooks-lite' ); ?></h2>
				<button type="button" id="bffb-quick-add-toggle" class="button">
					<?php esc_html_e( '+ Quick Add', 'bluefairy-farmbooks-lite' ); ?>
				</button>
			</div>

			<div id="bffb-quick-add-form" style="display:none;border:1px solid #ccd0d4;background:#fff;padding:16px;margin-bottom:12px">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bffb_quick_add_transaction">
					<?php wp_nonce_field( 'bffb_quick_add_transaction' ); ?>
					<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
						<label style="display:flex;flex-direction:column;gap:4px">
							<span style="font-weight:600;font-size:13px"><?php esc_html_e( 'Date', 'bluefairy-farmbooks-lite' ); ?></span>
							<input type="date" name="bffb_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" required style="padding:4px 8px">
						</label>
						<label style="display:flex;flex-direction:column;gap:4px">
							<span style="font-weight:600;font-size:13px"><?php esc_html_e( 'Type', 'bluefairy-farmbooks-lite' ); ?></span>
							<select name="bffb_type" id="bffb-quick-type" style="padding:4px 8px">
								<option value="income"><?php esc_html_e( 'Income', 'bluefairy-farmbooks-lite' ); ?></option>
								<option value="expense"><?php esc_html_e( 'Expense', 'bluefairy-farmbooks-lite' ); ?></option>
							</select>
						</label>
						<label style="display:flex;flex-direction:column;gap:4px">
							<span style="font-weight:600;font-size:13px"><?php esc_html_e( 'Amount', 'bluefairy-farmbooks-lite' ); ?></span>
							<input type="number" name="bffb_amount" min="0.01" step="0.01" required placeholder="0.00" style="padding:4px 8px;width:120px">
						</label>
						<label style="display:flex;flex-direction:column;gap:4px">
							<span style="font-weight:600;font-size:13px"><?php esc_html_e( 'Category', 'bluefairy-farmbooks-lite' ); ?></span>
							<select name="bffb_category_id" id="bffb-quick-category" style="padding:4px 8px">
								<option value="0"><?php esc_html_e( '— None —', 'bluefairy-farmbooks-lite' ); ?></option>
								<?php foreach ( $cats['income'] as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->id ); ?>" data-type="income">
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
								<?php foreach ( $cats['expense'] as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->id ); ?>" data-type="expense" style="display:none">
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label style="display:flex;flex-direction:column;gap:4px">
							<span style="font-weight:600;font-size:13px"><?php esc_html_e( 'Memo', 'bluefairy-farmbooks-lite' ); ?></span>
							<input type="text" name="bffb_memo" placeholder="<?php esc_attr_e( 'Optional', 'bluefairy-farmbooks-lite' ); ?>" style="padding:4px 8px;width:180px">
						</label>
						<?php submit_button( __( 'Add', 'bluefairy-farmbooks-lite' ), 'primary', 'submit', false, array( 'style' => 'margin-bottom:0' ) ); ?>
					</div>
				</form>
			</div>

			<?php if ( empty( $recent ) ) : ?>
				<p><?php esc_html_e( 'No transactions yet.', 'bluefairy-farmbooks-lite' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top:4px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'bluefairy-farmbooks-lite' ); ?></th>
							<th><?php esc_html_e( 'Type', 'bluefairy-farmbooks-lite' ); ?></th>
							<th><?php esc_html_e( 'Category', 'bluefairy-farmbooks-lite' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'bluefairy-farmbooks-lite' ); ?></th>
							<th><?php esc_html_e( 'Memo', 'bluefairy-farmbooks-lite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $tx ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) $tx->date ) ) ); ?></td>
								<td>
									<?php if ( $tx->type === 'income' ) : ?>
										<span style="color:#46b450;font-weight:600"><?php esc_html_e( 'Income', 'bluefairy-farmbooks-lite' ); ?></span>
									<?php else : ?>
										<span style="color:#dc3232;font-weight:600"><?php esc_html_e( 'Expense', 'bluefairy-farmbooks-lite' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo isset( $tx->category_name ) ? esc_html( $tx->category_name ) : '—'; ?></td>
								<td><?php echo esc_html( $currency . number_format( (float) $tx->amount, 2 ) ); ?></td>
								<td><?php echo isset( $tx->memo ) ? esc_html( $tx->memo ) : ''; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bffb-transactions' ) ); ?>">
						<?php esc_html_e( 'View all transactions →', 'bluefairy-farmbooks-lite' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		$this->print_script();
	}

	public function handle_quick_add(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bluefairy-farmbooks-lite' ) );
		}

		check_admin_referer( 'bffb_quick_add_transaction' );

		$date        = isset( $_POST['bffb_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bffb_date'] ) ) : '';
		$type        = isset( $_POST['bffb_type'] ) ? sanitize_text_field( wp_unslash( $_POST['bffb_type'] ) ) : '';
		$amount      = isset( $_POST['bffb_amount'] ) ? (float) $_POST['bffb_amount'] : 0.0;
		$category_id = isset( $_POST['bffb_category_id'] ) ? (int) $_POST['bffb_category_id'] : 0;
		$memo        = isset( $_POST['bffb_memo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bffb_memo'] ) ) : '';

		if (
			! in_array( $type, array( 'income', 'expense' ), true ) ||
			$amount <= 0.0 ||
			! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date )
		) {
			wp_safe_redirect( add_query_arg( 'page', 'bffb-dashboard', admin_url( 'admin.php' ) ) );
			exit;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bffb_transactions';

		$data    = array(
			'type'       => $type,
			'date'       => $date,
			'amount'     => round( $amount, 2 ),
			'created_by' => get_current_user_id(),
		);
		$formats = array( '%s', '%s', '%f', '%d' );

		if ( $category_id > 0 ) {
			$data['category_id'] = $category_id;
			$formats[]           = '%d';
		}

		if ( $memo !== '' ) {
			$data['memo'] = $memo;
			$formats[]    = '%s';
		}

		$wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'bffb-dashboard',
					'bffb_quick_added' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return float[] [income_total, expense_total]
	 */
	private function get_ytd_totals(): array {
		global $wpdb;

		$ytd_start = $this->get_fiscal_year_start_date();
		$table     = $wpdb->prefix . 'bffb_transactions';

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT `type`, COALESCE(SUM(`amount`), 0) AS total FROM `{$table}` WHERE `date` >= %s GROUP BY `type`", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ytd_start
			)
		) ?? array();

		$income   = 0.0;
		$expenses = 0.0;
		foreach ( $rows as $row ) {
			if ( $row->type === 'income' ) {
				$income = (float) $row->total;
			} else {
				$expenses = (float) $row->total;
			}
		}

		return array( $income, $expenses );
	}

	/**
	 * @return object[]
	 */
	private function get_recent_transactions(): array {
		global $wpdb;

		$tx_table  = $wpdb->prefix . 'bffb_transactions';
		$cat_table = $wpdb->prefix . 'bffb_categories';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.id, t.type, t.date, t.amount, t.memo, c.name AS category_name
             FROM `{$tx_table}` t
             LEFT JOIN `{$cat_table}` c ON c.id = t.category_id
             ORDER BY t.date DESC, t.id DESC
             LIMIT 10"
		) ?? array();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return array{income: object[], expense: object[]}
	 */
	private function get_categories_by_type(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bffb_categories';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT `id`, `name`, `type` FROM `{$table}` ORDER BY `type`, `sort_order`, `name`" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		) ?? array();

		$income  = array();
		$expense = array();
		foreach ( $rows as $row ) {
			if ( $row->type === 'income' ) {
				$income[] = $row;
			} else {
				$expense[] = $row;
			}
		}

		return array(
			'income'  => $income,
			'expense' => $expense,
		);
	}

	private function get_fiscal_year_start_date(): string {
		$fiscal_month  = max( 1, min( 12, (int) $this->settings->get( 'fiscal_year_start', 1 ) ) );
		$current_year  = (int) wp_date( 'Y' );
		$current_month = (int) wp_date( 'n' );

		$year = ( $current_month >= $fiscal_month ) ? $current_year : $current_year - 1;

		return sprintf( '%04d-%02d-01', $year, $fiscal_month );
	}

	private function render_stat_widget( string $label, string $value, string $color ): void {
		?>
		<div style="background:#fff;border:1px solid #ccd0d4;border-top:4px solid <?php echo esc_attr( $color ); ?>;padding:20px 24px;min-width:180px;flex:1;max-width:260px">
			<div style="font-size:13px;color:#555;margin-bottom:6px"><?php echo esc_html( $label ); ?></div>
			<div style="font-size:24px;font-weight:700;color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $value ); ?></div>
		</div>
		<?php
	}

	private function print_script(): void {
		?>
		<script>
		(function () {
			var btn  = document.getElementById('bffb-quick-add-toggle');
			var form = document.getElementById('bffb-quick-add-form');
			var sel  = document.getElementById('bffb-quick-type');
			var cat  = document.getElementById('bffb-quick-category');

			if (btn && form) {
				btn.addEventListener('click', function () {
					form.style.display = form.style.display === 'none' ? 'block' : 'none';
				});
			}

			if (sel && cat) {
				function filterCats() {
					var type = sel.value;
					cat.querySelectorAll('option[data-type]').forEach(function (opt) {
						opt.style.display = opt.getAttribute('data-type') === type ? '' : 'none';
					});
					var first = cat.querySelector('option[data-type="' + type + '"]');
					if (first) { cat.value = first.value; }
				}

				sel.addEventListener('change', filterCats);
			}
		}());
		</script>
		<?php
	}
}
