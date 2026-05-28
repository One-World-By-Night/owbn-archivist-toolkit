<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_Classification_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;
	private $scope;

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		$this->scope         = $args['scope'] ?? null;
		parent::__construct( array(
			'singular' => 'classification',
			'plural'   => 'classifications',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'classification' => 'R&U Classification',
			'coordinators'   => 'Coordinator',
			'total'          => 'Total',
			'pc_count'       => 'PCs',
			'npc_count'      => 'NPCs',
		);
	}

	public function get_sortable_columns() {
		return array(
			'classification' => array( 'classification', false ),
			'total'          => array( 'total', true ),
			'pc_count'       => array( 'pc_count', false ),
			'npc_count'      => array( 'npc_count', false ),
		);
	}

	public function prepare_items() {
		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$args = array(
			'pc_npc'   => $this->pc_npc,
			'per_page' => $per_page,
			'offset'   => $offset,
			'scope'    => $this->scope,
		);

		if ( $this->status_filter ) {
			$args['status'] = $this->status_filter;
		}
		if ( ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( $_GET['s'] );
		}
		if ( ! empty( $_GET['coordinator'] ) ) {
			$args['coordinator'] = sanitize_text_field( wp_unslash( $_GET['coordinator'] ) );
		}

		$allowed_orderby = array( 'classification', 'total', 'pc_count', 'npc_count' );
		if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = $_GET['orderby'];
		}
		if ( ! empty( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = strtoupper( $_GET['order'] );
		}

		$this->items = OAT_Report_Query::get_by_classification( $args );
		$total       = OAT_Report_Query::count_classifications( $args );

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
		$current_coord  = isset( $_GET['coordinator'] ) ? sanitize_text_field( wp_unslash( $_GET['coordinator'] ) ) : '';
		echo '<div class="alignleft actions">';

		// Coordinator filter — limit the list to elements a coordinator controls.
		$coords = function_exists( 'owc_get_coordinators' ) ? owc_get_coordinators() : array();
		if ( ! is_wp_error( $coords ) && ! empty( $coords ) ) {
			echo '<select name="coordinator">';
			echo '<option value="">' . esc_html__( 'All Coordinators', 'owbn-archivist-toolkit' ) . '</option>';
			foreach ( $coords as $co ) {
				$co   = (array) $co;
				$slug = $co['slug'] ?? '';
				if ( '' === $slug ) {
					continue;
				}
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $slug ),
					selected( $current_coord, $slug, false ),
					esc_html( $co['title'] ?? ucfirst( $slug ) )
				);
			}
			echo '</select> ';
		}

		echo ' <input type="search" name="s" value="' . esc_attr( $current_search ) . '" placeholder="Search..." />';
		submit_button( 'Filter', '', 'filter_action', false );
		if ( $current_search || $current_coord ) {
			$clear_url = admin_url( 'admin.php?page=oat-reports&tab=by-classification&pc_npc=' . $this->pc_npc );
			echo ' <a href="' . esc_url( $clear_url ) . '" class="button">Clear</a>';
		}
		echo '</div>';
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'classification':
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&classification=' . urlencode( $item->classification ) . '&pc_npc=' . $this->pc_npc );
				return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $item->classification ) . '</a>';
			case 'coordinators':
				if ( empty( $item->coordinators ) ) {
					return '<span style="color:#a00;">—</span>';
				}
				$slugs  = array_filter( array_map( 'trim', explode( ',', $item->coordinators ) ) );
				$titles = array();
				foreach ( $slugs as $slug ) {
					$titles[] = function_exists( 'owc_entity_get_title' )
						? ( owc_entity_get_title( 'coordinator', $slug ) ?: ucfirst( $slug ) )
						: ucfirst( $slug );
				}
				$label = esc_html( implode( ', ', $titles ) );
				return count( $titles ) > 1
					? '<strong>' . esc_html__( 'Shared:', 'owbn-archivist-toolkit' ) . '</strong> ' . $label
					: $label;
			case 'total':
				return number_format( $item->total );
			case 'pc_count':
				return number_format( $item->pc_count );
			case 'npc_count':
				return number_format( $item->npc_count );
			default:
				return '';
		}
	}
}
