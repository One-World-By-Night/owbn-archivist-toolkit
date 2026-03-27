<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_Player_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;
	private $scope;

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		$this->scope         = $args['scope'] ?? null;
		parent::__construct( array(
			'singular' => 'player',
			'plural'   => 'players',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'player_name'  => 'Player',
			'player_email' => 'Email',
			'ru'           => 'R&Us',
			'chars'        => 'Characters',
		);
	}

	public function get_sortable_columns() {
		return array(
			'player_name'  => array( 'player_name', false ),
			'player_email' => array( 'player_email', false ),
			'ru'           => array( 'ru', true ),
			'chars'        => array( 'chars', false ),
		);
	}

	public function prepare_items() {
		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$args = array(
			'per_page' => $per_page,
			'offset'   => $offset,
			'scope'    => $this->scope,
		);

		$args['pc_npc'] = $this->pc_npc;

		if ( $this->status_filter ) {
			$args['status'] = $this->status_filter;
		}
		if ( ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( $_GET['s'] );
		}

		$allowed_orderby = array( 'player_name', 'player_email', 'ru', 'chars' );
		if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = $_GET['orderby'];
		}
		if ( ! empty( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = strtoupper( $_GET['order'] );
		}

		$this->items = OAT_Report_Query::get_by_player( $args );
		$total       = OAT_Report_Query::count_players( $args );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$current_search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		echo '<div class="alignleft actions">';
		echo ' <input type="search" name="s" value="' . esc_attr( $current_search ) . '" placeholder="Search..." />';
		submit_button( 'Filter', '', 'filter_action', false );
		if ( $current_search ) {
			$clear_url = admin_url( 'admin.php?page=oat-reports&tab=by-player&pc_npc=' . $this->pc_npc );
			echo ' <a href="' . esc_url( $clear_url ) . '" class="button">Clear</a>';
		}
		echo '</div>';
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'player_name':
				$search = $item->player_email ?: $item->player_name;
				$url    = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&s=' . urlencode( $search ) );
				return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $item->player_name ?: '(unknown)' ) . '</a>';
			case 'player_email':
				return esc_html( $item->player_email ?: '—' );
			case 'ru':
				return number_format( $item->ru );
			case 'chars':
				return (int) $item->chars;
			default:
				return '';
		}
	}
}
