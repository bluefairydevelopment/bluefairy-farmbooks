<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

class AccountsListPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}

		$this->maybe_show_notice();

		$table = new AccountsListTable();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chart of Accounts', 'bluefairy-farmbooks' ); ?></h1>

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
