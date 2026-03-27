<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_Chronicle_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;
	private $scope;

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		$this->scope         = $args['scope'] ?? null;
		parent::__construct( array(
			'singular' => 'chronicle',
			'plural'   => 'chronicles',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'chronicle_slug' => 'Chronicle',
			'ru'             => 'R&Us',
			'chars'          => 'Characters',
			'avg'            => 'Avg R&U per Char',
		);
	}

	public function get_sortable_columns() {
		return array(
			'chronicle_slug' => array( 'chronicle_slug', false ),
			'ru'             => array( 'ru', true ),
			'chars'          => array( 'chars', false ),
			'avg'            => array( 'avg', false ),
		);
	}

	public function prepare_items() {
		$this->items = OAT_Report_Query::get_by_chronicle( $this->status_filter ?: null, $this->pc_npc, $this->scope );

		// Sort in PHP.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'ru';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'asc' : 'desc';
		$titles  = OAT_Report_Query::get_chronicle_titles();

		usort( $this->items, function( $a, $b ) use ( $orderby, $order, $titles ) {
			switch ( $orderby ) {
				case 'ru':    $cmp = $a->ru - $b->ru; break;
				case 'chars': $cmp = $a->chars - $b->chars; break;
				case 'avg':
					$va = $a->chars > 0 ? $a->ru / $a->chars : 0;
					$vb = $b->chars > 0 ? $b->ru / $b->chars : 0;
					$cmp = $va <=> $vb;
					break;
				default:
					$ta = $titles[ $a->chronicle_slug ] ?? $a->chronicle_slug;
					$tb = $titles[ $b->chronicle_slug ] ?? $b->chronicle_slug;
					$cmp = strcasecmp( $ta, $tb );
					break;
			}
			return 'desc' === $order ? -$cmp : $cmp;
		} );

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$total        = count( $this->items );
		$offset       = ( $current_page - 1 ) * $per_page;
		$this->items  = array_slice( $this->items, $offset, $per_page );

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

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'chronicle_slug':
				$titles = OAT_Report_Query::get_chronicle_titles();
				$title  = $titles[ $item->chronicle_slug ] ?? $item->chronicle_slug;
				$url    = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&chronicle=' . urlencode( $item->chronicle_slug ) );
				return '<a href="' . esc_url( $url ) . '" target="_blank"><strong>' . esc_html( $title ?: '(none)' ) . '</strong></a>';
			case 'ru':
				return number_format( $item->ru );
			case 'chars':
				return number_format( $item->chars );
			case 'avg':
				return $item->chars > 0 ? number_format( $item->ru / $item->chars, 2 ) : '0';
			default:
				return '';
		}
	}
}
