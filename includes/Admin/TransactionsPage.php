<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

class TransactionsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks-lite' ) );
		}

		$this->maybe_show_notice();

		$table = new TransactionsListTable();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Transactions', 'bluefairy-farmbooks-lite' ); ?></h1>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bffb-transaction-edit' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add Transaction', 'bluefairy-farmbooks-lite' ); ?>
			</a>

			<?php
			/**
			 * Fires after the Add Transaction button on the Transactions list page.
			 * Pro may add extra buttons (e.g. Export CSV, Import from WooCommerce).
			 */
			do_action( 'bffb_transactions_page_actions' );
			?>

			<form method="get">
				<input type="hidden" name="page" value="bffb-transactions">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	private function maybe_show_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['bffb_notice'] ) ? sanitize_key( $_GET['bffb_notice'] ) : '';

		$map = array(
			'saved'   => array( 'notice-success', __( 'Transaction saved.', 'bluefairy-farmbooks-lite' ) ),
			'deleted' => array( 'notice-success', __( 'Transaction deleted.', 'bluefairy-farmbooks-lite' ) ),
			'error'   => array( 'notice-error', __( 'Could not save transaction.', 'bluefairy-farmbooks-lite' ) ),
		);

		if ( isset( $map[ $notice ] ) ) {
			[$css, $message] = $map[ $notice ];
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr( $css ),
				esc_html( $message )
			);
		}
	}
}
