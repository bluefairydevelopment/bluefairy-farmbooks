<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

class TransactionEditPage {

	public function __construct() {
		add_action( 'admin_post_bffb_save_transaction', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bffb_delete_transaction', array( $this, 'handle_delete' ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks-lite' ) );
		}

		global $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		$transaction = null;
		if ( $id > 0 ) {
			$table = $wpdb->prefix . 'bffb_transactions';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$transaction = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		$cat_table = $wpdb->prefix . 'bffb_categories';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$categories = $wpdb->get_results(
			"SELECT id, name, type FROM `{$cat_table}` ORDER BY type, sort_order" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$extra_fields = apply_filters( 'bffb_transaction_form_fields', array(), $transaction );

		$page_title = $id > 0
			? __( 'Edit Transaction', 'bluefairy-farmbooks-lite' )
			: __( 'Add Transaction', 'bluefairy-farmbooks-lite' );

		$date        = $transaction ? $transaction->date : wp_date( 'Y-m-d' );
		$amount      = $transaction ? $transaction->amount : '';
		$type        = $transaction ? $transaction->type : 'expense';
		$category_id = $transaction ? $transaction->category_id : null;
		$memo        = $transaction ? (string) $transaction->memo : '';

		$income_cats  = array_filter( $categories, fn( $c ) => 'income' === $c->type );
		$expense_cats = array_filter( $categories, fn( $c ) => 'expense' === $c->type );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="bffb_save_transaction">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'bffb_save_transaction', 'bffb_transaction_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bffb_date"><?php esc_html_e( 'Date', 'bluefairy-farmbooks-lite' ); ?> <span aria-hidden="true">*</span></label>
						</th>
						<td>
							<input type="date" id="bffb_date" name="date" class="regular-text" required
								value="<?php echo esc_attr( (string) $date ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_amount"><?php esc_html_e( 'Amount', 'bluefairy-farmbooks-lite' ); ?> <span aria-hidden="true">*</span></label>
						</th>
						<td>
							<input type="number" id="bffb_amount" name="amount" class="regular-text"
								min="0.01" step="0.01" required
								value="<?php echo esc_attr( (string) $amount ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_type"><?php esc_html_e( 'Type', 'bluefairy-farmbooks-lite' ); ?></label>
						</th>
						<td>
							<select id="bffb_type" name="type">
								<option value="income"  <?php selected( $type, 'income' ); ?>><?php esc_html_e( 'Income', 'bluefairy-farmbooks-lite' ); ?></option>
								<option value="expense" <?php selected( $type, 'expense' ); ?>><?php esc_html_e( 'Expense', 'bluefairy-farmbooks-lite' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_category_id"><?php esc_html_e( 'Category', 'bluefairy-farmbooks-lite' ); ?></label>
						</th>
						<td>
							<select id="bffb_category_id" name="category_id">
								<option value=""><?php esc_html_e( '— None —', 'bluefairy-farmbooks-lite' ); ?></option>
								<?php if ( $income_cats ) : ?>
									<optgroup label="<?php esc_attr_e( 'Income', 'bluefairy-farmbooks-lite' ); ?>">
									<?php foreach ( $income_cats as $cat ) : ?>
										<option value="<?php echo esc_attr( (string) $cat->id ); ?>"
											<?php selected( (int) $category_id, (int) $cat->id ); ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
									</optgroup>
								<?php endif; ?>
								<?php if ( $expense_cats ) : ?>
									<optgroup label="<?php esc_attr_e( 'Expense', 'bluefairy-farmbooks-lite' ); ?>">
									<?php foreach ( $expense_cats as $cat ) : ?>
										<option value="<?php echo esc_attr( (string) $cat->id ); ?>"
											<?php selected( (int) $category_id, (int) $cat->id ); ?>>
											<?php echo esc_html( $cat->name ); ?>
										</option>
									<?php endforeach; ?>
									</optgroup>
								<?php endif; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_memo"><?php esc_html_e( 'Memo', 'bluefairy-farmbooks-lite' ); ?></label>
						</th>
						<td>
							<textarea id="bffb_memo" name="memo" class="large-text" rows="3"><?php echo esc_textarea( $memo ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php foreach ( $extra_fields as $field ) : ?>
					<?php echo wp_kses_post( $field ); ?>
				<?php endforeach; ?>

				<?php
				/**
				 * Fires inside the transaction edit form, after the base fields.
				 * Pro uses this hook to render fields that contain input/file elements
				 * which cannot pass through wp_kses_post (vendor, tax-deductible, attachment).
				 *
				 * @param object|null $transaction Current transaction row, or null for new.
				 */
				do_action( 'bffb_transaction_extra_fields', $transaction );
				?>

				<?php submit_button( $id > 0 ? __( 'Update Transaction', 'bluefairy-farmbooks-lite' ) : __( 'Add Transaction', 'bluefairy-farmbooks-lite' ) ); ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bffb-transactions' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'bluefairy-farmbooks-lite' ); ?>
				</a>
			</form>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks-lite' ) );
		}

		check_admin_referer( 'bffb_save_transaction', 'bffb_transaction_nonce' );

		global $wpdb;

		$id       = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$raw_date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$amount   = (float) ( $_POST['amount'] ?? 0 );
		$raw_type = sanitize_key( $_POST['type'] ?? '' );
		$type     = in_array( $raw_type, array( 'income', 'expense' ), true ) ? $raw_type : 'expense';

		$category_id = isset( $_POST['category_id'] ) && $_POST['category_id'] !== ''
							? (int) $_POST['category_id']
							: null;
		$memo        = sanitize_textarea_field( wp_unslash( $_POST['memo'] ?? '' ) );

		// Require a valid YYYY-MM-DD date.
		if ( $raw_date === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bffb-transactions&bffb_notice=error' ) );
			exit;
		}

		if ( $amount <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bffb-transactions&bffb_notice=error' ) );
			exit;
		}

		$table   = $wpdb->prefix . 'bffb_transactions';
		$data    = array(
			'type'        => $type,
			'date'        => $raw_date,
			'amount'      => $amount,
			'category_id' => $category_id,
			'memo'        => $memo !== '' ? $memo : null,
		);
		$formats = array( '%s', '%s', '%f', '%d', '%s' );

		if ( $id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$formats[]          = '%d';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data, $formats );
			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a transaction's core fields are saved.
		 * Pro hooks here to persist vendor_id, is_tax_deductible, account_id, and attachments.
		 * Nonce was already verified above via check_admin_referer().
		 *
		 * @param int   $id   Saved transaction ID.
		 * @param array $post Raw POST data — hook implementations must re-sanitize.
		 */
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		do_action( 'bffb_transaction_saved', $id, $_POST );

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-transactions&bffb_notice=saved' ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks-lite' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		check_admin_referer( 'bffb_delete_transaction_' . $id );

		global $wpdb;
		$table = $wpdb->prefix . 'bffb_transactions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		/**
		 * Fires after a transaction is deleted.
		 * Pro hooks here to remove associated attachments.
		 *
		 * @param int $id Deleted transaction ID.
		 */
		do_action( 'bffb_transaction_deleted', $id );

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-transactions&bffb_notice=deleted' ) );
		exit;
	}
}
