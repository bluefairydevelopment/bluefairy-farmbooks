<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

defined( 'ABSPATH' ) || exit;

/*
 * Lives in the FREE plugin because it edits the free plugin's own tables.
 *
 * Until 2026-08-31 both of these lived in Pro, so a free user opened Accounts,
 * saw 33 seeded rows, and had no Add button and no Edit link on any of them —
 * the chart of accounts was read-only for ever. Worse, the free plugin rendered
 * "Category saved." notices that only Pro's redirects could produce, so the
 * base was written assuming the paid half was installed.
 */

class AccountEditPage {

	public function __construct() {
		add_action( 'admin_post_bffb_save_account', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bffb_delete_account', array( $this, 'handle_delete' ) );
	}

	public function render(): void {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}


        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		$account = null;
		if ( $id > 0 ) {
			$table = $wpdb->prefix . 'bffb_accounts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$account = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		// Markup only: the return value is passed through wp_kses_post(),
		// which strips input, select, option and textarea. Use the
		// bffb_account_extra_fields action below to add an actual field.
		$extra_fields = apply_filters( 'bffb_account_form_fields', array(), $account );

		$page_title = $id > 0
			? __( 'Edit Account', 'bluefairy-farmbooks' )
			: __( 'Add Account', 'bluefairy-farmbooks' );

		$name            = $account ? $account->name : '';
		$type            = $account ? $account->type : 'income';
		$schedule_f_line = $account ? $account->schedule_f_line : '';
		$description     = $account ? $account->description : '';
		$sort_order      = $account ? (int) $account->sort_order : 0;

		$types = array(
			'income'    => __( 'Income', 'bluefairy-farmbooks' ),
			'expense'   => __( 'Expense', 'bluefairy-farmbooks' ),
			'asset'     => __( 'Asset', 'bluefairy-farmbooks' ),
			'liability' => __( 'Liability', 'bluefairy-farmbooks' ),
			'equity'    => __( 'Equity', 'bluefairy-farmbooks' ),
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bffb_save_account">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'bffb_save_account', 'bffb_account_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bffb_name"><?php esc_html_e( 'Name', 'bluefairy-farmbooks' ); ?> <span aria-hidden="true">*</span></label>
						</th>
						<td>
							<input type="text" id="bffb_name" name="name" class="regular-text" required
								value="<?php echo esc_attr( $name ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_type"><?php esc_html_e( 'Type', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<select id="bffb_type" name="type">
								<?php foreach ( $types as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_schedule_f_line"><?php esc_html_e( 'Schedule F Line', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input type="text" id="bffb_schedule_f_line" name="schedule_f_line" class="small-text"
								maxlength="10" value="<?php echo esc_attr( $schedule_f_line ?? '' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_description"><?php esc_html_e( 'Description', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<textarea id="bffb_description" name="description" class="large-text" rows="4"><?php echo esc_textarea( $description ?? '' ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_sort_order"><?php esc_html_e( 'Sort Order', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<input type="number" id="bffb_sort_order" name="sort_order" min="0"
								value="<?php echo esc_attr( (string) $sort_order ); ?>">
						</td>
					</tr>
				</table>

				<?php foreach ( $extra_fields as $field ) : ?>
					<?php echo wp_kses_post( $field ); ?>
				<?php endforeach; ?>

				<?php
				/**
				 * Fires inside the account edit form, after the base fields.
				 *
				 * The bffb_account_form_fields filter above runs its return value
				 * through wp_kses_post(), whose allowed tags include no input,
				 * select, option or textarea - so it can add markup around a
				 * field but never the field itself. This action is the seam
				 * that actually works, matching the transaction form.
				 *
				 * @since 1.0.1
				 * @param object|null $account Account row, or null for new.
				 */
				do_action( 'bffb_account_extra_fields', $account );
				?>

				<?php submit_button( $id > 0 ? __( 'Update Account', 'bluefairy-farmbooks' ) : __( 'Add Account', 'bluefairy-farmbooks' ) ); ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bffb-accounts' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'bluefairy-farmbooks' ); ?>
				</a>
			</form>
		</div>
		<?php
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks' ) );
		}

		check_admin_referer( 'bffb_save_account', 'bffb_account_nonce' );

		global $wpdb;

		$valid_types = array( 'income', 'expense', 'asset', 'liability', 'equity' );

		$id              = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name            = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$raw_type        = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$type            = in_array( $raw_type, $valid_types, true ) ? $raw_type : 'income';
		$schedule_f_line = sanitize_text_field( wp_unslash( $_POST['schedule_f_line'] ?? '' ) );
		$schedule_f_line = substr( $schedule_f_line, 0, 10 );
		$description     = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$sort_order      = absint( wp_unslash( $_POST['sort_order'] ?? '0' ) );

		if ( $name === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bffb-accounts&bffb_notice=error' ) );
			exit;
		}

		$table   = $wpdb->prefix . 'bffb_accounts';
		$data    = array(
			'name'            => $name,
			'type'            => $type,
			'schedule_f_line' => $schedule_f_line !== '' ? $schedule_f_line : null,
			'description'     => $description,
			'sort_order'      => $sort_order,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d' );

		if ( $id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$data['is_default'] = 0;
			$formats[]          = '%d';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data, $formats );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-accounts&bffb_notice=saved' ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		check_admin_referer( 'bffb_delete_account_' . $id );

		// Refuse while anything still points at it.
		//
		// There are no foreign keys on these tables, and the delete used to be
		// a bare $wpdb->delete. Removing a account orphaned every transaction
		// that referenced it — and with Schedule F resolving the account
		// through the category, an orphan silently drops those amounts off the
		// tax report rather than showing them as unassigned.
		global $wpdb;
		// BOTH relationships, not just the direct one.
		//
		// t.account_id is NULL on nearly every transaction — the account is
		// normally reached through the category — so checking only that column
		// reported "safe to delete" on exactly the case this guard exists to
		// catch: an account with no direct transactions but a category, and
		// forty sales, pointing at it.
		$in_use = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ( SELECT COUNT(*) FROM `{$wpdb->prefix}bffb_transactions` WHERE account_id = %d )
				       + ( SELECT COUNT(*) FROM `{$wpdb->prefix}bffb_categories` WHERE account_id = %d )",
				$id,
				$id
			)
		);

		/**
		 * Filter whether an account is still referenced somewhere.
		 *
		 * @since 1.0.1
		 * @param int $in_use     Reference count so far.
		 * @param int $account_id The account being deleted.
		 */
		$in_use = (int) apply_filters( 'bffb_account_in_use', $in_use, $id );

		if ( $in_use > 0 ) {
			wp_safe_redirect(
				admin_url( 'admin.php?page=bffb-accounts&bffb_notice=in_use' )
			);
			exit;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'bffb_accounts';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-accounts&bffb_notice=deleted' ) );
		exit;
	}
}
