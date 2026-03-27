<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_Region_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		parent::__construct( array(
			'singular' => 'region',
			'plural'   => 'regions',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'region'  => 'Region',
			'ru'      => 'Total R&Us',
			'games'   => 'Games',
			'rpg'     => 'R&Us per Game',
			'chars'   => '# Characters',
			'avg'     => 'Avg R&U per Char',
		);
	}

	public function get_sortable_columns() {
		return array(
			'region' => array( 'region', false ),
			'ru'     => array( 'ru', true ),
			'games'  => array( 'games', false ),
			'rpg'    => array( 'rpg', false ),
			'chars'  => array( 'chars', false ),
			'avg'    => array( 'avg', false ),
		);
	}

	public function prepare_items() {
		$this->items = OAT_Report_Query::get_regional_breakdown( $this->status_filter ?: null, $this->pc_npc );

		// Sort in PHP.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'region';
		$order   = isset( $_GET['order'] ) && 'desc' === strtolower( $_GET['order'] ) ? 'desc' : 'asc';

		usort( $this->items, function( $a, $b ) use ( $orderby, $order ) {
			switch ( $orderby ) {
				case 'ru':    $cmp = $a->ru - $b->ru; break;
				case 'games': $cmp = $a->games - $b->games; break;
				case 'chars': $cmp = $a->chars - $b->chars; break;
				case 'rpg':
					$va = $a->games > 0 ? $a->ru / $a->games : 0;
					$vb = $b->games > 0 ? $b->ru / $b->games : 0;
					$cmp = $va <=> $vb;
					break;
				case 'avg':
					$va = $a->chars > 0 ? $a->ru / $a->chars : 0;
					$vb = $b->chars > 0 ? $b->ru / $b->chars : 0;
					$cmp = $va <=> $vb;
					break;
				default:      $cmp = strcasecmp( $a->region, $b->region ); break;
			}
			return 'desc' === $order ? -$cmp : $cmp;
		} );

		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => count( $this->items ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'region':
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&region=' . urlencode( $item->region ) );
				return '<a href="' . esc_url( $url ) . '" target="_blank"><strong>' . esc_html( $item->region ) . '</strong></a>';
			case 'ru':
				return number_format( $item->ru );
			case 'games':
				return (int) $item->games;
			case 'rpg':
				return $item->games > 0 ? number_format( $item->ru / $item->games, 1 ) : '0';
			case 'chars':
				return number_format( $item->chars );
			case 'avg':
				return $item->chars > 0 ? number_format( $item->ru / $item->chars, 2 ) : '0';
			default:
				return '';
		}
	}

	/**
	 * Add totals row at the bottom.
	 */
	public function display_rows() {
		$total_ru    = 0;
		$total_games = 0;
		$total_chars = 0;

		foreach ( $this->items as $item ) {
			$total_ru    += $item->ru;
			$total_games += $item->games;
			$total_chars += $item->chars;
			$this->single_row( $item );
		}

		// Totals row.
		$rpg = $total_games > 0 ? number_format( $total_ru / $total_games, 1 ) : '0';
		$avg = $total_chars > 0 ? number_format( $total_ru / $total_chars, 2 ) : '0';
		echo '<tr class="oat-totals-row" style="font-weight:bold;background:#f0f0f0;">';
		echo '<td><strong>Total</strong></td>';
		echo '<td>' . number_format( $total_ru ) . '</td>';
		echo '<td>' . (int) $total_games . '</td>';
		echo '<td>' . $rpg . '</td>';
		echo '<td>' . number_format( $total_chars ) . '</td>';
		echo '<td>' . $avg . '</td>';
		echo '</tr>';
	}
}
