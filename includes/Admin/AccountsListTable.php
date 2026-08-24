<?php
declare(strict_types=1);

namespace BlueFairy\FarmBooks\Admin;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AccountsListTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'account',
				'plural'   => 'accounts',
				'ajax'     => false,
			)
		);
	}

	/** @return array<string, string> */
	public function get_columns(): array {
		return array(
			'name'            => __( 'Name', 'bluefairy-farmbooks-lite' ),
			'type'            => __( 'Type', 'bluefairy-farmbooks-lite' ),
			'schedule_f_line' => __( 'Schedule F Line', 'bluefairy-farmbooks-lite' ),
			'is_default'      => __( 'Default', 'bluefairy-farmbooks-lite' ),
			'sort_order'      => __( 'Order', 'bluefairy-farmbooks-lite' ),
		);
	}

	/** @return array<string, array{string, bool}> */
	protected function get_sortable_columns(): array {
		return array(
			'name'       => array( 'name', false ),
			'type'       => array( 'type', false ),
			'sort_order' => array( 'sort_order', true ),
		);
	}

	public function prepare_items(): void {
		global $wpdb;

		$allowed_orderby = array( 'name', 'type', 'sort_order' );
		$allowed_order   = array( 'ASC', 'DESC' );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true )
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( $_GET['orderby'] )
			: 'sort_order';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), $allowed_order, true )
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? strtoupper( $_GET['order'] )
			: 'ASC';

		$table = $wpdb->prefix . 'bffb_accounts';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->items = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM `{$table}` ORDER BY `{$orderby}` {$order}"
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * @param object $item        Row object from wpdb::get_results().
	 * @param mixed  $column_name Column key.
	 */
	protected function column_default( mixed $item, mixed $column_name ): string {
		if ( ! ( $item instanceof \stdClass ) || ! is_string( $column_name ) ) {
			return '';
		}
		return match ( $column_name ) {
			'type'            => esc_html( ucfirst( $item->type ) ),
			'schedule_f_line' => $item->schedule_f_line !== null && $item->schedule_f_line !== ''
									? esc_html( $item->schedule_f_line )
									: '&mdash;',
			'is_default'      => $item->is_default ? esc_html__( 'Yes', 'bluefairy-farmbooks-lite' ) : '&mdash;',
			'sort_order'      => esc_html( (string) $item->sort_order ),
			default           => '',
		};
	}

	protected function column_name( object $item ): string {
		$actions = apply_filters( 'bffb_account_row_actions', array(), $item );

		return esc_html( $item->name ) . $this->row_actions( $actions );
	}
}
