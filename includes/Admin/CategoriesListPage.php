<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

defined( 'ABSPATH' ) || exit;

class CategoriesListPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-farmbooks' ) );
		}

		$this->maybe_show_notice();

		$table = new CategoriesListTable();
		$table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Categories', 'bluefairy-farmbooks' ); ?></h1>

			<?php do_action( 'bffb_categories_page_actions' ); ?>

			<form method="get">
				<input type="hidden" name="page" value="bffb-categories">
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
				'message' => __( 'Category saved.', 'bluefairy-farmbooks' ),
			),
			'deleted' => array(
				'class'   => 'notice-success',
				'message' => __( 'Category deleted.', 'bluefairy-farmbooks' ),
			),
			'error'   => array(
				'class'   => 'notice-error',
				'message' => __( 'Could not save category.', 'bluefairy-farmbooks' ),
			),
	
			// The delete handler refuses while transactions still reference it.
			// Without this entry the refusal was a silent redirect that looked
			// exactly like a successful delete.
			'in_use'  => array(
				'class'   => 'notice-error',
				'message' => __( 'That category still has transactions assigned to it, so it was not deleted. Reassign or remove those transactions first.', 'bluefairy-farmbooks' ),
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
