<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_Type_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;
	private $sect_data = array();

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		parent::__construct( array(
			'singular' => 'type',
			'plural'   => 'types',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'creature_type' => 'Creature Type',
			'ru'            => 'R&Us',
			'chars'         => 'Characters',
			'avg'           => 'Avg R&U per Char',
		);
	}

	public function get_sortable_columns() {
		return array(
			'creature_type' => array( 'creature_type', false ),
			'ru'            => array( 'ru', true ),
			'chars'         => array( 'chars', false ),
			'avg'           => array( 'avg', false ),
		);
	}

	public function prepare_items() {
		$this->items     = OAT_Report_Query::get_by_creature_type( $this->status_filter ?: null, $this->pc_npc );
		$this->sect_data = OAT_Report_Query::get_by_sect( $this->status_filter ?: null, $this->pc_npc );

		// Sort creature type table in PHP.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'ru';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'asc' : 'desc';

		usort( $this->items, function( $a, $b ) use ( $orderby, $order ) {
			switch ( $orderby ) {
				case 'ru':    $cmp = $a->ru - $b->ru; break;
				case 'chars': $cmp = $a->chars - $b->chars; break;
				case 'avg':
					$va = $a->chars > 0 ? $a->ru / $a->chars : 0;
					$vb = $b->chars > 0 ? $b->ru / $b->chars : 0;
					$cmp = $va <=> $vb;
					break;
				default:      $cmp = strcasecmp( $a->creature_type, $b->creature_type ); break;
			}
			return 'desc' === $order ? -$cmp : $cmp;
		} );

		// Sort sect table by R&U desc (fixed).
		usort( $this->sect_data, function( $a, $b ) {
			return $b->ru - $a->ru;
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
			case 'creature_type':
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&creature_type=' . urlencode( $item->creature_type ) );
				return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $item->creature_type ?: '(unknown)' ) . '</a>';
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

	/**
	 * Override display to add sect summary below the creature type table.
	 */
	public function display() {
		echo '<h3>By Creature Type</h3>';
		parent::display();

		if ( ! empty( $this->sect_data ) ) {
			echo '<h3 style="margin-top:2em;">By Faction</h3>';
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>Faction</th><th>R&Us</th><th>Characters</th><th>Avg R&U per Char</th>';
			echo '</tr></thead><tbody>';

			foreach ( $this->sect_data as $row ) {
				$avg = $row->chars > 0 ? number_format( $row->ru / $row->chars, 2 ) : '0';
				$sect_url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&creature_sub_type=' . urlencode( $row->sect ) );
				echo '<tr>';
				echo '<td><a href="' . esc_url( $sect_url ) . '" target="_blank">' . esc_html( $row->sect ) . '</a></td>';
				echo '<td>' . number_format( $row->ru ) . '</td>';
				echo '<td>' . number_format( $row->chars ) . '</td>';
				echo '<td>' . $avg . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}
}
