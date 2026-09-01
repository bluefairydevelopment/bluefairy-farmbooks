<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

use BlueFairy\FarmBooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TransactionsListTable extends \WP_List_Table {

	/** @var array<int, string> */
	private array $categories_map = array();

	private string $currency_symbol;

	public function __construct( Settings $settings ) {
		parent::__construct(
			array(
				'singular' => 'transaction',
				'plural'   => 'transactions',
				'ajax'     => false,
			)
		);
		$this->currency_symbol = (string) $settings->get( 'currency_symbol', '$' );
		$this->load_categories_map();
	}

	private function load_categories_map(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bffb_categories';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT id, name FROM `{$table}` ORDER BY sort_order" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		foreach ( $rows as $row ) {
			$this->categories_map[ (int) $row->id ] = $row->name;
		}
	}

	/**
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'date'     => __( 'Date', 'bluefairy-farmbooks' ),
			'type'     => __( 'Type', 'bluefairy-farmbooks' ),
			'amount'   => __( 'Amount', 'bluefairy-farmbooks' ),
			'category' => __( 'Category', 'bluefairy-farmbooks' ),
			'memo'     => __( 'Memo', 'bluefairy-farmbooks' ),
		);
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'date'   => array( 'date', true ),
			'amount' => array( 'amount', false ),
			'type'   => array( 'type', false ),
		);
	}

	/**
	 * @param object $item        Row object from wpdb::get_results().
	 * @param mixed  $column_name Column key.
	 */
	public function column_default( mixed $item, mixed $column_name ): string {
		return '';
	}

	public function column_date( object $item ): string {
		$id         = (int) $item->id;
		$edit_url   = admin_url( 'admin.php?page=bffb-transaction-edit&id=' . $id );
		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=bffb_delete_transaction&id=' . $id ),
			'bffb_delete_transaction_' . $id
		);

		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'bluefairy-farmbooks' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" class="submitdelete">' . esc_html__( 'Delete', 'bluefairy-farmbooks' ) . '</a>',
		);

		/**
		 * Filters the row action links for a transaction.
		 * Pro may add extra links (e.g. download receipt).
		 *
		 * @param array<string, string> $actions Current action links.
		 * @param object                $item    Transaction row object.
		 */
		$actions = apply_filters( 'bffb_transaction_row_actions', $actions, $item );

		return sprintf(
			'<a href="%s">%s</a> %s',
			esc_url( $edit_url ),
			esc_html( $item->date ),
			$this->row_actions( $actions )
		);
	}

	public function column_type( object $item ): string {
		return 'income' === $item->type
			? '<span class="bffb-badge bffb-badge--income">' . esc_html__( 'Income', 'bluefairy-farmbooks' ) . '</span>'
			: '<span class="bffb-badge bffb-badge--expense">' . esc_html__( 'Expense', 'bluefairy-farmbooks' ) . '</span>';
	}

	public function column_amount( object $item ): string {
		return esc_html( $this->currency_symbol . number_format( (float) $item->amount, 2 ) );
	}

	public function column_category( object $item ): string {
		$cat_id = (int) $item->category_id;
		if ( $cat_id && isset( $this->categories_map[ $cat_id ] ) ) {
			return esc_html( $this->categories_map[ $cat_id ] );
		}
		return '—';
	}

	public function column_memo( object $item ): string {
		if ( empty( $item->memo ) ) {
			return '—';
		}
		return esc_html( wp_trim_words( (string) $item->memo, 10 ) );
	}

	protected function extra_tablenav( mixed $which ): void {
		if ( ! is_string( $which ) || 'top' !== $which ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_type = isset( $_GET['filter_type'] ) ? sanitize_key( $_GET['filter_type'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_category = isset( $_GET['filter_category'] ) ? (int) $_GET['filter_category'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		?>
		<div class="alignleft actions">
			<select name="filter_type">
				<option value=""><?php esc_html_e( 'All Types', 'bluefairy-farmbooks' ); ?></option>
				<option value="income"  <?php selected( $current_type, 'income' ); ?>><?php esc_html_e( 'Income', 'bluefairy-farmbooks' ); ?></option>
				<option value="expense" <?php selected( $current_type, 'expense' ); ?>><?php esc_html_e( 'Expense', 'bluefairy-farmbooks' ); ?></option>
			</select>

			<select name="filter_category">
				<option value=""><?php esc_html_e( 'All Categories', 'bluefairy-farmbooks' ); ?></option>
				<?php foreach ( $this->categories_map as $cat_id => $cat_name ) : ?>
					<option value="<?php echo esc_attr( (string) $cat_id ); ?>" <?php selected( $current_category, $cat_id ); ?>>
						<?php echo esc_html( $cat_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
			<input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>">

			<?php submit_button( __( 'Filter', 'bluefairy-farmbooks' ), 'secondary', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function prepare_items(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bffb_transactions';

		$per_page     = 20;
		$current_page = $this->get_pagenum();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_type = isset( $_GET['filter_type'] ) ? sanitize_key( $_GET['filter_type'] ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_category = isset( $_GET['filter_category'] ) ? (int) $_GET['filter_category'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		$valid_orderby = array( 'date', 'amount', 'type' );
		$valid_order   = array( 'ASC', 'DESC' );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : '';
		$orderby     = in_array( $raw_orderby, $valid_orderby, true ) ? $raw_orderby : 'date';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_order = isset( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : '';
		$order     = in_array( $raw_order, $valid_order, true ) ? $raw_order : 'DESC';

		$where  = ' WHERE 1=1';
		$params = array();

		if ( in_array( $filter_type, array( 'income', 'expense' ), true ) ) {
			$where   .= ' AND `type` = %s';
			$params[] = $filter_type;
		}

		if ( $filter_category > 0 ) {
			$where   .= ' AND `category_id` = %d';
			$params[] = $filter_category;
		}

		// Validate date format (YYYY-MM-DD) before use in query.
		if ( $date_from !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where   .= ' AND `date` >= %s';
			$params[] = $date_from;
		}

		if ( $date_to !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where   .= ' AND `date` <= %s';
			$params[] = $date_to;
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}`" . $where;
		if ( $params ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
		} else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$offset = ( $current_page - 1 ) * $per_page;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$data_sql    = "SELECT * FROM `{$table}`" . $where . " ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
		$data_params = array_merge( $params, array( $per_page, $offset ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$data_params ) );
	}
}
