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

class CategoryEditPage {

	public function __construct() {
		add_action( 'admin_post_bffb_save_category', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bffb_delete_category', array( $this, 'handle_delete' ) );
	}

	public function render(): void {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}


        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		$category = null;
		if ( $id > 0 ) {
			$cat_table = $wpdb->prefix . 'bffb_categories';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$category = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM `{$cat_table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		$acc_table = $wpdb->prefix . 'bffb_accounts';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$accounts = $wpdb->get_results(
			"SELECT id, name, type FROM `{$acc_table}` ORDER BY type, sort_order"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$extra_fields = apply_filters( 'bffb_category_form_fields', array(), $category );

		$page_title = $id > 0
			? __( 'Edit Category', 'bluefairy-farmbooks' )
			: __( 'Add Category', 'bluefairy-farmbooks' );

		$name        = $category ? $category->name : '';
		$type        = $category ? $category->type : 'income';
		$account_id  = $category ? $category->account_id : null;
		$description = $category ? $category->description : '';
		$sort_order  = $category ? (int) $category->sort_order : 0;

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bffb_save_category">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>">
				<?php wp_nonce_field( 'bffb_save_category', 'bffbp_category_nonce' ); ?>

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
								<option value="income" <?php selected( $type, 'income' ); ?>><?php esc_html_e( 'Income', 'bluefairy-farmbooks' ); ?></option>
								<option value="expense" <?php selected( $type, 'expense' ); ?>><?php esc_html_e( 'Expense', 'bluefairy-farmbooks' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_account_id"><?php esc_html_e( 'Account', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<select id="bffb_account_id" name="account_id">
								<option value=""><?php esc_html_e( '— None —', 'bluefairy-farmbooks' ); ?></option>
								<?php foreach ( $accounts as $account ) : ?>
									<option value="<?php echo esc_attr( (string) $account->id ); ?>"
										<?php selected( (int) $account_id, (int) $account->id ); ?>>
										<?php echo esc_html( $account->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bffb_description"><?php esc_html_e( 'Description', 'bluefairy-farmbooks' ); ?></label>
						</th>
						<td>
							<textarea id="bffb_description" name="description" class="large-text" rows="4"><?php echo esc_textarea( $description ); ?></textarea>
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

				<?php submit_button( $id > 0 ? __( 'Update Category', 'bluefairy-farmbooks' ) : __( 'Add Category', 'bluefairy-farmbooks' ) ); ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bffb-categories' ) ); ?>" class="button">
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

		check_admin_referer( 'bffb_save_category', 'bffbp_category_nonce' );

		global $wpdb;

		$id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$raw_type    = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$type        = in_array( $raw_type, array( 'income', 'expense' ), true ) ? $raw_type : 'income';
		$account_id  = isset( $_POST['account_id'] ) && $_POST['account_id'] !== ''
							? (int) $_POST['account_id']
							: null;
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$sort_order  = absint( wp_unslash( $_POST['sort_order'] ?? '0' ) );

		if ( $name === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bffb-categories&bffb_notice=error' ) );
			exit;
		}

		$table   = $wpdb->prefix . 'bffb_categories';
		$data    = array(
			'name'        => $name,
			'type'        => $type,
			'account_id'  => $account_id,
			'description' => $description,
			'sort_order'  => $sort_order,
		);
		$formats = array( '%s', '%s', '%d', '%s', '%d' );

		if ( $id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$data['is_default'] = 0;
			$formats[]          = '%d';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data, $formats );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-categories&bffb_notice=saved' ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'bluefairy-farmbooks' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		check_admin_referer( 'bffb_delete_category_' . $id );

		// Refuse while anything still points at it.
		//
		// There are no foreign keys on these tables, and the delete used to be
		// a bare $wpdb->delete. Removing a category orphaned every transaction
		// that referenced it — and with Schedule F resolving the account
		// through the category, an orphan silently drops those amounts off the
		// tax report rather than showing them as unassigned.
		global $wpdb;
		$in_use = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->prefix}bffb_transactions` WHERE category_id = %d" , $id
			)
		);

		if ( $in_use > 0 ) {
			wp_safe_redirect(
				admin_url( 'admin.php?page=bffb-categorys&bffb_notice=in_use' )
			);
			exit;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'bffb_categories';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=bffb-categories&bffb_notice=deleted' ) );
		exit;
	}
}
