<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

defined( 'ABSPATH' ) || exit;

class AccountsListPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}

		$table = new AccountsListTable();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chart of Accounts', 'bluefairy-farmbooks' ); ?></h1>

			<?php $this->maybe_show_notice(); ?>

			<?php do_action( 'bffb_accounts_page_actions' ); ?>

			<form method="get">
				<input type="hidden" name="page" value="bffb-accounts">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function maybe_show_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['bffb_notice'] ) ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice_key = sanitize_text_field( wp_unslash( $_GET['bffb_notice'] ) );

		$notices = array(
			'saved'   => array(
				'class'   => 'notice-success',
				'message' => __( 'Account saved.', 'bluefairy-farmbooks' ),
			),
			'deleted' => array(
				'class'   => 'notice-success',
				'message' => __( 'Account deleted.', 'bluefairy-farmbooks' ),
			),
			'error'   => array(
				'class'   => 'notice-error',
				'message' => __( 'Could not save account.', 'bluefairy-farmbooks' ),
			),
	
			// The delete handler refuses while transactions still reference it.
			// Without this entry the refusal was a silent redirect that looked
			// exactly like a successful delete.
			'is_default' => array(
				'class'   => 'notice-error',
				'message' => __( 'That account is part of the default chart of accounts and cannot be deleted.', 'bluefairy-farmbooks' ),
			),

			'in_use'  => array(
				'class'   => 'notice-error',
				'message' => __( 'That account is still referenced - by transactions, by a category, or by an add-on such as a budget - so it was not deleted. Clear those references first.', 'bluefairy-farmbooks' ),
			),
		);

		if ( ! isset( $notices[ $notice_key ] ) ) {
			return;
		}

		$notice = $notices[ $notice_key ];

		echo '<div class="notice ' . esc_attr( $notice['class'] ) . ' is-dismissible"><p>'
			. esc_html( $notice['message'] )
			. '</p></div>';
	}
}
