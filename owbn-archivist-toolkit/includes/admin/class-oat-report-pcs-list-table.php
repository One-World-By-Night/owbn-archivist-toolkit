<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OAT_Report_PCs_List_Table extends WP_List_Table {

	private $status_filter;
	private $pc_npc;

	public function __construct( $args = array() ) {
		$this->status_filter = $args['status_filter'] ?? '';
		$this->pc_npc        = $args['pc_npc'] ?? 'pc';
		parent::__construct( array(
			'singular' => 'character',
			'plural'   => 'characters',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'player_name'       => 'Player',
			'character_name'    => 'Character',
			'chronicle_slug'    => 'Chronicle',
			'creature_genre'    => 'Genre',
			'creature_type'     => 'Creature Type',
			'creature_sub_type' => 'Faction',
			'creature_variant'  => 'Variant',
			'status'            => 'Status',
			'ru_count'          => 'R&U',
		);
	}

	public function get_sortable_columns() {
		return array(
			'player_name'       => array( 'player_name', false ),
			'character_name'    => array( 'character_name', false ),
			'chronicle_slug'    => array( 'chronicle_slug', false ),
			'creature_genre'    => array( 'creature_genre', false ),
			'creature_type'     => array( 'creature_type', false ),
			'creature_sub_type' => array( 'creature_sub_type', false ),
			'creature_variant'  => array( 'creature_variant', false ),
			'status'            => array( 'status', false ),
			'ru_count'          => array( 'ru_count', true ),
		);
	}

	public function prepare_items() {
		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$args = array(
			'per_page' => $per_page,
			'offset'   => $offset,
		);

		$args['pc_npc'] = $this->pc_npc;

		if ( $this->status_filter ) {
			$args['status'] = $this->status_filter;
		}
		if ( ! empty( $_GET['chronicle'] ) ) {
			$args['chronicle'] = sanitize_text_field( $_GET['chronicle'] );
		}
		if ( ! empty( $_GET['region'] ) ) {
			$args['region'] = sanitize_text_field( $_GET['region'] );
		}
		if ( ! empty( $_GET['creature_genre'] ) ) {
			$args['creature_genre'] = sanitize_text_field( $_GET['creature_genre'] );
		}
		if ( ! empty( $_GET['creature_type'] ) ) {
			$args['creature_type'] = sanitize_text_field( $_GET['creature_type'] );
		}
		if ( ! empty( $_GET['creature_sub_type'] ) ) {
			$args['creature_sub_type'] = sanitize_text_field( $_GET['creature_sub_type'] );
		}
		if ( ! empty( $_GET['classification'] ) ) {
			$args['classification'] = sanitize_text_field( $_GET['classification'] );
		}
		if ( ! empty( $_GET['s'] ) ) {
			$args['search'] = sanitize_text_field( $_GET['s'] );
		}

		$allowed_orderby = array( 'player_name', 'character_name', 'chronicle_slug', 'creature_genre', 'creature_type', 'creature_sub_type', 'creature_variant', 'status', 'ru_count' );
		if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = $_GET['orderby'];
		}
		if ( ! empty( $_GET['order'] ) && in_array( strtoupper( $_GET['order'] ), array( 'ASC', 'DESC' ), true ) ) {
			$args['order'] = strtoupper( $_GET['order'] );
		}

		$this->items = OAT_Report_Query::get_pcs( $args );
		$total       = OAT_Report_Query::count_pcs( $args );

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
			case 'player_name':
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&s=' . urlencode( $item->player_name ) );
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->player_name ?: '(unknown)' ) . '</a>';
			case 'character_name':
				$url = home_url( '/oat-registry-detail/?character_id=' . $item->id );
				return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $item->character_name ) . '</a>';
			case 'chronicle_slug':
				if ( ! $item->chronicle_slug ) {
					return '—';
				}
				$titles = OAT_Report_Query::get_chronicle_titles();
				$title  = $titles[ $item->chronicle_slug ] ?? $item->chronicle_slug;
				$url    = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&chronicle=' . urlencode( $item->chronicle_slug ) );
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
			case 'creature_genre':
				if ( ! $item->creature_genre ) {
					return '—';
				}
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&creature_genre=' . urlencode( $item->creature_genre ) );
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->creature_genre ) . '</a>';
			case 'creature_type':
				if ( ! $item->creature_type ) {
					return '—';
				}
				$url = admin_url( 'admin.php?page=oat-reports&tab=all-pcs&creature_type=' . urlencode( $item->creature_type ) );
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->creature_type ) . '</a>';
			case 'creature_sub_type':
				return esc_html( $item->creature_sub_type ?: '—' );
			case 'creature_variant':
				return esc_html( $item->creature_variant ?: '—' );
			case 'status':
				return esc_html( ucfirst( $item->status ) );
			case 'ru_count':
				return (int) $item->ru_count;
			default:
				return '';
		}
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_chronicle = isset( $_GET['chronicle'] ) ? sanitize_text_field( $_GET['chronicle'] ) : '';
		$current_genre     = isset( $_GET['creature_genre'] ) ? sanitize_text_field( $_GET['creature_genre'] ) : '';
		$current_type      = isset( $_GET['creature_type'] ) ? sanitize_text_field( $_GET['creature_type'] ) : '';

		echo '<div class="alignleft actions">';

		// Genre filter.
		$genres = OAT_Report_Query::get_creature_genres( $this->pc_npc );
		echo '<select name="creature_genre">';
		echo '<option value="">All Genres</option>';
		foreach ( $genres as $g ) {
			$selected = $current_genre === $g ? ' selected' : '';
			echo '<option value="' . esc_attr( $g ) . '"' . $selected . '>' . esc_html( $g ) . '</option>';
		}
		echo '</select>';

		// Chronicle filter — show title, submit slug.
		$slugs  = OAT_Report_Query::get_chronicle_slugs( $this->pc_npc );
		$titles = OAT_Report_Query::get_chronicle_titles();

		usort( $slugs, function( $a, $b ) use ( $titles ) {
			return strcasecmp( $titles[ $a ] ?? $a, $titles[ $b ] ?? $b );
		} );

		echo '<select name="chronicle">';
		echo '<option value="">All Chronicles</option>';
		foreach ( $slugs as $s ) {
			$selected = $current_chronicle === $s ? ' selected' : '';
			$label    = $titles[ $s ] ?? $s;
			echo '<option value="' . esc_attr( $s ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Creature type filter.
		$types = OAT_Report_Query::get_creature_types( $this->pc_npc );
		echo '<select name="creature_type">';
		echo '<option value="">All Creature Types</option>';
		foreach ( $types as $t ) {
			$selected = $current_type === $t ? ' selected' : '';
			echo '<option value="' . esc_attr( $t ) . '"' . $selected . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select>';

		// Search box — searches all fields.
		$current_search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		echo ' <input type="search" name="s" value="' . esc_attr( $current_search ) . '" placeholder="Search..." />';

		submit_button( 'Filter', '', 'filter_action', false );

		// Clear button — only show if filters are active.
		if ( $current_chronicle || $current_genre || $current_type || $current_search ) {
			$clear_url = admin_url( 'admin.php?page=oat-reports&tab=' . ( isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'all-pcs' ) . '&pc_npc=' . $this->pc_npc );
			echo ' <a href="' . esc_url( $clear_url ) . '" class="button">Clear</a>';
		}
		echo '</div>';
	}
}
